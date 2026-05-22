<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    $this->session = bootScenario();
});

it('authenticates a user and returns session info', function () {
    $user = User::create([
        'username' => 'testserver',
        'name' => 'Test Server',
        'email' => 'test@example.test',
        'password' => Hash::make('secret'),
        'is_active' => true,
    ]);
    $user->assignRole('SERVER');

    $response = $this->postJson('/api/v1/auth/login', [
        'username' => 'testserver',
        'password' => 'secret',
        'language' => 'en-US',
    ]);

    $response->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.user.displayName', 'Test Server')
        ->assertJsonPath('data.user.roles.0', 'SERVER');
});

it('rejects invalid credentials', function () {
    $response = $this->postJson('/api/v1/auth/login', [
        'username' => 'nobody',
        'password' => 'wrong',
    ]);

    $response->assertStatus(401)
        ->assertJsonPath('success', false)
        ->assertJsonPath('error.code', 'UNAUTHENTICATED');
});

it('returns current user on me endpoint when authenticated', function () {
    $user = makeUser('SERVER');

    $response = $this->actingAs($user)->getJson('/api/v1/auth/me');

    $response->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.displayName', $user->name);
});

it('rejects me endpoint when unauthenticated', function () {
    $response = $this->getJson('/api/v1/auth/me');

    $response->assertStatus(401)
        ->assertJsonPath('error.code', 'UNAUTHENTICATED');
});

it('logs out and invalidates session', function () {
    $user = makeUser('SERVER');

    $this->actingAs($user)->postJson('/api/v1/auth/logout')
        ->assertStatus(200)
        ->assertJsonPath('success', true);

    $this->getJson('/api/v1/auth/me')
        ->assertStatus(401);
});
