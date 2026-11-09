<?php

declare(strict_types=1);

namespace App\Data\Auth;

use App\Actions\Auth\SendPasswordResetLink;
use Spatie\LaravelData\Data;

/**
 * Richiesta del link per reimpostare la password (/password-dimenticata), per
 * {@see SendPasswordResetLink}.
 */
final class ForgotPasswordData extends Data
{
    use NormalizesEmail;

    public function __construct(
        public readonly string $email,
    ) {}

    /**
     * @return array<string, list<string>>
     */
    public static function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function messages(): array
    {
        return [
            'email.required' => __('auth_public.validation.email_required'),
            'email.email' => __('auth_public.validation.email_invalid'),
            'email.max' => __('auth_public.validation.email_max'),
        ];
    }
}
