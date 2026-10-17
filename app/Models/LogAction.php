<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/** Entrée du journal d'audit : ajout seul. */
class LogAction extends Model
{
    protected $table = 'logs_actions';

    protected $fillable = ['user_id', 'action', 'description', 'entite_type', 'entite_id', 'adresse_ip'];

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException("Le journal d'audit est immuable."));
        static::deleting(fn () => throw new LogicException("Le journal d'audit est immuable."));
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
