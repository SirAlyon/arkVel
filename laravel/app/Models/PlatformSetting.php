<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\Settings\PlatformSettings;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Riga chiave/valore delle impostazioni di piattaforma (una sola istanza per l'intera
 * app ArkVel, non per ente).
 *
 * Model magro: solo mappatura tabellare e cast. La logica di lettura/scrittura vive
 * nel servizio {@see PlatformSettings}.
 *
 * SICUREZZA: `value` è cifrato a riposo (cast 'encrypted') — ogni valore, incluso il
 * client_secret OAuth, è illeggibile in chiaro nel DB.
 *
 * @property int $id
 * @property string $key
 * @property string|null $value
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class PlatformSetting extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'key',
        'value',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            // Cifratura a riposo: nessun valore (nemmeno un segreto) è leggibile nel DB.
            'value' => 'encrypted',
        ];
    }
}
