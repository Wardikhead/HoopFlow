<?php

namespace App\Policies;

use App\Models\Announcement;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class AnnouncementPolicy extends BasePolicy
{
    public function update(User $user, Model $model): bool
    {
        return $user->hasRole('organizer')
            || ($model instanceof Announcement && $model->created_by === $user->id);
    }
}
