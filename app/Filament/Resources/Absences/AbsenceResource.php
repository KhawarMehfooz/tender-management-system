<?php

namespace App\Filament\Resources\Absences;

use App\Filament\Resources\Absences\Pages\CreateAbsence;
use App\Filament\Resources\Absences\Pages\EditAbsence;
use App\Filament\Resources\Absences\Pages\ListAbsences;
use App\Filament\Resources\Absences\Schemas\AbsenceForm;
use App\Filament\Resources\Absences\Tables\AbsencesTable;
use App\Models\UserAbsence;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class AbsenceResource extends Resource
{
    protected static ?string $model = UserAbsence::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    public static function getModelLabel(): string
    {
        return __('absences.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('absences.plural_label');
    }

    public static function getNavigationLabel(): string
    {
        return __('absences.navigation_label');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('navigation.groups.people');
    }

    public static function form(Schema $schema): Schema
    {
        return AbsenceForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AbsencesTable::configure($table);
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
            'index' => ListAbsences::route('/'),
            'create' => CreateAbsence::route('/create'),
            'edit' => EditAbsence::route('/{record}/edit'),
        ];
    }
}
