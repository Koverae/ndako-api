<?php

use App\Services\AuthService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;

uses(RefreshDatabase::class);

it('creates a user via register service', function () {
    $service = app(AuthService::class);
    $user = $service->register([
        'name' => 'XYZ', 'email' => 'x@y.com', 'password' => 'Passw0rd!'
    ]);
    expect($user)->toBeInstanceOf(User::class);
    assertDatabaseHas('users', ['email' => 'x@y.com']);
});
