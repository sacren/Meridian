<?php

use App\Models\User;

test('an unauthenticated api/v1 request renders a JSON 401 rather than the Inertia error page', function () {
    $response = $this->get('/api/v1/user', ['Accept' => 'text/html']);

    $response->assertUnauthorized();
    $response->assertHeader('Content-Type', 'application/json');
    $response->assertJson(['message' => 'Unauthenticated.']);
});

test('a token authenticates an api/v1 route', function () {
    $user = User::factory()->create();
    $token = $user->createToken('test-token')->plainTextToken;

    $this->withToken($token)
        ->getJson('/api/v1/user')
        ->assertOk()
        ->assertJsonPath('id', $user->id);
});

test('an unknown api/v1 path renders a JSON 404 rather than the Inertia error page', function () {
    $response = $this->get('/api/v1/does-not-exist', ['Accept' => 'text/html']);

    $response->assertNotFound();
    $response->assertHeader('Content-Type', 'application/json');
});

test('a burst of requests past the api rate limit is rejected with 429', function () {
    $user = User::factory()->create();
    $token = $user->createToken('burst-token')->plainTextToken;

    foreach (range(1, 60) as $ignored) {
        $this->withToken($token)->getJson('/api/v1/user')->assertOk();
    }

    $this->withToken($token)->getJson('/api/v1/user')->assertStatus(429);
});
