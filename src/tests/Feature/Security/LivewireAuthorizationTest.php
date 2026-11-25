<?php

/** Tests for Livewire authorization test. */

use App\Livewire\Coach\MyTeam;
use App\Livewire\Coach\TeamRegister;
use App\Livewire\Organizer\AnnouncementCreate;
use App\Livewire\Organizer\AnnouncementsIndex;
use App\Livewire\Organizer\AuditLogIndex;
use App\Livewire\Organizer\GameManage;
use App\Livewire\Organizer\PaymentsIndex;
use App\Livewire\Organizer\SubmissionsIndex;
use App\Livewire\Organizer\TournamentCreate;
use App\Models\User;
use Database\Seeders\PrototypeSeeder;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;

beforeEach(function () {
    $this->seed(PrototypeSeeder::class);
});

/**
 * Hf expect forbidden.
 *
 * @param  callable  $callback  The callback.
 * @return void Nothing.
 */
function hfExpectForbidden(callable $callback): void
{
    try {
        $callback()->assertStatus(403);

        return;
    } catch (HttpException $exception) {
        expect($exception->getStatusCode())->toBe(403);

        return;
    }

    throw new RuntimeException('Expected the Livewire request to be forbidden.');
}

it('blocks non-organizers from organizer-only livewire components', function (string $component) {
    $fan = User::where('email', 'fan.juan@hoopflow.ph')->firstOrFail();

    hfExpectForbidden(fn () => Livewire::actingAs($fan)->test($component));
})->with([
    'payments' => [PaymentsIndex::class],
    'submissions' => [SubmissionsIndex::class],
    'audit log' => [AuditLogIndex::class],
    'announcement create' => [AnnouncementCreate::class],
    'tournament create' => [TournamentCreate::class],
    'game manage' => [GameManage::class],
]);

it('blocks non-coaches from coach-only livewire components', function (string $component) {
    $organizer = User::where('email', 'organizer@hoopflow.ph')->firstOrFail();

    hfExpectForbidden(fn () => Livewire::actingAs($organizer)->test($component));
})->with([
    'my team' => [MyTeam::class],
    'team register' => [TeamRegister::class],
]);

it('blocks guests from protected livewire components', function (string $component) {
    hfExpectForbidden(fn () => Livewire::test($component));
})->with([
    'payments' => [PaymentsIndex::class],
    'my team' => [MyTeam::class],
]);

it('blocks inactive organizers from protected livewire components', function () {
    $organizer = User::where('email', 'organizer@hoopflow.ph')->firstOrFail();
    $organizer->update(['status' => 'suspended']);

    hfExpectForbidden(fn () => Livewire::actingAs($organizer)->test(PaymentsIndex::class));
});

it('allows organizers into organizer-only livewire components', function () {
    $organizer = User::where('email', 'organizer@hoopflow.ph')->firstOrFail();

    Livewire::actingAs($organizer)
        ->test(PaymentsIndex::class)
        ->assertOk();
});

it('allows coaches into coach-only livewire components', function () {
    $coach = User::where('email', 'coach.marcus@hoopflow.ph')->firstOrFail();

    Livewire::actingAs($coach)
        ->test(MyTeam::class)
        ->assertOk();
});

it('keeps the shared announcements feed available to every signed-in role', function () {
    foreach (['organizer@hoopflow.ph', 'coach.marcus@hoopflow.ph', 'fan.juan@hoopflow.ph'] as $email) {
        $user = User::where('email', $email)->firstOrFail();

        Livewire::actingAs($user)
            ->test(AnnouncementsIndex::class)
            ->assertOk();
    }
});
