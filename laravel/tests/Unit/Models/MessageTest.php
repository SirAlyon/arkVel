<?php

declare(strict_types=1);

use App\Models\Message;
use App\Models\MessageThread;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('links to its thread and its sender', function () {
    $thread = MessageThread::factory()->create();
    $sender = User::factory()->create();

    $message = Message::factory()
        ->forThread($thread)
        ->fromUser($sender)
        ->create();

    expect($message->thread)->toBeInstanceOf(MessageThread::class)
        ->and($message->thread->is($thread))->toBeTrue()
        ->and($message->user->is($sender))->toBeTrue();
});

it('persists the body text', function () {
    $message = Message::factory()->create(['body' => 'Ciao, come sta Rex?']);

    expect($message->fresh()->body)->toBe('Ciao, come sta Rex?');
});

it('resolves the thread via the message_thread_id foreign key', function () {
    $thread = MessageThread::factory()->create();
    $message = Message::factory()->forThread($thread)->create();

    expect($message->message_thread_id)->toBe($thread->getKey());
});
