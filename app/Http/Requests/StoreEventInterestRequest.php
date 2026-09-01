<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * "Tell me when registration opens" (R2.7).
 *
 * Asks for as little as possible on purpose. This is the person who found the
 * site the week after registration closed; demanding an account, or an organization
 * they may not be able to spell the official name of, is how the lead is lost.
 */
class StoreEventInterestRequest extends FormRequest
{
    /**
     * The honeypot field. Named for something a browser autofill would ignore
     * but a naive bot fills in because it looks required.
     */
    public const HONEYPOT = 'website';

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'email:rfc', 'max:255'],
            'organization_name' => ['nullable', 'string', 'max:255'],
            // Must arrive empty. A human never sees this field.
            self::HONEYPOT => ['prohibited'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email.required' => __('We need an email address to tell you.'),
            'email.email' => __('That does not look like an email address.'),
            // Deliberately vague: a bot should not learn which field caught it.
            self::HONEYPOT.'.prohibited' => __('Something went wrong. Please try again.'),
        ];
    }
}
