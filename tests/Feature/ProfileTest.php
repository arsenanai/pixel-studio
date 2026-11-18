<?php

use App\Models\CreditTransaction;
use App\Models\Generation;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

test('profile page is displayed', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->get('/profile');

    $response->assertOk();
});

test('profile information can be updated', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->patch('/profile', [
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect('/profile');

    $user->refresh();

    $this->assertSame('Test User', $user->name);
    $this->assertSame('test@example.com', $user->email);
    $this->assertNull($user->email_verified_at);
});

test('email verification status is unchanged when the email address is unchanged', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->patch('/profile', [
            'name' => 'Test User',
            'email' => $user->email,
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect('/profile');

    $this->assertNotNull($user->refresh()->email_verified_at);
});

test('user can delete their account', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->delete('/profile', [
            'password' => 'password',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect('/');

    $this->assertGuest();
    $this->assertNull(User::query()->find($user->id));
    $this->assertSoftDeleted($user);
});

test('closing an account keeps its billing records but removes the personal details', function () {
    $user = User::factory()->withCredits(5)->create(['name' => 'Ada', 'email' => 'ada@example.com']);
    $generation = Generation::factory()->for($user)->succeeded()->create();

    $this->actingAs($user)->delete('/profile', ['password' => 'password'])->assertRedirect('/');

    $closed = User::withTrashed()->findOrFail($user->id);
    expect($closed->name)->toBe('Closed account')
        ->and($closed->email)->toBe("closed-{$user->id}@accounts.invalid")
        ->and($generation->fresh())->not->toBeNull()
        ->and(CreditTransaction::query()->where('user_id', $user->id)->count())->toBe(1)
        ->and(DB::table('daily_spend')->where('user_id', $user->id)->exists())->toBeTrue();

    $this->post('/login', ['email' => 'ada@example.com', 'password' => 'password']);
    $this->assertGuest();

    $this->post('/register', ['name' => 'Ada again', 'email' => 'ada@example.com', 'password' => 'password', 'password_confirmation' => 'password'])
        ->assertSessionHasNoErrors();
});

test('the database refuses to hard-delete a user who has billing records', function () {
    $user = User::factory()->create();
    Generation::factory()->for($user)->create();

    expect(fn () => $user->forceDelete())->toThrow(QueryException::class);
});

test('correct password must be provided to delete account', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->from('/profile')
        ->delete('/profile', [
            'password' => 'wrong-password',
        ]);

    $response
        ->assertSessionHasErrors('password')
        ->assertRedirect('/profile');

    $this->assertNotNull($user->fresh());
});
