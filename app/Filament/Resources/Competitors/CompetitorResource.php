<?php

namespace App\Filament\Resources\Competitors;

use App\Enums\Right;
use App\Filament\Resources\Competitors\Pages\CreateCompetitor;
use App\Filament\Resources\Competitors\Pages\EditCompetitor;
use App\Filament\Resources\Competitors\Pages\ListCompetitors;
use App\Filament\Resources\Competitors\Pages\ViewCompetitor;
use App\Filament\Resources\Competitors\RelationManagers\PriceEntriesRelationManager;
use App\Filament\Resources\Competitors\RelationManagers\TendersFacedRelationManager;
use App\Filament\Resources\Competitors\Schemas\CompetitorForm;
use App\Filament\Resources\Competitors\Tables\CompetitorsTable;
use App\Models\Competitor;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class CompetitorResource extends Resource
{
    protected static ?string $model = Competitor::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedFlag;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getModelLabel(): string
    {
        return __('competitors.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('competitors.plural_label');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('navigation.groups.market_intelligence');
    }

    /**
     * Competitor data (strengths/weaknesses/pricing/win-loss intelligence) is gated behind
     * Right::SEE_COMPETITOR_DATA end to end — unlike References/ConceptBlocks, the whole
     * resource is off-limits without the right, same shape as CertificateResource's
     * MANAGE_CERTIFICATES gate.
     */
    private static function canManage(): bool
    {
        return auth()->user()?->can(Right::SEE_COMPETITOR_DATA->value) ?? false;
    }

    public static function canViewAny(): bool
    {
        return self::canManage();
    }

    public static function canCreate(): bool
    {
        return self::canManage();
    }

    public static function canEdit(Model $record): bool
    {
        return self::canManage();
    }

    public static function canDelete(Model $record): bool
    {
        return self::canManage();
    }

    public static function canDeleteAny(): bool
    {
        return self::canManage();
    }

    public static function form(Schema $schema): Schema
    {
        return CompetitorForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CompetitorsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            PriceEntriesRelationManager::class,
            TendersFacedRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCompetitors::route('/'),
            'create' => CreateCompetitor::route('/create'),
            'view' => ViewCompetitor::route('/{record}'),
            'edit' => EditCompetitor::route('/{record}/edit'),
        ];
    }
}
