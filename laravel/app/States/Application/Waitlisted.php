<?php

declare(strict_types=1);

namespace App\States\Application;

use App\Enums\ApplicationStatus;

final class Waitlisted extends ApplicationState
{
    public static string $name = ApplicationStatus::Waitlisted->value;

    public function status(): ApplicationStatus
    {
        return ApplicationStatus::Waitlisted;
    }
}
