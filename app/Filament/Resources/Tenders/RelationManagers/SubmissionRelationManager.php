<?php

namespace App\Filament\Resources\Tenders\RelationManagers;

use App\Filament\Resources\Tenders\Schemas\TenderForm;
use App\Filament\Resources\Tenders\Schemas\TenderSubmissionInfolist;
use App\Models\Tender;
use App\Models\TenderSubmission;
use App\Models\TenderSubmissionFile;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;

/**
 * A tender has at most one submission record — `tender_submissions.tender_id` is unique at the
 * DB level. Filament's RelationManager has no first-class "singleton child" mode, so this is
 * an ordinary HasMany-shaped table that just happens to cap at one row: CreateAction hides
 * itself once a submission already exists, and the row's own EditAction is the only way to
 * change it afterward. Write access follows the same linkedToDocuments()/canManageTeam()
 * pattern as DocumentsRelationManager.
 */
class SubmissionRelationManager extends RelationManager
{
    protected static string $relationship = 'submission';

    private function canManage(): bool
    {
        $user = auth()->user();

        if ($user === null) {
            return false;
        }

        /** @var Tender $tender */
        $tender = $this->getOwnerRecord();

        return TenderForm::canManageTeam() || $tender->linkedToDocuments($user);
    }

    private function submissionAlreadyExists(): bool
    {
        /** @var Tender $tender */
        $tender = $this->getOwnerRecord();

        return $tender->submission !== null;
    }

    /**
     * Mirrors TenderForm::scopedUserOptions() — category-scoped users plus every
     * management-level (null-category) user.
     *
     * @return array<string, string>
     */
    private function responsibleEmployeeOptions(): array
    {
        $categoryId = auth()->user()?->service_category_id;

        return User::query()
            ->when(
                $categoryId,
                fn (Builder $query) => $query->where(function (Builder $query) use ($categoryId): void {
                    $query->whereNull('service_category_id')
                        ->orWhere('service_category_id', $categoryId);
                }),
            )
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            DatePicker::make('submission_date')
                ->label(__('tender_submissions.fields.submission_date'))
                ->prefixIcon(Heroicon::OutlinedCalendarDays)
                ->required(),
            TimePicker::make('submission_time')
                ->label(__('tender_submissions.fields.submission_time'))
                ->prefixIcon(Heroicon::OutlinedClock)
                ->required(),
            Select::make('responsible_employee_id')
                ->label(__('tender_submissions.fields.responsible_employee_id'))
                ->prefixIcon(Heroicon::OutlinedUser)
                ->options(fn (): array => $this->responsibleEmployeeOptions())
                ->searchable()
                ->required(),
            TextInput::make('portal')
                ->label(__('tender_submissions.fields.portal'))
                ->prefixIcon(Heroicon::OutlinedGlobeAlt)
                ->required(),
            TextInput::make('transmission_route')
                ->label(__('tender_submissions.fields.transmission_route'))
                ->prefixIcon(Heroicon::OutlinedPaperAirplane)
                ->required(),
            Toggle::make('receipt_confirmed')
                ->label(__('tender_submissions.fields.receipt_confirmed')),
            Textarea::make('notes')
                ->label(__('tender_submissions.fields.notes'))
                ->columnSpanFull(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('portal')
            ->columns([
                TextColumn::make('submission_date')
                    ->label(__('tender_submissions.fields.submission_date'))
                    ->date(),
                TextColumn::make('submission_time')
                    ->label(__('tender_submissions.fields.submission_time')),
                TextColumn::make('portal')
                    ->label(__('tender_submissions.fields.portal')),
                TextColumn::make('responsibleEmployee.name')
                    ->label(__('tender_submissions.fields.responsible_employee_id')),
                IconColumn::make('receipt_confirmed')
                    ->label(__('tender_submissions.fields.receipt_confirmed'))
                    ->boolean(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label(__('tender_submissions.actions.new_submission'))
                    ->visible(fn (): bool => $this->canManage() && ! $this->submissionAlreadyExists())
                    ->before(fn () => abort_unless($this->canManage() && ! $this->submissionAlreadyExists(), 403))
                    ->mutateDataUsing(function (array $data): array {
                        $data['created_by'] = auth()->id();
                        $data['receipt_confirmed_at'] = ($data['receipt_confirmed'] ?? false) ? now() : null;

                        return $data;
                    }),
            ])
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make()
                        ->schema(fn (): array => TenderSubmissionInfolist::components()),
                    EditAction::make()
                        ->visible(fn (): bool => $this->canManage())
                        ->before(fn () => abort_unless($this->canManage(), 403))
                        ->mutateDataUsing(function (array $data, TenderSubmission $record): array {
                            if (($data['receipt_confirmed'] ?? false) && ! $record->receipt_confirmed) {
                                $data['receipt_confirmed_at'] = now();
                            } elseif (! ($data['receipt_confirmed'] ?? false)) {
                                $data['receipt_confirmed_at'] = null;
                            }

                            return $data;
                        }),
                    Action::make('uploadFile')
                        ->label(__('tender_submissions.actions.upload_file'))
                        ->icon(Heroicon::OutlinedArrowUpTray)
                        ->schema([
                            FileUpload::make('file')
                                ->label(__('tender_submissions.fields.file'))
                                ->required()
                                ->disk('local')
                                ->directory('tender-submission-files')
                                ->preserveFilenames()
                                ->preventFilePathTampering(),
                        ])
                        ->visible(fn (): bool => $this->canManage())
                        ->before(fn () => abort_unless($this->canManage(), 403))
                        ->action(function (TenderSubmission $record, array $data): void {
                            $path = $data['file'];

                            TenderSubmissionFile::create([
                                'tender_submission_id' => $record->id,
                                'file_path' => $path,
                                'original_filename' => basename((string) $path),
                                'mime_type' => Storage::disk('local')->mimeType($path),
                                'size' => Storage::disk('local')->size($path),
                                'uploaded_by' => auth()->id(),
                            ]);
                        }),
                ]),
            ]);
    }
}
