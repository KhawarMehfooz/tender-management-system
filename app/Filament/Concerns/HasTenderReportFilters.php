<?php

namespace App\Filament\Concerns;

use App\Enums\TenderStatus;
use App\Models\Competitor;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Filters\BaseFilter;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Builder;

/**
 * The combinable tender filters idea.md's M12 lists (period, department, status, employee,
 * region, service category, sector, procedure, source, outcome, volume, competitor), shared by
 * TendersTable and the new ArchivedTenders list page so both filter the same way instead of two
 * divergent copies.
 *
 * "Department" is not a separate filter — this app has no distinct department concept, only
 * ServiceCategory (per [[scopes-models]]'s "department" == service_category convention already
 * established for User), so the service_category filter alone covers it. "Outcome" is likewise
 * not separate — TenderStatus already includes every terminal outcome (WON/LOST/CANCELLED/
 * NOT_EVALUATED/EXCLUDED), so the status filter alone covers it too. Adding a second filter for
 * either would just duplicate the same field under a different label.
 */
trait HasTenderReportFilters
{
    /**
     * @return array<int, BaseFilter>
     */
    public static function tenderReportFilters(): array
    {
        return [
            SelectFilter::make('service_category_id')
                ->label(__('tenders.fields.service_category_id'))
                ->relationship('serviceCategory', 'name'),
            SelectFilter::make('status')
                ->label(__('tenders.fields.status'))
                ->options(TenderStatus::class),
            SelectFilter::make('source_id')
                ->label(__('tenders.fields.source_id'))
                ->relationship('source', 'name'),
            SelectFilter::make('sector_id')
                ->label(__('tenders.fields.sector_id'))
                ->relationship('sector', 'name'),
            SelectFilter::make('procurement_procedure_id')
                ->label(__('tenders.fields.procurement_procedure_id'))
                ->relationship('procurementProcedure', 'name'),
            SelectFilter::make('nuts_code_id')
                ->label(__('tenders.fields.nuts_code_id'))
                ->relationship('nutsCode', 'label'),
            SelectFilter::make('owner_id')
                ->label(__('tenders.fields.owner_id'))
                ->relationship('owner', 'name')
                ->searchable(),
            Filter::make('period')
                ->label(__('tender_filters.period'))
                ->schema([
                    DatePicker::make('from')->label(__('tender_filters.period_from')),
                    DatePicker::make('until')->label(__('tender_filters.period_until')),
                ])
                ->query(fn (Builder $query, array $data): Builder => $query
                    ->when($data['from'] ?? null, fn (Builder $query, string $date): Builder => $query->whereDate('created_at', '>=', $date))
                    ->when($data['until'] ?? null, fn (Builder $query, string $date): Builder => $query->whereDate('created_at', '<=', $date)))
                ->indicateUsing(function (array $data): array {
                    $indicators = [];
                    if ($data['from'] ?? null) {
                        $indicators[] = __('tender_filters.period_from_indicator', ['date' => $data['from']]);
                    }
                    if ($data['until'] ?? null) {
                        $indicators[] = __('tender_filters.period_until_indicator', ['date' => $data['until']]);
                    }

                    return $indicators;
                }),
            Filter::make('estimated_contract_volume')
                ->label(__('tender_filters.volume'))
                ->schema([
                    TextInput::make('min')->label(__('tender_filters.volume_min'))->numeric()->minValue(0),
                    TextInput::make('max')->label(__('tender_filters.volume_max'))->numeric()->minValue(0),
                ])
                ->query(fn (Builder $query, array $data): Builder => $query
                    ->when($data['min'] ?? null, fn (Builder $query, $min): Builder => $query->where('estimated_contract_volume', '>=', $min))
                    ->when($data['max'] ?? null, fn (Builder $query, $max): Builder => $query->where('estimated_contract_volume', '<=', $max)))
                ->indicateUsing(function (array $data): array {
                    $indicators = [];
                    if ($data['min'] ?? null) {
                        $indicators[] = __('tender_filters.volume_min_indicator', ['amount' => $data['min']]);
                    }
                    if ($data['max'] ?? null) {
                        $indicators[] = __('tender_filters.volume_max_indicator', ['amount' => $data['max']]);
                    }

                    return $indicators;
                }),
            Filter::make('competitor')
                ->label(__('tender_filters.competitor'))
                ->schema([
                    Select::make('competitor_id')
                        ->label(__('tender_filters.competitor'))
                        ->options(fn (): array => Competitor::query()->orderBy('name')->pluck('name', 'id')->all())
                        ->searchable(),
                ])
                ->query(fn (Builder $query, array $data): Builder => $query
                    ->when(
                        $data['competitor_id'] ?? null,
                        fn (Builder $query, string $competitorId): Builder => $query->whereHas(
                            'tenderCompetitors',
                            fn (Builder $query) => $query->where('competitor_id', $competitorId),
                        ),
                    ))
                ->indicateUsing(function (array $data): ?string {
                    if (blank($data['competitor_id'] ?? null)) {
                        return null;
                    }

                    $name = Competitor::query()->whereKey($data['competitor_id'])->value('name');

                    return (string) __('tender_filters.competitor_indicator', ['name' => $name]);
                }),
        ];
    }
}
