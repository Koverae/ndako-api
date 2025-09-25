<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('registers a user and returns 201', function () {
    $payload = [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'P@ssword123',
        'password_confirmation' => 'P@ssword123',
    ];

    $response = $this->postJson('/api/auth/register', $payload);

    $response->assertStatus(201)
             ->assertJsonStructure(['message', 'user' => ['id', 'email']]);
    $this->assertDatabaseHas('users', ['email' => 'test@example.com']);
});

it('logs in with correct credentials', function () {
    $user = User::factory()->create(['password' => bcrypt('supersecret')]);

    $res = $this->postJson('/api/auth/login', [
        'email' => $user->email,
        'password' => 'supersecret',
        'device_name' => 'test-device',
    ]);

    $res->assertStatus(200)->assertJsonStructure(['message','user','token']);
});
