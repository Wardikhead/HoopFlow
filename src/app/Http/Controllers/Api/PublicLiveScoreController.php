<?php

/** HTTP controller for Public live score controller. */

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Tournament;
use App\Services\LiveScoreService;
use Illuminate\Http\JsonResponse;

final class PublicLiveScoreController extends Controller
{
    /**
     * Invoke the instance as a callable.
     *
     * @param  string  $slug  The slug.
     * @param  LiveScoreService  $liveScores  The live scores.
     * @return JsonResponse The result.
     */
    public function __invoke(string $slug, LiveScoreService $liveScores): JsonResponse
    {
        $tournament = Tournament::query()->where('slug', $slug)->firstOrFail();

        return response()->json(['data' => $liveScores->snapshot($tournament)]);
    }
}
