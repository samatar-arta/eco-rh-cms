<?php

namespace App\Filament\Resources\SitesAffectation\Pages;

use App\Filament\Resources\SitesAffectation\SiteAffectationResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageSitesAffectation extends ManageRecords
{
    protected static string $resource = SiteAffectationResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
