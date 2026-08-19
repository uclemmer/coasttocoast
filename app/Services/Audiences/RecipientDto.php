<?php

namespace App\Services\Audiences;

/**
 * One person a campaign will reach (doc 07 §2).
 *
 * `generic` is the flag that matters: true means this is a school's
 * `admissions_email` rather than a named representative, because the school
 * has none active. Those recipients get the same email, but the coordinator
 * should be able to see at a glance how much of a send is going to nobody in
 * particular.
 */
final readonly class RecipientDto
{
    public function __construct(
        public string $email,
        public ?string $name = null,
        public ?int $userId = null,
        public ?int $organizationId = null,
        public ?string $organizationName = null,
        public ?int $registrationId = null,
        public ?string $phone = null,
        public bool $smsOptIn = false,
        public bool $generic = false,
    ) {}

    /**
     * The dedupe key: the account when there is one, the address otherwise.
     *
     * A rep who was active across three past years qualifies through three
     * organizations' histories and must still receive one email (doc 07 §2
     * rule 2). Addresses are lowercased because `Dana@` and `dana@` are one
     * inbox.
     */
    public function dedupeKey(): string
    {
        return $this->userId !== null
            ? 'user:'.$this->userId
            : 'email:'.mb_strtolower($this->email);
    }

    public function canReceiveSms(): bool
    {
        return $this->smsOptIn && filled($this->phone);
    }

    /**
     * @return array<string, mixed>
     */
    public function toRecipientRow(): array
    {
        return [
            'user_id' => $this->userId,
            'organization_id' => $this->organizationId,
            'registration_id' => $this->registrationId,
            'organization_name' => $this->organizationName,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
        ];
    }
}
