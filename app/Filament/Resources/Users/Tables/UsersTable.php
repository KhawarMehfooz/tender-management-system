<?php

namespace App\Filament\Resources\Users\Tables;

use App\Enums\RoleName;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('users.fields.name'))
                    ->searchable(),
                TextColumn::make('email')
                    ->label(__('users.fields.email'))
                    ->searchable(),
                TextColumn::make('roles.name')
                    ->label(__('users.fields.role'))
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => RoleName::from($state)->getLabel()),
                TextColumn::make('serviceCategory.name')
                    ->label(__('users.fields.service_category_id'))
                    ->placeholder(__('users.fields.service_category_id_placeholder'))
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label(__('users.fields.created_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('roles')
                    ->label(__('users.fields.role'))
                    ->relationship('roles', 'name')
                    ->options(collect(RoleName::cases())->mapWithKeys(
                        fn (RoleName $role): array => [$role->value => $role->getLabel()],
                    )),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }
}
