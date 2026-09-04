<?php

use App\Enums\BidDecision;
use App\Enums\DeadlineType;
use App\Enums\RoleName;
use App\Enums\TenderStatus;
use App\Filament\Pages\Statistics;
use App\Models\ServiceCategory;
use App\Models\Tender;
use App\Models\TenderBidDecision;
use App\Models\TenderCalculation;
use App\Models\TenderDeadline;
use App\Models\TenderResult;
use App\Models\TenderSubmission;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

it('reconciles win rate, participation rate, and the formal-exclusion KPI against a known fixture', function () {
    $departmentHead = tap(User::factory()->create())->assignRole(RoleName::DEPARTMENT_HEAD);
    $this->actingAs($departmentHead);

    $won = Tender::factory()->create(['status' => TenderStatus::WON]);
    $lost = Tender::factory()->create(['status' => TenderStatus::LOST]);
    $excluded = Tender::factory()->create(['status' => TenderStatus::EXCLUDED]);

    foreach ([$won, $lost] as $tender) {
        TenderBidDecision::factory()->create(['tender_id' => $tender->id, 'decision' => BidDecision::BID]);
    }
    TenderBidDecision::factory()->noBid()->create(['tender_id' => $excluded->id]);

    Livewire::test(Statistics::class)
        ->assertSuccessful()
        ->assertSee('50.0%') // win rate: 1 won / (1 won + 1 lost)
        ->assertSee('66.7%'); // participation rate: 2 bid / 3 decided
});

it('hides price-bearing figures from a user without the see-prices right', function () {
    $staff = tap(User::factory()->create())->assignRole(RoleName::STAFF);
    $this->actingAs($staff);

    Tender::factory()->create([
        'status' => TenderStatus::WON,
        'estimated_contract_volume' => 100000,
        'estimated_contract_volume_unknown' => false,
    ]);

    Livewire::test(Statistics::class)
        ->assertSuccessful()
        ->assertSee(__('statistics.price_hidden'))
        ->assertDontSee('€100,000.00');
});

it('shows price-bearing figures to a user with the see-prices right', function () {
    $departmentHead = tap(User::factory()->create())->assignRole(RoleName::DEPARTMENT_HEAD);
    $this->actingAs($departmentHead);

    Tender::factory()->create([
        'status' => TenderStatus::WON,
        'estimated_contract_volume' => 100000,
        'estimated_contract_volume_unknown' => false,
    ]);

    Livewire::test(Statistics::class)
        ->assertSuccessful()
        ->assertSee(__('tenders.infolist.money_eur', ['amount' => '100,000.00']));
});

it('only counts a category-scoped user\'s own category, while a management user sees all', function () {
    $categoryA = ServiceCategory::factory()->create();
    $categoryB = ServiceCategory::factory()->create();

    Tender::factory()->create(['status' => TenderStatus::EXCLUDED, 'service_category_id' => $categoryA->id]);
    Tender::factory()->create(['status' => TenderStatus::EXCLUDED, 'service_category_id' => $categoryB->id]);

    $scopedUser = tap(User::factory()->create(['service_category_id' => $categoryA->id]))->assignRole(RoleName::STAFF);
    $this->actingAs($scopedUser);

    Livewire::test(Statistics::class)
        ->assertSuccessful()
        ->assertViewHas('formalExclusions', fn (array $data): bool => $data['count'] === 1);

    $management = tap(User::factory()->create(['service_category_id' => null]))->assignRole(RoleName::SUPER_ADMIN);
    $this->actingAs($management);

    Livewire::test(Statistics::class)
        ->assertSuccessful()
        ->assertViewHas('formalExclusions', fn (array $data): bool => $data['count'] === 2);
});

it('computes submission-deadline reliability from recorded submissions', function () {
    $departmentHead = tap(User::factory()->create())->assignRole(RoleName::DEPARTMENT_HEAD);
    $this->actingAs($departmentHead);

    $onTimeTender = Tender::factory()->create();
    TenderDeadline::factory()->create([
        'tender_id' => $onTimeTender->id,
        'type' => DeadlineType::SUBMISSION,
        'due_at' => now()->addDays(2),
    ]);
    TenderSubmission::factory()->create([
        'tender_id' => $onTimeTender->id,
        'submission_date' => now(),
    ]);

    Livewire::test(Statistics::class)
        ->assertSuccessful()
        ->assertSee('100.0%');
});

it('tallies win/loss reasons recorded across tender results', function () {
    $departmentHead = tap(User::factory()->create())->assignRole(RoleName::DEPARTMENT_HEAD);
    $this->actingAs($departmentHead);

    $tender = Tender::factory()->create(['status' => TenderStatus::LOST]);
    TenderResult::factory()->create([
        'tender_id' => $tender->id,
        'win_loss_reasons' => ['price', 'quality'],
    ]);

    Livewire::test(Statistics::class)
        ->assertSuccessful()
        ->assertSee(__('win_loss_reasons.price'))
        ->assertSee(__('win_loss_reasons.quality'));
});

it('averages actual margin from each tender\'s current calculation when see-prices is granted', function () {
    $departmentHead = tap(User::factory()->create())->assignRole(RoleName::DEPARTMENT_HEAD);
    $this->actingAs($departmentHead);

    $tender = Tender::factory()->create();
    TenderCalculation::factory()->create([
        'tender_id' => $tender->id,
        'version_number' => 1,
        'actual_margin' => 20,
    ]);

    Livewire::test(Statistics::class)
        ->assertSuccessful()
        ->assertSee('20.0%');
});
