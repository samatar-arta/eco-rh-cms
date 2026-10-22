<?php

namespace App\Models;

use App\Enums\StatutEmploye;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Employe extends Model
{
    use HasFactory;

    protected $fillable = [
        'matricule', 'name', 'fonction', 'grade', 'date_naissance', 'date_embauche',
        'contrat_id', 'statut_employe', 'date_sortie', 'telephone', 'num_cnss',
        'num_cni', 'niveau_etude', 'adresse', 'situation_familiale',
        'personne_contacter', 'lien_parental', 'telephone_parental',
        'formation_certificat', 'permis_conduite', 'categorie', 'handicap',
        'societe_id', 'departement_id', 'site_id', 'dossier_path', 'cree_par',
    ];

    /** @var list<string> */
    protected $hidden = ['num_cnss', 'num_cni'];

    protected function casts(): array
    {
        return [
            'date_naissance' => 'date',
            'date_embauche' => 'date',
            'date_sortie' => 'date',
            'handicap' => 'boolean',
            'statut_employe' => StatutEmploye::class,
        ];
    }

    protected function age(): Attribute
    {
        return Attribute::get(fn () => $this->date_naissance?->age);
    }

    /** Ancienneté en années entières (jusqu'à la date de sortie si renseignée). */
    protected function anciennete(): Attribute
    {
        return Attribute::get(
            fn () => $this->date_embauche?->diffInYears($this->date_sortie ?? now(), true)
        );
    }

    public function societe(): BelongsTo
    {
        return $this->belongsTo(Societe::class);
    }

    public function departement(): BelongsTo
    {
        return $this->belongsTo(Departement::class);
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(SiteAffectation::class, 'site_id');
    }

    public function contrat(): BelongsTo
    {
        return $this->belongsTo(Contrat::class);
    }

    public function createur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cree_par');
    }

    public function affectations(): HasMany
    {
        return $this->hasMany(EmployeAffectation::class);
    }

    /**
     * Restreint aux employés des périmètres site-département de l'utilisateur.
     * Aucun périmètre => aucun résultat. Seuls les ADMIN et RH_MANAGER actifs contournent ce
     * filtre (accès à tous les employés).
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->estAdministrateur()) {
            return $query;
        }

        return $query->whereExists(function ($sub) use ($user) {
            $sub->selectRaw('1')
                ->from('site_departement_user as sdu')
                ->join('site_departements as sd', 'sd.id', '=', 'sdu.site_departement_id')
                ->where('sdu.user_id', $user->getKey())
                ->whereColumn('sd.site_id', 'employes.site_id')
                ->whereColumn('sd.departement_id', 'employes.departement_id');
        });
    }

    public function isInScopeOf(User $user): bool
    {
        return static::query()->whereKey($this->getKey())->visibleTo($user)->exists();
    }
}
