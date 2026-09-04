<?php

use App\Enums\RoleName;
use App\Enums\TenderStatus;
use App\Filament\Pages\Reports;
use App\Models\Competitor;
use App\Models\ScheduledReport;
use App\Models\Tender;
use App\Models\TenderCompetitor;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Livewire\Livewire;
use Maatwebsite\Excel\Facades\Excel;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

it('hides the competitors and performance report rows from a user lacking the matching right', function () {
    $staff = tap(User::factory()->create())->assignRole(RoleName::STAFF);
    $this->actingAs($staff);

    Livewire::test(Reports::class)
        ->assertSuccessful()
        ->assertSee(__('reports.types.pipeline.label'))
        ->assertDontSee(__('reports.types.competitors.label'))
        ->assertDontSee(__('reports.types.performance.label'));
});

it('shows every report row to a department head', function () {
    $departmentHead = tap(User::factory()->create())->assignRole(RoleName::DEPARTMENT_HEAD);
    $this->actingAs($departmentHead);

    Livewire::test(Reports::class)
        ->assertSuccessful()
        ->assertSee(__('reports.types.pipeline.label'))
        ->assertSee(__('reports.types.win_loss.label'))
        ->assertSee(__('reports.types.competitors.label'))
        ->assertSee(__('reports.types.performance.label'))
        ->assertSee(__('reports.types.deadlines.label'))
        ->assertSee(__('reports.types.management.label'));
});

it('re-checks the right server-side when a gated report export is triggered directly', function () {
    $staff = tap(User::factory()->create())->assignRole(RoleName::STAFF);
    $this->actingAs($staff);

    Livewire::test(Reports::class)
        ->call('mountAction', 'exportPdf', ['report' => 'competitors'])
        ->assertForbidden();
});

it('downloads a PDF for every report type', function (string $key) {
    $departmentHead = tap(User::factory()->create())->assignRole(RoleName::DEPARTMENT_HEAD);
    $this->actingAs($departmentHead);

    Tender::factory()->create(['status' => TenderStatus::WON]);
    Tender::factory()->create(['status' => TenderStatus::LOST]);
    $competitor = Competitor::factory()->create();
    TenderCompetitor::factory()->create(['competitor_id' => $competitor->id]);

    Livewire::test(Reports::class)
        ->callAction('exportPdf', arguments: ['report' => $key])
        ->assertFileDownloaded($key.'-report.pdf');
})->with(['pipeline', 'win_loss', 'competitors', 'performance', 'deadlines', 'management']);

it('downloads an Excel file for every report type', function (string $key) {
    Excel::fake();

    $departmentHead = tap(User::factory()->create())->assignRole(RoleName::DEPARTMENT_HEAD);
    $this->actingAs($departmentHead);

    Livewire::test(Reports::class)
        ->callAction('exportExcel', arguments: ['report' => $key])
        ->assertFileDownloaded();

    Excel::assertDownloaded($key.'-report.xlsx');
})->with(['pipeline', 'win_loss', 'competitors', 'performance', 'deadlines', 'management']);

it('omits price columns from the management export for a user without see-prices', function () {
    Excel::fake();

    $staff = tap(User::factory()->create())->assignRole(RoleName::STAFF);
    $this->actingAs($staff);

    Livewire::test(Reports::class)
        ->callAction('exportExcel', arguments: ['report' => 'management'])
        ->assertFileDownloaded();

    Excel::assertDownloaded('management-report.xlsx', function ($export): bool {
        $rows = collect($export->array());

        return $rows->contains(fn (array $row): bool => $row[0] === 'Formal exclusions')
            && ! $rows->contains(fn (array $row): bool => $row[0] === 'Average contract value (EUR)');
    });
});

it('includes price columns in the management export for a user with see-prices', function () {
    Excel::fake();

    $departmentHead = tap(User::factory()->create())->assignRole(RoleName::DEPARTMENT_HEAD);
    $this->actingAs($departmentHead);

    Livewire::test(Reports::class)
        ->callAction('exportExcel', arguments: ['report' => 'management'])
        ->assertFileDownloaded();

    Excel::assertDownloaded('management-report.xlsx', function ($export): bool {
        $rows = collect($export->array());

        return $rows->contains(fn (array $row): bool => $row[0] === 'Average contract value (EUR)');
    });
});

describe('report history table', function () {
    it('lists past scheduled reports for a user holding view-employee-statistics', function () {
        $departmentHead = tap(User::factory()->create())->assignRole(RoleName::DEPARTMENT_HEAD);
        $this->actingAs($departmentHead);

        $report = ScheduledReport::factory()->create();

        Livewire::test(Reports::class)->assertCanSeeTableRecords([$report]);
    });

    it('shows no rows to a user lacking view-employee-statistics even though reports exist', function () {
        $staff = tap(User::factory()->create())->assignRole(RoleName::STAFF);
        $this->actingAs($staff);

        $report = ScheduledReport::factory()->create();

        Livewire::test(Reports::class)->assertCanNotSeeTableRecords([$report]);
    });
});
