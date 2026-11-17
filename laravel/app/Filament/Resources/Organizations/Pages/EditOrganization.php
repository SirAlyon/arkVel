<?php

declare(strict_types=1);

namespace App\Filament\Resources\Organizations\Pages;

use App\Actions\Organizations\SetOrganizationDemoMode;
use App\Filament\Resources\Organizations\OrganizationResource;
use App\Models\Organization;
use App\Models\User;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * Modifica Admin di un ente: il solo flag «Ente dimostrativo». Pagina sottile: il
 * salvataggio delega a {@see SetOrganizationDemoMode}.
 */
class EditOrganization extends EditRecord
{
    protected static string $resource = OrganizationResource::class;

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var Organization $record */
        $user = Auth::user();

        return app(SetOrganizationDemoMode::class)->handle(
            $record,
            (bool) ($data['is_demo'] ?? false),
            $user instanceof User ? $user : null,
        );
    }
}
