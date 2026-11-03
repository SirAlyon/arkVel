<?php

declare(strict_types=1);

namespace App\States\Application;

use App\Enums\ApplicationStatus;

final class Withdrawn extends ApplicationState
{
    public static string $name = ApplicationStatus::Withdrawn->value;

    public function status(): ApplicationStatus
    {
        return ApplicationStatus::Withdrawn;
    }
}
