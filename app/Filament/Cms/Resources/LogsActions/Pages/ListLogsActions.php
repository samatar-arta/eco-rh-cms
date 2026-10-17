<?php

namespace App\Filament\Cms\Resources\LogsActions\Pages;

use App\Filament\Cms\Resources\LogsActions\LogActionResource;
use Filament\Resources\Pages\ListRecords;

class ListLogsActions extends ListRecords
{
    protected static string $resource = LogActionResource::class;
}
