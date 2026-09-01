<?php

use App\Models\User;

test('guests are redirected from the home page to the medcast login', function () {
    $this->get(route('home'))
        ->assertRedirect(route('login'));
});

test('authenticated users are redirected from the home page to the dashboard', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('home'))
        ->assertRedirect(route('dashboard'));
});
