<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Support\Calendar\Google\GoogleCalendarConfig;
use App\Support\Settings\PlatformSettings;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
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
 * Pagina SUPER-ADMIN (pannello Admin) per configurare da UI le credenziali dell'app
 * OAuth Google Calendar a livello di PIATTAFORMA, in alternativa all'.env.
 *
 * Modello: una sola app OAuth per l'intera istanza; ogni ENTE collega poi il proprio
 * account Google (i token per-ente restano gestiti altrove, invariati). CalDAV è
 * self-service per-ente e NON viene toccato qui.
 *
 * Pagina SOTTILE: legge/scrive solo impostazioni via {@see PlatformSettings} e riporta
 * lo stato via {@see GoogleCalendarConfig}. Nessuna logica OAuth qui.
 *
 * SICUREZZA DEL SEGRETO: il client_secret NON viene mai ripresentato al browser (il
 * campo è sempre vuoto in mount); al salvataggio viene sovrascritto SOLO se il campo è
 * valorizzato — un submit con campo vuoto lascia intatto il segreto già memorizzato.
 *
 * @property-read Schema $form
 */
class CalendarIntegrationSettings extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDateRange;

    protected static ?int $navigationSort = 90;

    /**
     * @var array<string, mixed>
     */
    public ?array $data = [];

    /**
     * Solo il Super Admin di piattaforma può vedere e usare questa pagina. Gatea sia la
     * navigazione/rotta sia (in modo esplicito) l'azione di salvataggio.
     */
    public static function canAccess(): bool
    {
        return Auth::user()?->isSuperAdmin() === true;
    }

    public static function getNavigationGroup(): ?string
    {
        return __('calendar_settings.nav_group');
    }

    public static function getNavigationLabel(): string
    {
        return __('calendar_settings.nav');
    }

    public function getTitle(): string
    {
        return __('calendar_settings.title');
    }

    public function mount(): void
    {
        // Difesa in profondità: la rotta è già gated da canAccess(), ma un accesso
        // diretto deve comunque essere respinto.
        abort_unless(static::canAccess(), 403);

        // Precarica SOLO i valori non sensibili (client_id, redirect_uri). Il segreto
        // resta fuori dal form: mai ripresentato al browser.
        $settings = app(PlatformSettings::class);

        $this->form->fill([
            'client_id' => $settings->get(GoogleCalendarConfig::KEY_CLIENT_ID),
            'redirect_uri' => $settings->get(GoogleCalendarConfig::KEY_REDIRECT_URI),
            // Sempre vuoto: il segreto non lascia il server.
            'client_secret' => null,
        ]);
    }

    public function form(Schema $schema): Schema
    {
        $config = app(GoogleCalendarConfig::class);

        return $schema
            ->statePath('data')
            ->components([
                Section::make(__('calendar_settings.section_google'))
                    ->description(__('calendar_settings.section_google_help'))
                    ->schema([
                        TextInput::make('client_id')
                            ->label(__('calendar_settings.client_id'))
                            ->helperText(__('calendar_settings.client_id_help'))
                            ->maxLength(255),

                        TextInput::make('client_secret')
                            ->label(__('calendar_settings.client_secret'))
                            ->password()
                            ->revealable()
                            // Mai precompilato: placeholder/helper indicano solo SE un
                            // segreto è già memorizzato, mai il segreto stesso.
                            ->placeholder(__('calendar_settings.client_secret_placeholder'))
                            ->helperText($config->hasStoredClientSecret()
                                ? __('calendar_settings.client_secret_stored')
                                : __('calendar_settings.client_secret_missing'))
                            ->maxLength(255),

                        TextInput::make('redirect_uri')
                            ->label(__('calendar_settings.redirect_uri'))
                            ->url()
                            ->helperText(__('calendar_settings.redirect_uri_help', [
                                'url' => route('calendar.google.callback'),
                            ]))
                            ->maxLength(255),
                    ]),
            ]);
    }

    public function save(): void
    {
        // L'azione di salvataggio è gated allo stesso modo della pagina.
        abort_unless(static::canAccess(), 403);

        $data = $this->form->getState();
        $settings = app(PlatformSettings::class);

        // client_id e redirect_uri: valore vuoto CANCELLA la chiave (torna al fallback
        // .env), coerente con la semantica dello store.
        $settings->set(GoogleCalendarConfig::KEY_CLIENT_ID, $this->stringOrNull($data['client_id'] ?? null));
        $settings->set(GoogleCalendarConfig::KEY_REDIRECT_URI, $this->stringOrNull($data['redirect_uri'] ?? null));

        // Segreto: sovrascrivi SOLO se fornito. Un submit vuoto NON tocca il segreto
        // già memorizzato (né lo cancella).
        $secret = $this->stringOrNull($data['client_secret'] ?? null);

        if ($secret !== null) {
            $settings->set(GoogleCalendarConfig::KEY_CLIENT_SECRET, $secret);
        }

        // Non ripresentare mai il segreto: azzera il campo dopo il salvataggio.
        $this->data['client_secret'] = null;

        Notification::make()
            ->success()
            ->title(__('calendar_settings.saved'))
            ->send();
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                $this->statusBanner(),
                Form::make([EmbeddedSchema::make('form')])
                    ->id('form')
                    ->livewireSubmitHandler('save')
                    ->footer([
                        Actions::make([
                            Action::make('save')
                                ->label(__('calendar_settings.save'))
                                ->submit('save')
                                ->keyBindings(['mod+s']),
                        ]),
                    ]),
            ]);
    }

    /**
     * Riga di stato: configurato via DB (override), via .env, o non configurato.
     */
    private function statusBanner(): Text
    {
        $source = app(GoogleCalendarConfig::class)->source();

        $message = match ($source) {
            'db' => __('calendar_settings.status_db'),
            'env' => __('calendar_settings.status_env'),
            default => __('calendar_settings.status_none'),
        };

        return Text::make($message)
            ->weight(FontWeight::Medium)
            ->color($source === 'none' ? 'warning' : 'success');
    }

    /**
     * Normalizza un input di form a stringa non vuota o null.
     */
    private function stringOrNull(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }
}
