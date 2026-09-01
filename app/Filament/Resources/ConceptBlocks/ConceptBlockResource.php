<?php

namespace App\Filament\Resources\ConceptBlocks;

use App\Filament\Resources\ConceptBlocks\Pages\CreateConceptBlock;
use App\Filament\Resources\ConceptBlocks\Pages\EditConceptBlock;
use App\Filament\Resources\ConceptBlocks\Pages\ListConceptBlocks;
use App\Filament\Resources\ConceptBlocks\Pages\ViewConceptBlock;
use App\Filament\Resources\ConceptBlocks\RelationManagers\VersionsRelationManager;
use App\Filament\Resources\ConceptBlocks\Schemas\ConceptBlockForm;
use App\Filament\Resources\ConceptBlocks\Schemas\ConceptBlockInfolist;
use App\Filament\Resources\ConceptBlocks\Tables\ConceptBlocksTable;
use App\Models\ConceptBlock;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ConceptBlockResource extends Resource
{
    protected static ?string $model = ConceptBlock::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBookOpen;

    protected static ?string $recordTitleAttribute = 'title';

    public static function getModelLabel(): string
    {
        return __('concept_blocks.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('concept_blocks.plural_label');
    }

    public static function getNavigationLabel(): string
    {
        return __('concept_blocks.navigation_label');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('navigation.groups.reference_library');
    }

    public static function form(Schema $schema): Schema
    {
        return ConceptBlockForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return ConceptBlockInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ConceptBlocksTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            VersionsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListConceptBlocks::route('/'),
            'create' => CreateConceptBlock::route('/create'),
            'view' => ViewConceptBlock::route('/{record}'),
            'edit' => EditConceptBlock::route('/{record}/edit'),
        ];
    }
}
