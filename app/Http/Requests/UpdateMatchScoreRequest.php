<?php

namespace App\Http\Requests;

use App\Models\MatchModel;
use App\Services\LiveScoreService;
use Illuminate\Foundation\Http\FormRequest;

class UpdateMatchScoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        $match = $this->route('match');

        return $match instanceof MatchModel
            && ($this->user()?->can('update', $match) ?? false);
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'home_score' => ['required', 'integer', 'min:0', 'max:'.LiveScoreService::MAX_SCORE],
            'away_score' => ['required', 'integer', 'min:0', 'max:'.LiveScoreService::MAX_SCORE],
            'version' => ['required', 'integer', 'min:1'],
        ];
    }
}
