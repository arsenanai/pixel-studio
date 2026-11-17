<?php

it('sends visitors from the home page to the generator', function () {
    $this->get('/')->assertRedirect('/generate');
});

it('asks guests to sign in before generating', function () {
    $this->get('/generate')->assertRedirect('/login');
});
