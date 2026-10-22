# Architecture technique

## Principe directeur

Workflow **centralisé et polymorphique** ; tables métier **distinctes** ; types et modèles **propres à chaque
module** ; **un compteur de références par module et par année**, commun à tous les types du module, attribué
uniquement à l'approbation finale par `ReferenceService`.

```
Sociétés/Sites/Départements → Utilisateurs/Employés/Contacts
  → Module (Documents, Congés, Sanctions, Évaluations, Promotions, Mutations)
  → Type → Modèle HTML → Enregistrement → Workflow → Approbation
  → Référence → PDF + signature interne → Archivage privé
```

## Couches

| Couche | Emplacement |
|---|---|
| Modèles, casts, relations, traits (`HasWorkflow`, `HasImmutableReference`, `HasFrozenContent`, `ValidatesTemplateConsistency`) | `app/Models` |
| Services métier | `app/Services` |
| Policies (auto-découvertes) | `app/Policies` |
| Jobs / Events / Listeners | `app/Jobs`, `app/Events`, `app/Listeners` |
| Panneau RH (équipes, paie) | `app/Filament/Resources`, `Widgets`, `Support` |
| Administration (ADMIN et RH_MANAGER, panneau rh) | `app/Filament/Resources` (référentiels, types, modèles, utilisateurs, audit) |
| Commandes planifiées | `app/Console/Commands`, `routes/console.php` |

### Services

- `ReferenceService` : séquence verrouillée (`lockForUpdate`) par module+année, `assign()` idempotent, unicité en base.
- `WorkflowService` : toutes les transitions ; rôle, périmètre, statut et séparation des tâches **revérifiés sous verrou**.
  Écrit l'historique (immuable), le journal d'audit et déclenche les notifications.
- `TemplateRendererService` : liste blanche de variables, valeurs échappées, balises actives retirées, aucun PHP exécuté.
- `DocumentGenerationService` / `PdfGenerationService` : contenu figé (`contenu_genere`, `model_version`), PDF dompdf,
  signature interne, archivage privé, rejouable sans nouvelle référence.
- `EffetsApprobationService` : règles par type et effets des promotions/mutations (historique `employe_affectations`).
- `PaylistImportService` / `PaylistDistributionService` : import en deux temps, distribution vérifiée et idempotente.
- `NotificationService`, `AuditService`, `DashboardStatsService`, `UserAdministrationService`.

## Données

- Contraintes SQL en complément du code : FK composites `(departement_id, societe_id)`, unicité des références,
  unicité module+année des séquences, unicité bulletin (matricule, année, mois) et distribution (bulletin, contact).
- Compatibilité SQLite (tests) **et** MySQL/InnoDB (production). Pas de `timestamp NOT NULL` sans défaut (MariaDB).
- Morph map explicite (`AppServiceProvider`) : aucun nom de classe en base.

## Sécurité

- Authentification Filament (login limité à 5 essais/min). Pas d'inscription publique.
- Autorisations dans Policies **et** services ; masquer un bouton ne suffit jamais. `ADMIN` n'accède ni aux dossiers RH ni aux bulletins ; `RH_MANAGER` a les droits d'administration d'ADMIN et conserve ses droits sur les dossiers RH (jamais sur les bulletins).
- Périmètres : `Employe::scopeVisibleTo()` appliqué à toutes les requêtes (listes, statistiques, destinataires de notifications).
- Fichiers : disque privé (`storage/app/private`), téléchargement par routes authentifiées + policy, audités.
- Import Paylist : seul le nom de base d'une entrée ZIP est utilisé, signature `%PDF-` contrôlée, limites de taille/nombre.
- PDF/modèles : variables en liste blanche, valeurs échappées.
- Audit : `logs_actions` en ajout seul (sans secrets). Historique de workflow immuable. Références et dossiers finalisés immuables.

## Hypothèses à confirmer

1. Format d'export Sage : ZIP de PDF `MATRICULE[_suite].pdf` (ou PDF unique). Adapter `PaylistImportService` si CSV/XLSX.
2. Les salaires de promotion restent sur la promotion, non reportés sur la fiche employé ; le « grade » est un champ texte `employes.grade`.
3. Aucun ADMIN ni RH_MANAGER n'a accès aux bulletins ; l'ADMIN seul n'a pas accès aux dossiers RH.
4. Les congés disposent d'une référence interne `CG_DARH/DLS` (modifiable en base).
5. Le statut `EXPIRE` n'est pas appliqué automatiquement : les retards sont signalés et relancés.
6. « Envoyé » = remis au serveur de messagerie.

## Limites connues

Pas de signature électronique qualifiée (intégration prestataire à prévoir si exigée), pas d'API Sage,
pas d'accès externe aux contacts (ils reçoivent les bulletins, ne se connectent pas).
