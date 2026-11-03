<?php

declare(strict_types=1);

namespace App\States\Application;

use App\Enums\ApplicationStatus;

final class Submitted extends ApplicationState
{
    public static string $name = ApplicationStatus::Submitted->value;

    public function status(): ApplicationStatus
    {
        return ApplicationStatus::Submitted;
    }
}
