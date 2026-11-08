<?php

declare(strict_types=1);

namespace App\Support\Media;

/**
 * Un model con media le cui foto originali vanno ripulite dai metadati (EXIF, GPS, XMP,
 * IPTC) appena caricate: lo fa App\Listeners\Media\StripUploadedImageMetadata per le
 * collection indicate qui.
 */
interface CleansImageMetadata
{
    /**
     * @return list<string>
     */
    public function imageMetadataCollections(): array;
}
