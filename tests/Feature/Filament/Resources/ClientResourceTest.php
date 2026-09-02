<?php

use App\Filament\Resources\Clients\ClientResource;
use App\Filament\Resources\Clients\Pages\CreateClient;
use App\Filament\Resources\Clients\Pages\EditClient;
use App\Models\Client;
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
