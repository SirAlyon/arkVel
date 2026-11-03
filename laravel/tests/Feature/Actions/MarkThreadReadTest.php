<?php

declare(strict_types=1);

use App\Actions\Messaging\MarkThreadRead;
use App\Models\Message;
use App\Models\MessageThread;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

beforeEach(function () {
    Notification::fake();
    $this->organization = Organization::factory()->create();
    $this->adopter = User::factory()->create();
    $this->staff = User::factory()->create();
    $this->thread = MessageThread::factory()
        ->forOrganization($this->organization)
        ->forAdopter($this->adopter)
        ->create([
            'adopter_last_read_at' => null,
            'org_last_read_at' => null,
        ]);
});

it('marks the adopter side read when the reader is the adopter participant', function () {
    // Un messaggio dallo staff resta non-letto finché l'adottante non legge.
    Message::factory()->forThread($this->thread)->fromUser($this->staff)->create();

    expect($this->thread->unreadCountForAdopter())->toBe(1);

    app(MarkThreadRead::class)->handle($this->thread, $this->adopter);

    $fresh = $this->thread->fresh();

    expect($fresh->adopter_last_read_at)->not->toBeNull()
        ->and($fresh->org_last_read_at)->toBeNull()
        ->and($fresh->unreadCountForAdopter())->toBe(0);
});

it('marks the org side read when the reader is not the adopter (staff)', function () {
    Message::factory()->forThread($this->thread)->fromUser($this->adopter)->create();

    expect($this->thread->unreadCountForOrg())->toBe(1);

    app(MarkThreadRead::class)->handle($this->thread, $this->staff);

    $fresh = $this->thread->fresh();

    expect($fresh->org_last_read_at)->not->toBeNull()
        ->and($fresh->adopter_last_read_at)->toBeNull()
        ->and($fresh->unreadCountForOrg())->toBe(0);
});

it('does not send any notification', function () {
    app(MarkThreadRead::class)->handle($this->thread, $this->adopter);

    Notification::assertNothingSent();
});
