<?php

use App\Enums\DeadlineType;
use App\Enums\TeamRole;
use App\Filament\Pages\TenderCalendar;
use App\Filament\Widgets\TenderDeadlineCalendarWidget;
use App\Filament\Widgets\UserAbsenceCalendarWidget;
use App\Models\ServiceCategory;
use App\Models\Tender;
use App\Models\TenderDeadline;
use App\Models\TenderTeamMember;
use App\Models\User;
use App\Models\UserAbsence;
use Guava\Calendar\ValueObjects\FetchInfo;
use Livewire\Livewire;

function fetchAllDeadlines(TenderDeadlineCalendarWidget $widget): array
{
    $info = new FetchInfo([
        'startStr' => now()->subYear()->toIso8601String(),
        'endStr' => now()->addYear()->toIso8601String(),
    ]);

    $method = new ReflectionMethod($widget, 'getEvents');

    return $method->invoke($widget, $info)->pluck('id')->all();
}

function fetchAllAbsences(UserAbsenceCalendarWidget $widget): array
{
    $info = new FetchInfo([
        'startStr' => now()->subYear()->toIso8601String(),
        'endStr' => now()->addYear()->toIso8601String(),
    ]);

    $method = new ReflectionMethod($widget, 'getEvents');

    return $method->invoke($widget, $info)->pluck('id')->all();
}

it('allows any authenticated user to view the calendar page', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test(TenderCalendar::class)->assertSuccessful();
});

it('returns deadlines within the requested date range only', function () {
    $this->actingAs(User::factory()->create());

    $tender = Tender::factory()->create();
    $tender->deadlines()->delete();
    $inRange = TenderDeadline::factory()->for($tender)->create(['due_at' => now()->addMonth()]);
    TenderDeadline::factory()->for($tender)->create(['due_at' => now()->addYears(5)]);

    $widget = new TenderDeadlineCalendarWidget;

    expect(fetchAllDeadlines($widget))->toBe([$inRange->id]);
});

it('filters by tender', function () {
    $this->actingAs(User::factory()->create());

    $tender = Tender::factory()->create();
    $otherTender = Tender::factory()->create();
    $tender->deadlines()->delete();
    $otherTender->deadlines()->delete();
    $matching = TenderDeadline::factory()->for($tender)->create(['due_at' => now()->addWeek()]);
    TenderDeadline::factory()->for($otherTender)->create(['due_at' => now()->addWeek()]);

    $widget = new TenderDeadlineCalendarWidget;
    $widget->pageFilters = ['tender_id' => $tender->id];

    expect(fetchAllDeadlines($widget))->toBe([$matching->id]);
});

it('filters by deadline type', function () {
    $this->actingAs(User::factory()->create());

    $tender = Tender::factory()->create();
    $tender->deadlines()->delete();
    $matching = TenderDeadline::factory()->for($tender)->create([
        'type' => DeadlineType::SITE_VISIT,
        'due_at' => now()->addWeek(),
    ]);
    TenderDeadline::factory()->for($tender)->create([
        'type' => DeadlineType::APPROVAL,
        'due_at' => now()->addWeek(),
    ]);

    $widget = new TenderDeadlineCalendarWidget;
    $widget->pageFilters = ['deadline_type' => DeadlineType::SITE_VISIT->value];

    expect(fetchAllDeadlines($widget))->toBe([$matching->id]);
});

it('filters by contracting authority', function () {
    $this->actingAs(User::factory()->create());

    $tender = Tender::factory()->create(['contracting_authority' => 'City of Munich']);
    $otherTender = Tender::factory()->create(['contracting_authority' => 'City of Berlin']);
    $tender->deadlines()->delete();
    $otherTender->deadlines()->delete();
    $matching = TenderDeadline::factory()->for($tender)->create(['due_at' => now()->addWeek()]);
    TenderDeadline::factory()->for($otherTender)->create(['due_at' => now()->addWeek()]);

    $widget = new TenderDeadlineCalendarWidget;
    $widget->pageFilters = ['contracting_authority' => 'City of Munich'];

    expect(fetchAllDeadlines($widget))->toBe([$matching->id]);
});

