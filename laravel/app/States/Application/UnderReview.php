<?php

declare(strict_types=1);

namespace App\States\Application;

use App\Enums\ApplicationStatus;

final class UnderReview extends ApplicationState
{
    public static string $name = ApplicationStatus::UnderReview->value;

    public function status(): ApplicationStatus
    {
        return ApplicationStatus::UnderReview;
    }
}
