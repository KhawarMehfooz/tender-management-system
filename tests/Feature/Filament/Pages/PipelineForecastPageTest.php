<?php

use App\Enums\Right;
use App\Enums\RoleName;
use App\Enums\TeamRole;
use App\Enums\TenderStatus;
use App\Filament\Pages\PipelineForecast;
use App\Models\Tender;
use App\Models\TenderParticipationScore;
use App\Models\TenderTeamMember;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

it('excludes terminal-status tenders from the pipeline', function () {
    $admin = tap(User::factory()->create())->assignRole(RoleName::SUPER_ADMIN);
    $this->actingAs($admin);

    $open = Tender::factory()->create(['status' => TenderStatus::PROCESSING]);
    $won = Tender::factory()->create(['status' => TenderStatus::WON]);
    $lost = Tender::factory()->create(['status' => TenderStatus::LOST]);

    Livewire::test(PipelineForecast::class)
        ->assertSuccessful()
        ->assertCanSeeTableRecords([$open])
        ->assertCanNotSeeTableRecords([$won, $lost]);
});

it('computes the weighted value as volume times normalized win probability', function () {
    $admin = tap(User::factory()->create())->assignRole(RoleName::SUPER_ADMIN);
    $this->actingAs($admin);

    $tender = Tender::factory()->create([
        'status' => TenderStatus::PROCESSING,
        'estimated_contract_volume' => 100000,
        'estimated_contract_volume_unknown' => false,
    ]);
    $score = TenderParticipationScore::factory()->rated(3)->create(['tender_id' => $tender->id]);

    $expected = 100000 * $score->winProbability();

    Livewire::test(PipelineForecast::class)
        ->assertSuccessful()
        ->assertTableColumnStateSet('weighted_value', $expected, record: $tender);
});

it('reports a null weighted value when the participation score is incomplete', function () {
    $admin = tap(User::factory()->create())->assignRole(RoleName::SUPER_ADMIN);
    $this->actingAs($admin);

    $tender = Tender::factory()->create([
        'status' => TenderStatus::PROCESSING,
        'estimated_contract_volume' => 100000,
        'estimated_contract_volume_unknown' => false,
    ]);

    Livewire::test(PipelineForecast::class)
        ->assertSuccessful()
        ->assertTableColumnStateSet('weighted_value', null, record: $tender);
});

it('hides the volume and weighted value columns from a user without see-prices', function () {
    $staff = tap(User::factory()->create())->assignRole(RoleName::STAFF);
    $this->actingAs($staff);

    Livewire::test(PipelineForecast::class)
        ->assertSuccessful()
        ->assertTableColumnHidden('estimated_contract_volume')
        ->assertTableColumnHidden('weighted_value');
});

it('shows the volume and weighted value columns to a see-prices holder', function () {
    $admin = tap(User::factory()->create())->assignRole(RoleName::SUPER_ADMIN);
    $this->actingAs($admin);

    expect($admin->can(Right::SEE_PRICES->value))->toBeTrue();

    Livewire::test(PipelineForecast::class)
        ->assertSuccessful()
        ->assertTableColumnVisible('estimated_contract_volume')
        ->assertTableColumnVisible('weighted_value');
});

it('marks full team-role coverage in the resource-check column', function () {
    $admin = tap(User::factory()->create())->assignRole(RoleName::SUPER_ADMIN);
    $this->actingAs($admin);

    $tender = Tender::factory()->create(['status' => TenderStatus::PROCESSING]);

    foreach (TeamRole::cases() as $role) {
        TenderTeamMember::factory()->create([
            'tender_id' => $tender->id,
            'functional_role' => $role,
        ]);
    }

    Livewire::test(PipelineForecast::class)
        ->assertSuccessful()
        ->assertTableColumnStateSet(
            'resource_check',
            __('pipeline_forecast.resource_check_coverage', ['covered' => count(TeamRole::cases()), 'total' => count(TeamRole::cases())]),
            record: $tender,
        );
});
