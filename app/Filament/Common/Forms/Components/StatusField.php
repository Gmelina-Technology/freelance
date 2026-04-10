<?php

namespace App\Filament\Common\Forms\Components;

use App\Enums\TaskStatus;
use Filament\Forms\Components\Select;

class StatusField extends Select
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->options(TaskStatus::class)->default(TaskStatus::OPEN);
    }
}
