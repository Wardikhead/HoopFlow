<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\ScoreConflictException;
use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateMatchScoreRequest;
use App\Models\MatchModel;
use App\Services\LiveScoreService;
use Illuminate\Http\JsonResponse;
use InvalidArgumentException;

final class MatchScoreController extends Controller
{
    public function __invoke(
        UpdateMatchScoreRequest $request,
        MatchModel $match,
        LiveScoreService $liveScores,
    ): JsonResponse {
        $user = $request->user();

        abort_if($user === null, 401);

        try {
            $updated = $liveScores->updateScore($match, $request->validated(), $user);
        } catch (ScoreConflictException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
                'data' => $liveScores->matchPayload($exception->current),
            ], 409);
        } catch (InvalidArgumentException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json(['data' => $liveScores->matchPayload($updated)]);
    }
}
