<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * TenderDeadline has no service_category_id of its own — it inherits category scoping from
 * its parent Tender, the same way TaskTenderCategoryScope does for Task. Needed once a
 * standalone query surface exists (the M3 tender calendar page queries TenderDeadline
 * directly), not just relation-manager access through an already-scoped Tender.
 */
class TenderDeadlineCategoryScope implements Scope
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
