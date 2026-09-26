<?php

declare(strict_types=1);

namespace App\States\Foster;

use App\Enums\FosterApplicationStatus;

final class Completed extends FosterApplicationState
{
    public static string $name = FosterApplicationStatus::Completed->value;

    public function status(): FosterApplicationStatus
    {
        return FosterApplicationStatus::Completed;
    }
}
