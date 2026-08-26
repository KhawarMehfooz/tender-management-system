<?php

namespace App\Filament\Resources\NutsCodes;

use App\Filament\Resources\NutsCodes\Pages\CreateNutsCode;
use App\Filament\Resources\NutsCodes\Pages\EditNutsCode;
use App\Filament\Resources\NutsCodes\Pages\ListNutsCodes;
use App\Filament\Resources\NutsCodes\Schemas\NutsCodeForm;
use App\Filament\Resources\NutsCodes\Tables\NutsCodesTable;
use App\Models\NutsCode;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class NutsCodeResource extends Resource
{
    protected static ?string $model = NutsCode::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMap;

    public static function getModelLabel(): string
    {
        return __('nuts_codes.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('nuts_codes.plural_label');
    }

    /**
     * NUTS codes are deactivated, never deleted, to preserve historical
     * tender filtering/reporting integrity and the region hierarchy.
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
        return NutsCodeForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return NutsCodesTable::configure($table);
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
            'index' => ListNutsCodes::route('/'),
            'create' => CreateNutsCode::route('/create'),
            'edit' => EditNutsCode::route('/{record}/edit'),
        ];
    }
}
