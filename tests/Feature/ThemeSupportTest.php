<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    $this->session = bootScenario();
});

it('defaults user theme to system', function () {
    $user = User::create([
        'username'  => 'themetest',
        'name'      => 'Theme Test',
        'email'     => 'theme@example.test',
        'password'  => Hash::make('secret'),
        'is_active' => true,
    ]);

    expect($user->fresh()->theme)->toBe(User::THEME_SYSTEM);
});

it('allows setting theme to light or dark', function () {
    $user = makeUser('SERVER');

    $user->update(['theme' => User::THEME_DARK]);
    expect($user->fresh()->theme)->toBe(User::THEME_DARK);

    $user->update(['theme' => User::THEME_LIGHT]);
    expect($user->fresh()->theme)->toBe(User::THEME_LIGHT);
});

it('returns theme in login response', function () {
    $user = User::create([
        'username'  => 'themetest',
        'name'      => 'Theme Test',
        'email'     => 'theme@example.test',
        'password'  => Hash::make('secret'),
        'is_active' => true,
        'theme'     => User::THEME_DARK,
    ]);
    $user->assignRole('SERVER');

    $response = $this->postJson('/api/v1/auth/login', [
        'username' => 'themetest',
        'password' => 'secret',
    ]);

    $response->assertStatus(200)
        ->assertJsonPath('data.user.theme', 'dark');
});

it('returns theme in me response', function () {
    $user = makeUser('SERVER');
    $user->update(['theme' => User::THEME_LIGHT]);

    $response = $this->actingAs($user)->getJson('/api/v1/auth/me');

    $response->assertStatus(200)
        ->assertJsonPath('data.theme', 'light');
});

it('allows admin to update user theme via api', function () {
    $admin = makeUser('ADMIN');
    $user = makeUser('SERVER');

    $response = $this->actingAs($admin)->patchJson("/api/v1/admin/users/{$user->id}", [
        'theme' => User::THEME_DARK,
    ]);

    $response->assertStatus(200);
    expect($user->fresh()->theme)->toBe(User::THEME_DARK);
});

it('rejects invalid theme values via api', function () {
    $admin = makeUser('ADMIN');
    $user = makeUser('SERVER');

    $response = $this->actingAs($admin)->patchJson("/api/v1/admin/users/{$user->id}", [
        'theme' => 'invalid',
    ]);

    $response->assertStatus(422);
});

it('injects theme script for authenticated user', function () {
    $user = makeUser('SERVER');
    $user->update(['theme' => User::THEME_DARK]);

    $response = $this->actingAs($user)->get('/floor');

    $response->assertStatus(200);
    $response->assertSee("theme = 'dark'", false);
});

it('injects system theme script when user theme is system', function () {
    $user = makeUser('SERVER');
    $user->update(['theme' => User::THEME_SYSTEM]);

    $response = $this->actingAs($user)->get('/floor');

    $response->assertStatus(200);
    $response->assertSee("theme = 'system'", false);
});

it('includes theme in admin users list', function () {
    $admin = makeUser('ADMIN');
    $user = makeUser('SERVER');
    $user->update(['theme' => User::THEME_DARK]);

    $response = $this->actingAs($admin)->getJson('/api/v1/admin/users');

    $response->assertStatus(200);
    $data = $response->json('data');
    $found = collect($data)->first(fn ($u) => $u['userId'] === $user->id);
    expect($found)->not->toBeNull();
    expect($found['theme'])->toBe('dark');
});
