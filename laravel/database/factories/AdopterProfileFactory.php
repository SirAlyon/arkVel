<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\HousingType;
use App\Enums\RentOrOwn;
use App\Models\AdopterProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AdopterProfile>
 */
class AdopterProfileFactory extends Factory
{
    protected $model = AdopterProfile::class;

    /**
     * Default: profilo realistico ma INCOMPLETO. Nessun consenso ancora prestato
     * (consent_data_processing = false, consented_at = null), così
     * `isComplete()` è false finché non si applica lo stato complete().
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $faker = fake('it_IT');

        return [
            'user_id' => User::factory(),
            'housing_type' => $faker->randomElement(HousingType::cases()),
            'has_garden' => $faker->boolean(),
            'fenced' => $faker->optional()->boolean(),
            'rent_or_own' => $faker->randomElement(RentOrOwn::cases()),
            'landlord_allows_pets' => $faker->optional()->boolean(),
            'adults_in_home' => $faker->numberBetween(1, 4),
            'kids_in_home' => $faker->numberBetween(0, 3),
            'other_pets' => $faker->optional()->randomElement([
                'Un gatto europeo di 5 anni', 'Nessuno', 'Due cani di taglia media',
            ]),
            'experience' => $faker->optional()->sentence(10),
            'hours_alone_weekday' => $faker->numberBetween(0, 10),
            'hours_alone_weekend' => $faker->numberBetween(0, 8),
            'lifestyle' => $faker->optional()->sentence(12),
            'ideal_animal' => $faker->optional()->sentence(8),
            'contingency_plan' => $faker->optional()->sentence(10),
            'previous_surrender' => $faker->optional()->boolean(),
            'referral_source' => $faker->optional()->randomElement([
                'Passaparola', 'Social network', 'Sito web', 'Evento di adozione',
            ]),
            'phone' => $faker->optional()->phoneNumber(),
            'country' => 'IT',
            'region' => $faker->optional()->randomElement([
                'Lombardia', 'Lazio', 'Campania', 'Veneto', 'Sicilia',
            ]),
            'city' => $faker->optional()->city(),
            'postal_code' => $faker->optional()->postcode(),
            'consent_data_processing' => false,
            'consent_marketing' => $faker->boolean(),
            'consented_at' => null,
        ];
    }

    /**
     * Profilo COMPLETO: tutti i campi obbligatori valorizzati con valori validi
     * (proprietà, oppure affitto con landlord_allows_pets = true) e consenso al
     * trattamento dati prestato e datato. Garantisce `isComplete() === true`.
     */
    public function complete(): static
    {
        return $this->state(function (array $attributes): array {
            $faker = fake('it_IT');

            // Sorteggia proprietà/affitto; in affitto imponi il consenso del
            // proprietario, così la regola condizionale è sempre soddisfatta.
            $rentOrOwn = $faker->randomElement(RentOrOwn::cases());
            $hasGarden = $faker->boolean();

            return [
                'housing_type' => $faker->randomElement(HousingType::cases()),
                'has_garden' => $hasGarden,
                // Se c'è giardino, la recinzione deve avere una risposta.
                'fenced' => $hasGarden ? $faker->boolean() : null,
                'rent_or_own' => $rentOrOwn,
                'landlord_allows_pets' => $rentOrOwn === RentOrOwn::Rent
                    ? true
                    : $faker->optional()->boolean(),
                'adults_in_home' => $faker->numberBetween(1, 4),
                'hours_alone_weekday' => $faker->numberBetween(0, 8),
                'contingency_plan' => $faker->sentence(10),
                'consent_data_processing' => true,
                'consented_at' => now(),
            ];
        });
    }

    /**
     * Come complete() ma SENZA consenso al trattamento: campi del questionario
     * validi, ma consent_data_processing = false e consented_at = null, quindi
     * `isComplete()` resta false (utile a testare il gate GDPR).
     */
    public function withoutConsent(): static
    {
        return $this->complete()->state(fn (array $attributes): array => [
            'consent_data_processing' => false,
            'consented_at' => null,
        ]);
    }

    public function forUser(User $user): static
    {
        return $this->state(fn (array $attributes): array => [
            'user_id' => $user->getKey(),
        ]);
    }
}
