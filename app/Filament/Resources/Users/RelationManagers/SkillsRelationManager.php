<?php

namespace App\Filament\Resources\Users\RelationManagers;

use App\Enums\SkillProficiency;
use Filament\Actions\AttachAction;
use Filament\Actions\DetachAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Attach/detach only — Skills are created and edited on SkillResource, never inline here.
 * The pivot's proficiency_level is set at attach time and editable afterward via the
 * standard "editing with pivot attributes" RelationManager form (see EditAction's schema()).
 */
class SkillsRelationManager extends RelationManager
{
    protected static string $relationship = 'skills';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('proficiency_level')
                    ->label(__('skills.fields.proficiency_level'))
                    ->options(SkillProficiency::class)
                    ->required(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('name')
                    ->label(__('skills.fields.name')),
                TextColumn::make('category')
                    ->label(__('skills.fields.category'))
                    ->badge(),
                TextColumn::make('pivot.proficiency_level')
                    ->label(__('skills.fields.proficiency_level'))
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => $state === null
                        ? '-'
                        : SkillProficiency::from($state)->getLabel())
                    ->color(fn (?string $state): string => $state === null
                        ? 'gray'
                        : SkillProficiency::from($state)->color()),
            ])
            ->headerActions([
                AttachAction::make()
                    ->label(__('skills.attach'))
                    ->schema(fn (AttachAction $action): array => [
                        $action->getRecordSelect(),
                        Select::make('proficiency_level')
                            ->label(__('skills.fields.proficiency_level'))
                            ->options(SkillProficiency::class)
                            ->required(),
                    ])
                    ->preloadRecordSelect()
                    ->recordSelectSearchColumns(['name']),
            ])
            ->recordActions([
                EditAction::make(),
                DetachAction::make(),
            ]);
    }
}
