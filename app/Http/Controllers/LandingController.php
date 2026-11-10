<?php

namespace App\Http\Controllers;

use App\Support\LandingShowcase;
use Illuminate\Contracts\View\View;

final class LandingController extends Controller
{
    public function __invoke(LandingShowcase $showcase): View
    {
        return view('pages.home', ['showcase' => $showcase]);
    }
}
