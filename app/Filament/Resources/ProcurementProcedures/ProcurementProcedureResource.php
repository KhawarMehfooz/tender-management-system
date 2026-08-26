<?php

namespace App\Filament\Resources\ProcurementProcedures;

use App\Filament\Resources\ProcurementProcedures\Pages\CreateProcurementProcedure;
use App\Filament\Resources\ProcurementProcedures\Pages\EditProcurementProcedure;
use App\Filament\Resources\ProcurementProcedures\Pages\ListProcurementProcedures;
use App\Filament\Resources\ProcurementProcedures\Schemas\ProcurementProcedureForm;
use App\Filament\Resources\ProcurementProcedures\Tables\ProcurementProceduresTable;
use App\Models\ProcurementProcedure;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class ProcurementProcedureResource extends Resource
{
    protected static ?string $model = ProcurementProcedure::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedScale;

    public static function getModelLabel(): string
    {
        return __('procurement_procedures.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('procurement_procedures.plural_label');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('navigation.groups.master_data');
    }

    public static function getNavigationSort(): ?int
    {
        return 3;
    }

    /**
     * Procurement procedures are deactivated, never deleted, to preserve
     * historical procedure-type reporting integrity.
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
        return ProcurementProcedureForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ProcurementProceduresTable::configure($table);
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
            'index' => ListProcurementProcedures::route('/'),
            'create' => CreateProcurementProcedure::route('/create'),
            'edit' => EditProcurementProcedure::route('/{record}/edit'),
        ];
    }
}
