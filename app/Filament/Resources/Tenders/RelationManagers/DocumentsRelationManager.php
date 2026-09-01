<?php

namespace App\Filament\Resources\Tenders\RelationManagers;

use App\Enums\DocumentCategory;
use App\Enums\Right;
use App\Filament\Resources\Tenders\Schemas\TenderForm;
use App\Models\Tender;
use App\Models\TenderDocument;
use App\Models\TenderDocumentVersion;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;

/**
 * CALCULATION-category documents are the only category gated behind the see-prices right
 * (mirrored from TenderInfolist's price fields) — every other
 * category is visible to anyone with tender access. Upload/new-version is gated on
 * Tender::linkedToDocuments() (owner or tender_team_members row) or
 * TenderForm::canManageTeam() (team lead/department head/super admin); delete is
 * uploader-or-manager, and both new-version and delete are additionally blocked once the
 * parent document is locked (see [[deadlines]]-adjacent locking in Tender::changeStatusTo()).
 * All of these are re-checked server-side in before()/mutateDataUsing hooks, not just via
 * ->visible(), per .ai/rules/permissions.md.
 */
class DocumentsRelationManager extends RelationManager
{
    protected static string $relationship = 'documents';

    private function canSeePrices(): bool
    {
        return auth()->user()?->can(Right::SEE_PRICES->value) ?? false;
    }

    private function canUpload(): bool
    {
        $user = auth()->user();

        if ($user === null) {
            return false;
        }

        /** @var Tender $tender */
        $tender = $this->getOwnerRecord();

        return TenderForm::canManageTeam() || $tender->linkedToDocuments($user);
    }

    private function canDeleteDocument(TenderDocument $record): bool
    {
        $user = auth()->user();

        if ($user === null || $record->isLocked()) {
            return false;
        }

        return $record->created_by === $user->id || TenderForm::canManageTeam();
    }

    /**
     * @return array<string, string>
     */
    private function categoryOptions(): array
    {
        return collect(DocumentCategory::cases())
            ->reject(fn (DocumentCategory $category): bool => $category === DocumentCategory::CALCULATION && ! $this->canSeePrices())
            ->mapWithKeys(fn (DocumentCategory $category): array => [$category->value => $category->getLabel()])
            ->all();
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('title')
                ->label(__('tender_documents.fields.title'))
                ->prefixIcon(Heroicon::OutlinedDocumentText)
                ->required(),
            Select::make('category')
                ->label(__('tender_documents.fields.category'))
                ->prefixIcon(Heroicon::OutlinedTag)
                ->searchable()
                ->options(fn (): array => $this->categoryOptions())
                ->required(),
            FileUpload::make('file')
                ->label(__('tender_documents.fields.file'))
                ->required()
                ->disk('local')
                ->directory('tender-documents')
                ->preserveFilenames()
                ->preventFilePathTampering(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('title')
            ->modifyQueryUsing(fn (Builder $query): Builder => $this->canSeePrices()
                ? $query
                : $query->where('category', '!=', DocumentCategory::CALCULATION))
            ->groups([
                Group::make('category')->label(__('tender_documents.fields.category')),
            ])
            ->columns([
                TextColumn::make('title')
                    ->label(__('tender_documents.fields.title'))
                    ->icon(Heroicon::OutlinedDocument)
                    ->searchable(),
                TextColumn::make('category')
                    ->label(__('tender_documents.fields.category'))
                    ->badge(),
                TextColumn::make('currentVersion.version_number')
                    ->label(__('tender_documents.fields.version_number'))
                    ->placeholder('—'),
                TextColumn::make('currentVersion.original_filename')
                    ->label(__('tender_documents.fields.file'))
                    ->placeholder('—'),
                TextColumn::make('currentVersion.uploadedBy.name')
                    ->label(__('tender_documents.fields.uploaded_by'))
                    ->placeholder('—'),
                TextColumn::make('currentVersion.created_at')
                    ->label(__('tender_documents.fields.uploaded_at'))
                    ->dateTime()
                    ->placeholder('—'),
                TextColumn::make('locked_at')
                    ->label(__('tender_documents.fields.locked'))
                    ->dateTime()
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('category')
                    ->label(__('tender_documents.fields.category'))
                    ->options(fn (): array => $this->categoryOptions()),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label(__('tender_documents.actions.new_document'))
                    ->visible(fn (): bool => $this->canUpload())
                    ->before(fn () => abort_unless($this->canUpload(), 403))
                    ->mutateDataUsing(function (array $data): array {
                        $data['created_by'] = auth()->id();

                        return $data;
                    })
                    ->after(function (TenderDocument $record, array $data): void {
                        $path = $data['file'];

                        TenderDocumentVersion::create([
                            'tender_document_id' => $record->id,
                            'version_number' => 1,
                            'file_path' => $path,
                            'original_filename' => basename((string) $path),
                            'mime_type' => Storage::disk('local')->mimeType($path),
                            'size' => Storage::disk('local')->size($path),
                            'uploaded_by' => auth()->id(),
                        ]);
                    }),
            ])
            ->recordActions([
                ActionGroup::make([
                    Action::make('download')
                        ->label(__('tender_documents.actions.download'))
                        ->icon(Heroicon::OutlinedArrowDownTray)
                        ->visible(fn (TenderDocument $record): bool => $record->currentVersion !== null)
                        ->url(fn (TenderDocument $record): string => $record->currentVersion->downloadUrl())
                        ->openUrlInNewTab(),
                    Action::make('addVersion')
                        ->label(__('tender_documents.actions.new_version'))
                        ->icon(Heroicon::OutlinedArrowUpTray)
                        ->schema([
                            FileUpload::make('file')
                                ->label(__('tender_documents.fields.file'))
                                ->required()
                                ->disk('local')
                                ->directory('tender-documents')
                                ->preserveFilenames()
                                ->preventFilePathTampering(),
                        ])
                        ->visible(fn (TenderDocument $record): bool => $this->canUpload() && ! $record->isLocked())
                        ->before(fn (TenderDocument $record) => abort_unless($this->canUpload() && ! $record->isLocked(), 403))
                        ->action(function (TenderDocument $record, array $data): void {
                            $path = $data['file'];

                            TenderDocumentVersion::create([
                                'tender_document_id' => $record->id,
                                'version_number' => $record->versions()->max('version_number') + 1,
                                'file_path' => $path,
                                'original_filename' => basename((string) $path),
                                'mime_type' => Storage::disk('local')->mimeType($path),
                                'size' => Storage::disk('local')->size($path),
                                'uploaded_by' => auth()->id(),
                            ]);
                        })
                        ->successNotificationTitle(__('tender_documents.actions.new_version_success')),
                    DeleteAction::make()
                        ->visible(fn (TenderDocument $record): bool => $this->canDeleteDocument($record))
                        ->before(function (TenderDocument $record): void {
                            abort_unless($this->canDeleteDocument($record), 403);

                            $record->versions->each(
                                fn (TenderDocumentVersion $version) => Storage::disk('local')->delete($version->file_path),
                            );
                        }),
                ]),
            ]);
    }
}
