<?php

/** HTTP controller for Landing controller. */

namespace App\Http\Controllers;

use App\Support\LandingShowcase;
use Illuminate\Contracts\View\View;

final class LandingController extends Controller
{
    /**
     * Invoke the instance as a callable.
     *
     * @param  LandingShowcase  $showcase  The showcase.
     * @return View The result.
     */
    public function __invoke(LandingShowcase $showcase): View
    {
        return view('pages.home', ['showcase' => $showcase]);
    }
}
