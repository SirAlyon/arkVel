<?php

declare(strict_types=1);

namespace App\Data\Auth;

use App\Actions\Auth\RegisterAdopter;
use App\Models\User;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use SensitiveParameter;
use Spatie\LaravelData\Attributes\Hidden;
use Spatie\LaravelData\Data;

/**
 * Dati del modulo di registrazione dell'adottante (/registrati), per {@see RegisterAdopter}.
 *
 * Stesse regole della registrazione del pannello Filament che sostituisce (nome ed email
 * obbligatori fino a 255 caratteri, email univoca, password `Password::defaults()` con
 * conferma), più la presa visione dell'informativa privacy, obbligatoria (`accepted`).
 * L'email arriva normalizzata in minuscolo ({@see NormalizesEmail}). Lo valida
 * RegisterAdopter, dopo il rate limit, con `validateAndCreate()`.
 */
final class RegisterAdopterData extends Data
{
    use NormalizesEmail;

    public function __construct(
        public readonly string $name,
        public readonly string $email,
        #[Hidden, SensitiveParameter]
        public readonly string $password,
        public readonly bool $privacy,
    ) {}

    /**
     * @return array<string, list<mixed>>
     */
    public static function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique(User::class, 'email')],
            'password' => ['required', 'string', Password::defaults(), 'confirmed'],
            'privacy' => ['accepted'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function messages(): array
    {
        return [
            'name.required' => __('auth_public.validation.name_required'),
            'name.max' => __('auth_public.validation.name_max'),
            'email.required' => __('auth_public.validation.email_required'),
            'email.email' => __('auth_public.validation.email_invalid'),
            'email.max' => __('auth_public.validation.email_max'),
            'email.unique' => __('auth_public.validation.email_taken'),
            'privacy.accepted' => __('auth_public.validation.privacy_required'),
            ...PasswordMessages::all(),
        ];
    }
}
