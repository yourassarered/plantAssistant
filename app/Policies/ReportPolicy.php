<?php

namespace App\Policies;

use App\Models\Plant;
use App\Models\Report;
use App\Models\Tip;
use App\Models\User;

class ReportPolicy
{
    public function createForPlant(User $user, Plant $plant): bool
    {
        return $plant->user_id !== $user->id;
    }

    public function createForTip(User $user, Tip $tip): bool
    {
        return $tip->author_id !== $user->id;
    }

    public function review(User $user, Report $report): bool
    {
        return $user->isAdmin();
    }

    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function view(User $user, Report $report): bool
    {
        return $user->isAdmin();
    }
}
