<?php

use App\Models\Reference;
use App\Models\ReferenceAttachment;
use App\Models\Sector;
use App\Models\ServiceCategory;
use App\Models\User;

test('a reference belongs to a service category, sector, and creator', function () {
    $serviceCategory = ServiceCategory::factory()->create();
    $sector = Sector::factory()->create();
    $user = User::factory()->create();

    $reference = Reference::factory()->create([
        'service_category_id' => $serviceCategory->id,
        'sector_id' => $sector->id,
        'created_by' => $user->id,
    ]);

    expect($reference->serviceCategory->is($serviceCategory))->toBeTrue();
    expect($reference->sector->is($sector))->toBeTrue();
    expect($reference->createdBy->is($user))->toBeTrue();
});

test('deleting a reference cascades to its attachments', function () {
    $reference = Reference::factory()->create();
    $attachment = ReferenceAttachment::factory()->for($reference)->create();

    $reference->delete();

    expect(ReferenceAttachment::find($attachment->id))->toBeNull();
});

test('the volumeUnknown factory state clears the contract volume', function () {
    $reference = Reference::factory()->volumeUnknown()->create();

    expect($reference->contract_volume)->toBeNull();
    expect($reference->contract_volume_unknown)->toBeTrue();
});
