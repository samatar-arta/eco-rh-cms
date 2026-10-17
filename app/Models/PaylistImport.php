<?php

namespace App\Models;

use App\Enums\PaylistImportStatut;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PaylistImport extends Model
{
    protected $fillable = [
        'nom_fichier', 'chemin_fichier', 'mois', 'annee', 'importe_par', 'date_import',
        'statut_import', 'total_lignes', 'lignes_importees', 'lignes_rejetees',
        'apercu', 'rapport_erreurs',
    ];

    protected function casts(): array
    {
        return [
            'date_import' => 'datetime',
            'statut_import' => PaylistImportStatut::class,
            'apercu' => 'array',
            'rapport_erreurs' => 'array',
        ];
    }

    public function importeur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'importe_par');
    }

    public function paylists(): HasMany
    {
        return $this->hasMany(Paylist::class);
    }

    public function periode(): string
    {
        return sprintf('%02d/%d', $this->mois, $this->annee);
    }
}
