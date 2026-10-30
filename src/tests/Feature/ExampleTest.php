<?php

test('the application boots and serves the landing page', function () {
    $this->get('/')->assertOk();
});
