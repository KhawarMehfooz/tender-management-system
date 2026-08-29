<?php

use App\Models\ServiceCategory;
use App\Models\ServiceCategoryCostDriverField;
use Illuminate\Database\QueryException;

describe('field_key uniqueness', function () {
    it('allows the same field_key across different categories', function () {
        $first = ServiceCategoryCostDriverField::factory()->create(['field_key' => 'deployment_hours']);
        $second = ServiceCategoryCostDriverField::factory()->create(['field_key' => 'deployment_hours']);

        expect($first->service_category_id)->not->toBe($second->service_category_id);
    });

    it('rejects a duplicate field_key within the same category', function () {
        $category = ServiceCategory::factory()->create();
        ServiceCategoryCostDriverField::factory()->create([
            'service_category_id' => $category->id,
            'field_key' => 'deployment_hours',
        ]);

        expect(fn () => ServiceCategoryCostDriverField::factory()->create([
            'service_category_id' => $category->id,
            'field_key' => 'deployment_hours',
        ]))->toThrow(QueryException::class);
    });
});

describe('ordering', function () {
    it('orders costDriverFields by order ascending', function () {
        $category = ServiceCategory::factory()->create();
        $second = ServiceCategoryCostDriverField::factory()->create(['service_category_id' => $category->id, 'order' => 2]);
        $first = ServiceCategoryCostDriverField::factory()->create(['service_category_id' => $category->id, 'order' => 1]);

        expect($category->costDriverFields()->pluck('id')->all())->toBe([$first->id, $second->id]);
    });
});

describe('cascade delete', function () {
    it('deletes cost driver fields when the service category is deleted', function () {
        $category = ServiceCategory::factory()->create();
        $field = ServiceCategoryCostDriverField::factory()->create(['service_category_id' => $category->id]);

        $category->delete();

        expect(ServiceCategoryCostDriverField::find($field->id))->toBeNull();
    });
});
