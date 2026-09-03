<?php

namespace App\Filament\Resources\Skills\Tables;

use App\Enums\SkillCategory;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class SkillsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('skills.fields.name'))
                    ->searchable(),
                TextColumn::make('category')
                    ->label(__('skills.fields.category'))
                    ->badge()
                    ->placeholder('-'),
                TextColumn::make('created_at')
                    ->label(__('skills.fields.created_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->label(__('skills.fields.updated_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('category')
                    ->label(__('skills.fields.category'))
                    ->options(SkillCategory::class),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }
}
