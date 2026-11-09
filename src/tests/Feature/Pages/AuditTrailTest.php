<?php

use App\Livewire\Coach\TeamRegister;
use App\Livewire\Organizer\BracketShow;
use App\Livewire\Organizer\PaymentsIndex;
use App\Livewire\Organizer\SubmissionsIndex;
use App\Livewire\Organizer\TournamentCreate;
use App\Models\AuditLog;
use App\Models\Court;
use App\Models\MatchModel;
use App\Models\MatchSlot;
use App\Models\Payment;
use App\Models\Registration;
use App\Models\Team;
use App\Models\Tournament;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\BracketService;
use App\Services\SchedulingService;
use Database\Seeders\PrototypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(PrototypeSeeder::class);
});

it('persists the explicit audit log id', function () {
    $log = app(AuditLogger::class)->log('test.action', 'tournament', 1);

    expect($log->id)->not->toBeNull();
    expect((int) $log->id)->toBeGreaterThan(0);
    expect($log->fresh()?->id)->toBe($log->id);
});

it('records an audit entry when a tournament is created', function () {
    $organizer = User::where('email', 'organizer@hoopflow.ph')->firstOrFail();

    Livewire::actingAs($organizer)
        ->test(TournamentCreate::class)
        ->call('nextStep')
        ->call('nextStep')
        ->call('save')
        ->assertHasNoErrors();

    $tournament = Tournament::where('name', 'HoopFlow Metro Invitational')->firstOrFail();
    $log = AuditLog::where('action', 'tournament.create')->latest('id')->firstOrFail();

    expect($log->entity_type)->toBe('tournament');
    expect((int) $log->entity_id)->toBe((int) $tournament->id);
    expect((int) $log->actor_user_id)->toBe((int) $organizer->id);
    expect($log->after_json['slots'])->toBe(8);
    expect($log->after_json['division'])->toBe('Under 12 (U12)');
});

it('shows a newly created tournament on the audit page', function () {
    $organizer = User::where('email', 'organizer@hoopflow.ph')->firstOrFail();

    $this->actingAs($organizer)
        ->get('/audit')
        ->assertOk()
        ->assertSee('8 of 8 entries');

    Livewire::actingAs($organizer)
        ->test(TournamentCreate::class)
        ->call('nextStep')
        ->call('nextStep')
        ->call('save')
        ->assertHasNoErrors();

    $this->actingAs($organizer)
        ->get('/audit')
        ->assertOk()
        ->assertSee('9 of 9 entries')
        ->assertSee('Tournament created');
});

it('shows the newest audit entry above the seeded timeline', function () {
    $organizer = User::where('email', 'organizer@hoopflow.ph')->firstOrFail();

    Livewire::actingAs($organizer)
        ->test(TournamentCreate::class)
        ->call('nextStep')
        ->call('nextStep')
        ->call('save')
        ->assertHasNoErrors();

    $this->actingAs($organizer)
        ->get('/audit')
        ->assertOk()
        ->assertSeeInOrder(['Created HoopFlow Metro Invitational', 'Payment verified']);
});

it('records an audit entry when a coach registers a team', function () {
    $coach = User::where('email', 'coach.marcus@hoopflow.ph')->firstOrFail();

    $players = [];
    for ($number = 1; $number <= 8; $number++) {
        $players[] = [
            'name' => 'Audit Player '.$number,
            'number' => (string) $number,
            'position' => 'Point Guard (PG)',
            'age' => '14',
            'guardianConsent' => true,
            'documentStatus' => 'pending',
        ];
    }

    Livewire::actingAs($coach)
        ->test(TeamRegister::class, ['tournament_id' => 2])
        ->set('name', 'Auditville Titans')
        ->set('shortName', 'AVT')
        ->set('city', 'Auditville')
        ->set('coach', 'Coach Audit')
        ->set('avatarColor', '#123456')
        ->set('players', $players)
        ->set('paymentMethod', 'GCash')
        ->set('paymentRef', 'AUDITTEST123')
        ->call('save')
        ->assertHasNoErrors();

    $team = Team::where('name', 'Auditville Titans')->firstOrFail();
    $log = AuditLog::where('action', 'team.register')->latest('id')->firstOrFail();

    expect($log->entity_type)->toBe('team');
    expect((int) $log->entity_id)->toBe((int) $team->id);
    expect((int) $log->actor_user_id)->toBe((int) $coach->id);
    expect($log->after_json['players'])->toBe(8);
    expect($log->after_json['ref'])->toBe('AUDITTEST123');
});

it('records an audit entry when a submission is approved', function () {
    $organizer = User::where('email', 'organizer@hoopflow.ph')->firstOrFail();
    $registration = Registration::where('status', 'submitted')->firstOrFail();

    Livewire::actingAs($organizer)
        ->test(SubmissionsIndex::class)
        ->call('approve', $registration->id)
        ->call('confirmApprove');

    $log = AuditLog::where('action', 'team.approve')->latest('id')->firstOrFail();

    expect($log->entity_type)->toBe('team');
    expect((int) $log->entity_id)->toBe((int) $registration->team_id);
    expect((int) $log->actor_user_id)->toBe((int) $organizer->id);
    expect($log->after_json['status'])->toBe('approved');
});

it('records an audit entry when a submission is rejected', function () {
    $organizer = User::where('email', 'organizer@hoopflow.ph')->firstOrFail();
    $registration = Registration::where('status', 'submitted')->firstOrFail();

    Livewire::actingAs($organizer)
        ->test(SubmissionsIndex::class)
        ->call('reject', $registration->id)
        ->set('rejectReason', 'Missing PSA birth certificate.')
        ->call('confirmReject');

    $log = AuditLog::where('action', 'team.reject')->latest('id')->firstOrFail();

    expect($log->entity_type)->toBe('team');
    expect((int) $log->entity_id)->toBe((int) $registration->team_id);
    expect($log->after_json['status'])->toBe('rejected');
});

