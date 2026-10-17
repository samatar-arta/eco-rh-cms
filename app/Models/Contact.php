<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Destinataire externe (ex. chef de département client). Ce n'est pas un
 * utilisateur de l'application : aucun compte `users` n'est créé pour lui.
 */
class Contact extends Model
{
    use HasFactory;

    protected $fillable = [
        'nom', 'prenom', 'fonction', 'email', 'telephone',
        'societe_id', 'departement_id', 'is_active',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function societe(): BelongsTo
    {
        return $this->belongsTo(Societe::class);
    }

    public function departement(): BelongsTo
    {
        return $this->belongsTo(Departement::class);
    }
}
