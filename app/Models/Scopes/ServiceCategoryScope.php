<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Restricts a category-scoped query to the acting user's own service category —
 * category-level views stay scoped. A user with no `service_category_id` (e.g.
 * the seeded super admin) is management-level and spans all categories, so the
 * scope is a no-op for them — this mirrors the nullable FK already on
 * `users.service_category_id`. No-ops entirely outside a web-authenticated
 * request (console commands, seeders, queued jobs).
 *
 * @implements Scope<Model>
 */
class ServiceCategoryScope implements Scope
{
    /**
     * Apply the scope to a given Eloquent query builder.
     */
    public function apply(Builder $builder, Model $model): void
    {
        $user = auth()->user();

        if (! $user || ! $user->service_category_id) {
            return;
        }

        $builder->where($model->qualifyColumn('service_category_id'), $user->service_category_id);
    }
}
