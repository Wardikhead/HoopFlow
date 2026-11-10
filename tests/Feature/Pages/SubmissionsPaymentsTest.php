<?php

use App\Livewire\Organizer\PaymentsIndex;
use App\Livewire\Organizer\SubmissionsIndex;
use App\Models\Payment;
use App\Models\Player;
use App\Models\Registration;
use App\Models\Team;
use App\Models\Tournament;
use App\Models\User;
use Database\Seeders\PrototypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(PrototypeSeeder::class);
});

it('submissions loads for organizer', function () {
    $this->actingAs(User::where('email', 'organizer@hoopflow.ph')->firstOrFail())->get('/submissions')->assertStatus(200);
});

it('submissions forbidden for coach', function () {
    $user = User::where('email', 'coach.marcus@hoopflow.ph')->firstOrFail();
    $this->actingAs($user)->get('/submissions')->assertStatus(403);
});

it('submissions forbidden for fan', function () {
    $user = User::where('email', 'fan.juan@hoopflow.ph')->firstOrFail();
    $this->actingAs($user)->get('/submissions')->assertStatus(403);
});

it('guest redirected from submissions', function () {
    $this->get('/submissions')->assertRedirect('/login');
});

it('payments loads for organizer', function () {
    $this->actingAs(User::where('email', 'organizer@hoopflow.ph')->firstOrFail())->get('/payments')->assertStatus(200);
});

it('payments forbidden for coach', function () {
    $user = User::where('email', 'coach.marcus@hoopflow.ph')->firstOrFail();
    $this->actingAs($user)->get('/payments')->assertStatus(403);
});

it('payments forbidden for fan', function () {
    $user = User::where('email', 'fan.juan@hoopflow.ph')->firstOrFail();
    $this->actingAs($user)->get('/payments')->assertStatus(403);
});

it('guest redirected from payments', function () {
    $this->get('/payments')->assertRedirect('/login');
});

it('submissions render prototype filters, kpis, and buttons', function () {
    $this->actingAs(User::where('email', 'organizer@hoopflow.ph')->firstOrFail())
        ->get('/submissions')
        ->assertOk()
        ->assertSee('Submissions')
        ->assertSee('Pending review')
        ->assertSee('Across all tournaments')
        ->assertSee('Approved')
        ->assertSee('Rejected')
        ->assertSee('Open registration')
        ->assertSeeHtml('data-testid="submission-status-filter"')
        ->assertSeeHtml('data-testid="submission-tournament-filter"')
        ->assertSeeHtml('data-testid="submission-search"')
        ->assertSeeHtml('data-testid="submissions-open-payments"');
});

it('payments render prototype filters, kpis, destination, and buttons', function () {
    $this->actingAs(User::where('email', 'organizer@hoopflow.ph')->firstOrFail())
        ->get('/payments')
        ->assertOk()
        ->assertSee('Payments')
        ->assertSee('Collected')
        ->assertSee('Outstanding')
        ->assertSee('Expected')
        ->assertSee('Collection rate')
        ->assertSeeHtml('data-testid="payment-destination"')
        ->assertSeeHtml('data-testid="payment-status-filter"')
        ->assertSeeHtml('data-testid="payment-tournament-filter"')
        ->assertSeeHtml('data-testid="payment-search"')
        ->assertSeeHtml('data-testid="payments-open-submissions"');
});

it('approve moves a submission to approved', function () {
    $registration = Registration::where('status', 'submitted')->firstOrFail();

    Livewire::actingAs(User::where('email', 'organizer@hoopflow.ph')->firstOrFail())
        ->test(SubmissionsIndex::class)
        ->call('approve', $registration->id)
        ->call('confirmApprove')
        ->assertSet('approvingId', null);

    expect($registration->fresh()->status)->toBe('approved');
});

it('reject moves a submission to rejected with a reason', function () {
    $registration = Registration::where('status', 'submitted')->firstOrFail();

    Livewire::actingAs(User::where('email', 'organizer@hoopflow.ph')->firstOrFail())
        ->test(SubmissionsIndex::class)
        ->call('reject', $registration->id)
        ->set('rejectReason', 'Missing PSA birth certificate.')
        ->call('confirmReject');

    expect($registration->fresh()->status)->toBe('rejected');
    expect($registration->fresh()->rejection_reason)->toBe('Missing PSA birth certificate.');
});

it('reject requires a reason of at least 5 characters', function () {
    $registration = Registration::where('status', 'submitted')->firstOrFail();

    Livewire::actingAs(User::where('email', 'organizer@hoopflow.ph')->firstOrFail())
        ->test(SubmissionsIndex::class)
        ->set('rejectReason', 'No')
        ->call('reject', $registration->id)
        ->call('confirmReject')
        ->assertHasErrors(['rejectReason']);

    expect($registration->fresh()->status)->toBe('submitted');
});

