<?php

namespace App\Services;

use App\Enums\RhModule;
use App\Models\Departement;
use App\Models\Employe;
use App\Models\Societe;
use Carbon\CarbonInterface;
use Closure;
use Illuminate\Database\Eloquent\Model;

/**
 * Rendu des modèles HTML. Aucun code n'est exécuté : seuls les jetons
 * `{{ variable }}` d'une liste blanche sont remplacés, et chaque valeur est
 * échappée. Les balises actives (script, iframe, attributs on*, etc.) sont
 * supprimées du modèle avant rendu.
 */
class TemplateRendererService
{
    /** @var array<string, Closure(Model|null, Employe|null): mixed> */
    private array $variables;

    public function __construct()
    {
        $emp = fn (string $champ) => fn ($r, ?Employe $e) => $e?->{$champ};
        $rec = fn (string $champ) => fn (?Model $r) => $r?->{$champ};

        $this->variables = [
            'employe.matricule' => $emp('matricule'),
            'employe.name' => $emp('name'),
            'employe.fonction' => $emp('fonction'),
            'employe.date_embauche' => $emp('date_embauche'),
            'employe.societe.name' => fn ($r, ?Employe $e) => $e?->societe?->name,
            'employe.departement.name' => fn ($r, ?Employe $e) => $e?->departement?->name,

            'document.reference' => $rec('reference'),
            'document.date_production' => $rec('date_production'),

            'conge.reference' => $rec('reference'),
            'conge.date_debut' => $rec('date_debut'),
            'conge.date_fin' => $rec('date_fin'),
            'conge.nbre_jours' => $rec('nbre_jours'),

            'sanction.reference' => $rec('reference'),
            'sanction.date_sanction' => $rec('date_sanction'),
            'sanction.description' => $rec('description'),

            'evaluation.reference' => $rec('reference'),
            'evaluation.date_evaluation' => $rec('date_evaluation'),
            'evaluation.note' => $rec('note'),

            'promotion.reference' => $rec('reference'),
            'promotion.ancienne_fonction' => $rec('ancienne_fonction'),
            'promotion.nouvelle_fonction' => $rec('nouvelle_fonction'),
            'promotion.date_effet' => $rec('date_effet'),

            'mutation.reference' => $rec('reference'),
            'mutation.ancienne_fonction' => $rec('ancienne_fonction'),
            'mutation.nouvelle_fonction' => $rec('nouvelle_fonction'),
            'mutation.date_effet' => $rec('date_effet'),

            'date_du_jour' => fn () => now(),

            // Alias en majuscules utilisés par les modèles DLS.
            'REFERENCE' => $rec('reference'),
            'DATE_PRODUCTION' => fn (?Model $r) => $r?->date_production ?? now(),
            'NOM_COMPLET' => $emp('name'),
            'MATRICULE' => $emp('matricule'),
            'DATE_EMBAUCHE' => $emp('date_embauche'),
            'ANNEE_EMBAUCHE' => fn ($r, ?Employe $e) => $e?->date_embauche?->format('Y'),
            'FONCTION' => $emp('fonction'),
            'CODE' => fn ($r, ?Employe $e) => $e?->societe?->code,
            'DEPARTEMENT' => fn ($r, ?Employe $e) => $e?->departement?->name,
        ];
    }

    /** @return list<string> */
    public function variablesAutorisees(): array
    {
        return array_keys($this->variables);
    }

    /** Variables du modèle absentes de la liste blanche (pour refuser l'enregistrement). */
    public function variablesInconnues(string $template): array
    {
        preg_match_all('/\{\{\s*([^{}]*?)\s*\}\}/', $template, $m);

        return array_values(array_unique(array_filter(
            $m[1],
            fn (string $nom) => ! array_key_exists($nom, $this->variables)
        )));
    }

    public function rendre(string $template, ?Model $record, ?Employe $employe = null): string
    {
        $employe ??= $record?->employe;
        $template = $this->assainir($template);

        return preg_replace_callback('/\{\{\s*([^{}]*?)\s*\}\}/', function (array $m) use ($record, $employe) {
            $resolveur = $this->variables[$m[1]] ?? null;

            return $resolveur === null ? '' : e($this->formater($resolveur($record, $employe)));
        }, $template);
    }

    /** Aperçu avec des données fictives, sans toucher à la base. */
    public function apercu(string $template, RhModule $module): string
    {
        $societe = new Societe(['name' => 'DLS']);
        $departement = new Departement(['name' => 'Ressources humaines']);
        $employe = new Employe([
            'matricule' => 'MAT-0001', 'name' => 'Employé Exemple', 'fonction' => 'Agent',
            'date_embauche' => now()->subYears(3),
        ]);
        $employe->setRelation('societe', $societe)->setRelation('departement', $departement);

        $classe = $module->recordClass();
        $record = new $classe([
            'reference' => '0001/EXEMPLE/'.now()->year,
            'date_production' => now(), 'date_debut' => now(), 'date_fin' => now()->addDays(5),
            'nbre_jours' => 5, 'date_sanction' => now(), 'description' => 'Description exemple',
            'date_evaluation' => now(), 'note' => 15, 'date_effet' => now()->addMonth(),
            'ancienne_fonction' => 'Agent', 'nouvelle_fonction' => 'Superviseur',
        ]);

        return $this->rendre($template, $record, $employe);
    }

    private function formater(mixed $valeur): string
    {
        return match (true) {
            $valeur === null => '',
            $valeur instanceof CarbonInterface => $valeur->format('d/m/Y'),
            is_float($valeur) => rtrim(rtrim(number_format($valeur, 2, ',', ' '), '0'), ','),
            is_numeric($valeur) && str_contains((string) $valeur, '.') => rtrim(rtrim((string) $valeur, '0'), '.'),
            default => (string) $valeur,
        };
    }

    /** Retire tout ce qui peut s'exécuter ou charger du contenu externe. */
    public function assainir(string $html): string
    {
        $html = preg_replace('#<\?(?:php|=).*?(?:\?>|$)#is', '', $html);
        $html = preg_replace('#<(script|iframe|object|embed|link|meta|base|form)\b.*?(?:</\1\s*>|$)#is', '', $html);
        $html = preg_replace('#<(script|iframe|object|embed|link|meta|base|form)\b[^>]*>#i', '', $html);
        $html = preg_replace('#\son[a-z]+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)#i', '', $html);
        $html = preg_replace('#(href|src)\s*=\s*(["\']?)\s*(javascript|data|vbscript):#i', '$1=$2#blocked:', $html);

        return $html;
    }
}
