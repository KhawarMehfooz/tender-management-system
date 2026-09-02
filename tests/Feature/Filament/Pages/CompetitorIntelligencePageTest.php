<?php

use App\Enums\CompetitorOutcome;
use App\Enums\RoleName;
use App\Filament\Pages\CompetitorIntelligence;
use App\Models\Competitor;
use App\Models\NutsCode;
use App\Models\Sector;
use App\Models\Tender;
use App\Models\TenderCompetitor;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

it('rejects a user without the right, server-side', function () {
    $staff = tap(User::factory()->create())->assignRole(RoleName::STAFF);

    $this->actingAs($staff);

    Livewire::test(CompetitorIntelligence::class)
        ->assertForbidden();
});

it('renders correct aggregate numbers for a known fixture set', function () {
    $admin = tap(User::factory()->create())->assignRole(RoleName::SUPER_ADMIN);
    $this->actingAs($admin);

    $competitor = Competitor::factory()->create(['name' => 'Rival Services GmbH']);

    $constructionSector = Sector::factory()->create(['name' => 'Construction']);
    $itSector = Sector::factory()->create(['name' => 'IT Services']);
    $bavaria = NutsCode::factory()->create(['label' => 'Bavaria']);
    $berlin = NutsCode::factory()->create(['label' => 'Berlin']);

    $tenderOne = Tender::factory()->create(['sector_id' => $constructionSector->id, 'nuts_code_id' => $bavaria->id]);
    $tenderTwo = Tender::factory()->create(['sector_id' => $constructionSector->id, 'nuts_code_id' => $bavaria->id]);
    $tenderThree = Tender::factory()->create(['sector_id' => $itSector->id, 'nuts_code_id' => $berlin->id]);

    TenderCompetitor::factory()->create([
        'tender_id' => $tenderOne->id,
        'competitor_id' => $competitor->id,
        'outcome' => CompetitorOutcome::THEY_WON,
    ]);
    TenderCompetitor::factory()->create([
        'tender_id' => $tenderTwo->id,
        'competitor_id' => $competitor->id,
        'outcome' => CompetitorOutcome::THEY_WON,
    ]);
    TenderCompetitor::factory()->create([
        'tender_id' => $tenderThree->id,
        'competitor_id' => $competitor->id,
        'outcome' => CompetitorOutcome::WE_WON,
    ]);

    $unrelatedCompetitor = Competitor::factory()->create(['name' => 'Never Seen Ltd']);

    $component = Livewire::test(CompetitorIntelligence::class)
        ->assertSuccessful()
        ->assertCanSeeTableRecords([$competitor, $unrelatedCompetitor]);

    $component->assertTableColumnStateSet('encounters', 3, record: $competitor)
        ->assertTableColumnStateSet('wins_against_us', 2, record: $competitor)
        ->assertTableColumnStateSet('losses_to_us', 1, record: $competitor)
        ->assertTableColumnStateSet('common_sector', 'Construction', record: $competitor)
        ->assertTableColumnStateSet('common_region', 'Bavaria', record: $competitor);

    $component->assertTableColumnStateSet('encounters', 0, record: $unrelatedCompetitor)
        ->assertTableColumnStateSet('wins_against_us', 0, record: $unrelatedCompetitor)
        ->assertTableColumnStateSet('losses_to_us', 0, record: $unrelatedCompetitor);
});
