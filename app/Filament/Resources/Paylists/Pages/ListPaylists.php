<?php

namespace App\Filament\Resources\Paylists\Pages;

use App\Filament\Resources\Paylists\PaylistResource;
use Filament\Resources\Pages\ListRecords;

class ListPaylists extends ListRecords
{
    protected static string $resource = PaylistResource::class;
}
