<?php

use App\Livewire\Organizer\GameManage;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'role:organizer'])->group(function () {
    Route::get('/game-manage', GameManage::class)->name('gameManage');
});
