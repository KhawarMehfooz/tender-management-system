<?php

namespace App\Filament\Resources\Tenders\RelationManagers;

use App\Enums\DocumentRequestStatus;
use App\Filament\Resources\Tenders\Schemas\TenderDocumentRequestInfolist;
use App\Filament\Resources\Tenders\Schemas\TenderForm;
use App\Models\Tender;
use App\Models\TenderDocumentRequest;
use App\Models\TenderDocumentRequestFile;
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
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;

/**
 * Document requests are their own tracked mini-process: description, owner, deadline, files,
 * status, history — not just a checkbox on a communication entry. A request can optionally
 * point back to the communication entry it arose from. Status changes go through
 * TenderDocumentRequest::changeStatusTo() (audit-trailed like Tender's own status changes), so
 * there is deliberately no delete action — a resolved/abandoned request is withdrawn, not
 * removed, same append-only philosophy as CommunicationRelationManager. Write access follows
 * the same linkedToDocuments()/canManageTeam() pattern as DocumentsRelationManager.
 */
class DocumentRequestsRelationManager extends RelationManager
{
    protected static string $relationship = 'documentRequests';

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

    /**
     * Mirrors TenderForm::scopedUserOptions() — category-scoped users plus every
     * management-level (null-category) user.
     *
     * @return array<string, string>
     */
    private function ownerOptions(): array
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

    /**
     * @return array<string, string>
     */
    private function communicationOptions(): array
    {
        /** @var Tender $tender */
        $tender = $this->getOwnerRecord();

        return $tender->communications()->pluck('subject', 'id')->all();
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Textarea::make('description')
                ->label(__('tender_document_requests.fields.description'))
                ->required()
                ->columnSpanFull(),
            Select::make('tender_communication_id')
                ->label(__('tender_document_requests.fields.tender_communication_id'))
                ->prefixIcon(Heroicon::OutlinedChatBubbleLeftRight)
                ->options(fn (): array => $this->communicationOptions())
                ->searchable(),
            Select::make('owner_id')
                ->label(__('tender_document_requests.fields.owner_id'))
                ->prefixIcon(Heroicon::OutlinedUser)
                ->options(fn (): array => $this->ownerOptions())
                ->searchable()
                ->required(),
            DatePicker::make('deadline')
                ->label(__('tender_document_requests.fields.deadline'))
                ->prefixIcon(Heroicon::OutlinedCalendarDays),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('description')
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('description')
                    ->label(__('tender_document_requests.fields.description'))
                    ->limit(50)
                    ->searchable(),
                TextColumn::make('owner.name')
                    ->label(__('tender_document_requests.fields.owner_id')),
                TextColumn::make('deadline')
                    ->label(__('tender_document_requests.fields.deadline'))
                    ->date()
                    ->placeholder('—')
                    ->sortable(),
                TextColumn::make('status')
                    ->label(__('tender_document_requests.fields.status'))
                    ->badge(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label(__('tender_document_requests.fields.status'))
                    ->options(DocumentRequestStatus::class),
            ])
            ->headerActions([
                CreateAction::make()
                    ->visible(fn (): bool => $this->canManage())
                    ->before(fn () => abort_unless($this->canManage(), 403))
                    ->mutateDataUsing(function (array $data): array {
                        $data['created_by'] = auth()->id();

                        return $data;
                    }),
            ])
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make()
                        ->schema(fn (): array => TenderDocumentRequestInfolist::components()),
                    EditAction::make()
                        ->visible(fn (): bool => $this->canManage())
                        ->before(fn () => abort_unless($this->canManage(), 403)),
                    Action::make('changeStatus')
                        ->label(__('tender_document_requests.actions.change_status'))
                        ->icon(Heroicon::OutlinedArrowPath)
                        ->color('gray')
                        ->visible(fn (TenderDocumentRequest $record): bool => $this->canManage() && $record->status->allowedTransitions() !== [])
                        ->before(fn () => abort_unless($this->canManage(), 403))
                        ->modalWidth(Width::Medium)
                        ->schema(fn (TenderDocumentRequest $record) => [
                            Select::make('status')
                                ->label(__('tender_document_requests.fields.status'))
                                ->options(collect($record->status->allowedTransitions())
                                    ->mapWithKeys(fn (DocumentRequestStatus $status) => [$status->value => $status->getLabel()]))
                                ->required(),
                            Textarea::make('reason')
                                ->label(__('tender_document_requests.fields.status_change_reason'))
                                ->rows(2),
                        ])
                        ->action(function (TenderDocumentRequest $record, array $data): void {
                            $record->changeStatusTo(DocumentRequestStatus::from($data['status']), auth()->user(), $data['reason'] ?? null);
                        })
                        ->successNotification(
                            Notification::make()
                                ->success()
                                ->title(__('tender_document_requests.actions.change_status_success')),
                        ),
                    Action::make('uploadFile')
                        ->label(__('tender_document_requests.actions.upload_file'))
                        ->icon(Heroicon::OutlinedArrowUpTray)
                        ->schema([
                            FileUpload::make('file')
                                ->label(__('tender_document_requests.fields.file'))
                                ->required()
                                ->disk('local')
                                ->directory('tender-document-request-files')
                                ->preserveFilenames()
                                ->preventFilePathTampering(),
                        ])
                        ->visible(fn (): bool => $this->canManage())
                        ->before(fn () => abort_unless($this->canManage(), 403))
                        ->action(function (TenderDocumentRequest $record, array $data): void {
                            $path = $data['file'];

                            TenderDocumentRequestFile::create([
                                'tender_document_request_id' => $record->id,
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
