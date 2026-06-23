<?php

use App\Models\User;

test('valid credentials issue a working bearer token', function () {
    $user = User::factory()->create();

    $response = $this->postJson('/api/v1/tokens', [
        'email' => $user->email,
        'password' => 'password',
        'device_name' => 'integration-test',
    ]);

    $response->assertCreated()
        ->assertJsonStructure(['token', 'token_type'])
        ->assertJsonPath('token_type', 'Bearer');

    $token = $response->json('token');

    $this->withToken($token)
        ->getJson('/api/v1/user')
        ->assertOk()
        ->assertJsonPath('id', $user->id);
});

test('invalid credentials are rejected with a 422 validation error', function () {
    $user = User::factory()->create();

    $this->postJson('/api/v1/tokens', [
        'email' => $user->email,
        'password' => 'wrong-password',
        'device_name' => 'integration-test',
    ])->assertStatus(422)->assertJsonValidationErrors('email');
});

test('missing credentials are rejected with a 422 validation error', function () {
    $this->postJson('/api/v1/tokens', [])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['email', 'password', 'device_name']);
});

test('a revoked token can no longer authenticate', function () {
    $user = User::factory()->create();

    $token = $this->postJson('/api/v1/tokens', [
        'email' => $user->email,
        'password' => 'password',
        'device_name' => 'integration-test',
    ])->json('token');

    $this->withToken($token)->getJson('/api/v1/user')->assertOk();

    $this->withToken($token)->deleteJson('/api/v1/tokens')->assertNoContent();

    $this->assertDatabaseCount('personal_access_tokens', 0);

    // The sanctum guard caches the resolved user on the singleton auth manager,
    // and the app container persists across sub-requests within one test. Forget
    // the guard so the next request re-resolves against the now-empty token store,
    // exactly as a fresh HTTP request would.
    $this->app['auth']->forgetGuards();

    $this->withToken($token)->getJson('/api/v1/user')->assertUnauthorized();
});
