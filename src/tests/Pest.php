<?php

/** Tests for Pest. */

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test case
|--------------------------------------------------------------------------
|
| Feature tests boot the application and run against an in-memory SQLite
| database (see phpunit.xml). Unit tests stay framework-free so domain
| services can be tested without a container.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Unit/Services');

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Unit/Rules');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
*/

expect()->extend('toBeOne', fn () => $this->toBe(1));
