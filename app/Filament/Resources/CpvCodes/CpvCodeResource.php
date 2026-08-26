<?php

namespace App\Filament\Resources\CpvCodes;

use App\Filament\Resources\CpvCodes\Pages\CreateCpvCode;
use App\Filament\Resources\CpvCodes\Pages\EditCpvCode;
use App\Filament\Resources\CpvCodes\Pages\ListCpvCodes;
use App\Filament\Resources\CpvCodes\Schemas\CpvCodeForm;
use App\Filament\Resources\CpvCodes\Tables\CpvCodesTable;
use App\Models\CpvCode;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class CpvCodeResource extends Resource
{
    protected static ?string $model = CpvCode::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleGroup;

    public static function getModelLabel(): string
    {
        return __('cpv_codes.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('cpv_codes.plural_label');
    }

    /**
     * CPV codes are deactivated, never deleted, to preserve historical
     * tender filtering/reporting integrity.
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
        return CpvCodeForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CpvCodesTable::configure($table);
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
            'index' => ListCpvCodes::route('/'),
            'create' => CreateCpvCode::route('/create'),
            'edit' => EditCpvCode::route('/{record}/edit'),
        ];
    }
}
