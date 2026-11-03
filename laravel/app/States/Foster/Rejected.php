<?php

declare(strict_types=1);

namespace App\States\Foster;

use App\Enums\FosterApplicationStatus;

final class Rejected extends FosterApplicationState
{
    public static string $name = FosterApplicationStatus::Rejected->value;

    public function status(): FosterApplicationStatus
    {
        return FosterApplicationStatus::Rejected;
    }
}
