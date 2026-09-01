<?php

use App\Enums\CertificateType;
use App\Enums\RoleName;
use App\Filament\Resources\Certificates\CertificateResource;
use App\Filament\Resources\Certificates\Pages\CreateCertificate;
use App\Filament\Resources\Certificates\Pages\EditCertificate;
use App\Filament\Resources\Certificates\Pages\ListCertificates;
use App\Models\Certificate;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

describe('access', function () {
    it('allows a manage-certificates holder to view the list, create, and edit pages', function () {
        $admin = tap(User::factory()->create())->assignRole(RoleName::SUPER_ADMIN);
        $certificate = Certificate::factory()->create();

        $this->actingAs($admin);

        Livewire::test(ListCertificates::class)->assertSuccessful();
        Livewire::test(CreateCertificate::class)->assertSuccessful();
        Livewire::test(EditCertificate::class, ['record' => $certificate->getRouteKey()])->assertSuccessful();
    });

    it('rejects a user without the right from the list, create, and edit pages, server-side', function () {
        $staff = tap(User::factory()->create())->assignRole(RoleName::STAFF);
        $certificate = Certificate::factory()->create();

        $this->actingAs($staff);

        Livewire::test(ListCertificates::class)->assertForbidden();
        Livewire::test(CreateCertificate::class)->assertForbidden();
        Livewire::test(EditCertificate::class, ['record' => $certificate->getRouteKey()])->assertForbidden();
    });

    it('gates canDelete/canDeleteAny on the right', function () {
        $certificate = Certificate::factory()->create();

        expect(CertificateResource::canDelete($certificate))->toBeFalse();
        expect(CertificateResource::canDeleteAny())->toBeFalse();

        $admin = tap(User::factory()->create())->assignRole(RoleName::SUPER_ADMIN);
        $this->actingAs($admin);

        expect(CertificateResource::canDelete($certificate))->toBeTrue();
        expect(CertificateResource::canDeleteAny())->toBeTrue();
    });
});

describe('creation', function () {
    it('creates a certificate and stamps the creator', function () {
        $admin = tap(User::factory()->create())->assignRole(RoleName::SUPER_ADMIN);
        $this->actingAs($admin);

        Livewire::test(CreateCertificate::class)
            ->fillForm([
                'type' => CertificateType::INSURANCE->value,
                'name' => 'Liability insurance',
                'valid_from' => now()->subYear()->toDateString(),
                'expiry_date' => now()->addYear()->toDateString(),
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $certificate = Certificate::where('name', 'Liability insurance')->first();
        expect($certificate)->not->toBeNull();
        expect($certificate->created_by)->toBe($admin->id);
    });

    it('rejects an expiry date before the valid-from date', function () {
        $admin = tap(User::factory()->create())->assignRole(RoleName::SUPER_ADMIN);
        $this->actingAs($admin);

        Livewire::test(CreateCertificate::class)
            ->fillForm([
                'type' => CertificateType::INSURANCE->value,
                'name' => 'Liability insurance',
                'valid_from' => now()->toDateString(),
                'expiry_date' => now()->subYear()->toDateString(),
            ])
            ->call('create')
            ->assertHasFormErrors(['expiry_date']);
    });
});

describe('download', function () {
    it('streams the file for a manage-certificates holder', function () {
        Storage::fake('local');
        $admin = tap(User::factory()->create())->assignRole(RoleName::SUPER_ADMIN);
        $certificate = Certificate::factory()->create(['file_path' => 'certificates/policy.pdf', 'original_filename' => 'policy.pdf']);
        Storage::disk('local')->put($certificate->file_path, 'contents');

        $this->actingAs($admin)
            ->get($certificate->downloadUrl())
            ->assertOk();
    });

    it('rejects a user without the right, even with a validly signed link', function () {
        Storage::fake('local');
        $staff = tap(User::factory()->create())->assignRole(RoleName::STAFF);
        $certificate = Certificate::factory()->create(['file_path' => 'certificates/policy.pdf', 'original_filename' => 'policy.pdf']);
        Storage::disk('local')->put($certificate->file_path, 'contents');

        $this->actingAs($staff)
            ->get($certificate->downloadUrl())
            ->assertForbidden();
    });

    it('rejects an unsigned download link', function () {
        Storage::fake('local');
        $admin = tap(User::factory()->create())->assignRole(RoleName::SUPER_ADMIN);
        $certificate = Certificate::factory()->create(['file_path' => 'certificates/policy.pdf', 'original_filename' => 'policy.pdf']);
        Storage::disk('local')->put($certificate->file_path, 'contents');

        $this->actingAs($admin)
            ->get(route('certificates.download', $certificate))
            ->assertForbidden();
    });

    it('returns 404 when no file has been uploaded', function () {
        $admin = tap(User::factory()->create())->assignRole(RoleName::SUPER_ADMIN);
        $certificate = Certificate::factory()->create();

        $this->actingAs($admin)
            ->get($certificate->downloadUrl())
            ->assertNotFound();
    });
});
