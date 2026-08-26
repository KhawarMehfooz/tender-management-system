<?php

namespace App\Filament\Resources\ServiceCategories;

use App\Filament\Resources\ServiceCategories\Pages\CreateServiceCategory;
use App\Filament\Resources\ServiceCategories\Pages\EditServiceCategory;
use App\Filament\Resources\ServiceCategories\Pages\ListServiceCategories;
use App\Filament\Resources\ServiceCategories\Pages\ViewServiceCategory;
use App\Filament\Resources\ServiceCategories\Schemas\ServiceCategoryForm;
use App\Filament\Resources\ServiceCategories\Schemas\ServiceCategoryInfolist;
use App\Filament\Resources\ServiceCategories\Tables\ServiceCategoriesTable;
use App\Models\ServiceCategory;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class ServiceCategoryResource extends Resource
{
    protected static ?string $model = ServiceCategory::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTag;

    public static function getModelLabel(): string
    {
        return __('service_categories.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('service_categories.plural_label');
    }

    /**
     * Service categories are deactivated, never deleted, to preserve historical
     * tender scoping and reporting integrity.
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
        return ServiceCategoryForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return ServiceCategoryInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ServiceCategoriesTable::configure($table);
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
            'index' => ListServiceCategories::route('/'),
            'create' => CreateServiceCategory::route('/create'),
            'view' => ViewServiceCategory::route('/{record}'),
            'edit' => EditServiceCategory::route('/{record}/edit'),
        ];
    }
}
