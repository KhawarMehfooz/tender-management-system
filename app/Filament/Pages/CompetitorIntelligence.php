<?php

namespace App\Filament\Pages;

use App\Enums\CompetitorOutcome;
use App\Enums\Right;
use App\Models\Competitor;
use App\Models\TenderCompetitor;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Support\Collection;

class CompetitorIntelligence extends Page implements HasTable
{
    use InteractsWithTable;

    protected string $view = 'filament.pages.competitor-intelligence';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPresentationChartLine;

    public static function canAccess(): bool
    {
        return auth()->user()?->can(Right::SEE_COMPETITOR_DATA->value) ?? false;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public static function getNavigationLabel(): string
    {
        return __('competitor_intelligence.navigation_label');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('navigation.groups.market_intelligence');
    }

    public function getTitle(): string
    {
        return __('competitor_intelligence.title');
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Competitor::query()->with(['tenderCompetitors.tender.sector', 'tenderCompetitors.tender.nutsCode'])
            )
            ->columns([
                TextColumn::make('name')
                    ->label(__('competitors.fields.name'))
                    ->weight('medium')
                    ->searchable(),

                TextColumn::make('encounters')
                    ->label(__('competitor_intelligence.columns.encounters'))
                    ->state(fn (Competitor $record): int => $record->tenderCompetitors->count()),

                TextColumn::make('wins_against_us')
                    ->label(__('competitor_intelligence.columns.wins_against_us'))
                    ->state(fn (Competitor $record): int => $record->tenderCompetitors
                        ->where('outcome', CompetitorOutcome::THEY_WON)->count()),

                TextColumn::make('losses_to_us')
                    ->label(__('competitor_intelligence.columns.losses_to_us'))
                    ->state(fn (Competitor $record): int => $record->tenderCompetitors
                        ->where('outcome', CompetitorOutcome::WE_WON)->count()),

                TextColumn::make('common_sector')
                    ->label(__('competitor_intelligence.columns.common_sector'))
                    ->state(fn (Competitor $record): string => self::mostCommon(
                        $record->tenderCompetitors,
                        fn (TenderCompetitor $tc): ?string => $tc->tender?->sector?->name,
                    ))
                    ->placeholder('—'),

                TextColumn::make('common_region')
                    ->label(__('competitor_intelligence.columns.common_region'))
                    ->state(fn (Competitor $record): string => self::mostCommon(
                        $record->tenderCompetitors,
                        fn (TenderCompetitor $tc): ?string => $tc->tender?->nutsCode?->label,
                    ))
                    ->placeholder('—'),
            ]);
    }

    /**
     * @param  Collection<int, TenderCompetitor>  $tenderCompetitors
     * @param  \Closure(TenderCompetitor): ?string  $value
     */
    private static function mostCommon(Collection $tenderCompetitors, \Closure $value): string
    {
        return $tenderCompetitors
            ->map($value)
            ->filter()
            ->countBy()
            ->sortDesc()
            ->keys()
            ->first() ?? '';
    }
}
