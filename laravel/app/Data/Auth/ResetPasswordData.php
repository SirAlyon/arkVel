<?php

declare(strict_types=1);

namespace App\Data\Auth;

use App\Actions\Auth\ResetPassword;
use Illuminate\Validation\Rules\Password;
use SensitiveParameter;
use Spatie\LaravelData\Attributes\Hidden;
use Spatie\LaravelData\Data;

/**
 * Nuova password dal link ricevuto via email (/reimposta-password/{token}), per
 * {@see ResetPassword}. Token e password non finiscono mai nelle serializzazioni.
 * Le regole della password sono quelle di `Password::defaults()` (le stesse della
 * registrazione), con la conferma (`password_confirmation`).
 */
final class ResetPasswordData extends Data
{
    use NormalizesEmail;

    public function __construct(
        #[Hidden, SensitiveParameter]
        public readonly string $token,
        public readonly string $email,
        #[Hidden, SensitiveParameter]
        public readonly string $password,
    ) {}

    /**
     * @return array<string, list<mixed>>
     */
    public static function rules(): array
    {
        return [
            'token' => ['required', 'string'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'string', Password::defaults(), 'confirmed'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function messages(): array
    {
        return [
            'token.required' => __('auth_public.reset.invalid_link'),
            'email.required' => __('auth_public.validation.email_required'),
            'email.email' => __('auth_public.validation.email_invalid'),
            'email.max' => __('auth_public.validation.email_max'),
            ...PasswordMessages::all(),
        ];
    }
}
