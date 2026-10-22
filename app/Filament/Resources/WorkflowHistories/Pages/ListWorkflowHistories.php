<?php

namespace App\Filament\Resources\WorkflowHistories\Pages;

use App\Filament\Resources\WorkflowHistories\WorkflowHistoryResource;
use Filament\Resources\Pages\ListRecords;

class ListWorkflowHistories extends ListRecords
{
    protected static string $resource = WorkflowHistoryResource::class;
}
