<?php

namespace App\Filament\Resources\Sectors;

use App\Filament\Resources\Sectors\Pages\CreateSector;
use App\Filament\Resources\Sectors\Pages\EditSector;
use App\Filament\Resources\Sectors\Pages\ListSectors;
use App\Filament\Resources\Sectors\Schemas\SectorForm;
use App\Filament\Resources\Sectors\Tables\SectorsTable;
use App\Models\Sector;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class SectorResource extends Resource
{
    protected static ?string $model = Sector::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBriefcase;

    public static function getModelLabel(): string
    {
        return __('sectors.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('sectors.plural_label');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('navigation.groups.master_data');
    }

    public static function getNavigationSort(): ?int
    {
        return 2;
    }

    /**
     * Sectors are deactivated, never deleted, to preserve historical
     * market-analysis reporting integrity.
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
        return SectorForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SectorsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSectors::route('/'),
            'create' => CreateSector::route('/create'),
            'edit' => EditSector::route('/{record}/edit'),
        ];
    }
}
