<?php

declare(strict_types=1);

namespace App\Support\Auth;

use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Panel;
use Illuminate\Contracts\Session\Session;

/**
 * Messaggio di conferma da mostrare dopo un redirect (es. «Email confermata»), nel sistema
 * di notifiche della pagina di destinazione:
 *
 * - pannello Filament (/account, /manage, /admin): notifica Filament (la mostra il
 *   componente notifiche del pannello, che non ha il toaster del sito);
 * - pagina del sito pubblico: flash `toast` di sessione, letto da x-ui.toaster.
 */
final readonly class SuccessNotice
{
    public function flash(Session $session, string $destination, string $title): void
    {
        if ($this->isPanelUrl($destination)) {
            Notification::make()->success()->title($title)->send();

            return;
        }

        $session->flash('toast', ['type' => 'success', 'title' => $title]);
    }

    /** L'URL (assoluto o relativo) porta dentro un pannello Filament. */
    public function isPanelUrl(string $url): bool
    {
        $path = '/'.trim((string) parse_url($url, PHP_URL_PATH), '/');

        foreach (Filament::getPanels() as $panel) {
            if ($this->panelContains($panel, $path)) {
                return true;
            }
        }

        return false;
    }

    private function panelContains(Panel $panel, string $path): bool
    {
        $panelPath = trim($panel->getPath(), '/');

        // Un pannello montato sulla radice conterrebbe tutto il sito: qui non esiste.
        if ($panelPath === '') {
            return false;
        }

        return $path === '/'.$panelPath || str_starts_with($path, '/'.$panelPath.'/');
    }
}
