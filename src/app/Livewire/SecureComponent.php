<?php

/** Livewire component for Secure component. */

namespace App\Livewire;

use Livewire\Component;

abstract class SecureComponent extends Component
{
    /**
     * @return array<int, string>
     */
    abstract protected function allowedRoles(): array;

    /**
     * Boot.
     *
     * @return void Nothing.
     */
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
