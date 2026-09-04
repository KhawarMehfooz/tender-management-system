<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Task has no service_category_id of its own — it inherits category scoping from its
 * parent Tender. Relation-manager access (`$tender->tasks()`) is already implicitly scoped
 * because the parent Tender was fetched through Tender's own ServiceCategoryScope, but the
 * standalone TaskResource queries Task directly, so this scope re-derives the same
 * restriction via the tender relationship. Same no-op-for-management-users/no-op-outside-web-
 * auth behavior as ServiceCategoryScope.
 *
 * @implements Scope<Model>
 */
class TaskTenderCategoryScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $user = auth()->user();

        if (! $user || ! $user->service_category_id) {
            return;
        }

        $builder->whereRelation('tender', 'service_category_id', $user->service_category_id);
    }
}
