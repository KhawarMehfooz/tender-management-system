<?php

use App\Models\Client;
use App\Models\Tender;

it('creates one client per distinct contracting authority and links matching tenders', function () {
    $a1 = Tender::factory()->create(['contracting_authority' => 'City of Example']);
    $a2 = Tender::factory()->create(['contracting_authority' => 'City of Example']);
    $b1 = Tender::factory()->create(['contracting_authority' => 'County of Sample']);

    $this->artisan('tenders:backfill-clients')->assertSuccessful();

    expect(Client::where('name', 'City of Example')->count())->toBe(1);
    expect(Client::where('name', 'County of Sample')->count())->toBe(1);

    $cityClient = Client::where('name', 'City of Example')->first();
    $countyClient = Client::where('name', 'County of Sample')->first();

    expect($a1->refresh()->client_id)->toBe($cityClient->id);
    expect($a2->refresh()->client_id)->toBe($cityClient->id);
    expect($b1->refresh()->client_id)->toBe($countyClient->id);
});

it('is safe to re-run without creating duplicate clients or overwriting existing links', function () {
    $tender = Tender::factory()->create(['contracting_authority' => 'City of Example']);

    $this->artisan('tenders:backfill-clients')->assertSuccessful();
    $this->artisan('tenders:backfill-clients')->assertSuccessful();

    expect(Client::where('name', 'City of Example')->count())->toBe(1);
    expect($tender->refresh()->client_id)->not->toBeNull();
});

it('leaves an already-linked tender untouched even if another tender shares its authority string', function () {
    $existingClient = Client::factory()->create(['name' => 'Manually Linked Client']);
    $tender = Tender::factory()->create([
        'contracting_authority' => 'City of Example',
        'client_id' => $existingClient->id,
    ]);
    $other = Tender::factory()->create(['contracting_authority' => 'City of Example']);

    $this->artisan('tenders:backfill-clients')->assertSuccessful();

    expect($tender->refresh()->client_id)->toBe($existingClient->id);
    expect($other->refresh()->client_id)->not->toBeNull();
    expect($other->refresh()->client_id)->not->toBe($existingClient->id);
});
