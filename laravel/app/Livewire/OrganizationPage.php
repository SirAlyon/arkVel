<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Actions\Animals\FindFavoritedAnimalIds;
use App\Actions\Donations\StartDonationCheckout;
use App\Actions\Donations\StartRecurringDonationCheckout;
use App\Actions\Organizations\SummarizeOrganizationAnimals;
use App\Enums\OrganizationStatus;
use App\Exceptions\DonationsNotConfigured;
use App\Models\Animal;
use App\Models\Organization;
use App\Models\User;
use App\Support\Billing\StripeConfig;
use App\Support\Map\ApproximateLocation;
use App\Support\Seo\InteractsWithSeo;
use App\Support\Seo\PublicPageSeo;
use App\Support\Site\PublicLocation;
use App\Support\Site\TranslatedText;
use App\Support\Site\WebUrl;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\View as ViewFacade;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Profilo pubblico di un ente + griglia dei suoi animali adottabili + sezione donazioni.
 *
 * L'ente è legato per slug (`/enti/{organization:slug}`). Visibilità imposta
 * qui: 404 se l'ente non è verificato. La griglia usa
 * {@see Animal::publiclyVisible()} sulla relazione, così non trapelano bozze
 * anche quando (in teoria) l'ente fosse verificato.
 *
 * Donazione una tantum (P4-6): sezione "Sostieni {ente}" con importi preset + importo
 * libero + messaggio, e (per gli ospiti) nome + email. Config-gated su
 * {@see StripeConfig::configured()}: se Stripe non è configurato la sezione è nascosta con
 * una nota localizzata. Componente SOTTILE: valida l'input e delega a
 * {@see StartDonationCheckout}, poi redirige all'URL di Stripe Checkout. Le guardie di
 * dominio (importo, identità, config) vivono nell'Action.
 *
 * Numeri dell'ente (animali in adozione, adozioni concluse) da
 * {@see SummarizeOrganizationAnimals}. La pagina segue il tema (`themeable`).
 *
 * Dati di presentazione calcolati qui, non nella vista: la descrizione nella lingua
 * corrente con fallback e la lingua in cui è scritta ({@see TranslatedText}, per `lang`),
 * la località pubblica ({@see PublicLocation}), la zona APPROSSIMATA per la mappa
 * ({@see ApproximateLocation}: le coordinate salvate non arrivano mai alla vista), il sito
 * solo se http(s) ({@see WebUrl}) e i preferiti dell'utente tra gli animali della pagina,
 * in una query ({@see FindFavoritedAnimalIds}).
 */
class OrganizationPage extends Component
{
    use InteractsWithSeo;
    use WithPagination;

    public Organization $organization;

    /**
     * Frequenza scelta: 'one_off' (una tantum, P4-6) oppure 'monthly' (ricorrente, P4-7).
     * La mensile è riservata agli utenti autenticati (serve un account per gestirla).
     */
    public string $donationFrequency = 'one_off';

    /**
     * Importo scelto in EURO come stringa dal form (preset o libero). Validato in
     * {@see self::donationRules()} e convertito in centesimi prima di delegare.
     */
    public string $donationAmount = '';

    /**
     * Messaggio facoltativo del donatore, passato all'Action.
     */
    public string $donationMessage = '';

    /**
     * Nome dell'ospite (ignorato per i donatori autenticati, che portano il proprio).
     */
    public string $donorName = '';

    /**
     * Email dell'ospite (obbligatoria per i donatori non autenticati).
     */
    public string $donorEmail = '';

    /**
     * Feedback in-place di errore del ramo donazione (es. Stripe diventato non
     * configurato fra render e submit). Il successo è un redirect a Stripe.
     */
    #[Locked]
    public ?string $donationError = null;

    /**
     * Invii del modulo di donazione: solo la chiave del riepilogo errori (x-ui.error-summary
     * viene ricreato a ogni tentativo fallito e riprende il focus). Non è stato di dominio.
     */
    #[Locked]
    public int $donationAttempts = 0;

    public function mount(Organization $organization): void
    {
        abort_unless($organization->status === OrganizationStatus::Verified, 404);

        $this->organization = $organization;
    }

