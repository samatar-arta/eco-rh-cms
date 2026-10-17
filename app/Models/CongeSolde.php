<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CongeSolde extends Model
{
    protected $table = 'conge_soldes';

    protected $fillable = ['matricule', 'type_conge_id', 'annee', 'jours_acquis', 'jours_consommes'];

    protected function casts(): array
    {
        return ['jours_acquis' => 'decimal:1', 'jours_consommes' => 'decimal:1'];
    }

    protected function solde(): Attribute
    {
        return Attribute::get(fn () => (float) $this->jours_acquis - (float) $this->jours_consommes);
    }

    public function employe(): BelongsTo
    {
        return $this->belongsTo(Employe::class, 'matricule', 'matricule');
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(TypeConge::class, 'type_conge_id');
    }
}
