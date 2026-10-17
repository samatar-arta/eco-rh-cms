<?php

namespace App\Models;

use App\Enums\PaylistStatut;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Bulletin de paie individuel (fichier privé, jamais exposé publiquement). */
class Paylist extends Model
{
    protected $fillable = [
        'paylist_import_id', 'matricule', 'mois', 'annee', 'fichier_path',
        'empreinte_fichier', 'statut', 'importe_par',
    ];

    protected $hidden = ['fichier_path', 'empreinte_fichier'];

    protected function casts(): array
    {
        return ['statut' => PaylistStatut::class];
    }

    public function import(): BelongsTo
    {
        return $this->belongsTo(PaylistImport::class, 'paylist_import_id');
    }

    public function employe(): BelongsTo
    {
        return $this->belongsTo(Employe::class, 'matricule', 'matricule');
    }

    public function distributions(): HasMany
    {
        return $this->hasMany(PaylistDistribution::class);
    }

    public function periode(): string
    {
        return sprintf('%02d/%d', $this->mois, $this->annee);
    }
}
