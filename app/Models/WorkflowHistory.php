<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/** Journal en ajout seul : une entrée ne se modifie ni ne se supprime. */
class WorkflowHistory extends Model
{
    protected $fillable = [
        'workflow_id', 'user_id', 'role', 'etape', 'statut', 'commentaire', 'date_limite', 'date_action',
    ];

    protected function casts(): array
    {
        return ['date_limite' => 'datetime', 'date_action' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException("L'historique de workflow est immuable."));
        static::deleting(fn () => throw new LogicException("L'historique de workflow est immuable."));
    }

    public function workflow(): BelongsTo
    {
        return $this->belongsTo(Workflow::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
