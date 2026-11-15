<?php

use App\Livewire\Coach\MyTeam;
use App\Livewire\Coach\TeamRegister;
use App\Livewire\Organizer\AnnouncementCreate;
use App\Livewire\Organizer\PaymentsIndex;
use App\Livewire\Organizer\SubmissionsIndex;
use App\Livewire\Organizer\TournamentCreate;
use App\Models\Division;
use App\Models\Payment;
use App\Models\Registration;
use App\Models\Team;
use App\Models\Tournament;
use App\Models\User;
use Database\Seeders\PrototypeSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(PrototypeSeeder::class);
});

function formXPath(string $html): DOMXPath
{
    $dom = new DOMDocument;
    libxml_use_internal_errors(true);
    $dom->loadHTML('<?xml encoding="utf-8" ?>'.$html);
    libxml_clear_errors();

    return new DOMXPath($dom);
}

/**
 * @return array<int, string>
 */
function formRequiredIds(string $html): array
{
    $xpath = formXPath($html);
    $ids = [];

    foreach ($xpath->query('//*[@required]') as $node) {
        if ($node->hasAttribute('id')) {
            $ids[] = $node->getAttribute('id');
        }
    }

    return array_values(array_unique($ids));
}

/**
 * @return array<int, string>
 */
function formSummaryTargets(string $html): array
{
    $xpath = formXPath($html);
    $targets = [];

    foreach ($xpath->query('//*[@data-hf-error-summary]//a[starts-with(@href, "#")]') as $link) {
        $targets[] = substr($link->getAttribute('href'), 1);
    }

    return array_values(array_unique($targets));
}

function formExpectSummaryTargetsResolve(string $html): void
{
    $targets = formSummaryTargets($html);
    expect($targets)->not->toBeEmpty('No error summary links were rendered');

    $xpath = formXPath($html);
    $ids = [];
    foreach ($xpath->query('//*[@id]') as $node) {
        $ids[] = $node->getAttribute('id');
    }

    expect(array_values(array_diff($targets, $ids)))
        ->toBe([], 'Error summary links point at ids that do not exist: '.implode(', ', $targets));
}

it('marks every required control on the tournament create form', function () {
    $organizer = User::where('email', 'organizer@hoopflow.ph')->firstOrFail();

    $html = $this->actingAs($organizer)->get('/tournaments/create')->getContent();

    expect(formRequiredIds($html))->toContain(
        'createName',
        'createDivision',
        'createVenue',
        'createStart',
        'createEnd',
        'createFormatElimination',
        'createSlots',
        'createFee',
        'createPaymentMethod',
        'createPaymentAccount',
        'createCourt0',
        'createSlotMinutes',
        'createRestMinutes',
    )->and($html)->toContain('Fields marked');
});

it('marks every required control on the team registration form', function () {
    $coach = User::where('email', 'coach.marcus@hoopflow.ph')->firstOrFail();
    $tournament = Tournament::factory()->create([
        'status' => 'registration-open',
        'start_date' => '2026-06-01',
        'end_date' => '2026-06-05',
    ]);

    $html = Livewire::actingAs($coach)
        ->test(TeamRegister::class, ['tournament_id' => $tournament->id])
        ->html();

    expect(formRequiredIds($html))->toContain(
        'registerTeamName',
        'registerShortName',
        'registerCity',
        'registerCoach',
        'registerColor0',
        'playerName0',
        'playerNumber0',
        'playerPosition0',
        'playerBirthdate0',
        'registerMethod',
        'registerRef',
    )->and($html)->toContain('Fields marked');
});

it('marks every required control on the announcement composer', function () {
    $organizer = User::where('email', 'organizer@hoopflow.ph')->firstOrFail();

    $html = $this->actingAs($organizer)->get('/announcements/create')->getContent();

    expect(formRequiredIds($html))->toContain(
        'announcementTournament',
        'announcementTitle',
        'announcementBody',
        'announcementCategory',
        'announcementAudience',
    )->and($html)->toContain('Fields marked');
});

it('marks every required control in the my-team dialogs', function () {
    $coach = User::factory()->create();
    $tournament = Tournament::factory()->create(['start_date' => '2026-06-01']);
    $division = Division::factory()->create([
        'tournament_id' => $tournament->id,
        'name' => 'Under 15 (U15)',
        'age_min' => null,
        'age_max' => 15,
    ]);
    $team = Team::factory()->create(['coach_user_id' => $coach->id]);
    Registration::factory()->create([
        'tournament_id' => $tournament->id,
        'division_id' => $division->id,
        'team_id' => $team->id,
    ]);

    $component = Livewire::actingAs($coach)->test(MyTeam::class);

    $playerHtml = $component->call('openPlayerDialog')->html();
    expect(formRequiredIds($playerHtml))->toContain(
        'myPlayerName',
        'myPlayerNumber',
        'myPlayerPosition',
        'myPlayerBirthdate',
    );

    $paymentHtml = $component->call('closePlayerDialog')->call('openPaymentDialog')->html();
    expect(formRequiredIds($paymentHtml))->toContain('myPaymentMethod', 'myPaymentRef');
});

