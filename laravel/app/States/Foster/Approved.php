<?php

declare(strict_types=1);

namespace App\States\Foster;

use App\Enums\FosterApplicationStatus;

final class Approved extends FosterApplicationState
{
    public static string $name = FosterApplicationStatus::Approved->value;

    public function status(): FosterApplicationStatus
    {
        return FosterApplicationStatus::Approved;
    }
}
