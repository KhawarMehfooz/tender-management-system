<?php

use App\Enums\TenderStatus;
use App\Filament\Resources\Tenders\Pages\ArchivedTenders;
use App\Models\ServiceCategory;
use App\Models\Tender;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

it('only lists archived tenders', function () {
    $archived = Tender::factory()->create(['is_archived' => true]);
    $active = Tender::factory()->create(['is_archived' => false]);

    Livewire::test(ArchivedTenders::class)
        ->assertCanSeeTableRecords([$archived])
        ->assertCanNotSeeTableRecords([$active]);
});

it('still shows a won tender\'s own status badge once archived', function () {
    $tender = Tender::factory()->create(['is_archived' => true, 'status' => TenderStatus::WON]);

    Livewire::test(ArchivedTenders::class)
        ->assertCanSeeTableRecords([$tender])
        ->assertSee(TenderStatus::WON->getLabel());
});

it('respects category scoping', function () {
    $categoryA = ServiceCategory::factory()->create();
    $categoryB = ServiceCategory::factory()->create();

    $archivedInA = Tender::factory()->create(['is_archived' => true, 'service_category_id' => $categoryA->id]);
    $archivedInB = Tender::factory()->create(['is_archived' => true, 'service_category_id' => $categoryB->id]);

    $scopedUser = User::factory()->create(['service_category_id' => $categoryA->id]);
    $this->actingAs($scopedUser);

    Livewire::test(ArchivedTenders::class)
        ->assertCanSeeTableRecords([$archivedInA])
        ->assertCanNotSeeTableRecords([$archivedInB]);
});

it('filters archived tenders combinably', function () {
    $category = ServiceCategory::factory()->create();
    $matches = Tender::factory()->create([
        'is_archived' => true,
        'service_category_id' => $category->id,
        'status' => TenderStatus::LOST,
    ]);
    $wrongStatus = Tender::factory()->create([
        'is_archived' => true,
        'service_category_id' => $category->id,
        'status' => TenderStatus::WON,
    ]);

    Livewire::test(ArchivedTenders::class)
        ->filterTable('service_category_id', $category->id)
        ->filterTable('status', TenderStatus::LOST->value)
        ->assertCanSeeTableRecords([$matches])
        ->assertCanNotSeeTableRecords([$wrongStatus]);
});
