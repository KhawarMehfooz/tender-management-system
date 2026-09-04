<?php

use App\Enums\RoleName;
use App\Filament\Resources\Clients\ClientResource;
use App\Filament\Resources\Competitors\CompetitorResource;
use App\Filament\Resources\Tenders\TenderResource;
use App\Filament\Resources\Users\UserResource;
use App\Models\Client;
use App\Models\Competitor;
use App\Models\ServiceCategory;
use App\Models\Tender;
use App\Models\TenderCompetitor;
use App\Models\TenderDocument;
use App\Models\TenderResult;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

describe('tender global search', function () {
    it('finds a tender by internal ID, city, and procurement office', function () {
        $this->actingAs(tap(User::factory()->create())->assignRole(RoleName::DEPARTMENT_HEAD));

        $tender = Tender::factory()->create([
            'city' => 'Musterstadt',
            'procurement_office' => 'Amt für Beschaffung',
        ]);

        expect(TenderResource::getGlobalSearchResults($tender->internal_id)->pluck('title'))->toContain($tender->title);
        expect(TenderResource::getGlobalSearchResults('Musterstadt')->pluck('title'))->toContain($tender->title);
        expect(TenderResource::getGlobalSearchResults('Amt für Beschaffung')->pluck('title'))->toContain($tender->title);
    });

    it('finds a tender through related client, service category, owner, document, result winner, and competitor', function () {
        $this->actingAs(tap(User::factory()->create())->assignRole(RoleName::DEPARTMENT_HEAD));

        $client = Client::factory()->create(['name' => 'Globex Municipal']);
        $owner = User::factory()->create(['name' => 'Jane Reviewer']);
        $category = ServiceCategory::factory()->create(['name' => 'Facility Services Alpha']);
        $tender = Tender::factory()->create([
            'client_id' => $client->id,
            'owner_id' => $owner->id,
            'service_category_id' => $category->id,
        ]);

        TenderDocument::factory()->create(['tender_id' => $tender->id, 'title' => 'Bid Specification Sheet']);
        TenderResult::factory()->create(['tender_id' => $tender->id, 'winner' => 'Rival Corp']);
        $competitor = Competitor::factory()->create(['name' => 'Acme Rivals Ltd']);
        TenderCompetitor::factory()->create(['tender_id' => $tender->id, 'competitor_id' => $competitor->id]);

        expect(TenderResource::getGlobalSearchResults('Globex Municipal')->pluck('title'))->toContain($tender->title);
        expect(TenderResource::getGlobalSearchResults('Jane Reviewer')->pluck('title'))->toContain($tender->title);
        expect(TenderResource::getGlobalSearchResults('Facility Services Alpha')->pluck('title'))->toContain($tender->title);
        expect(TenderResource::getGlobalSearchResults('Bid Specification Sheet')->pluck('title'))->toContain($tender->title);
        expect(TenderResource::getGlobalSearchResults('Rival Corp')->pluck('title'))->toContain($tender->title);
        expect(TenderResource::getGlobalSearchResults('Acme Rivals Ltd')->pluck('title'))->toContain($tender->title);
    });

    it('does not return a tender from another category to a category-scoped user', function () {
        $categoryA = ServiceCategory::factory()->create();
        $categoryB = ServiceCategory::factory()->create();

        $tenderA = Tender::factory()->create(['service_category_id' => $categoryA->id, 'title' => 'Alpha Tender']);
        $tenderB = Tender::factory()->create(['service_category_id' => $categoryB->id, 'title' => 'Beta Tender']);

        $scopedUser = tap(User::factory()->create(['service_category_id' => $categoryA->id]))->assignRole(RoleName::STAFF);
        $this->actingAs($scopedUser);

        $titles = TenderResource::getGlobalSearchResults($tenderA->internal_id)->pluck('title');
        expect($titles)->toContain('Alpha Tender');

        $titles = TenderResource::getGlobalSearchResults($tenderB->internal_id)->pluck('title');
        expect($titles)->not->toContain('Beta Tender');
    });
});

it('finds a client by name via global search', function () {
    $this->actingAs(tap(User::factory()->create())->assignRole(RoleName::DEPARTMENT_HEAD));

    $client = Client::factory()->create(['name' => 'Northgate Council']);

    expect(ClientResource::getGlobalSearchResults('Northgate Council')->pluck('title'))->toContain($client->name);
});

it('finds a competitor by name via global search when the user holds see-competitor-data', function () {
    $this->actingAs(tap(User::factory()->create())->assignRole(RoleName::DEPARTMENT_HEAD));

    $competitor = Competitor::factory()->create(['name' => 'Sentinel Security Group']);

    expect(CompetitorResource::getGlobalSearchResults('Sentinel Security Group')->pluck('title'))->toContain($competitor->name);
});

it('does not return competitors to a user without see-competitor-data', function () {
    $this->actingAs(tap(User::factory()->create())->assignRole(RoleName::STAFF));

    $competitor = Competitor::factory()->create(['name' => 'Hidden Competitor']);

    expect(CompetitorResource::canGloballySearch())->toBeFalse();
});

describe('user global search', function () {
    it('finds an employee by name and email for a user who can manage users', function () {
        $this->actingAs(tap(User::factory()->create())->assignRole(RoleName::SUPER_ADMIN));

        $employee = User::factory()->create(['name' => 'Alex Employee', 'email' => 'alex.employee@example.test']);

        expect(UserResource::getGlobalSearchResults('Alex Employee')->pluck('title'))->toContain($employee->name);
        expect(UserResource::getGlobalSearchResults('alex.employee@example.test')->pluck('title'))->toContain($employee->name);
    });

    it('is not globally searchable for a plain staff user', function () {
        $this->actingAs(tap(User::factory()->create())->assignRole(RoleName::STAFF));

        expect(UserResource::canGloballySearch())->toBeFalse();
    });
});
