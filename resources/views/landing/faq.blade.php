{{-- Blade view for Faq. --}}
@php
    $questions = [
        [
            'q' => 'Is the feature preview really read-only?',
            'a' => 'Yes. The preview on this page is loaded with a sample barangay league and every editing control is locked. Switch between the bracket, calendar, and standings without creating anything.',
        ],
        [
            'q' => 'What does a team need to submit?',
            'a' => 'A roster with birthdates, guardian consent, and document review, plus a payment reference. Eligibility is a first-class step, not a note in a spreadsheet.',
        ],
        [
            'q' => 'How are brackets seeded?',
            'a' => 'Approved teams are seeded from the standings and published, with byes handled. Winners advance automatically as soon as a score is final.',
        ],
        [
            'q' => 'Can we run more than one division?',
            'a' => 'A tournament holds its divisions, venues, courts, and entry fee, so a weekend can run multiple age groups without a second workbook.',
        ],
        [
            'q' => 'Which payments are tracked?',
            'a' => 'GCash, Maya, bank transfers, and cash are recorded alongside the entry fee, with a written reason on every rejection.',
        ],
        [
            'q' => 'Does it work on a phone at the court?',
            'a' => 'The bracket, schedule, live scores, standings, and announcements are built to be read on the phone in your pocket — no app install needed.',
        ],
    ];
@endphp

<section id="faq" class="hf-lp-faq" aria-labelledby="faq-title">
    <div class="hf-lp-shell hf-lp-faq__inner">
        <header class="hf-lp-faq__head hf-lp-wipe">
            <span class="hf-eyebrow hf-lp-eyebrow">Before you sign up</span>
            <h2 id="faq-title">Questions from <em>the scorer's table.</em></h2>
            <p>Short answers to what organizers ask first.</p>
        </header>

        <div class="hf-lp-faq__list">
            @foreach ($questions as $question)
                <details class="hf-lp-faq__item hf-lp-reveal" style="--i: {{ $loop->index }}" {{ $loop->first ? 'open' : '' }}>
                    <summary>
                        <span>{{ $question['q'] }}</span>
                        <i class="bi bi-plus-lg" aria-hidden="true"></i>
                    </summary>
                    <div class="hf-lp-faq__answer"><p>{{ $question['a'] }}</p></div>
                </details>
            @endforeach
        </div>
    </div>
</section>
