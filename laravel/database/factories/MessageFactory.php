<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Message;
use App\Models\MessageThread;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Message>
 */
class MessageFactory extends Factory
{
    protected $model = Message::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'message_thread_id' => MessageThread::factory(),
            'user_id' => User::factory(),
            'body' => fake()->paragraph(),
        ];
    }

    /**
     * Lega il messaggio a un thread specifico.
     */
    public function forThread(MessageThread $thread): static
    {
        return $this->state(fn (array $attributes): array => [
            'message_thread_id' => $thread->getKey(),
        ]);
    }

    /**
     * Imposta il mittente del messaggio.
     */
    public function fromUser(User $user): static
    {
        return $this->state(fn (array $attributes): array => [
            'user_id' => $user->getKey(),
        ]);
    }
}
