<?php

use App\Models\ProcurementProcedure;
use App\Models\Sector;
use App\Models\ServiceCategory;
use App\Models\Source;
use App\Models\Tender;
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
