<?php

declare(strict_types=1);

namespace App\Filament\Adopter\Pages;

use App\Filament\Adopter\Schemas\AdopterProfileForm;
use App\Models\AdopterProfile;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Checkbox;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;

/**
 * Pagina "Il mio profilo" del pannello adottante.
 *
 * Scelta di design: Page a record SINGOLO, non una Resource CRUD. AdopterProfile
 * è 1:1 con l'utente (unique su user_id), quindi list/create/edit di una Resource
 * sarebbero ridondanti: c'è al più un record per utente e nessuna navigazione tra
 * record. La Page carica (o istanzia) il profilo dell'utente corrente e lo salva.
 *
 * Pagina sottile: solo wiring di form/salvataggio. L'unica logica applicativa è la
 * CATTURA DEL CONSENSO GDPR (timestamp `consented_at` al primo consenso): è stato
 * di UI legato al momento dell'azione, tenuto qui, minimale e commentato. Le regole
 * di completezza restano nel dominio (AdopterProfile::isComplete()) e nello schema
 * riusabile (AdopterProfileForm), non qui.
 *
 * @property-read Schema $form
 */
class ManageAdopterProfile extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserCircle;

    /**
     * @var array<string, mixed>
     */
    public ?array $data = [];

    /**
     * Cache per-request del profilo risolto. NON è stato Livewire persistente: si
     * risolve sempre da Auth::user() (come EditProfile::getUser()), quindi resta
     * coerente attraverso mount/hydrate senza dipendere dalla serializzazione.
     */
    protected ?AdopterProfile $profile = null;

    public static function getNavigationLabel(): string
    {
        return 'Il mio profilo';
    }

    public function getTitle(): string
    {
        return 'Il mio profilo';
    }

    public function mount(): void
    {
        $profile = $this->getProfile();

        // Ownership: la Policy autorizza solo il proprietario. Un profilo nuovo
        // (non ancora salvato) è autorizzato via create; uno esistente via update.
        abort_unless(
            $profile->exists
                ? Auth::user()?->can('update', $profile)
                : Auth::user()?->can('create', AdopterProfile::class),
            403,
        );

        // Se l'utente è stato instradato qui da un tentativo di candidatura con
        // profilo incompleto (slice 8), mostriamo l'avviso passato via flash.
        $notice = session()->get('adopter.profile_notice');

        if (is_string($notice) && $notice !== '') {
            Notification::make()
                ->warning()
                ->title($notice)
                ->send();
        }

        $this->fillForm();
    }

    /**
     * Profilo dell'utente corrente, o un'istanza nuova (find-or-new by auth id) da
     * creare al primo salvataggio. Risolto da Auth::user() e memoizzato per request:
     * nessuna scrittura qui, solo risoluzione. Coerente con EditProfile::getUser().
     */
    public function getProfile(): AdopterProfile
    {
        if ($this->profile instanceof AdopterProfile) {
            return $this->profile;
        }

        /** @var User $user */
        $user = Auth::user();

        return $this->profile = $user->adopterProfile()->firstOrNew();
    }

    protected function fillForm(): void
    {
        $this->form->fill($this->getProfile()->attributesToArray());
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->model($this->getProfile())
            ->statePath('data')
            ->components([
                ...AdopterProfileForm::sections(),
                $this->consentSection(),
            ]);
    }

    private function consentSection(): Section
    {
        return Section::make('Consensi (GDPR)')
            ->description('Il trattamento dei dati è necessario per valutare la tua candidatura all\'adozione.')
            ->schema([
                Checkbox::make('consent_data_processing')
                    ->label('Acconsento al trattamento dei miei dati personali')
                    ->helperText('I dati del questionario sono usati solo per valutare la compatibilità con gli animali in adozione e gestire il processo di adozione. Puoi revocare il consenso in ogni momento.')
                    ->accepted()
                    ->validationMessages([
                        'accepted' => 'Il consenso al trattamento dei dati è obbligatorio per salvare il profilo.',
                    ]),
                Checkbox::make('consent_marketing')
                    ->label('Acconsento a ricevere comunicazioni e aggiornamenti (facoltativo)'),
            ]);
    }

    public function save(): void
    {
        $data = $this->form->getState();
        $profile = $this->getProfile();

        // Cattura del momento del consenso: alla PRIMA prestazione del consenso al
        // trattamento fissiamo consented_at = now(). Se il consenso era già stato
        // dato ed è tuttora attivo, NON riscriviamo il timestamp (conserviamo il
        // momento originale). È stato di UI legato all'azione: minimo e locale.
        if (($data['consent_data_processing'] ?? false) === true && $profile->consented_at === null) {
            $data['consented_at'] = now();
        }

        $profile->fill($data);
        $profile->user()->associate(Auth::user());
        $profile->save();

        Notification::make()
            ->success()
            ->title('Profilo salvato')
            ->send();
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                $this->completenessBanner(),
                Form::make([EmbeddedSchema::make('form')])
                    ->id('form')
                    ->livewireSubmitHandler('save')
                    ->footer([
                        Actions::make([
                            Action::make('save')
                                ->label('Salva')
                                ->submit('save')
                                ->keyBindings(['mod+s']),
                        ]),
                    ]),
            ]);
    }

    /**
     * Indicatore discreto di completezza: un profilo completo è precondizione per
     * candidarsi (slice successive). Delega il giudizio al predicato di dominio.
     */
    private function completenessBanner(): Text
    {
        $profile = $this->getProfile();
        $complete = $profile->exists && $profile->isComplete();

        return Text::make($complete
            ? 'Profilo completo: puoi candidarti alle adozioni.'
            : 'Profilo incompleto: completa i campi obbligatori e presta il consenso per poterti candidare.')
            ->weight(FontWeight::Medium)
            ->color($complete ? 'success' : 'warning');
    }
}