it('marks every required control in the organizer dialogs', function () {
    $organizer = User::where('email', 'organizer@hoopflow.ph')->firstOrFail();
    $payment = Payment::query()->firstOrFail();
    $registration = Registration::where('status', 'submitted')->firstOrFail();

    $paymentHtml = Livewire::actingAs($organizer)
        ->test(PaymentsIndex::class)
        ->call('openVerify', $payment->id)
        ->html();
    expect(formRequiredIds($paymentHtml))->toContain('verifyRef', 'verifyAmount');

    $submissionHtml = Livewire::actingAs($organizer)
        ->test(SubmissionsIndex::class)
        ->call('reject', $registration->id)
        ->html();
    expect(formRequiredIds($submissionHtml))->toContain('submissionRejectReason');
});

it('marks every required control on the sign-in and registration forms', function () {
    $login = $this->get('/login')->getContent();
    expect(formRequiredIds($login))
        ->toContain('loginEmail', 'loginPassword')
        ->and($login)->toContain('Fields marked');

    $register = $this->get('/register')->getContent();
    expect(formRequiredIds($register))
        ->toContain('registerName', 'registerEmail', 'registerPassword', 'registerRole')
        ->and($register)->toContain('Fields marked');
});

it('resolves every error summary link on the tournament create form', function () {
    $organizer = User::where('email', 'organizer@hoopflow.ph')->firstOrFail();

    $html = Livewire::actingAs($organizer)
        ->test(TournamentCreate::class)
        ->set('name', '')
        ->call('nextStep')
        ->assertHasErrors()
        ->html();

    formExpectSummaryTargetsResolve($html);
});

it('resolves every error summary link on the team registration form', function () {
    $coach = User::where('email', 'coach.marcus@hoopflow.ph')->firstOrFail();
    $tournament = Tournament::factory()->create([
        'status' => 'registration-open',
        'start_date' => '2026-06-01',
        'end_date' => '2026-06-05',
    ]);

    $html = Livewire::actingAs($coach)
        ->test(TeamRegister::class, ['tournament_id' => $tournament->id])
        ->call('nextStep')
        ->assertHasErrors()
        ->html();

    formExpectSummaryTargetsResolve($html);
});

it('resolves every error summary link on the announcement composer', function () {
    $organizer = User::where('email', 'organizer@hoopflow.ph')->firstOrFail();

    $html = Livewire::actingAs($organizer)
        ->test(AnnouncementCreate::class)
        ->call('save')
        ->assertHasErrors()
        ->html();

    formExpectSummaryTargetsResolve($html);
});

it('resolves every error summary link in the my-team dialogs', function () {
    $coach = User::factory()->create();
    $tournament = Tournament::factory()->create(['start_date' => '2026-06-01']);
    $division = Division::factory()->create([
        'tournament_id' => $tournament->id,
        'name' => 'Under 15 (U15)',
        'age_min' => null,
        'age_max' => 15,
    ]);
    $team = Team::factory()->create(['coach_user_id' => $coach->id]);
    Registration::factory()->create([
        'tournament_id' => $tournament->id,
        'division_id' => $division->id,
        'team_id' => $team->id,
    ]);

    $component = Livewire::actingAs($coach)->test(MyTeam::class);

    $playerHtml = $component->call('openPlayerDialog')->call('savePlayer')->assertHasErrors()->html();
    formExpectSummaryTargetsResolve($playerHtml);

    $paymentHtml = $component->call('closePlayerDialog')->call('openPaymentDialog')->call('savePayment')->assertHasErrors()->html();
    formExpectSummaryTargetsResolve($paymentHtml);
});

it('resolves every error summary link in the organizer dialogs', function () {
    $organizer = User::where('email', 'organizer@hoopflow.ph')->firstOrFail();
    $payment = Payment::query()->firstOrFail();
    $registration = Registration::where('status', 'submitted')->firstOrFail();

    $paymentHtml = Livewire::actingAs($organizer)
        ->test(PaymentsIndex::class)
        ->call('openVerify', $payment->id)
        ->set('verifyRef', '')
        ->set('verifyAmount', '')
        ->call('saveVerify')
        ->assertHasErrors()
        ->html();
    formExpectSummaryTargetsResolve($paymentHtml);

    $submissionHtml = Livewire::actingAs($organizer)
        ->test(SubmissionsIndex::class)
        ->call('reject', $registration->id)
        ->call('confirmReject')
        ->assertHasErrors()
        ->html();
    formExpectSummaryTargetsResolve($submissionHtml);
});
