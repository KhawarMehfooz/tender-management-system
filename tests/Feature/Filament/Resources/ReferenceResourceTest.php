<?php

use App\Filament\Resources\References\Pages\CreateReference;
use App\Filament\Resources\References\Pages\EditReference;
use App\Filament\Resources\References\ReferenceResource;
use App\Filament\Resources\References\RelationManagers\AttachmentsRelationManager;
use App\Models\Reference;
use App\Models\ReferenceAttachment;
use App\Models\Sector;
use App\Models\ServiceCategory;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

describe('creation', function () {
    it('creates a reference with valid data', function () {
        $serviceCategory = ServiceCategory::factory()->create();
        $sector = Sector::factory()->create();

        Livewire::test(CreateReference::class)
            ->fillForm([
                'client' => 'Acme Corp',
                'service_category_id' => $serviceCategory->id,
                'sector_id' => $sector->id,
                'location' => 'Berlin',
                'contract_volume' => 150_000,
                'headcount' => 20,
                'contact_person_name' => 'Jane Doe',
                'contact_person_email' => 'jane@example.com',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        expect(Reference::where('client', 'Acme Corp')->exists())->toBeTrue();
        expect(Reference::where('client', 'Acme Corp')->first()->created_by)->toBe(auth()->id());
    });

    it('clears the contract volume when marked unknown', function () {
        Livewire::test(CreateReference::class)
            ->fillForm([
                'client' => 'Acme Corp',
                'contract_volume' => 150_000,
                'contract_volume_unknown' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $reference = Reference::where('client', 'Acme Corp')->first();
        expect($reference->contract_volume)->toBeNull();
        expect($reference->contract_volume_unknown)->toBeTrue();
    });
});

describe('editing', function () {
    it('updates an existing reference', function () {
        $reference = Reference::factory()->create(['client' => 'Old Name']);

        Livewire::test(EditReference::class, ['record' => $reference->getRouteKey()])
            ->fillForm(['client' => 'New Name'])
            ->call('save')
            ->assertHasNoFormErrors();

        expect($reference->refresh()->client)->toBe('New Name');
    });
});

describe('deletion', function () {
    it('deletes a reference', function () {
        $reference = Reference::factory()->create();

        expect(ReferenceResource::canDelete($reference))->toBeTrue();
    });
});

describe('attachments relation manager', function () {
    it('lists the reference\'s own attachments', function () {
        $reference = Reference::factory()->create();
        $attachment = ReferenceAttachment::factory()->for($reference)->create();
        $foreignAttachment = ReferenceAttachment::factory()->create();

        Livewire::test(AttachmentsRelationManager::class, ['ownerRecord' => $reference, 'pageClass' => EditReference::class])
            ->assertCanSeeTableRecords([$attachment])
            ->assertCanNotSeeTableRecords([$foreignAttachment]);
    });
});

describe('attachment download', function () {
    it('streams the file for an authenticated user', function () {
        Storage::fake('local');
        $attachment = ReferenceAttachment::factory()->create(['file_path' => 'reference-attachments/letter.pdf']);
        Storage::disk('local')->put($attachment->file_path, 'contents');

        $this->get($attachment->downloadUrl())->assertOk();
    });

    it('rejects an unsigned download link', function () {
        Storage::fake('local');
        $attachment = ReferenceAttachment::factory()->create(['file_path' => 'reference-attachments/letter.pdf']);
        Storage::disk('local')->put($attachment->file_path, 'contents');

        $this->get(route('reference-attachments.download', $attachment))
            ->assertForbidden();
    });

    it('rejects an expired download link', function () {
        Storage::fake('local');
        $attachment = ReferenceAttachment::factory()->create(['file_path' => 'reference-attachments/letter.pdf']);
        Storage::disk('local')->put($attachment->file_path, 'contents');
        $expiredUrl = $attachment->downloadUrl();

        $this->travel(6)->minutes();

        $this->get($expiredUrl)->assertForbidden();
    });

    it('returns 404 when the file is missing from disk', function () {
        Storage::fake('local');
        $attachment = ReferenceAttachment::factory()->create(['file_path' => 'reference-attachments/missing.pdf']);

        $this->get($attachment->downloadUrl())->assertNotFound();
    });
});
