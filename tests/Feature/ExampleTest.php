<?php

test('guests are redirected to login from the root page', function () {
    $this->get('/')->assertRedirectToRoute('login');
});

test('the login page renders successfully', function () {
    $this->get('/login')->assertSuccessful();
});
