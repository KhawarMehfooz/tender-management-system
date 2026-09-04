<?php

use App\Enums\CalculationApprovalStep;
use App\Models\Tender;
use App\Models\TenderCalculation;
use App\Models\TenderCalculationApproval;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

/**
 * Fully approves a fresh calculation for the given tender by directly creating approved
 * TenderCalculationApproval rows for all 6 CalculationApprovalStep cases, bypassing
 * TenderCalculation::approve()'s rights/order enforcement — those are covered separately in
 * TenderCalculationApprovalTest.php, so tests that only need "a tender ready for submission"
 * (the SUBMISSION gate, document locking, etc.) don't need to re-derive team memberships.
 */
function fullyApprovedCalculationFor(Tender $tender): TenderCalculation
{
    $calculation = TenderCalculation::factory()->create(['tender_id' => $tender->id]);

    foreach (CalculationApprovalStep::cases() as $step) {
        TenderCalculationApproval::factory()->create([
            'tender_calculation_id' => $calculation->id,
            'step' => $step,
            'approved_by' => User::factory(),
            'approved_at' => now(),
        ]);
    }

    return $calculation;
}
