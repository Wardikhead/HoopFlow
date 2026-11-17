<?php

namespace App\Http\Controllers;

use App\Models\Tournament;
use App\Services\LiveScoreService;
use Illuminate\Contracts\View\View;

final class LiveScoreboardController extends Controller
{
    public function __invoke(Tournament $tournament, LiveScoreService $liveScores): View
    {
        return view('pages.live.show', [
            'tournament' => $tournament,
            'snapshot' => $liveScores->snapshot($tournament),
        ]);
    }
}
