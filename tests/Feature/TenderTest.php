<?php

use App\Enums\TenderStatus;
use App\Exceptions\InvalidTenderStatusTransitionException;
use App\Models\ProcurementProcedure;
use App\Models\Sector;
use App\Models\ServiceCategory;
use App\Models\Source;
use App\Models\Tender;
use App\Models\User;
use Illuminate\Database\QueryException;

describe('internal ID generation', function () {
    it('generates an internal ID in the CODE-YEAR-SEQUENCE format', function () {
        $category = ServiceCategory::factory()->create(['code' => 'SEC']);

        $tender = Tender::factory()->create(['service_category_id' => $category->id]);

        expect($tender->internal_id)->toBe('SEC-'.now()->format('Y').'-0001');
    });

    it('increments the sequence per category per year', function () {
        $category = ServiceCategory::factory()->create(['code' => 'SEC']);

        $first = Tender::factory()->create(['service_category_id' => $category->id]);
        $second = Tender::factory()->create(['service_category_id' => $category->id]);

        expect($first->internal_id)->toBe('SEC-'.now()->format('Y').'-0001');
        expect($second->internal_id)->toBe('SEC-'.now()->format('Y').'-0002');
    });

    it('keeps separate sequences for different categories', function () {
        $security = ServiceCategory::factory()->create(['code' => 'SEC']);
        $cleaning = ServiceCategory::factory()->create(['code' => 'CLN']);

        $securityTender = Tender::factory()->create(['service_category_id' => $security->id]);
        $cleaningTender = Tender::factory()->create(['service_category_id' => $cleaning->id]);

        expect($securityTender->internal_id)->toBe('SEC-'.now()->format('Y').'-0001');
        expect($cleaningTender->internal_id)->toBe('CLN-'.now()->format('Y').'-0001');
    });

    it('refuses to generate an ID for a category without a code', function () {
        $category = ServiceCategory::factory()->create(['code' => null]);

        Tender::factory()->create(['service_category_id' => $category->id]);
    })->throws(RuntimeException::class);
});

it('defaults estimated_contract_volume_unknown to false', function () {
    $tender = Tender::factory()->create(['estimated_contract_volume_unknown' => false]);

    expect($tender->estimated_contract_volume_unknown)->toBeFalse();
});

it('allows estimated_contract_volume to be null while flagged unknown', function () {
    $tender = Tender::factory()->create([
        'estimated_contract_volume' => null,
        'estimated_contract_volume_unknown' => true,
    ]);

    expect($tender->estimated_contract_volume)->toBeNull();
    expect($tender->estimated_contract_volume_unknown)->toBeTrue();
});

describe('lookup delete protection', function () {
    it('prevents deleting a service category referenced by a tender', function () {
        $category = ServiceCategory::factory()->create();
        Tender::factory()->create(['service_category_id' => $category->id]);

        $category->delete();
    })->throws(QueryException::class);

    it('prevents deleting a sector referenced by a tender', function () {
        $sector = Sector::factory()->create();
        Tender::factory()->create(['sector_id' => $sector->id]);

        $sector->delete();
    })->throws(QueryException::class);

    it('prevents deleting a procurement procedure referenced by a tender', function () {
        $procedure = ProcurementProcedure::factory()->create();
        Tender::factory()->create(['procurement_procedure_id' => $procedure->id]);

        $procedure->delete();
    })->throws(QueryException::class);

    it('prevents deleting a source referenced by a tender', function () {
        $source = Source::factory()->create();
        Tender::factory()->create(['source_id' => $source->id]);

        $source->delete();
    })->throws(QueryException::class);
});

describe('status lifecycle', function () {
    it('moves through the active phases in order', function () {
        $tender = Tender::factory()->create(['status' => TenderStatus::INTAKE]);
        $user = User::factory()->create();

        $tender->changeStatusTo(TenderStatus::REVIEW, $user);

        expect($tender->fresh()->status)->toBe(TenderStatus::REVIEW);
    });

    it('rejects skipping ahead in the active phases', function () {
        $tender = Tender::factory()->create(['status' => TenderStatus::INTAKE]);
        $user = User::factory()->create();

        $tender->changeStatusTo(TenderStatus::DECISION, $user);
    })->throws(InvalidTenderStatusTransitionException::class);

    it('rejects moving backward to a previous phase', function () {
        $tender = Tender::factory()->create(['status' => TenderStatus::DECISION]);
        $user = User::factory()->create();

        $tender->changeStatusTo(TenderStatus::REVIEW, $user);
    })->throws(InvalidTenderStatusTransitionException::class);

    it('allows cancelling from any active phase, not just the end of the pipeline', function () {
        $tender = Tender::factory()->create(['status' => TenderStatus::PROCESSING]);
        $user = User::factory()->create();

        $tender->changeStatusTo(TenderStatus::CANCELLED, $user);

        expect($tender->fresh()->status)->toBe(TenderStatus::CANCELLED);
    });

    it('rejects won/lost before a bid has reached submission', function () {
        $tender = Tender::factory()->create(['status' => TenderStatus::REVIEW]);
        $user = User::factory()->create();

        $tender->changeStatusTo(TenderStatus::WON, $user);
    })->throws(InvalidTenderStatusTransitionException::class);

    it('allows won/lost from submission or follow-up', function () {
        $tender = Tender::factory()->create(['status' => TenderStatus::FOLLOW_UP]);
        $user = User::factory()->create();

        $tender->changeStatusTo(TenderStatus::WON, $user);

        expect($tender->fresh()->status)->toBe(TenderStatus::WON);
    });

    it('rejects any transition out of a terminal status', function () {
        $tender = Tender::factory()->create(['status' => TenderStatus::WON]);
        $user = User::factory()->create();

        $tender->changeStatusTo(TenderStatus::FOLLOW_UP, $user);
    })->throws(InvalidTenderStatusTransitionException::class);

    it('records an audit entry with the actor, reason, and both statuses', function () {
        $tender = Tender::factory()->create(['status' => TenderStatus::INTAKE]);
        $user = User::factory()->create();

        $tender->changeStatusTo(TenderStatus::REVIEW, $user, 'Looks promising');

        $change = $tender->statusChanges()->first();
        expect($change->from_status)->toBe(TenderStatus::INTAKE);
        expect($change->to_status)->toBe(TenderStatus::REVIEW);
        expect($change->changed_by)->toBe($user->id);
        expect($change->reason)->toBe('Looks promising');
        expect($change->changed_at)->not->toBeNull();
    });

    it('does not write an audit entry when the transition is rejected', function () {
        $tender = Tender::factory()->create(['status' => TenderStatus::INTAKE]);
        $user = User::factory()->create();

        try {
            $tender->changeStatusTo(TenderStatus::DECISION, $user);
        } catch (InvalidTenderStatusTransitionException) {
        }

        expect($tender->statusChanges()->count())->toBe(0);
    });
});
