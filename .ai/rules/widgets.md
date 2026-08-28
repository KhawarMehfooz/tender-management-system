---
paths:
  - 'app/Filament/Pages/TenderCalendar.php,app/Filament/Widgets/**'
---

# Widgets

## Widget-page filter wiring uses Filament's Dashboard filter machinery, not custom events
To make a custom `Filament\Pages\Page` filter a widget reactively (e.g. `TenderCalendar` → `TenderDeadlineCalendarWidget`), reuse Filament's own dashboard-filter pattern instead of inventing event dispatch:
- Page: `use Filament\Pages\Dashboard\Concerns\HasFiltersForm;` — gives a `filters` state property (URL+session persisted) and a `filtersForm(Schema $schema)` you implement. Override `content()` to render `EmbeddedSchema::make('filtersForm')` plus a `Grid` of `getWidgetsSchemaComponents($this->getWidgets())` (copy Filament's own `Dashboard.php` as the template).
- Widget: `use Filament\Widgets\Concerns\InteractsWithPageFilters;` gives a `#[Reactive] public ?array $pageFilters`. Filament's `Page::getWidgetsSchemaComponents()` auto-injects it as `pageFilters` whenever `property_exists($this, 'filters')` on the page is true — no manual wiring needed to get the data across.
- For `guava/calendar` widgets specifically, `#[Reactive]` alone doesn't re-fetch calendar events (they're pulled via a JS-triggered `getEventsJs` call, not a Blade re-render) — add `public function updatedPageFilters(): void { $this->refreshRecords(); }` on the widget to force a re-fetch when a filter changes.
