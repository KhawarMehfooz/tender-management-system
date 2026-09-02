<?php

use App\Enums\RoleName;
use App\Filament\Pages\MarketAnalysis;
use App\Filament\Widgets\TendersByClientChartWidget;
use App\Filament\Widgets\TendersBySectorChartWidget;
use App\Models\Client;
use App\Models\Sector;
use App\Models\Tender;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

it('is reachable by any authenticated panel user', function () {
    $user = tap(User::factory()->create())->assignRole(RoleName::STAFF);

    $this->actingAs($user);

    Livewire::test(MarketAnalysis::class)
        ->assertSuccessful();
});

it('breaks tenders down by sector with correct counts', function () {
    $admin = tap(User::factory()->create())->assignRole(RoleName::SUPER_ADMIN);
    $this->actingAs($admin);

    $construction = Sector::factory()->create(['name' => 'Construction']);
    $it = Sector::factory()->create(['name' => 'IT Services']);

    Tender::factory()->count(2)->create(['sector_id' => $construction->id]);
    Tender::factory()->count(1)->create(['sector_id' => $it->id]);

    $widget = Livewire::test(TendersBySectorChartWidget::class)
        ->assertSuccessful()
        ->instance();

    $data = invade($widget)->getData();

    expect($data['labels'])->toBe(['Construction', 'IT Services']);
    expect($data['datasets'][0]['data'])->toBe([2, 1]);
});

it('groups tenders with no client under the unknown label', function () {
    $admin = tap(User::factory()->create())->assignRole(RoleName::SUPER_ADMIN);
    $this->actingAs($admin);

    $client = Client::factory()->create(['name' => 'Stadt Musterstadt']);
    Tender::factory()->create(['client_id' => $client->id]);
    Tender::factory()->create(['client_id' => null]);

    $widget = Livewire::test(TendersByClientChartWidget::class)
        ->assertSuccessful()
        ->instance();

    $data = invade($widget)->getData();

    $labels = $data['labels'];
    $totals = $data['datasets'][0]['data'];

    expect($totals[array_search('Stadt Musterstadt', $labels, true)])->toBe(1);
    expect($totals[array_search(__('market_analysis.unknown_label'), $labels, true)])->toBe(1);
});

it('folds anything past the top 5 values into an "Other" slice', function () {
    $admin = tap(User::factory()->create())->assignRole(RoleName::SUPER_ADMIN);
    $this->actingAs($admin);

    foreach (range(1, 7) as $i) {
        $sector = Sector::factory()->create(['name' => "Sector {$i}"]);
        Tender::factory()->count($i)->create(['sector_id' => $sector->id]);
    }

    $widget = Livewire::test(TendersBySectorChartWidget::class)
        ->assertSuccessful()
        ->instance();

    $data = invade($widget)->getData();

    expect($data['labels'])->toHaveCount(6);
    expect($data['labels'])->toContain(__('market_analysis.other_label'));

    $otherIndex = array_search(__('market_analysis.other_label'), $data['labels'], true);
    // Sectors 1..7 have counts 1..7 (total 28); top 5 are 7,6,5,4,3 (25), so "Other" folds
    // sectors 2 and 1 together (3).
    expect($data['datasets'][0]['data'][$otherIndex])->toBe(3);
});
