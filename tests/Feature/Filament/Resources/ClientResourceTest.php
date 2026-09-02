<?php

use App\Enums\CompetitorOutcome;
use App\Filament\Resources\Clients\ClientResource;
use App\Filament\Resources\Clients\Pages\CreateClient;
use App\Filament\Resources\Clients\Pages\EditClient;
use App\Filament\Resources\Clients\Pages\ViewClient;
use App\Filament\Resources\Clients\RelationManagers\TendersRelationManager;
use App\Models\Client;
use App\Models\Competitor;
use App\Models\Tender;
use App\Models\TenderCompetitor;
use App\Models\TenderResult;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

describe('creation', function () {
    it('creates a client with valid data', function () {
        Livewire::test(CreateClient::class)
            ->fillForm([
                'name' => 'City of Springfield',
                'region' => 'Bavaria',
                'active' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        expect(Client::where('name', 'City of Springfield')->exists())->toBeTrue();
    });

    it('rejects a name that duplicates an existing client', function () {
        Client::factory()->create(['name' => 'City of Springfield']);

        Livewire::test(CreateClient::class)
            ->fillForm([
                'name' => 'City of Springfield',
                'active' => true,
            ])
            ->call('create')
            ->assertHasFormErrors(['name' => 'unique']);
    });
});

describe('deletion', function () {
    it('never authorizes deleting a client, even for the acting user', function () {
        $client = Client::factory()->create();

        expect(ClientResource::canDelete($client))->toBeFalse();
        expect(ClientResource::canDeleteAny())->toBeFalse();
    });

    it('offers no delete action on the edit page', function () {
        $client = Client::factory()->create();

        Livewire::test(EditClient::class, ['record' => $client->getRouteKey()])
            ->assertActionDoesNotExist('delete');
    });
});

describe('client history', function () {
    it('lists a tender linked to the client with its outcome and competitors, read-only', function () {
        $client = Client::factory()->create();
        $tender = Tender::factory()->create(['client_id' => $client->id]);
        TenderResult::factory()->create(['tender_id' => $tender->id, 'winner' => 'Rival Services GmbH']);
        $competitor = Competitor::factory()->create(['name' => 'Rival Services GmbH']);
        TenderCompetitor::factory()->create([
            'tender_id' => $tender->id,
            'competitor_id' => $competitor->id,
            'outcome' => CompetitorOutcome::THEY_WON,
        ]);

        Livewire::test(TendersRelationManager::class, ['ownerRecord' => $client, 'pageClass' => ViewClient::class])
            ->assertCanSeeTableRecords([$tender])
            ->assertTableActionDoesNotExist('create')
            ->assertTableActionDoesNotExist('edit', record: $tender)
            ->assertTableActionDoesNotExist('delete', record: $tender);
    });

    it('does not list a tender linked to a different client', function () {
        $client = Client::factory()->create();
        $otherClient = Client::factory()->create();
        $otherTender = Tender::factory()->create(['client_id' => $otherClient->id]);

        Livewire::test(TendersRelationManager::class, ['ownerRecord' => $client, 'pageClass' => ViewClient::class])
            ->assertCanNotSeeTableRecords([$otherTender]);
    });

    it('renders the view page', function () {
        $client = Client::factory()->create();

        Livewire::test(ViewClient::class, ['record' => $client->getRouteKey()])
            ->assertSuccessful();
    });
});