it('filters by employee, matching owner or team member', function () {
    $employee = User::factory()->create();

    $this->actingAs(User::factory()->create());

    $ownedTender = Tender::factory()->create(['owner_id' => $employee->id]);
    $teamTender = Tender::factory()->create();
    $unrelatedTender = Tender::factory()->create();
    $ownedTender->deadlines()->delete();
    $teamTender->deadlines()->delete();
    $unrelatedTender->deadlines()->delete();

    TenderTeamMember::factory()->create([
        'tender_id' => $teamTender->id,
        'user_id' => $employee->id,
        'functional_role' => TeamRole::CALCULATION,
    ]);

    $ownedDeadline = TenderDeadline::factory()->for($ownedTender)->create(['due_at' => now()->addWeek()]);
    $teamDeadline = TenderDeadline::factory()->for($teamTender)->create(['due_at' => now()->addWeek()]);
    TenderDeadline::factory()->for($unrelatedTender)->create(['due_at' => now()->addWeek()]);

    $widget = new TenderDeadlineCalendarWidget;
    $widget->pageFilters = ['employee_id' => $employee->id];

    expect(fetchAllDeadlines($widget))->toContain($ownedDeadline->id, $teamDeadline->id)
        ->and(fetchAllDeadlines($widget))->toHaveCount(2);
});

it('scopes calendar events to the acting user\'s service category', function () {
    $category = ServiceCategory::factory()->create();
    $otherCategory = ServiceCategory::factory()->create();
    $tender = Tender::factory()->create(['service_category_id' => $category->id]);
    $otherTender = Tender::factory()->create(['service_category_id' => $otherCategory->id]);
    $tender->deadlines()->delete();
    $otherTender->deadlines()->delete();
    $visible = TenderDeadline::factory()->for($tender)->create(['due_at' => now()->addWeek()]);
    TenderDeadline::factory()->for($otherTender)->create(['due_at' => now()->addWeek()]);

    $scopedUser = User::factory()->create(['service_category_id' => $category->id]);
    $this->actingAs($scopedUser);

    $widget = new TenderDeadlineCalendarWidget;

    expect(fetchAllDeadlines($widget))->toBe([$visible->id]);
});

it('returns absences overlapping the requested date range only', function () {
    $this->actingAs(User::factory()->create());

    $inRange = UserAbsence::factory()->create([
        'starts_at' => now()->addMonth(),
        'ends_at' => now()->addMonth()->addDays(2),
    ]);
    UserAbsence::factory()->create([
        'starts_at' => now()->addYears(5),
        'ends_at' => now()->addYears(5)->addDays(2),
    ]);

    $widget = new UserAbsenceCalendarWidget;

    expect(fetchAllAbsences($widget))->toBe([$inRange->id]);
});

it('filters absences by employee', function () {
    $employee = User::factory()->create();
    $this->actingAs(User::factory()->create());

    $matching = UserAbsence::factory()->create([
        'user_id' => $employee->id,
        'starts_at' => now()->addWeek(),
        'ends_at' => now()->addWeek()->addDays(2),
    ]);
    UserAbsence::factory()->create([
        'starts_at' => now()->addWeek(),
        'ends_at' => now()->addWeek()->addDays(2),
    ]);

    $widget = new UserAbsenceCalendarWidget;
    $widget->pageFilters = ['employee_id' => $employee->id];

    expect(fetchAllAbsences($widget))->toBe([$matching->id]);
});

it('shows an absence event alongside a deadline event on the same calendar range', function () {
    $this->actingAs(User::factory()->create());

    $tender = Tender::factory()->create();
    $tender->deadlines()->delete();
    $deadline = TenderDeadline::factory()->for($tender)->create(['due_at' => now()->addWeek()]);
    $absence = UserAbsence::factory()->create([
        'starts_at' => now()->addWeek(),
        'ends_at' => now()->addWeek()->addDays(2),
    ]);

    expect(fetchAllDeadlines(new TenderDeadlineCalendarWidget))->toContain($deadline->id);
    expect(fetchAllAbsences(new UserAbsenceCalendarWidget))->toContain($absence->id);
});
