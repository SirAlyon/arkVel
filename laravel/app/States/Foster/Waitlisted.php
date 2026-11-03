<?php

declare(strict_types=1);

namespace App\States\Foster;

use App\Enums\FosterApplicationStatus;

final class Waitlisted extends FosterApplicationState
{
    public static string $name = FosterApplicationStatus::Waitlisted->value;

    public function status(): FosterApplicationStatus
    {
        return FosterApplicationStatus::Waitlisted;
    }
}
