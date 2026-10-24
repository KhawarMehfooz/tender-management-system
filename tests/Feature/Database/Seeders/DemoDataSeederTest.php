<?php

use App\Enums\TenderStatus;
use App\Models\Client;
use App\Models\ProcurementProcedure;
use App\Models\Sector;
use App\Models\Source;
use App\Models\Tender;
use Database\Seeders\DemoDataSeeder;
use Database\Seeders\NutsCodeSeeder;
use Database\Seeders\ProcurementProcedureSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SectorSeeder;
use Database\Seeders\ServiceCategorySeeder;
use Database\Seeders\SourceSeeder;
use Illuminate\Support\Facades\Storage;

it('populates screenshot-ready tenders without generating placeholder reference data', function () {
    Storage::fake('local');
    $this->freezeTime();

    $this->seed([
        RolesAndPermissionsSeeder::class,
        ServiceCategorySeeder::class,
        SectorSeeder::class,
        SourceSeeder::class,
        ProcurementProcedureSeeder::class,
        NutsCodeSeeder::class,
    ]);

    $this->seed(DemoDataSeeder::class);

    // Factories previously added a Latin-labelled sector, source, and procedure per tender,
    // plus additional sectors for references, rather than reusing the named lookup records.
    $this->assertDatabaseCount('sectors', 6);
    $this->assertDatabaseCount('sources', 10);
    $this->assertDatabaseCount('procurement_procedures', 8);
    $this->assertDatabaseCount('clients', 10);
    $this->assertDatabaseHas('users', ['email' => 'admin@example.com', 'name' => 'Alexander Weber']);
    $this->assertDatabaseHas('clients', ['name' => 'Elbpark Medical Centre', 'region' => 'Hamburg']);
    $this->assertDatabaseHas('competitors', ['name' => 'Nordblick Facility Services']);
    $this->assertDatabaseHas('tasks', ['title' => 'Review tender requirements']);

    $tenders = Tender::withoutGlobalScopes()->with(['client', 'nutsCode', 'sector', 'source', 'procurementProcedure', 'serviceCategory'])->get();

    expect($tenders)->toHaveCount(count(TenderStatus::cases()) * 3);
    expect($tenders->pluck('nuts_code_id')->unique())->toHaveCount(5);
    expect($tenders->pluck('sector_id')->unique())->toHaveCount(5);
    expect($tenders->pluck('client_id')->unique())->toHaveCount(10);

    foreach ($tenders as $tender) {
        expect($tender->client)->toBeInstanceOf(Client::class);
        expect($tender->nutsCode)->not->toBeNull();
        expect($tender->sector)->toBeInstanceOf(Sector::class);
        expect($tender->source)->toBeInstanceOf(Source::class);
        expect($tender->procurementProcedure)->toBeInstanceOf(ProcurementProcedure::class);
        expect($tender->contracting_authority)->toBe($tender->client->name);
        expect($tender->title)->toBe($tender->serviceCategory->name.' — '.$tender->client->name);
    }

    // Preserve the demo workflow: later-stage tenders must still pass the approval gate.
    foreach ([TenderStatus::SUBMISSION, TenderStatus::FOLLOW_UP, TenderStatus::WON, TenderStatus::LOST] as $status) {
        $atStage = $tenders->where('status', $status);
        expect($atStage)->toHaveCount(3);
        foreach ($atStage as $tender) {
            expect($tender->currentCalculation->isFullyApproved())->toBeTrue();
        }
    }

    $document = $tenders->first()->documents()->with('versions')->firstOrFail();
    expect($document->title)->toContain($tenders->first()->contracting_authority);
    Storage::disk('local')->assertExists($document->versions->first()->file_path);
});
