<?php

namespace App\Models;

use App\Enums\DistributionCanal;
use App\Enums\DistributionStatut;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaylistDistribution extends Model
{
    protected $fillable = [
        'paylist_id', 'contact_id', 'distribue_par', 'date_distribution', 'canal',
        'statut', 'preuve_distribution', 'commentaire', 'tentatives',
    ];

    protected function casts(): array
    {
        return [
            'date_distribution' => 'datetime',
            'canal' => DistributionCanal::class,
            'statut' => DistributionStatut::class,
        ];
    }

    public function paylist(): BelongsTo
    {
        return $this->belongsTo(Paylist::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function distributeur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'distribue_par');
    }
}
