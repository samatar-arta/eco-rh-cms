<?php

namespace App\Filament\Resources\LogsActions\Pages;

use App\Filament\Resources\LogsActions\LogActionResource;
use Filament\Resources\Pages\ListRecords;

class ListLogsActions extends ListRecords
{
    protected static string $resource = LogActionResource::class;
}
