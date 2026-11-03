<?php

declare(strict_types=1);

namespace App\States\Application;

use App\Enums\ApplicationStatus;

final class AdoptionPending extends ApplicationState
{
    public static string $name = ApplicationStatus::AdoptionPending->value;

    public function status(): ApplicationStatus
    {
        return ApplicationStatus::AdoptionPending;
    }
}
