<?php

use App\Enums\TaskStatus;
use App\Exceptions\InvalidTaskStatusTransitionException;
use App\Exceptions\TaskDependenciesNotCompleteException;
use App\Models\Task;
use App\Models\Tender;
use App\Models\User;
use Illuminate\Database\QueryException;

describe('status lifecycle', function () {
    it('moves through the chain in order', function () {
        $task = Task::factory()->create(['status' => TaskStatus::OPEN]);
        $user = User::factory()->create();

        $task->changeStatusTo(TaskStatus::IN_PROGRESS, $user);

        expect($task->fresh()->status)->toBe(TaskStatus::IN_PROGRESS);
    });

    it('rejects skipping ahead in the chain', function () {
        $task = Task::factory()->create(['status' => TaskStatus::OPEN]);
        $user = User::factory()->create();

        $task->changeStatusTo(TaskStatus::IN_REVIEW, $user);
    })->throws(InvalidTaskStatusTransitionException::class);

    it('allows a correction loop from review back to in progress', function () {
        $task = Task::factory()->create(['status' => TaskStatus::IN_REVIEW]);
        $user = User::factory()->create();

        $task->changeStatusTo(TaskStatus::CORRECTION_REQUIRED, $user);
        expect($task->fresh()->status)->toBe(TaskStatus::CORRECTION_REQUIRED);

        $task->changeStatusTo(TaskStatus::IN_PROGRESS, $user);
        expect($task->fresh()->status)->toBe(TaskStatus::IN_PROGRESS);
    });

    it('allows pausing on a dependency from open or in progress', function () {
        $task = Task::factory()->create(['status' => TaskStatus::IN_PROGRESS]);
        $user = User::factory()->create();

        $task->changeStatusTo(TaskStatus::WAITING_ON_ANOTHER_TASK, $user);

        expect($task->fresh()->status)->toBe(TaskStatus::WAITING_ON_ANOTHER_TASK);
    });

    it('rejects any transition out of done', function () {
        $task = Task::factory()->create(['status' => TaskStatus::DONE]);
        $user = User::factory()->create();

        $task->changeStatusTo(TaskStatus::IN_PROGRESS, $user);
    })->throws(InvalidTaskStatusTransitionException::class);

    it('stamps completion_date when reaching done', function () {
        $task = Task::factory()->create(['status' => TaskStatus::IN_REVIEW, 'completion_date' => null]);
        $user = User::factory()->create();

        $task->changeStatusTo(TaskStatus::DONE, $user);

        expect($task->fresh()->completion_date)->not->toBeNull();
    });

    it('records an audit entry with the actor, reason, and both statuses', function () {
        $task = Task::factory()->create(['status' => TaskStatus::OPEN]);
        $user = User::factory()->create();

        $task->changeStatusTo(TaskStatus::IN_PROGRESS, $user, 'Starting work');

        $change = $task->statusChanges()->first();
        expect($change->from_status)->toBe(TaskStatus::OPEN);
        expect($change->to_status)->toBe(TaskStatus::IN_PROGRESS);
        expect($change->changed_by)->toBe($user->id);
        expect($change->reason)->toBe('Starting work');
        expect($change->changed_at)->not->toBeNull();
    });

    it('does not write an audit entry when the transition is rejected', function () {
        $task = Task::factory()->create(['status' => TaskStatus::OPEN]);
        $user = User::factory()->create();

        try {
            $task->changeStatusTo(TaskStatus::IN_REVIEW, $user);
        } catch (InvalidTaskStatusTransitionException) {
        }

        expect($task->statusChanges()->count())->toBe(0);
    });
});

