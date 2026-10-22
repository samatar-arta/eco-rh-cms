<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use App\Services\UserAdministrationService;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['roles'] = array_map(fn ($r) => $r->value, $this->record->rolesEnum());
        $data['perimetres'] = $this->record->siteDepartements()->pluck('site_departements.id')->all();
        unset($data['password']);

        return $data;
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        return app(UserAdministrationService::class)->modifier(auth()->user(), $record, $data);
    }
}
