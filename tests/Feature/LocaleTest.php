<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia;

it('defaults to English and shares the supported locales', function () {
    $this->get('/login')->assertInertia(fn (AssertableInertia $page) => $page
        ->where('locale', 'en')
        ->where('locales', ['en' => 'English', 'kk' => 'Қазақша']));
});

it('follows the browser language for guests', function () {
    $this->get('/login', ['Accept-Language' => 'kk-KZ,kk;q=0.9,en;q=0.8'])
        ->assertInertia(fn (AssertableInertia $page) => $page->where('locale', 'kk'));
});

it('remembers a guest choice in the session', function () {
    $this->from('/login')->post('/locale', ['locale' => 'kk'])->assertRedirect('/login');

    $this->get('/login')->assertInertia(fn (AssertableInertia $page) => $page->where('locale', 'kk'));
});

it('saves the choice on the account so it follows the user to other devices', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->from('/history')->post('/locale', ['locale' => 'kk'])->assertRedirect('/history');

    expect($user->fresh()?->locale)->toBe('kk');

    $this->flushSession();
    $this->actingAs($user->fresh())->get('/history')
        ->assertInertia(fn (AssertableInertia $page) => $page->where('locale', 'kk'));
});

it('rejects a language it does not support', function () {
    $this->post('/locale', ['locale' => 'fr'])->assertSessionHasErrors('locale');
});
