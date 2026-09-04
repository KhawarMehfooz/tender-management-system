<?php

namespace App\Exceptions;

use App\Models\Task;
use RuntimeException;

class TaskDependenciesNotCompleteException extends RuntimeException
{
    public static function make(Task $task): self
    {
        return new self("Task \"{$task->id}\" cannot be marked done while dependencies are incomplete.");
    }
}