describe('overdue', function () {
    it('is overdue when the due date has passed and the task is not done', function () {
        $task = Task::factory()->create(['due_date' => now()->subDay(), 'status' => TaskStatus::IN_PROGRESS]);

        expect($task->isOverdue())->toBeTrue();
    });

    it('is not overdue when the due date is in the future', function () {
        $task = Task::factory()->create(['due_date' => now()->addDay(), 'status' => TaskStatus::IN_PROGRESS]);

        expect($task->isOverdue())->toBeFalse();
    });

    it('is not overdue once done, even past the due date', function () {
        $task = Task::factory()->create(['due_date' => now()->subDay(), 'status' => TaskStatus::DONE]);

        expect($task->isOverdue())->toBeFalse();
    });

    it('is not overdue without a due date', function () {
        $task = Task::factory()->create(['due_date' => null, 'status' => TaskStatus::IN_PROGRESS]);

        expect($task->isOverdue())->toBeFalse();
    });
});

describe('cascade delete', function () {
    it('deletes a tender\'s tasks when the tender is hard-deleted', function () {
        $tender = Tender::factory()->create();
        $task = Task::factory()->create(['tender_id' => $tender->id]);

        $tender->hardDelete(User::factory()->create(), 'Genuine test junk entry');

        expect(Task::find($task->id))->toBeNull();
    });
});

describe('dependencies', function () {
    it('blocks marking a task done while a dependency is not done', function () {
        $dependency = Task::factory()->create(['status' => TaskStatus::IN_PROGRESS]);
        $task = Task::factory()->create(['status' => TaskStatus::IN_REVIEW]);
        $task->dependencies()->attach($dependency);
        $user = User::factory()->create();

        $task->changeStatusTo(TaskStatus::DONE, $user);
    })->throws(TaskDependenciesNotCompleteException::class);

    it('does not write an audit entry when blocked by an incomplete dependency', function () {
        $dependency = Task::factory()->create(['status' => TaskStatus::OPEN]);
        $task = Task::factory()->create(['status' => TaskStatus::IN_REVIEW]);
        $task->dependencies()->attach($dependency);
        $user = User::factory()->create();

        try {
            $task->changeStatusTo(TaskStatus::DONE, $user);
        } catch (TaskDependenciesNotCompleteException) {
        }

        expect($task->statusChanges()->count())->toBe(0);
    });

    it('allows marking a task done once all dependencies are done', function () {
        $dependency = Task::factory()->create(['status' => TaskStatus::DONE]);
        $task = Task::factory()->create(['status' => TaskStatus::IN_REVIEW]);
        $task->dependencies()->attach($dependency);
        $user = User::factory()->create();

        $task->changeStatusTo(TaskStatus::DONE, $user);

        expect($task->fresh()->status)->toBe(TaskStatus::DONE);
    });

    it('allows marking a task done with no dependencies', function () {
        $task = Task::factory()->create(['status' => TaskStatus::IN_REVIEW]);
        $user = User::factory()->create();

        $task->changeStatusTo(TaskStatus::DONE, $user);

        expect($task->fresh()->status)->toBe(TaskStatus::DONE);
    });

    it('reports dependenciesComplete false while any dependency is unfinished', function () {
        $dependency = Task::factory()->create(['status' => TaskStatus::OPEN]);
        $task = Task::factory()->create();
        $task->dependencies()->attach($dependency);

        expect($task->dependenciesComplete())->toBeFalse();
    });

    it('finds direct dependents via transitiveDependentIds', function () {
        $a = Task::factory()->create();
        $b = Task::factory()->create();
        $b->dependencies()->attach($a);

        expect($a->transitiveDependentIds())->toBe([$b->id]);
    });

    it('finds transitive dependents several hops away', function () {
        $a = Task::factory()->create();
        $b = Task::factory()->create();
        $c = Task::factory()->create();
        $b->dependencies()->attach($a);
        $c->dependencies()->attach($b);

        expect($a->transitiveDependentIds())->toEqualCanonicalizing([$b->id, $c->id]);
    });

    it('has no dependents when nothing depends on it', function () {
        $task = Task::factory()->create();

        expect($task->transitiveDependentIds())->toBe([]);
    });
});

describe('lookup delete protection', function () {
    it('prevents deleting a user referenced as a task owner', function () {
        $owner = User::factory()->create();
        Task::factory()->create(['owner_id' => $owner->id]);

        $owner->delete();
    })->throws(QueryException::class);
});
