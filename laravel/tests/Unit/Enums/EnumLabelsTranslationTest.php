<?php

declare(strict_types=1);

use App\Enums\AppLocale;
use App\Enums\AppointmentType;
use App\Enums\EnergyLevel;
use App\Enums\OrganizationType;
use App\Enums\PermissionName;
use App\Enums\RoleName;
use App\Enums\Sex;
use App\Enums\Size;
use App\Enums\Species;
use Filament\Support\Contracts\HasLabel;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Str;

/**
 * Tutti gli enum di App\Enums che implementano HasLabel, tranne AppLocale
 * (endonimi voluti, indipendenti dalla lingua corrente). Scoperti dal
 * filesystem: un enum nuovo entra automaticamente nella verifica.
 *
 * @return list<class-string<BackedEnum&HasLabel>>
 */
function translatableLabelEnums(): array
{
    $enums = [];

    foreach (glob(dirname(__DIR__, 3).'/app/Enums/*.php') ?: [] as $file) {
        $class = 'App\\Enums\\'.basename($file, '.php');

        if ($class === AppLocale::class || ! enum_exists($class) || ! is_subclass_of($class, HasLabel::class)) {
            continue;
        }

        /** @var class-string<BackedEnum&HasLabel> $class */
        $enums[] = $class;
    }

    return $enums;
}

/**
 * Chiave di traduzione attesa per un case: `enums.<enum_snake>.<value>`.
 * RoleName usa il nome del case in snake_case (il value contiene spazi);
 * il value di PermissionName (`<risorsa>.<azione>`) è un percorso annidato.
 */
function enumLabelKey(BackedEnum $case): string
{
    $segment = $case instanceof RoleName ? Str::snake($case->name) : (string) $case->value;

    return 'enums.'.Str::snake(class_basename($case)).'.'.$segment;
}

/**
 * @return array<string, array{0: BackedEnum&HasLabel}>
 */
function translatableLabelCases(): array
{
    $dataset = [];

    foreach (translatableLabelEnums() as $enum) {
        foreach ($enum::cases() as $case) {
            $dataset[class_basename($enum).'::'.$case->name] = [$case];
        }
    }

    return $dataset;
}

it('discovers the label enums to translate, excluding AppLocale', function () {
    $enums = translatableLabelEnums();

    expect($enums)->not->toBeEmpty()
        ->toContain(Species::class, Sex::class, Size::class, EnergyLevel::class, AppointmentType::class, OrganizationType::class)
        ->not->toContain(AppLocale::class);
});

it('has a translated label for every case in every supported locale', function (BackedEnum&HasLabel $case) {
    $key = enumLabelKey($case);

    foreach (AppLocale::codes() as $locale) {
        expect(Lang::has($key, $locale, false))->toBeTrue("Missing [{$key}] for locale [{$locale}]");

        App::setLocale($locale);

        expect($case->getLabel())
            ->toBeString()
            ->not->toBeEmpty()
            ->not->toBe($key)
            ->toBe(__($key, [], $locale));
    }
})->with(translatableLabelCases());

it('defines the same enum label keys in Italian and English', function () {
    $italian = Lang::get('enums', [], 'it');
    $english = Lang::get('enums', [], 'en');

    expect($italian)->toBeArray()
        ->and($english)->toBeArray();

    /** @var array<string, mixed> $italian */
    /** @var array<string, mixed> $english */
    $italianKeys = array_keys(Arr::dot($italian));
    $englishKeys = array_keys(Arr::dot($english));
    sort($italianKeys);
    sort($englishKeys);

    expect($englishKeys)->toBe($italianKeys);
});

it('defines no orphan keys without a matching enum case', function () {
    $expected = collect(translatableLabelCases())
        ->map(fn (array $case): string => Str::after(enumLabelKey($case[0]), 'enums.'))
        ->values()
        ->sort()
        ->values()
        ->all();

    /** @var array<string, mixed> $italian */
    $italian = Lang::get('enums', [], 'it');
    $actual = collect(array_keys(Arr::dot($italian)))->sort()->values()->all();

    expect($actual)->toBe($expected);
});

it('localizes public enum labels', function (BackedEnum&HasLabel $case, string $italian, string $english) {
    App::setLocale('it');
    expect($case->getLabel())->toBe($italian);

    App::setLocale('en');
    expect($case->getLabel())->toBe($english);
})->with([
    'species dog' => [Species::Dog, 'Cane', 'Dog'],
    'species cat' => [Species::Cat, 'Gatto', 'Cat'],
    'species other' => [Species::Other, 'Altro', 'Other'],
    'sex male' => [Sex::Male, 'Maschio', 'Male'],
    'sex female' => [Sex::Female, 'Femmina', 'Female'],
    'size small' => [Size::Small, 'Piccola', 'Small'],
    'size large' => [Size::Large, 'Grande', 'Large'],
    'energy low' => [EnergyLevel::Low, 'Basso', 'Low'],
    'energy high' => [EnergyLevel::High, 'Alto', 'High'],
    'appointment visit' => [AppointmentType::Visit, 'Visita', 'Visit'],
    'appointment home check' => [AppointmentType::HomeCheck, 'Controllo domiciliare', 'Home check'],
    'organization municipal' => [OrganizationType::CanileComunale, 'Canile comunale', 'Municipal dog shelter'],
    'organization company' => [OrganizationType::Societa, 'Società', 'Company'],
]);

it('resolves nested permission and snake-cased role keys', function () {
    App::setLocale('en');

    expect(PermissionName::OrganizationManageStaff->getLabel())->toBe('Manage organization staff')
        ->and(PermissionName::MedicalRecordDelete->getLabel())->toBe('Delete medical records')
        ->and(RoleName::SuperAdmin->getLabel())->toBe('Super Administrator')
        ->and(RoleName::OrgVolunteer->getLabel())->toBe('Organization volunteer');

    App::setLocale('it');

    expect(PermissionName::OrganizationManageStaff->getLabel())->toBe('Gestisci staff ente')
        ->and(RoleName::SuperAdmin->getLabel())->toBe('Super Amministratore');
});

it('keeps AppLocale endonyms regardless of the current locale', function () {
    App::setLocale('en');

    expect(AppLocale::Italian->getLabel())->toBe('Italiano')
        ->and(AppLocale::English->getLabel())->toBe('English');
});
