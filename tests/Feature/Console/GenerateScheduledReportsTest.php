<?php

use App\Console\Commands\GenerateScheduledReports;
use App\Enums\ReportPeriod;
use App\Enums\RoleName;
use App\Models\ScheduledReport;
use App\Models\User;
use App\Notifications\ScheduledReportGeneratedNotification;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->admin = tap(User::factory()->create())->assignRole(RoleName::SUPER_ADMIN);
    $this->departmentHead = tap(User::factory()->create())->assignRole(RoleName::DEPARTMENT_HEAD);
    $this->staff = tap(User::factory()->create())->assignRole(RoleName::STAFF);
});

it('creates a ScheduledReport row and a stored PDF file for each period option', function (string $period, ReportPeriod $expected) {
    Storage::fake('local');
    Notification::fake();

    $this->artisan(GenerateScheduledReports::class, ['--period' => $period])->assertSuccessful();

    $report = ScheduledReport::query()->sole();

    expect($report->report_type)->toBe('management');
    expect($report->period_type)->toBe($expected);
    expect($report->period_start)->not->toBeNull();
    expect($report->period_end)->not->toBeNull();
    expect($report->generated_at)->not->toBeNull();
    Storage::disk('local')->assertExists($report->file_path);
})->with([
    ['monthly', ReportPeriod::MONTHLY],
    ['quarterly', ReportPeriod::QUARTERLY],
    ['annual', ReportPeriod::ANNUAL],
]);

it('notifies every super-admin and department-head, not other users', function () {
    Storage::fake('local');
    Notification::fake();

    $this->artisan(GenerateScheduledReports::class, ['--period' => 'monthly'])->assertSuccessful();

    $report = ScheduledReport::query()->sole();

    Notification::assertSentTo($this->admin, ScheduledReportGeneratedNotification::class, fn ($n) => $n->scheduledReport->is($report));
    Notification::assertSentTo($this->departmentHead, ScheduledReportGeneratedNotification::class);
    Notification::assertNotSentTo($this->staff, ScheduledReportGeneratedNotification::class);
});

it('fails on an invalid --period value without creating anything', function () {
    Storage::fake('local');
    Notification::fake();

    $this->artisan(GenerateScheduledReports::class, ['--period' => 'weekly'])->assertFailed();

    expect(ScheduledReport::query()->count())->toBe(0);
    Notification::assertNothingSent();
});

describe('download route', function () {
    it('returns 403 for a user without view-employee-statistics', function () {
        Storage::fake('local');
        $report = ScheduledReport::factory()->create(['file_path' => 'scheduled-reports/report.pdf']);
        Storage::disk('local')->put($report->file_path, 'contents');

        $this->actingAs($this->staff)
            ->get($report->downloadUrl())
            ->assertForbidden();
    });

    it('streams the file for a user holding view-employee-statistics', function () {
        Storage::fake('local');
        $report = ScheduledReport::factory()->create(['file_path' => 'scheduled-reports/report.pdf']);
        Storage::disk('local')->put($report->file_path, 'contents');

        $this->actingAs($this->admin)
            ->get($report->downloadUrl())
            ->assertOk();
    });

    it('rejects an unsigned download link', function () {
        Storage::fake('local');
        $report = ScheduledReport::factory()->create(['file_path' => 'scheduled-reports/report.pdf']);
        Storage::disk('local')->put($report->file_path, 'contents');

        $this->actingAs($this->admin)
            ->get(route('scheduled-reports.download', $report))
            ->assertForbidden();
    });
});
