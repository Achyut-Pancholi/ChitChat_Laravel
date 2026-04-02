<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('chat.{id}', fn($user, $id) => (int) $user->id === (int) $id);
Broadcast::channel('group.chat', fn($user) => ['id' => $user->id, 'name' => $user->name]);