    /**
     * Avvia una donazione una tantum verso questo ente.
     *
     * Config-gating: se Stripe non è configurato la sezione non è nemmeno renderizzata;
     * ri-verifichiamo comunque qui (la UI non è il confine) e, in caso, mostriamo la nota.
     * Validazione MIRATA ai campi donazione (importo sempre; nome/email solo per gli
     * ospiti). Delega a {@see StartDonationCheckout} e redirige all'URL di Checkout.
     */
    public function donate(
        StartDonationCheckout $startDonationCheckout,
        StartRecurringDonationCheckout $startRecurringDonationCheckout,
        StripeConfig $stripeConfig,
    ): mixed {
        $this->donationAttempts++;

        if (! $stripeConfig->configured()) {
            $this->donationError = __('donations.public.not_configured');

            return null;
        }

        // Donatore tipizzato una volta sola (User|null): evita narrowing ambiguo a valle
        // e mantiene i rami puliti per la static analysis.
        $authenticated = Auth::user();
        $donor = $authenticated instanceof User ? $authenticated : null;
        $isGuest = $donor === null;
        $isMonthly = $this->donationFrequency === 'monthly';

        // Le ricorrenti NON sono per ospiti: serve un account per gestirle/disdirle. La
        // UI nasconde già l'opzione mensile agli ospiti; ri-verifichiamo qui (la UI non è
        // il confine) e rifiutiamo con un avviso localizzato.
        if ($isMonthly && $isGuest) {
            $this->donationError = __('donations.public.monthly_requires_login');

            return null;
        }

        $validated = $this->validate(
            $this->donationRules($isGuest),
            $this->donationMessages(),
        );

        // EURO → centesimi: il form lavora in euro (preset/libero), il dominio in
        // centesimi (fonte di verità). I limiti fini sono ri-verificati nell'Action.
        $amountCents = (int) round(((float) $validated['donationAmount']) * 100);

        try {
            $checkoutUrl = $isMonthly
                ? $startRecurringDonationCheckout->handle(
                    organization: $this->organization,
                    amountCents: $amountCents,
                    currency: 'eur',
                    donor: $donor,
                )
                : $startDonationCheckout->handle(
                    organization: $this->organization,
                    amountCents: $amountCents,
                    currency: 'eur',
                    donor: $donor,
                    donorName: $isGuest && $this->donorName !== '' ? $this->donorName : null,
                    donorEmail: $isGuest && $this->donorEmail !== '' ? $this->donorEmail : null,
                    message: $this->donationMessage !== '' ? $this->donationMessage : null,
                );
        } catch (DonationsNotConfigured) {
            $this->donationError = __('donations.public.not_configured');

            return null;
        }

        // Redirect ESTERNO alla pagina ospitata di Stripe Checkout.
        return $this->redirect($checkoutUrl);
    }

    public function render(): View
    {
        /** @var LengthAwarePaginator<int, Animal> $animals */
        $animals = $this->organization
            ->animals()
            ->publiclyVisible()
            ->with('media')
            ->latest()
            ->paginate(12);

        $locale = app()->getLocale();

        $view = ViewFacade::make('livewire.organization-page', [
            'animals' => $animals,
            'favoriteIds' => app(FindFavoritedAnimalIds::class)->handle(Auth::user(), $animals->items()),
            'locale' => $locale,
            'description' => TranslatedText::pick($this->organization, 'description', $locale),
            'location' => PublicLocation::of($this->organization),
            'mapArea' => ApproximateLocation::of($this->organization),
            'website' => WebUrl::safe($this->organization->getAttribute('website')),
            'summary' => app(SummarizeOrganizationAnimals::class)->handle($this->organization),
            'donationsEnabled' => app(StripeConfig::class)->configured(),
            'donationPresets' => [5, 10, 25, 50],
            'isGuestDonor' => ! Auth::user() instanceof User,
            // La donazione mensile è disponibile solo agli utenti autenticati: serve un
            // account per gestirla/disdirla dal pannello /account.
            'canDonateMonthly' => Auth::user() instanceof User,
        ]);
        $view->layoutData(['themeable' => true]); // @phpstan-ignore method.notFound

        return $this->withSeo($view, app(PublicPageSeo::class)->organization($this->organization, $animals->currentPage(), $animals->lastPage()));
    }

    /**
     * Regole di validazione del form di donazione. L'importo (euro) è sempre richiesto,
     * numerico e nei limiti dell'Action convertiti in euro ({@see StartDonationCheckout}:
     * 1€..10.000€). Nome/email d'ospite sono richiesti SOLO per i donatori non
     * autenticati (i loggati portano i propri dati dall'account).
     *
     * @return array<string, mixed>
     */
    protected function donationRules(bool $isGuest): array
    {
        $rules = [
            'donationAmount' => [
                'required',
                'numeric',
                'min:'.(StartDonationCheckout::MIN_AMOUNT / 100),
                'max:'.(StartDonationCheckout::MAX_AMOUNT / 100),
            ],
            'donationMessage' => ['nullable', 'string', 'max:500'],
        ];

        if ($isGuest) {
            $rules['donorName'] = ['nullable', 'string', 'max:255'];
            $rules['donorEmail'] = ['required', 'email', 'max:255'];
        }

        return $rules;
    }

    /**
     * Messaggi di validazione localizzati per il form di donazione.
     *
     * @return array<string, string>
     */
    protected function donationMessages(): array
    {
        return [
            'donationAmount.required' => __('donations.public.validation.amount_required'),
            'donationAmount.numeric' => __('donations.public.validation.amount_numeric'),
            'donationAmount.min' => __('donations.public.validation.amount_min'),
            'donationAmount.max' => __('donations.public.validation.amount_max'),
            'donorEmail.required' => __('donations.public.validation.email_required'),
            'donorEmail.email' => __('donations.public.validation.email_invalid'),
        ];
    }
}
