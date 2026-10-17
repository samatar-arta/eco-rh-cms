<?php

namespace App\Filament\Support;

use App\Enums\WorkflowStatut;
use App\Models\Employe;
use Closure;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Infolists\Components\ViewEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;
use UnitEnum;

/**
 * Socle des ressources des six modules RH. La sécurité repose sur la Policy
 * et sur le filtre de périmètre appliqué à la requête (jamais sur le seul
 * masquage de boutons).
 */
abstract class ModuleResource extends Resource
{
    protected static string|UnitEnum|null $navigationGroup = 'Modules RH';

    /** Colonne qui reçoit l'utilisateur créateur. */
    public static function creatorColumn(): string
    {
        return 'cree_par';
    }

    /** Complète les données avant création (calculs serveur). */
    public static function completerDonnees(array $data): array
    {
        return $data;
    }

    /** Complète les données avant mise à jour (par défaut : inchangées). */
    public static function donneesAEdition(array $data): array
    {
        return $data;
    }

    /** Un enregistrement n'est visible que si son employé est dans le périmètre. */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->whereHas('employe', fn (Builder $q) => $q->visibleTo(auth()->user()));
    }

    /** Employés actifs que l'utilisateur connecté a le droit de traiter. */
    public static function employesAccessibles(): Builder
    {
        return Employe::query()->visibleTo(auth()->user())->where('statut_employe', 'ACTIF');
    }

    public static function employeSelect(): Select
    {
        return Select::make('matricule')
            ->label('Employé')
            ->required()
            ->searchable()
            ->live()
            ->disabled(fn (string $operation) => $operation === 'edit')
            ->getSearchResultsUsing(fn (string $search) => static::employesAccessibles()
                ->where(fn (Builder $q) => $q->where('name', 'like', "%{$search}%")->orWhere('matricule', 'like', "%{$search}%"))
                ->limit(50)->get()
                ->mapWithKeys(fn (Employe $e) => [$e->matricule => "{$e->matricule} — {$e->name}"])->all())
            ->getOptionLabelUsing(fn ($value) => ($e = Employe::where('matricule', $value)->first()) ? "{$e->matricule} — {$e->name}" : $value)
            ->rule(fn () => function (string $attribute, $value, Closure $fail) {
                if (! static::employesAccessibles()->where('matricule', $value)->exists()) {
                    $fail('Cet employé est hors de votre périmètre ou inactif.');
                }
            });
    }

    public static function employeInfo(): Text
    {
        return Text::make(function (Get $get) {
            $e = Employe::with(['societe', 'departement', 'site'])->where('matricule', $get('matricule'))->first();

            return $e
                ? "{$e->name} · {$e->fonction} · {$e->societe?->name} / {$e->departement?->name} · site {$e->site?->name} · embauché le {$e->date_embauche?->format('d/m/Y')}"
                : 'Sélectionnez un employé pour afficher sa fiche.';
        });
    }

    /**
     * Sélecteur de type : à chaque changement, le modèle par défaut du type
     * est proposé (le filtrage serveur reste porté par le sélecteur de modèle).
     *
     * @param  class-string<Model>  $typeClass
     * @param  class-string<Model>  $templateClass
     */
    public static function typeSelect(string $typeKey, string $templateKey, string $typeClass, string $templateClass): Select
    {
        return Select::make($typeKey)
            ->label('Type')
            ->required()
            ->live()
            ->options(fn () => $typeClass::where('is_active', true)->orderBy('name')->pluck('name', 'id'))
            ->afterStateUpdated(fn ($state, Set $set) => $set(
                $templateKey,
                $state ? $templateClass::where($typeKey, $state)->where('is_active', true)->where('is_default', true)->value('id') : null
            ));
    }

    /**
     * Les modèles proposés sont ceux du type choisi ; la règle `exists` refuse
     * côté serveur tout identifiant forcé d'un autre type.
     *
     * @param  class-string<Model>  $templateClass
     */
    public static function modelSelect(string $typeKey, string $templateKey, string $templateClass): Select
    {
        return Select::make($templateKey)
            ->label('Modèle')
            ->live()
            ->options(fn (Get $get) => $get($typeKey)
                ? $templateClass::where($typeKey, $get($typeKey))->where('is_active', true)->orderBy('titre')->pluck('titre', 'id')
                : [])
            ->rule(fn (Get $get) => Rule::exists((new $templateClass)->getTable(), 'id')->where($typeKey, $get($typeKey)))
            ->placeholder("Choisissez d'abord un type");
    }

    /** @param  class-string<Model>  $templateClass */
    public static function modelPreview(string $templateKey, string $templateClass): Text
    {
        return Text::make(function (Get $get) use ($templateKey, $templateClass) {
            $modele = $templateClass::find($get($templateKey));

            return $modele
                ? "Modèle « {$modele->titre} » v{$modele->version} — aperçu : ".str(strip_tags($modele->contenu))->squish()->limit(400)
                : 'Aucun modèle sélectionné.';
        });
    }

    public static function statutColumn(): TextColumn
    {
        return TextColumn::make('workflow.statut')
            ->label('Statut')
            ->badge()
            ->formatStateUsing(fn ($state) => $state instanceof WorkflowStatut ? $state->value : ($state ?? 'BROUILLON'))
            ->color(fn ($state) => match ($state instanceof WorkflowStatut ? $state->value : 'BROUILLON') {
                'APPROUVE' => 'success',
                'REJETE', 'EXPIRE' => 'danger',
                'EN_ATTENTE_VALIDATION', 'EN_ATTENTE_APPROBATION', 'DOCUMENT_SOUMIS', 'VALIDE' => 'warning',
                default => 'gray',
            });
    }

    public static function referenceColumn(): TextColumn
    {
        return TextColumn::make('reference')->label('Référence')->searchable()->placeholder('—')->copyable();
    }

    public static function statutFilter(): SelectFilter
    {
        return SelectFilter::make('statut')
            ->label('Statut du workflow')
            ->options(collect(WorkflowStatut::cases())->mapWithKeys(fn ($s) => [$s->value => $s->value])->all())
            ->query(function (Builder $query, array $data) {
                $statut = $data['value'] ?? null;
                if ($statut === null || $statut === '') {
                    return;
                }
                $statut === WorkflowStatut::Brouillon->value
                    ? $query->where(fn ($q) => $q->doesntHave('workflow')->orWhereHas('workflow', fn ($w) => $w->where('statut', $statut)))
                    : $query->whereHas('workflow', fn ($w) => $w->where('statut', $statut));
            });
    }

    /** @return list<Action|ActionGroup> */
    public static function recordActions(): array
    {
        return [ViewAction::make(), EditAction::make(), ...WorkflowActions::all()];
    }

    public static function timelineSection(): Section
    {
        return Section::make('Historique du workflow')
            ->schema([ViewEntry::make('workflow')->view('filament.workflow-timeline')->hiddenLabel()])
            ->columnSpanFull();
    }
}
