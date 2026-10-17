<?php

namespace App\Filament\Resources\Employes\Pages;

use App\Filament\Resources\Employes\EmployeResource;
use Filament\Resources\Pages\CreateRecord;

class CreateEmploye extends CreateRecord
{
    protected static string $resource = EmployeResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['cree_par'] = auth()->id();

        return $data;
    }
}
