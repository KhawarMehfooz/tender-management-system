<?php

namespace App\Filament\Resources\Tenders\RelationManagers;

use App\Filament\Resources\Tenders\Schemas\TenderForm;
use App\Filament\Resources\Tenders\Schemas\TenderSiteVisitInfolist;
use App\Models\Tender;
use App\Models\TenderSiteVisit;
use App\Models\TenderSiteVisitPhoto;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Storage;

/**
 * A tender can have several site visits over its lifecycle (e.g. an initial pre-bid visit and
 * a later follow-up). Photos are managed separately from the visit's own fields, via a
 * dedicated "Upload photo" action (mirrors DocumentsRelationManager::addVersion()) rather than
 * a Repeater embedded in the create/edit form — a private-disk FileUpload inside a
 * ->relationship() Repeater has no clean way to derive mime_type/size server-side per item.
 * Photos are shown via a custom ViewAction schema (TenderSiteVisitInfolist), same pattern
 * TasksRelationManager uses for TaskInfolist (see [[tenders-relation-managers]]).
 * Write access follows the same linkedToDocuments()/canManageTeam() pattern as
 * DocumentsRelationManager.
 */
class SiteVisitsRelationManager extends RelationManager
{
    protected static string $relationship = 'siteVisits';

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

    private function canDeleteVisit(TenderSiteVisit $record): bool
    {
        $user = auth()->user();

        if ($user === null) {
            return false;
        }

        return $record->created_by === $user->id || TenderForm::canManageTeam();
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            DatePicker::make('visit_date')
                ->label(__('tender_site_visits.fields.visit_date'))
                ->prefixIcon(Heroicon::OutlinedCalendarDays)
                ->required(),
            TextInput::make('contact_person')
                ->label(__('tender_site_visits.fields.contact_person'))
                ->prefixIcon(Heroicon::OutlinedUser),
            Textarea::make('attendees')
                ->label(__('tender_site_visits.fields.attendees'))
                ->required()
                ->columnSpanFull(),
            Textarea::make('access_routes')
                ->label(__('tender_site_visits.fields.access_routes')),
            Textarea::make('parking')
                ->label(__('tender_site_visits.fields.parking')),
            Textarea::make('areas')
                ->label(__('tender_site_visits.fields.areas')),
            Textarea::make('risks')
                ->label(__('tender_site_visits.fields.risks')),
            Textarea::make('technical_particularities')
                ->label(__('tender_site_visits.fields.technical_particularities')),
            Textarea::make('staffing_requirement')
                ->label(__('tender_site_visits.fields.staffing_requirement')),
            Textarea::make('competitors_spotted')
                ->label(__('tender_site_visits.fields.competitors_spotted')),
            Textarea::make('open_questions')
                ->label(__('tender_site_visits.fields.open_questions')),
            Textarea::make('notes')
                ->label(__('tender_site_visits.fields.notes'))
                ->columnSpanFull(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('visit_date')
            ->defaultSort('visit_date', 'desc')
            ->columns([
                TextColumn::make('visit_date')
                    ->label(__('tender_site_visits.fields.visit_date'))
                    ->date()
                    ->sortable(),
                TextColumn::make('contact_person')
                    ->label(__('tender_site_visits.fields.contact_person'))
                    ->placeholder('—'),
                TextColumn::make('photos_count')
                    ->label(__('tender_site_visits.fields.photo_count'))
                    ->counts('photos'),
                TextColumn::make('createdBy.name')
                    ->label(__('tender_communications.fields.logged_by')),
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
                        ->schema(fn (): array => TenderSiteVisitInfolist::components()),
                    EditAction::make()
                        ->visible(fn (): bool => $this->canManage())
                        ->before(fn () => abort_unless($this->canManage(), 403)),
                    Action::make('uploadPhoto')
                        ->label(__('tender_site_visits.actions.upload_photo'))
                        ->icon(Heroicon::OutlinedArrowUpTray)
                        ->schema([
                            FileUpload::make('file')
                                ->label(__('tender_site_visits.fields.photo'))
                                ->image()
                                ->required()
                                ->disk('local')
                                ->directory('tender-site-visit-photos')
                                ->preserveFilenames()
                                ->preventFilePathTampering(),
                        ])
                        ->visible(fn (): bool => $this->canManage())
                        ->before(fn () => abort_unless($this->canManage(), 403))
                        ->action(function (TenderSiteVisit $record, array $data): void {
                            $path = $data['file'];

                            TenderSiteVisitPhoto::create([
                                'tender_site_visit_id' => $record->id,
                                'file_path' => $path,
                                'original_filename' => basename((string) $path),
                                'mime_type' => Storage::disk('local')->mimeType($path),
                                'size' => Storage::disk('local')->size($path),
                                'uploaded_by' => auth()->id(),
                            ]);
                        }),
                    DeleteAction::make()
                        ->visible(fn (TenderSiteVisit $record): bool => $this->canDeleteVisit($record))
                        ->before(function (TenderSiteVisit $record): void {
                            abort_unless($this->canDeleteVisit($record), 403);

                            $record->photos->each(
                                fn (TenderSiteVisitPhoto $photo) => Storage::disk('local')->delete($photo->file_path),
                            );
                        }),
                ]),
            ]);
    }
}