it('verify records a payment as verified', function () {
    $payment = Payment::where('status', 'pending')->firstOrFail();

    Livewire::actingAs(User::where('email', 'organizer@hoopflow.ph')->firstOrFail())
        ->test(PaymentsIndex::class)
        ->call('openVerify', $payment->id)
        ->set('verifyRef', 'GCASH-NEW-123')
        ->set('verifyAmount', 1500)
        ->call('saveVerify')
        ->assertSet('verifyingId', null);

    expect($payment->fresh()->status)->toBe('verified');
    expect($payment->fresh()->reference_no)->toBe('GCASH-NEW-123');
    expect((float) $payment->fresh()->amount)->toBe(1500.0);
});

it('verify requires a reference and an amount greater than zero', function () {
    $payment = Payment::where('status', 'pending')->firstOrFail();

    Livewire::actingAs(User::where('email', 'organizer@hoopflow.ph')->firstOrFail())
        ->test(PaymentsIndex::class)
        ->call('openVerify', $payment->id)
        ->set('verifyRef', '')
        ->set('verifyAmount', null)
        ->call('saveVerify')
        ->assertHasErrors(['verifyRef', 'verifyAmount']);

    expect($payment->fresh()->status)->toBe('pending');
});

it('payments hydrates filters from the query string', function () {
    Livewire::actingAs(User::where('email', 'organizer@hoopflow.ph')->firstOrFail())
        ->withQueryParams(['status' => 'paid', 'q' => 'Vipers', 'tournament' => '7'])
        ->test(PaymentsIndex::class)
        ->assertSet('status', 'paid')
        ->assertSet('search', 'Vipers')
        ->assertSet('tournament', '7');
});

it('payments maps the paid filter to verified records', function () {
    $component = Livewire::actingAs(User::where('email', 'organizer@hoopflow.ph')->firstOrFail())
        ->withQueryParams(['status' => 'paid'])
        ->test(PaymentsIndex::class)
        ->assertSee('₱1,500');

    $statuses = $component->instance()->payments->pluck('status')->unique()->values()->all();

    expect($statuses)->toBe(['verified']);
});

it('payments exclude rejected registrations', function () {
    $rejected = Registration::where('status', 'rejected')->firstOrFail();

    $component = Livewire::actingAs(User::where('email', 'organizer@hoopflow.ph')->firstOrFail())
        ->withQueryParams(['status' => 'all'])
        ->test(PaymentsIndex::class);

    $registrationIds = $component->instance()->payments->pluck('registration_id')->all();

    expect($registrationIds)->not->toContain($rejected->id);
});

it('payment kpis ignore status and search filters', function () {
    $user = User::where('email', 'organizer@hoopflow.ph')->firstOrFail();

    $filtered = Livewire::actingAs($user)
        ->withQueryParams(['status' => 'pending', 'q' => 'no-team-matches-this'])
        ->test(PaymentsIndex::class)
        ->instance()
        ->kpis;

    $unfiltered = Livewire::actingAs($user)
        ->withQueryParams(['status' => 'all'])
        ->test(PaymentsIndex::class)
        ->instance()
        ->kpis;

    expect($filtered)->toBe($unfiltered)
        ->and($filtered['collected'])->toBeGreaterThan(0);
});

it('submissions hydrates filters from the query string and maps pending', function () {
    Livewire::actingAs(User::where('email', 'organizer@hoopflow.ph')->firstOrFail())
        ->withQueryParams(['status' => 'pending', 'q' => 'Vipers', 'tournament' => '7'])
        ->test(SubmissionsIndex::class)
        ->assertSet('status', 'submitted')
        ->assertSet('search', 'Vipers')
        ->assertSet('tournament', '7');
});

it('submissions accepts an approved query filter', function () {
    Livewire::actingAs(User::where('email', 'organizer@hoopflow.ph')->firstOrFail())
        ->withQueryParams(['status' => 'approved'])
        ->test(SubmissionsIndex::class)
        ->assertSet('status', 'approved');
});

it('labels coach-declared verified documents as verified in submission detail', function () {
    $tournament = Tournament::where('slug', 'pasig-youth-hoopfest')->firstOrFail();
    $division = $tournament->divisions()->firstOrFail();
    $team = Team::factory()->create(['name' => 'No Docs Ballers']);
    Player::factory()->create([
        'team_id' => $team->id,
        'consent_at' => now(),
        'eligibility_status' => 'verified',
        'verified_at' => now(),
    ]);
    $registration = Registration::factory()->create([
        'tournament_id' => $tournament->id,
        'division_id' => $division->id,
        'team_id' => $team->id,
        'status' => 'submitted',
        'submitted_at' => now(),
    ]);

    $component = Livewire::actingAs(User::where('email', 'organizer@hoopflow.ph')->firstOrFail())
        ->withQueryParams(['q' => 'No Docs Ballers'])
        ->test(SubmissionsIndex::class)
        ->call('openReview', $registration->id);

    expect($component->html())
        ->toMatch('/hf-chip--success">\s*<i class="bi bi-patch-check-fill"[^>]*><\/i>Documents/')
        ->not->toMatch('/hf-chip--danger">\s*<i class="bi bi-file-earmark-x"[^>]*><\/i>(Documents|Missing)/');
});
