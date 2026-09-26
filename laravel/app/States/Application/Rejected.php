<?php

declare(strict_types=1);

namespace App\States\Application;

use App\Enums\ApplicationStatus;

final class Rejected extends ApplicationState
{
    public static string $name = ApplicationStatus::Rejected->value;

    public function status(): ApplicationStatus
    {
        return ApplicationStatus::Rejected;
    }
}
