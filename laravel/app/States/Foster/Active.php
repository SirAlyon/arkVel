<?php

declare(strict_types=1);

namespace App\States\Foster;

use App\Enums\FosterApplicationStatus;

final class Active extends FosterApplicationState
{
    public static string $name = FosterApplicationStatus::Active->value;

    public function status(): FosterApplicationStatus
    {
        return FosterApplicationStatus::Active;
    }
}
