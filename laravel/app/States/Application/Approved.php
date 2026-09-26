<?php

declare(strict_types=1);

namespace App\States\Application;

use App\Enums\ApplicationStatus;

final class Approved extends ApplicationState
{
    public static string $name = ApplicationStatus::Approved->value;

    public function status(): ApplicationStatus
    {
        return ApplicationStatus::Approved;
    }
}
