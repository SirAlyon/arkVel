<?php

declare(strict_types=1);

namespace App\States\Foster;

use App\Enums\FosterApplicationStatus;

final class Withdrawn extends FosterApplicationState
{
    public static string $name = FosterApplicationStatus::Withdrawn->value;

    public function status(): FosterApplicationStatus
    {
        return FosterApplicationStatus::Withdrawn;
    }
}
