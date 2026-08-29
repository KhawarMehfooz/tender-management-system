<?php

use App\Enums\CalculationModel;
use App\Models\ServiceCategory;
use App\Models\Tender;
use App\Models\TenderCalculation;
use App\Models\User;
use Illuminate\Database\QueryException;

describe('version_number uniqueness', function () {
    it('allows the same version_number across different tenders', function () {
        $first = TenderCalculation::factory()->create(['version_number' => 1]);
        $second = TenderCalculation::factory()->create(['version_number' => 1]);

        expect($first->tender_id)->not->toBe($second->tender_id);
    });

    it('rejects a duplicate version_number within the same tender', function () {
        $tender = Tender::factory()->create();
        TenderCalculation::factory()->create(['tender_id' => $tender->id, 'version_number' => 1]);

        expect(fn () => TenderCalculation::factory()->create([
            'tender_id' => $tender->id,
            'version_number' => 1,
        ]))->toThrow(QueryException::class);
    });
});

describe('currentCalculation', function () {
    it('exposes the highest version_number via currentCalculation', function () {
        $tender = Tender::factory()->create();
        TenderCalculation::factory()->create(['tender_id' => $tender->id, 'version_number' => 1]);
        $latest = TenderCalculation::factory()->create(['tender_id' => $tender->id, 'version_number' => 2]);

        expect($tender->currentCalculation()->first()->id)->toBe($latest->id);
    });

    it('orders calculations by version_number descending', function () {
        $tender = Tender::factory()->create();
        $first = TenderCalculation::factory()->create(['tender_id' => $tender->id, 'version_number' => 1]);
        $latest = TenderCalculation::factory()->create(['tender_id' => $tender->id, 'version_number' => 2]);

        expect($tender->calculations()->pluck('id')->all())->toBe([$latest->id, $first->id]);
    });
});

describe('computeOutputs', function () {
    it('fills the output columns using the tender\'s service category calculation model', function () {
        $category = ServiceCategory::factory()->create(['calculation_model' => CalculationModel::DEPLOYMENT_HOURS]);
        $tender = Tender::factory()->create(['service_category_id' => $category->id]);
        $calculation = TenderCalculation::factory()->create([
            'tender_id' => $tender->id,
            'input_values' => [
                'hours' => 100,
                'wage_rate' => 20,
                'supplements_pct' => 0.1,
                'social_costs_pct' => 0.2,
                'target_margin_pct' => 0.15,
                'min_margin_pct' => 0.1,
                'risk_surcharge_pct' => 0.05,
            ],
        ]);

        $calculation->computeOutputs();

        expect((float) $calculation->fresh()->bid_price)->toEqualWithDelta(3187.8, 0.01);
        expect((float) $calculation->fresh()->break_even)->toEqualWithDelta(2640.0, 0.01);
    });

    it('throws when the service category has no calculation model configured', function () {
        $category = ServiceCategory::factory()->create(['calculation_model' => null]);
        $tender = Tender::factory()->create(['service_category_id' => $category->id]);
        $calculation = TenderCalculation::factory()->create(['tender_id' => $tender->id]);

        expect(fn () => $calculation->computeOutputs())->toThrow(RuntimeException::class);
    });
});

describe('cascade delete', function () {
    it('deletes a tender\'s calculations when the tender is hard-deleted', function () {
        $tender = Tender::factory()->create();
        $calculation = TenderCalculation::factory()->create(['tender_id' => $tender->id]);

        $tender->hardDelete(User::factory()->create(), 'Genuine test junk entry');

        expect(TenderCalculation::find($calculation->id))->toBeNull();
    });
});
