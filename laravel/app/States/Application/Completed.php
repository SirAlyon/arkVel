<?php

declare(strict_types=1);

namespace App\States\Application;

use App\Enums\ApplicationStatus;

final class Completed extends ApplicationState
{
    public static string $name = ApplicationStatus::Completed->value;

    public function status(): ApplicationStatus
    {
        return ApplicationStatus::Completed;
    }
}
