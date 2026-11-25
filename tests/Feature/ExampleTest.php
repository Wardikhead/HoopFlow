<?php

/** Tests for Example test. */
test('the application boots and serves the landing page', function () {
    $this->get('/')->assertOk();
});
