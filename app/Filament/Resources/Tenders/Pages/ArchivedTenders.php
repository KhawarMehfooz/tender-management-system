<?php

namespace App\Filament\Resources\Tenders\Pages;

use App\Filament\Concerns\HasTenderReportFilters;
use App\Filament\Resources\Tenders\TenderResource;
use App\Models\Tender;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * A filterable list of archived tenders — still Tender rows with their own lifecycle status
 * visible (idea.md's explicit "keeps its own status visible alongside the archive flag"
 * requirement), not a separate resource. The regular ServiceCategoryScope global scope still
 * applies, so a category-scoped user only ever sees their own category's archive.
 */
class ArchivedTenders extends ListRecords
{
    use HasTenderReportFilters;

    protected static string $resource = TenderResource::class;

    public function getTitle(): string
    {
        return __('tenders.archive.title');
    }

    public function getBreadcrumb(): string
    {
        return __('tenders.archive.title');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => Tender::query()->where('is_archived', true))
            ->columns([
                TextColumn::make('internal_id')
                    ->label(__('tenders.fields.internal_id'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('title')
                    ->label(__('tenders.fields.title'))
                    ->searchable()
                    ->limit(40),
                TextColumn::make('status')
                    ->label(__('tenders.fields.status'))
                    ->badge(),
                TextColumn::make('invalidity_reason')
                    ->label(__('tenders.fields.invalidity_reason'))
                    ->limit(40)
                    ->placeholder('—'),
                TextColumn::make('archived_at')
                    ->label(__('tenders.archive.archived_at'))
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters(static::tenderReportFilters())
            ->recordActions([
                ViewAction::make()
                    ->url(fn (Tender $record): string => TenderResource::getUrl('view', ['record' => $record])),
            ]);
    }
}
