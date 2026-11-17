<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Tournament;
use App\Services\LiveScoreService;
use Illuminate\Http\JsonResponse;

final class PublicLiveScoreController extends Controller
{
    public function __invoke(string $slug, LiveScoreService $liveScores): JsonResponse
    {
        $tournament = Tournament::query()->where('slug', $slug)->firstOrFail();

        return response()->json(['data' => $liveScores->snapshot($tournament)]);
    }
}
