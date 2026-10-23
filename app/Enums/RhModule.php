<?php

namespace App\Enums;

use App\Models\Conge;
use App\Models\Document;
use App\Models\Evaluation;
use App\Models\Mutation;
use App\Models\Promotion;
use App\Models\Sanction;
use Illuminate\Database\Eloquent\Model;

/** Les six modules RH : chacun a son propre compteur de références annuel. */
enum RhModule: string
{
    case Document = 'DOCUMENT';
    case Conge = 'CONGE';
    case Sanction = 'SANCTION';
    case Evaluation = 'EVALUATION';
    case Promotion = 'PROMOTION';
    case Mutation = 'MUTATION';

    /** Préfixe initial ; la valeur effective vit dans `reference_sequences`. */
    public function defaultPrefix(): string
    {
        return match ($this) {
            self::Document => 'DA_DARH/DLS',
            self::Conge => 'CG_DARH/DLS',
            self::Sanction => 'DS_DARH/DLS',
            self::Evaluation => 'EV_DARH/DLS',
            self::Promotion => 'PR_DARH/DLS',
            self::Mutation => 'MU_DARH/DLS',
        };
    }

    /** @return class-string<Model> */
    public function recordClass(): string
    {
        return match ($this) {
            self::Document => Document::class,
            self::Conge => Conge::class,
            self::Sanction => Sanction::class,
            self::Evaluation => Evaluation::class,
            self::Promotion => Promotion::class,
            self::Mutation => Mutation::class,
        };
    }

    /** Alias stocké dans `workflows.workflowable_type` (morph map explicite). */
    public function morphAlias(): string
    {
        return strtolower($this->value);
    }

    /**
     * Rôles autorisés à créer et soumettre un dossier du module.
     * Évaluations, promotions et mutations : Manager et Senior uniquement.
     *
     * @return list<Role>
     */
    public function creationRoles(): array
    {
        return match ($this) {
            self::Evaluation, self::Promotion, self::Mutation => [Role::Admin, Role::RhManager, Role::RhSenior],
            default => [Role::Admin, ...Role::rh()],
        };
    }
}
