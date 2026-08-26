<?php

namespace App\Filament\Resources\Tenders\Tables;

use App\Enums\TenderStatus;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class TendersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('internal_id')
                    ->label(__('tenders.fields.internal_id'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('title')
                    ->label(__('tenders.fields.title'))
                    ->searchable()
                    ->limit(40),
                TextColumn::make('contracting_authority')
                    ->label(__('tenders.fields.contracting_authority'))
                    ->searchable(),
                TextColumn::make('serviceCategory.name')
                    ->label(__('tenders.fields.service_category_id')),
                TextColumn::make('status')
                    ->label(__('tenders.fields.status'))
                    ->badge(),
                TextColumn::make('submission_deadline')
                    ->label(__('tenders.fields.submission_deadline'))
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label(__('tenders.fields.created_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->label(__('tenders.fields.updated_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('service_category_id')
                    ->label(__('tenders.fields.service_category_id'))
                    ->relationship('serviceCategory', 'name'),
                SelectFilter::make('status')
                    ->label(__('tenders.fields.status'))
                    ->options(TenderStatus::class),
                SelectFilter::make('source_id')
                    ->label(__('tenders.fields.source_id'))
                    ->relationship('source', 'name'),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }
}
