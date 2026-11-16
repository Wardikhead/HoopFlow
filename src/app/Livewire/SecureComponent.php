<?php

namespace App\Livewire;

use Livewire\Component;

abstract class SecureComponent extends Component
{
    /**
     * @return array<int, string>
     */
    abstract protected function allowedRoles(): array;

    public function boot(): void
    {
        $user = auth()->user();

        abort_unless(
            $user !== null
                && $user->isActive()
                && $user->hasAnyRole(...$this->allowedRoles()),
            403,
        );
    }
}
