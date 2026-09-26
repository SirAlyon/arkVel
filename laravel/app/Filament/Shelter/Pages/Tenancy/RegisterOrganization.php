<?php

declare(strict_types=1);

namespace App\Filament\Shelter\Pages\Tenancy;

use App\Actions\Organizations\AddOrganizationMember;
use App\Enums\MembershipRole;
use App\Enums\OrganizationStatus;
use App\Enums\OrganizationType;
use App\Models\Organization;
use App\Models\User;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Pages\Tenancy\RegisterTenant;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Pagina di registrazione tenant del pannello shelter.
 *
 * Pagina sottile: raccoglie/valida il form, poi delega la creazione dell'ente
 * e la membership all'Action. L'utente autenticato diventa Org Owner
 * verificato del nuovo ente, creato in stato Pending (in attesa di verifica
 * della piattaforma). Nessuna logica di business qui.
 */
class RegisterOrganization extends RegisterTenant
{
    public static function getLabel(): string
    {
        return __('Registra il tuo ente');
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label(__('Nome ente'))
                    ->required()
                    ->maxLength(255),
                Select::make('type')
                    ->label(__('Tipo'))
                    ->options(OrganizationType::class)
                    ->required(),
                TextInput::make('country')
                    ->label(__('Paese'))
                    ->required()
                    ->maxLength(2)
                    ->default('IT'),
                TextInput::make('city')
                    ->label(__('Città'))
                    ->maxLength(255),
            ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRegistration(array $data): Organization
    {
        /** @var User $user */
        $user = Auth::user();

        // Transazione esplicita: ente + owner nascono insieme o non nascono.
        // Non ci affidiamo al wrapping interno di RegisterTenant::register(),
        // così l'atomicità (nessun ente orfano senza membership) resta garantita
        // anche se quel wrapping cambiasse. La logica di dominio resta nell'Action.
        return DB::transaction(function () use ($data, $user): Organization {
            $organization = new Organization;
            $organization->fill($data);
            $organization->status = OrganizationStatus::Pending;
            $organization->save();

            app(AddOrganizationMember::class)->handle(
                organization: $organization,
                user: $user,
                role: MembershipRole::Owner,
                verified: true,
            );

            return $organization;
        });
    }
}
