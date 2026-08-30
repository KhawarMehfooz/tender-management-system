<?php

use App\Models\Tender;
use App\Models\TenderCalculation;
use App\Models\TenderParticipationScore;
use Illuminate\Database\QueryException;

describe('tender_id uniqueness', function () {
    it('rejects a second participation score for the same tender', function () {
        $tender = Tender::factory()->create();
        TenderParticipationScore::factory()->create(['tender_id' => $tender->id]);

        expect(fn () => TenderParticipationScore::factory()->create(['tender_id' => $tender->id]))
            ->toThrow(QueryException::class);
    });
});

describe('cascade delete', function () {
    it('is deleted when its tender is deleted', function () {
        $tender = Tender::factory()->create();
        $score = TenderParticipationScore::factory()->create(['tender_id' => $tender->id]);

        $tender->delete();

        expect(TenderParticipationScore::find($score->id))->toBeNull();
    });
});

describe('score', function () {
    it('is null until all 7 manual ratings are set', function () {
        $score = TenderParticipationScore::factory()->create([
            'distance_rating' => 3,
            'staffing_requirement_rating' => 3,
        ]);

        expect($score->score())->toBeNull();
    });

    it('computes the score once all 7 manual ratings are set, with derived factors at their lowest bucket by default', function () {
        $tender = Tender::factory()->create(['estimated_contract_volume' => null]);
        $score = TenderParticipationScore::factory()->rated(3)->create(['tender_id' => $tender->id]);

        // 7 manual ratings of 3 = 21, + contract value (1, unset) + margin (1, no calculation)
        // + past win rate (3, fixed) = 26; 26 / 50 * 100 = 52.
        expect($score->score())->toBe(52);
    });

    it('rates all manual factors at the maximum alongside a high contract value and margin', function () {
        $tender = Tender::factory()->create(['estimated_contract_volume' => 2_000_000]);
        TenderCalculation::factory()->create(['tender_id' => $tender->id, 'version_number' => 1, 'actual_margin' => 25]);
        $score = TenderParticipationScore::factory()->rated(5)->create(['tender_id' => $tender->id]);

        // 7 manual ratings of 5 = 35, + contract value (5) + margin (5) + past win rate (3)
        // = 48; 48 / 50 * 100 = 96.
        expect($score->score())->toBe(96);
    });
});

describe('contractValueRating', function () {
    it('buckets the estimated contract volume into a 1-5 rating', function (?float $volume, int $expected) {
        $tender = Tender::factory()->create(['estimated_contract_volume' => $volume]);
        $score = TenderParticipationScore::factory()->create(['tender_id' => $tender->id]);

        expect($score->contractValueRating())->toBe($expected);
    })->with([
        'unset' => [null, 1],
        'just under 50k' => [49_999.99, 1],
        'at 50k' => [50_000, 2],
        'just under 150k' => [149_999.99, 2],
        'at 150k' => [150_000, 3],
        'just under 400k' => [399_999.99, 3],
        'at 400k' => [400_000, 4],
        'just under 1m' => [999_999.99, 4],
        'at 1m' => [1_000_000, 5],
        'well over 1m' => [5_000_000, 5],
    ]);

    it('treats a volume flagged unknown the same as unset, even when a stale value is present', function () {
        $tender = Tender::factory()->create([
            'estimated_contract_volume' => 5_000_000,
            'estimated_contract_volume_unknown' => true,
        ]);
        $score = TenderParticipationScore::factory()->create(['tender_id' => $tender->id]);

        expect($score->contractValueRating())->toBe(1);
    });
});

describe('marginRating', function () {
    it('buckets the current calculation\'s actual_margin into a 1-5 rating', function (?float $margin, int $expected) {
        $tender = Tender::factory()->create();

        if ($margin !== null) {
            TenderCalculation::factory()->create(['tender_id' => $tender->id, 'version_number' => 1, 'actual_margin' => $margin]);
        }

        $score = TenderParticipationScore::factory()->create(['tender_id' => $tender->id]);

        expect($score->marginRating())->toBe($expected);
    })->with([
        'no calculation' => [null, 1],
        'negative margin' => [-5, 1],
        'zero margin' => [0, 1],
        'just under 5%' => [4.99, 2],
        'at 5%' => [5, 3],
        'just under 10%' => [9.99, 3],
        'at 10%' => [10, 4],
        'just under 20%' => [19.99, 4],
        'at 20%' => [20, 5],
        'well over 20%' => [50, 5],
    ]);

    it('uses the highest version_number calculation when several exist', function () {
        $tender = Tender::factory()->create();
        TenderCalculation::factory()->create(['tender_id' => $tender->id, 'version_number' => 1, 'actual_margin' => 1]);
        TenderCalculation::factory()->create(['tender_id' => $tender->id, 'version_number' => 2, 'actual_margin' => 25]);
        $score = TenderParticipationScore::factory()->create(['tender_id' => $tender->id]);

        expect($score->marginRating())->toBe(5);
    });
});

describe('pastWinRateRating', function () {
    it('is always fixed at the neutral rating', function () {
        $score = TenderParticipationScore::factory()->create();

        expect($score->pastWinRateRating())->toBe(3);
    });
});