it('records an audit entry when a payment is verified', function () {
    $organizer = User::where('email', 'organizer@hoopflow.ph')->firstOrFail();
    $payment = Payment::where('status', 'pending')->firstOrFail();
    $teamId = $payment->registration->team_id;

    Livewire::actingAs($organizer)
        ->test(PaymentsIndex::class)
        ->call('openVerify', $payment->id)
        ->set('verifyRef', 'GCASH-AUDIT-1')
        ->set('verifyAmount', 1500)
        ->call('saveVerify');

    $log = AuditLog::where('action', 'payment.verify')->latest('id')->firstOrFail();

    expect($log->entity_type)->toBe('team');
    expect((int) $log->entity_id)->toBe((int) $teamId);
    expect((int) $log->actor_user_id)->toBe((int) $organizer->id);
    expect($log->after_json['status'])->toBe('verified');
    expect($log->after_json['ref'])->toBe('GCASH-AUDIT-1');
});

it('records an audit entry when a bracket is generated', function () {
    $organizer = User::where('email', 'organizer@hoopflow.ph')->firstOrFail();
    $tournament = Tournament::where('slug', 'hoopflow-summer-cup')->firstOrFail();

    $this->actingAs($organizer);

    $lastId = (int) (AuditLog::where('action', 'bracket.generate')->max('id') ?? 0);

    app(BracketService::class)->generate($tournament, $tournament->format());

    $log = AuditLog::where('action', 'bracket.generate')->where('id', '>', $lastId)->firstOrFail();

    expect($log->entity_type)->toBe('tournament');
    expect((int) $log->entity_id)->toBe((int) $tournament->id);
    expect((int) $log->actor_user_id)->toBe((int) $organizer->id);
    expect($log->after_json['teams'])->toBeGreaterThan(0);
    expect($log->after_json)->toHaveKey('byes');
});

it('records an audit entry when a schedule is generated', function () {
    $organizer = User::where('email', 'organizer@hoopflow.ph')->firstOrFail();
    $tournament = Tournament::where('slug', 'hoopflow-summer-cup')->firstOrFail();

    MatchModel::query()
        ->where('tournament_id', $tournament->id)
        ->update([
            'status' => 'scheduled',
            'home_score' => null,
            'away_score' => null,
            'winner_team_id' => null,
        ]);

    $this->actingAs($organizer);

    $lastId = (int) (AuditLog::where('action', 'schedule.generate')->max('id') ?? 0);

    app(SchedulingService::class)->generate($tournament);

    $log = AuditLog::where('action', 'schedule.generate')->where('id', '>', $lastId)->firstOrFail();

    expect($log->entity_type)->toBe('tournament');
    expect((int) $log->entity_id)->toBe((int) $tournament->id);
    expect((int) $log->actor_user_id)->toBe((int) $organizer->id);
    expect($log->after_json['courts'])->toBe(4);
    expect($log->after_json['slotMinutes'])->toBe(30);
    expect($log->after_json['restMinutes'])->toBe(15);
});

it('records an audit entry when a match is rescheduled', function () {
    $organizer = User::where('email', 'organizer@hoopflow.ph')->firstOrFail();
    $slot = MatchSlot::query()
        ->whereHas('match', fn ($query) => $query->where('status', 'scheduled'))
        ->firstOrFail();
    $court = Court::query()->whereKeyNot($slot->court_id)->firstOrFail();

    $this->actingAs($organizer);

    app(SchedulingService::class)->reschedule($slot->match, $court->id, '2026-05-19 10:00:00');

    $log = AuditLog::where('action', 'schedule.reschedule')->latest('id')->firstOrFail();

    expect($log->entity_type)->toBe('match');
    expect((int) $log->entity_id)->toBe((int) $slot->match_id);
    expect((int) $log->actor_user_id)->toBe((int) $organizer->id);
    expect((int) $log->after_json['courtId'])->toBe((int) $court->id);
});

it('records an audit entry when bracket slots are reassigned', function () {
    $organizer = User::where('email', 'organizer@hoopflow.ph')->firstOrFail();
    $tournament = Tournament::where('slug', 'hoopflow-summer-cup')->firstOrFail();

    MatchModel::query()
        ->where('tournament_id', $tournament->id)
        ->update([
            'status' => 'scheduled',
            'home_score' => null,
            'away_score' => null,
            'winner_team_id' => null,
        ]);

    $assignments = MatchModel::query()
        ->where('tournament_id', $tournament->id)
        ->where('round_number', 1)
        ->orderBy('match_number')
        ->get()
        ->map(fn (MatchModel $match): array => [
            'matchId' => (int) $match->id,
            'home' => $match->home_team_id !== null ? (int) $match->home_team_id : null,
            'away' => $match->away_team_id !== null ? (int) $match->away_team_id : null,
        ])
        ->values()
        ->all();

    $this->actingAs($organizer);

    $lastId = (int) (AuditLog::where('action', 'bracket.slots')->max('id') ?? 0);

    Livewire::actingAs($organizer)
        ->test(BracketShow::class)
        ->call('openEditSlots')
        ->set('slotAssignments', $assignments)
        ->call('saveSlots')
        ->assertHasNoErrors();

    $log = AuditLog::where('action', 'bracket.slots')->where('id', '>', $lastId)->firstOrFail();

    expect($log->entity_type)->toBe('tournament');
    expect((int) $log->entity_id)->toBe((int) $tournament->id);
    expect((int) $log->actor_user_id)->toBe((int) $organizer->id);
    expect($log->after_json['assignments'])->toHaveCount(count($assignments));
});
