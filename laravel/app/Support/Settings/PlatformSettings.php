<?php

declare(strict_types=1);

namespace App\Support\Settings;

use App\Models\PlatformSetting;
use Illuminate\Database\QueryException;

/**
 * Store tipizzato chiave/valore delle impostazioni di PIATTAFORMA.
 *
 * Unica responsabilità: lettura/scrittura persistente di stringhe cifrate (il cast
 * 'encrypted' vive sul model {@see PlatformSetting}). Nessuna logica di dominio: non
 * conosce il significato delle chiavi, si limita a memorizzarle.
 *
 * Memo per-request: le letture sono servite da una cache in memoria valida per la
 * singola richiesta e invalidata a ogni scrittura. Non è una cache persistente: non
 * sopravvive alla request, così due request non vedono valori stantii.
 *
 * ROBUSTEZZA: se la tabella non esiste ancora (prima della migrate, o in contesti di
 * unità che non montano lo schema), la lettura degrada a "assente" invece di sollevare,
 * così i consumatori (es. la risoluzione config Google) ricadono sull'.env.
 */
class PlatformSettings
{
    /**
     * Memo per-request: chiave → valore (string|null). L'assenza di una chiave nella
     * mappa significa "non ancora letta", il valore null significa "letta, assente".
     *
     * @var array<string, string|null>
     */
    private array $memo = [];

    /**
     * Valore della chiave, o $default se assente/vuota.
     */
    public function get(string $key, ?string $default = null): ?string
    {
        if (! array_key_exists($key, $this->memo)) {
            try {
                $this->memo[$key] = PlatformSetting::query()
                    ->where('key', $key)
                    ->value('value');
            } catch (QueryException) {
                // Tabella assente (pre-migrate): tratta come "non impostato".
                $this->memo[$key] = null;
            }
        }

        $value = $this->memo[$key];

        return ($value === null || $value === '') ? $default : $value;
    }

    /**
     * Imposta (upsert) il valore della chiave.
     *
     * Convenzione: un valore null o stringa vuota CANCELLA la riga (semantica di
     * "non impostato" = assente dal DB), così una chiave svuotata torna a delegare
     * al fallback a monte (es. l'.env per la config Google). Invalida il memo.
     */
    public function set(string $key, ?string $value): void
    {
        if ($value === null || $value === '') {
            $this->forget($key);

            return;
        }

        PlatformSetting::query()->updateOrCreate(
            ['key' => $key],
            ['value' => $value],
        );

        $this->memo[$key] = $value;
    }

    /**
     * Rimuove la chiave (e il valore) dal DB e dal memo.
     */
    public function forget(string $key): void
    {
        PlatformSetting::query()->where('key', $key)->delete();

        $this->memo[$key] = null;
    }
}
