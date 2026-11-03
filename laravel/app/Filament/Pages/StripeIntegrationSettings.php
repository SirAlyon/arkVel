<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Support\Billing\StripeConfig;
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
 * Pagina SUPER-ADMIN (pannello Admin) per configurare da UI le credenziali Stripe a
 * livello di PIATTAFORMA, in alternativa all'.env. Gemella di
 * {@see CalendarIntegrationSettings}.
 *
 * Modello (deciso in P4-5): UN SOLO account Stripe per l'intera istanza ArkVel; le
 * donazioni sono registrate PER-ENTE. I payout per-ente (Stripe Connect) sono FUORI
 * SCOPE. Le chiavi impostate qui VINCONO sull'.env (DB-over-env, vedi {@see StripeConfig}).
 *
 * Pagina SOTTILE: legge/scrive solo impostazioni via {@see PlatformSettings} e riporta lo
 * stato via {@see StripeConfig}. Nessuna logica di pagamento qui.
 *
 * SICUREZZA DEI SEGRETI: la secret key e il webhook secret NON vengono mai ripresentati al
 * browser (i campi sono sempre vuoti in mount); al salvataggio sono sovrascritti SOLO se il
 * campo è valorizzato — un submit con campo vuoto lascia intatto il segreto memorizzato.
 * La publishable key NON è un segreto (è pensata per il frontend) e viene precaricata.
 *
 * @property-read Schema $form
 */
class StripeIntegrationSettings extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCreditCard;

    protected static ?int $navigationSort = 91;

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
        return __('stripe_settings.nav_group');
    }

    public static function getNavigationLabel(): string
    {
        return __('stripe_settings.nav');
    }

    public function getTitle(): string
    {
        return __('stripe_settings.title');
    }

    public function mount(): void
    {
        // Difesa in profondità: la rotta è già gated da canAccess(), ma un accesso
        // diretto deve comunque essere respinto.
        abort_unless(static::canAccess(), 403);

        // Precarica SOLO il valore non sensibile (publishable key). I segreti (secret key e
        // webhook secret) restano fuori dal form: mai ripresentati al browser.
        $settings = app(PlatformSettings::class);

        $this->form->fill([
            'publishable_key' => $settings->get(StripeConfig::KEY_PUBLISHABLE),
            // Sempre vuoti: i segreti non lasciano il server.
            'secret_key' => null,
            'webhook_secret' => null,
        ]);
    }

    public function form(Schema $schema): Schema
    {
        $config = app(StripeConfig::class);

        return $schema
            ->statePath('data')
            ->components([
                Section::make(__('stripe_settings.section'))
                    ->description(__('stripe_settings.section_help'))
                    ->schema([
                        TextInput::make('publishable_key')
                            ->label(__('stripe_settings.publishable_key'))
                            ->helperText(__('stripe_settings.publishable_key_help'))
                            ->maxLength(255),

                        TextInput::make('secret_key')
                            ->label(__('stripe_settings.secret_key'))
                            ->password()
                            ->revealable()
                            // Mai precompilato: placeholder/helper indicano solo SE un
                            // segreto è già memorizzato, mai il segreto stesso.
                            ->placeholder(__('stripe_settings.secret_placeholder'))
                            ->helperText($config->hasStoredSecretKey()
                                ? __('stripe_settings.secret_stored')
                                : __('stripe_settings.secret_missing'))
                            ->maxLength(255),

                        TextInput::make('webhook_secret')
                            ->label(__('stripe_settings.webhook_secret'))
                            ->password()
                            ->revealable()
                            ->placeholder(__('stripe_settings.secret_placeholder'))
                            ->helperText($config->hasStoredWebhookSecret()
                                ? __('stripe_settings.webhook_stored')
                                : __('stripe_settings.webhook_missing', [
                                    'url' => url('/stripe/webhook'),
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

        // publishable key: valore vuoto CANCELLA la chiave (torna al fallback .env),
        // coerente con la semantica dello store.
        $settings->set(StripeConfig::KEY_PUBLISHABLE, $this->stringOrNull($data['publishable_key'] ?? null));

        // Segreti: sovrascrivi SOLO se forniti. Un submit vuoto NON tocca il segreto
        // già memorizzato (né lo cancella).
        $secret = $this->stringOrNull($data['secret_key'] ?? null);

        if ($secret !== null) {
            $settings->set(StripeConfig::KEY_SECRET, $secret);
        }

        $webhookSecret = $this->stringOrNull($data['webhook_secret'] ?? null);

        if ($webhookSecret !== null) {
            $settings->set(StripeConfig::KEY_WEBHOOK_SECRET, $webhookSecret);
        }

        // Non ripresentare mai i segreti: azzera i campi dopo il salvataggio.
        $this->data['secret_key'] = null;
        $this->data['webhook_secret'] = null;

        Notification::make()
            ->success()
            ->title(__('stripe_settings.saved'))
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
                                ->label(__('stripe_settings.save'))
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
        $source = app(StripeConfig::class)->source();

        $message = match ($source) {
            'db' => __('stripe_settings.status_db'),
            'env' => __('stripe_settings.status_env'),
            default => __('stripe_settings.status_none'),
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
