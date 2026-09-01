<?php

namespace App\Filament\Resources\References;

use App\Filament\Resources\References\Pages\CreateReference;
use App\Filament\Resources\References\Pages\EditReference;
use App\Filament\Resources\References\Pages\ListReferences;
use App\Filament\Resources\References\Pages\ViewReference;
use App\Filament\Resources\References\RelationManagers\AttachmentsRelationManager;
use App\Filament\Resources\References\Schemas\ReferenceForm;
use App\Filament\Resources\References\Schemas\ReferenceInfolist;
use App\Filament\Resources\References\Tables\ReferencesTable;
use App\Models\Reference;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ReferenceResource extends Resource
{
    protected static ?string $model = Reference::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedIdentification;

    protected static ?string $recordTitleAttribute = 'client';

    public static function getModelLabel(): string
    {
        return __('references.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('references.plural_label');
    }

    public static function getNavigationLabel(): string
    {
        return __('references.navigation_label');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('navigation.groups.reference_library');
    }

    public static function form(Schema $schema): Schema
    {
        return ReferenceForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return ReferenceInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ReferencesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            AttachmentsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListReferences::route('/'),
            'create' => CreateReference::route('/create'),
            'view' => ViewReference::route('/{record}'),
            'edit' => EditReference::route('/{record}/edit'),
        ];
    }
}
