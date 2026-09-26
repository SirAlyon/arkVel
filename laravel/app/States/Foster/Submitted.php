<?php

declare(strict_types=1);

namespace App\States\Foster;

use App\Enums\FosterApplicationStatus;

final class Submitted extends FosterApplicationState
{
    public static string $name = FosterApplicationStatus::Submitted->value;

    public function status(): FosterApplicationStatus
    {
        return FosterApplicationStatus::Submitted;
    }
}
