<?php

use App\Models\User;

test('a personal access token authenticates a request to an auth:sanctum route', function () {
    $user = User::factory()->create();
    $token = $user->createToken('test-token')->plainTextToken;

    $this->withToken($token)
        ->getJson('/api/user')
        ->assertOk()
        ->assertJsonPath('id', $user->id);
});

test('a request with no token to an auth:sanctum route is unauthenticated', function () {
    $this->getJson('/api/user')
        ->assertUnauthorized();
});

test('a request with an invalid token to an auth:sanctum route is unauthenticated', function () {
    $this->withToken('not-a-real-token')
        ->getJson('/api/user')
        ->assertUnauthorized();
});
