<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\MedicalRecordType;
use App\Models\Animal;
use App\Models\MedicalRecord;
use Faker\Generator;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MedicalRecord>
 */
class MedicalRecordFactory extends Factory
{
    protected $model = MedicalRecord::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $faker = fake('it_IT');

        $type = $faker->randomElement(MedicalRecordType::cases());

        return [
            'animal_id' => Animal::factory(),
            'type' => $type,
            'date' => $faker->dateTimeBetween('-1 year', 'now'),
            'vet_name' => $this->vetName($faker),
            'description' => $this->descriptionFor($type, $faker),
            // I richiami/scadenze hanno senso soprattutto per vaccino e sverminazione.
            'next_due_at' => in_array($type, [MedicalRecordType::Vaccination, MedicalRecordType::Deworming], true)
                ? $faker->dateTimeBetween('+1 month', '+1 year')
                : null,
        ];
    }

    public function vaccination(): static
    {
        return $this->state(fn (array $attributes): array => [
            'type' => MedicalRecordType::Vaccination,
            'description' => 'Vaccino polivalente annuale.',
            'next_due_at' => fake('it_IT')->dateTimeBetween('+11 months', '+1 year'),
        ]);
    }

    /**
     * Voce con scadenza imminente (entro pochi giorni), utile agli scadenzari.
     */
    public function dueSoon(): static
    {
        return $this->state(fn (array $attributes): array => [
            'next_due_at' => fake('it_IT')->dateTimeBetween('now', '+5 days'),
        ]);
    }

    public function forAnimal(Animal $animal): static
    {
        return $this->state(fn (array $attributes): array => [
            'animal_id' => $animal->getKey(),
        ]);
    }

    private function vetName(Generator $faker): string
    {
        $title = $faker->randomElement(['Dott.', 'Dott.ssa']);

        return $title.' '.$faker->name();
    }

    private function descriptionFor(MedicalRecordType $type, Generator $faker): string
    {
        return match ($type) {
            MedicalRecordType::Vaccination => 'Somministrato vaccino polivalente.',
            MedicalRecordType::Treatment => 'Terapia antibiotica per '.$faker->numberBetween(5, 14).' giorni.',
            MedicalRecordType::Surgery => 'Intervento di sterilizzazione, decorso regolare.',
            MedicalRecordType::Checkup => 'Visita generale: parametri nella norma.',
            MedicalRecordType::Deworming => 'Trattamento antiparassitario interno.',
        };
    }
}
