<?php

namespace App\Filament\Resources\Absences\Tables;

use App\Enums\AbsenceType;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class AbsencesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('user.name')
                    ->label(__('absences.fields.user_id'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('type')
                    ->label(__('absences.fields.type'))
                    ->badge(),
                TextColumn::make('starts_at')
                    ->label(__('absences.fields.starts_at'))
                    ->date()
                    ->sortable(),
                TextColumn::make('ends_at')
                    ->label(__('absences.fields.ends_at'))
                    ->date()
                    ->sortable(),
                TextColumn::make('coverUser.name')
                    ->label(__('absences.fields.cover_user_id'))
                    ->placeholder('—'),
            ])
            ->defaultSort('starts_at', 'desc')
            ->filters([
                SelectFilter::make('type')
                    ->label(__('absences.fields.type'))
                    ->options(AbsenceType::class),
                SelectFilter::make('user')
                    ->label(__('absences.fields.user_id'))
                    ->relationship('user', 'name'),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }
}
