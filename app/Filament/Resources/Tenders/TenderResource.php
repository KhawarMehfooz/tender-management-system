<?php

namespace App\Filament\Resources\Tenders;

use App\Filament\Resources\Tenders\Pages\CreateTender;
use App\Filament\Resources\Tenders\Pages\EditTender;
use App\Filament\Resources\Tenders\Pages\ListTenders;
use App\Filament\Resources\Tenders\Pages\ViewTender;
use App\Filament\Resources\Tenders\RelationManagers\BidDecisionRelationManager;
use App\Filament\Resources\Tenders\RelationManagers\CalculationsRelationManager;
use App\Filament\Resources\Tenders\RelationManagers\CertificatesRelationManager;
use App\Filament\Resources\Tenders\RelationManagers\CommunicationRelationManager;
use App\Filament\Resources\Tenders\RelationManagers\ConceptBlocksRelationManager;
use App\Filament\Resources\Tenders\RelationManagers\DeadlinesRelationManager;
use App\Filament\Resources\Tenders\RelationManagers\DocumentRequestsRelationManager;
use App\Filament\Resources\Tenders\RelationManagers\DocumentsRelationManager;
use App\Filament\Resources\Tenders\RelationManagers\FollowUpRelationManager;
use App\Filament\Resources\Tenders\RelationManagers\LessonsLearnedRelationManager;
use App\Filament\Resources\Tenders\RelationManagers\ReferencesRelationManager;
use App\Filament\Resources\Tenders\RelationManagers\ResultRelationManager;
use App\Filament\Resources\Tenders\RelationManagers\SiteVisitsRelationManager;
use App\Filament\Resources\Tenders\RelationManagers\SubmissionRelationManager;
use App\Filament\Resources\Tenders\RelationManagers\TasksRelationManager;
use App\Filament\Resources\Tenders\Schemas\TenderForm;
use App\Filament\Resources\Tenders\Schemas\TenderInfolist;
use App\Filament\Resources\Tenders\Tables\TendersTable;
use App\Models\Tender;
use BackedEnum;
use Filament\Resources\RelationManagers\RelationGroup;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class TenderResource extends Resource
{
    protected static ?string $model = Tender::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static ?string $recordTitleAttribute = 'title';

    public static function getModelLabel(): string
    {
        return __('tenders.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('tenders.plural_label');
    }

    public static function getNavigationSort(): ?int
    {
        return -1;
    }

    /**
     * Tenders are never hard-deleted, only archived/flagged invalid — see
     * .ai/rules/data-integrity.md. Only an admin-gated action may hard-delete
     * a true junk entry, which is not built yet, so block deletion entirely.
     */
    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return TenderForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return TenderInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TendersTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            TasksRelationManager::class,
            DeadlinesRelationManager::class,
            DocumentsRelationManager::class,
            CalculationsRelationManager::class,
            BidDecisionRelationManager::class,
            RelationGroup::make(__('tenders.relation_groups.engagement'), [
                CommunicationRelationManager::class,
                SiteVisitsRelationManager::class,
                DocumentRequestsRelationManager::class,
            ]),
            RelationGroup::make(__('tenders.relation_groups.closure'), [
                SubmissionRelationManager::class,
                FollowUpRelationManager::class,
                ResultRelationManager::class,
                LessonsLearnedRelationManager::class,
            ]),
            RelationGroup::make(__('reference_library.tab_label'), [
                ReferencesRelationManager::class,
                CertificatesRelationManager::class,
                ConceptBlocksRelationManager::class,
            ]),
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTenders::route('/'),
            'create' => CreateTender::route('/create'),
            'view' => ViewTender::route('/{record}'),
            'edit' => EditTender::route('/{record}/edit'),
        ];
    }
}
