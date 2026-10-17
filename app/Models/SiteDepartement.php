<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class SiteDepartement extends Model
{
    use HasFactory;

    protected $fillable = ['site_id', 'societe_id', 'departement_id'];

    public function site(): BelongsTo
    {
        return $this->belongsTo(SiteAffectation::class, 'site_id');
    }

    public function societe(): BelongsTo
    {
        return $this->belongsTo(Societe::class);
    }

    public function departement(): BelongsTo
    {
        return $this->belongsTo(Departement::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'site_departement_user')->withTimestamps();
    }
}
