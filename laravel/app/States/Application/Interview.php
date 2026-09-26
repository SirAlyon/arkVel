<?php

declare(strict_types=1);

namespace App\States\Application;

use App\Enums\ApplicationStatus;

final class Interview extends ApplicationState
{
    public static string $name = ApplicationStatus::Interview->value;

    public function status(): ApplicationStatus
    {
        return ApplicationStatus::Interview;
    }
}
