<?php

namespace Database\Factories;

use App\Enums\Audience;
use App\Enums\MessageChannel;
use App\Models\Event;
use App\Models\Message;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<Message>
 */
class MessageFactory extends Factory
{
    /**
     * An unsent, email-only draft to this fair's confirmed schools.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'event_id' => Event::factory(),
            'subject' => fake()->sentence(6),
            'email_body' => fake()->paragraphs(3, true),
            'sms_body' => null,
            'channels' => [MessageChannel::Email->value],
            'audience' => Audience::ThisEventConfirmed,
            'audience_filters' => null,
            'scheduled_for' => null,
            'sent_at' => null,
            'created_by' => User::factory()->coordinator(),
        ];
    }

    public function to(Audience $audience): static
    {
        return $this->state(fn (array $attributes) => ['audience' => $audience]);
    }

    /**
     * Adds SMS alongside email. Selecting the channel is permission to try,
     * not a promise that every recipient gets a text — only opted-in numbers do.
     */
    public function withSms(string $body = 'See you at the fair on Thursday at 6:30 PM.'): static
    {
        return $this->state(fn (array $attributes) => [
            'channels' => [MessageChannel::Email->value, MessageChannel::Sms->value],
            'sms_body' => $body,
        ]);
    }

    public function scheduledFor(Carbon $when): static
    {
        return $this->state(fn (array $attributes) => ['scheduled_for' => $when]);
    }

    public function sent(): static
    {
        return $this->state(fn (array $attributes) => ['sent_at' => Carbon::now()]);
    }
}
