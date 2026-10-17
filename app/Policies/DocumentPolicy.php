<?php

namespace App\Policies;

use App\Models\Document;

class DocumentPolicy extends ModuleRecordPolicy
{
    protected string $modelClass = Document::class;
}
