<?php

namespace App\Exceptions;

use App\Models\Project;
use RuntimeException;

class NoBillableTasksException extends RuntimeException
{
    public function __construct(public Project $project)
    {
        parent::__construct(sprintf('Project "%s" has no completed, billable tasks.', $project->name));
    }
}
