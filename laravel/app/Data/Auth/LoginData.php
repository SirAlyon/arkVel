<?php

declare(strict_types=1);

namespace App\Data\Auth;

use App\Actions\Auth\AuthenticateUser;
use SensitiveParameter;
use Spatie\LaravelData\Attributes\Hidden;
use Spatie\LaravelData\Data;

/**
 * Credenziali del modulo di accesso (/accedi), validate alla creazione
 * (`LoginData::validateAndCreate($input)`), poi passate a {@see AuthenticateUser}.
 * La password non finisce mai nelle serializzazioni del DTO (`#[Hidden]`).
 */
final class LoginData extends Data
{
    use NormalizesEmail;

    public function __construct(
        public readonly string $email,
        #[Hidden, SensitiveParameter]
        public readonly string $password,
        public readonly bool $remember = false,
    ) {}

    /**
     * @return array<string, list<string>>
     */
    public static function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'string'],
            'remember' => ['boolean'],
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
            'password.required' => __('auth_public.validation.password_required'),
        ];
    }
}
