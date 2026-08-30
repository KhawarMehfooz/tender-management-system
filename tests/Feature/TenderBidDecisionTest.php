<?php

use App\Enums\BidDecision;
use App\Exceptions\BidDecisionReasonRequiredException;
use App\Models\Tender;
use App\Models\TenderBidDecision;

describe('mandatory reason on NO_BID', function () {
    it('rejects a NO_BID decision without a reason', function () {
        expect(fn () => TenderBidDecision::factory()->create([
            'decision' => BidDecision::NO_BID,
            'reason' => null,
        ]))->toThrow(BidDecisionReasonRequiredException::class);
    });

    it('accepts a NO_BID decision with a reason', function () {
        $decision = TenderBidDecision::factory()->create([
            'decision' => BidDecision::NO_BID,
            'reason' => 'Margin too thin given the distance to the site.',
        ]);

        expect($decision->decision)->toBe(BidDecision::NO_BID)
            ->and($decision->reason)->toBe('Margin too thin given the distance to the site.');
    });

    it('accepts a BID decision without a reason', function () {
        $decision = TenderBidDecision::factory()->create([
            'decision' => BidDecision::BID,
            'reason' => null,
        ]);

        expect($decision->decision)->toBe(BidDecision::BID);
    });
});

describe('append-only history', function () {
    it('accumulates multiple decisions as separate rows rather than overwriting', function () {
        $tender = Tender::factory()->create();
        TenderBidDecision::factory()->create(['tender_id' => $tender->id, 'decided_at' => now()->subDay()]);
        TenderBidDecision::factory()->create(['tender_id' => $tender->id, 'decided_at' => now()]);

        expect($tender->bidDecisions()->count())->toBe(2);
    });

    it('orders bidDecisions by decided_at descending', function () {
        $tender = Tender::factory()->create();
        $earlier = TenderBidDecision::factory()->create(['tender_id' => $tender->id, 'decided_at' => now()->subDay()]);
        $latest = TenderBidDecision::factory()->create(['tender_id' => $tender->id, 'decided_at' => now()]);

        expect($tender->bidDecisions()->pluck('id')->all())->toBe([$latest->id, $earlier->id]);
    });
});

describe('currentBidDecision', function () {
    it('returns the most recently decided row', function () {
        $tender = Tender::factory()->create();
        TenderBidDecision::factory()->create(['tender_id' => $tender->id, 'decided_at' => now()->subDay()]);
        $latest = TenderBidDecision::factory()->create(['tender_id' => $tender->id, 'decided_at' => now()]);

        expect($tender->currentBidDecision()->first()->id)->toBe($latest->id);
    });

    it('is null when no decision has been recorded', function () {
        $tender = Tender::factory()->create();

        expect($tender->currentBidDecision()->first())->toBeNull();
    });
});

describe('cascade delete', function () {
    it('is deleted when its tender is deleted', function () {
        $tender = Tender::factory()->create();
        $decision = TenderBidDecision::factory()->create(['tender_id' => $tender->id]);

        $tender->delete();

        expect(TenderBidDecision::find($decision->id))->toBeNull();
    });
});
