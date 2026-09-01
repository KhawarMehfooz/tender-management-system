<?php

namespace App\Filament\Resources\Certificates\Tables;

use App\Enums\CertificateStatus;
use App\Enums\CertificateType;
use App\Models\Certificate;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class CertificatesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('certificates.fields.name'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('type')
                    ->label(__('certificates.fields.type'))
                    ->badge(),
                TextColumn::make('status')
                    ->label(__('certificates.fields.status'))
                    ->state(fn (Certificate $record) => $record->status())
                    ->badge()
                    ->color(fn (CertificateStatus $state): string => $state->color()),
                TextColumn::make('issuing_body')
                    ->label(__('certificates.fields.issuing_body'))
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('expiry_date')
                    ->label(__('certificates.fields.expiry_date'))
                    ->date()
                    ->sortable(),
            ])
            ->defaultSort('expiry_date')
            ->filters([
                SelectFilter::make('type')
                    ->label(__('certificates.fields.type'))
                    ->options(CertificateType::class),
            ])
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make(),
                    EditAction::make(),
                    DeleteAction::make(),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
