<?php

declare(strict_types=1);

namespace App\States\Foster;

use App\Enums\FosterApplicationStatus;

final class UnderReview extends FosterApplicationState
{
    public static string $name = FosterApplicationStatus::UnderReview->value;

    public function status(): FosterApplicationStatus
    {
        return FosterApplicationStatus::UnderReview;
    }
}
