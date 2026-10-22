# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Commandes

- `composer setup` : install complète (deps, `.env`, key, migrate, `npm install`, `npm run build`)
- `composer dev` : `artisan serve` + `queue:listen` + `pail` + `npm run dev` en parallèle
- `composer test` : `config:clear` puis `php artisan test`
- Un seul test : `php artisan test --filter=NomDuTest` ou `php artisan test tests/Feature/FoundationTest.php`
- Format : `vendor/bin/pint`
- Les tests tournent sur SQLite `:memory:` (défini dans `phpunit.xml`), indépendamment du `.env`. Les migrations/contraintes doivent donc rester compatibles SQLite **et** MySQL (cible de prod : MySQL/InnoDB sous XAMPP ; `lockForUpdate()` n'a pas d'effet réel sous SQLite, donc les tests de concurrence des références nécessitent MySQL).

## Architecture (état après Phase 10 : validation finale)

- Laravel 12, Filament 5, Livewire 4, PHP ^8.2. Auth = Filament uniquement (Breeze supprimé, `/` redirige vers `/rh`, pas de `/register`). Premier admin : `php artisan app:create-admin {name} {email}` (mot de passe saisi en interactif).
- Panneau unique `rh` (`/rh`) ; le panneau `cms` a été supprimé, ses ressources d'administration (référentiels, types, modèles, utilisateurs, audit) vivent dans `app/Filament/Resources` et sont réservées à ADMIN et RH_MANAGER (accès complet) via `AdminOnlyResource`, les policies et Shield. Accès via `User::canAccessPanel()` : utilisateur actif avec au moins un rôle.
- Rôles : enum `App\Enums\Role`, table `role_user` (multi-rôles, hors `$fillable`). Périmètres : pivot `site_departement_user` ; `Employe::scopeVisibleTo($user)` limite les rôles RH/paie à leurs périmètres ; un utilisateur actif ADMIN ou RH_MANAGER (`User::estAdministrateur()`) le contourne (accès total aux employés, tous périmètres, y compris via `EmployePolicy::before()`).
- Cohérence société/département garantie par FK composites `(departement_id, societe_id) -> departements(id, societe_id)` (site_departements, employes, contacts, employe_affectations).
- Policies auto-découvertes dans `app/Policies` (référentiels = `ReferentialPolicy`, écriture ADMIN et RH_MANAGER).
- RH_MANAGER = ADMIN (décision du 2026-10-22) : mêmes droits d'administration (ressources d'administration, utilisateurs, journal d'audit, historique des workflows, `app:sync-permissions`, tous les périmètres). Les permissions Shield sont accordées aux deux rôles. Le gestionnaire de paie reste le seul rôle des bulletins Paylist.
- Tests : `tests/Feature/FoundationTest.php` ; `APP_KEY` de test défini dans `phpunit.xml`.
- Modules (Phase 3) : par module `types_*`, `model_*` (modèles HTML versionnés), enregistrement ; le trait `ValidatesTemplateConsistency` impose modèle ↔ type côté serveur.
- Références et workflow (Phase 4) : `ReferenceService` (séquence verrouillée par module+année, `assign()` idempotent), `WorkflowService` (rôle + périmètre + séparation des tâches revérifiés sous verrou), morph map explicite dans `AppServiceProvider`, `WorkflowHistory` immuable, références immuables (`HasImmutableReference`). Aucun `timestamp NOT NULL` sans défaut dans les migrations (MariaDB/XAMPP refuse) : utiliser `dateTime`.
- Test de concurrence réel (MySQL, lent ~2 min, exclu par défaut) : créer la base `eco_rh_dls_test` puis `php artisan test --group=mysql` (il fait `migrate:fresh` sur cette base uniquement).
- Interfaces (Phase 5) : panneau `rh` = Employés, Contacts et les 6 modules (`app/Filament/Resources/<Module>`, socle `app/Filament/Support/ModuleResource` + pages de base ; actions Soumettre/Valider/Rejeter/Approuver dans `WorkflowActions`, timeline = `resources/views/filament/workflow-timeline.blade.php`). Panneau `cms` (ADMIN) = référentiels, types, modèles HTML, niveaux de sanction, utilisateurs (`UserAdministrationService`). Les ressources CMS simples sont générées par script puis maintenues à la main. Sécurité : `ModuleRecordPolicy` (+ `SanctionPolicy` à accès renforcé), requêtes filtrées par périmètre (`whereHas('employe', visibleTo)`), modèle ↔ type vérifié par règle `exists` + trait serveur ; nbre de jours de congé et anciennes valeurs de mutation calculés côté serveur. Tests : `tests/Feature/FilamentModulesTest.php`.
- Documents (Phase 6) : `TemplateRendererService` (liste blanche de variables, valeurs échappées, balises actives retirées, jamais d'exécution), `DocumentGenerationService::finaliser()` (contenu figé `contenu_genere` + `model_version`, PDF dompdf, signature interne = bloc avec approbateur/date/identifiant/SHA-256, archivage privé `storage/app/private/dossiers/<module>/<année>/`), idempotent et rejouable sans nouvelle référence (`php artisan dossiers:finaliser [module] [id]`, action « Relancer le PDF »). Déclenché par `DossierApprouve` → listener → `FinaliserDossierJob` (file `database`, 3 essais) ; un échec n'annule jamais l'approbation. `HasFrozenContent` rend un dossier finalisé immuable ; le PDF d'un congé est dans `conges.decision_path` (`fichier_path` = justificatif). Règle des N mois : `DocumentRenewalRule` (`types_documents.delai_renouvellement_mois`), contrôlée à la soumission et à l'approbation. Téléchargement : route `dossiers.fichier` (auth + policy `view`). Les tests isolent le stockage (`Storage::fake('local')` dans `Tests\TestCase`). Un worker de file est nécessaire en production (`php artisan queue:work`).
- Paylist (Phase 7) : `PaylistImportService` (analyse = aperçu sans rien importer, puis `confirmer()` idempotent/reprenable ; source = ZIP de PDF nommés `MATRICULE[_suite].pdf` ou PDF unique, hypothèse à confirmer avec l'export Sage réel ; limites ZIP dans `config/rh.php` clé `paylist`, seul le nom de base d'une entrée est utilisé, magic bytes `%PDF-` vérifiés, unicité matricule+année+mois). `PaylistDistributionService` (contact actif, même société, même département si renseigné, e-mail valide, vérifié à la demande ET à l'envoi ; lot refusé en entier si un destinataire est invalide ; unique paylist+contact = idempotent ; `EnvoyerBulletinJob` 1 essai, échec => ECHEC + `relancer()`; statut ENVOYE = remis au serveur de messagerie, pas lu). Policies `PaylistModulePolicy` : GESTIONNAIRE_PAIE actif uniquement (ni ADMIN ni RH), aucune suppression. Fichiers privés `storage/app/private/paylists/`, téléchargement via route `paylists.fichier`. Écrans rh (groupe Paylist) : Contacts, Imports, Bulletins (action groupée Distribuer), Distributions. Tests : `tests/Feature/PaylistTest.php`.
- Évaluations/promotions/mutations (Phase 8) : `EffetsApprobationService`. Avant soumission ET approbation : champs exigés par type (codes `PROMOTION_*`/`MUTATION_*`), changement réel, département appartenant à la société de destination. À l'approbation, dans la même transaction que la référence, `appliquerSiEchu()` modifie l'employé (fonction, `grade`, site/société/département) si `date_effet` <= aujourd'hui, ferme l'affectation courante et en ouvre une nouvelle dans `employe_affectations` (historique jamais écrasé, ligne « Situation initiale » créée si vide) et trace `effets_appliques` dans l'historique du workflow ; `effet_applique_le` rend l'application idempotente. Date future : commande planifiée `rh:appliquer-effets` (quotidienne) ; un conflit (affectation de l'employé changée depuis la création de la mutation) bloque l'approbation si elle est due, sinon est tracé une fois (`effets_en_conflit`). Le salaire n'est pas porté sur la fiche employé. Évaluation : la note finale est toujours recalculée côté serveur depuis `resultats_criteres` (coefficients du modèle) ; le formulaire génère les champs de notes par critère. Tests : `tests/Feature/PromotionsMutationsEffectsTest.php`.
- Notifications, audit, tableaux de bord (Phase 9) : `NotificationService` (une seule classe `AppNotification`, canal base de données = cloche Filament `databaseNotifications()`, e-mail seulement si `RH_NOTIFICATIONS_MAIL=true` ; destinataires filtrés par rôle + actif + périmètre ; jamais bloquant ; relecture du workflow via `->workflow()->first()` pour éviter une relation périmée). Événements : soumission -> Seniors, validation -> Managers, rejet/approbation -> créateur+validateur, retard (`workflow:check-delays` horaire, une relance par échéance via `workflows.retard_notifie_le`), fin d'import et échec de distribution Paylist -> gestionnaire de paie. `AuditService` -> table `logs_actions` (immuable, ADMIN et RH_MANAGER seulement, ressource CMS « Journal d'audit ») : chaque entrée d'historique de workflow (`workflow.<étape>`), imports/distributions/téléchargements Paylist, téléchargements de dossiers, création/modification/permissions des utilisateurs ; jamais de mot de passe ni de contenu de bulletin. `DashboardStatsService` + widgets `RhStatsWidget`/`EffectifsSocieteChart`/`ContratsChart` (panneau rh) : tous les chiffres passent par `visibleTo`, la politique `viewAny` du module et la restriction des sanctions (Junior = siennes) ; chiffres Paylist réservés au gestionnaire de paie. Tests : `tests/Feature/NotificationsAuditDashboardTest.php`. Un worker de file ET le cron `schedule:run` sont nécessaires en production.
- Phase 10 : documentation dans `docs/` (INSTALLATION, UTILISATEUR, ARCHITECTURE, SAUVEGARDE_RESTAURATION, DEPLOIEMENT) + README. Validé : 136 tests SQLite, test de concurrence MySQL (`--group=mysql`, migrate:fresh complet sur MariaDB 10.4), Pint. Non fait / hors périmètre : signature électronique qualifiée, API Sage, format d'export Sage réel à confirmer, test de restauration sur le serveur cible.

## Cahier des charges

Tout ce qui suit (à partir de « PROMPT MAÎTRE ») est le cahier des charges et les règles d'exécution du projet ECOSYSTEME RH DLS. La règle non négociable : workflow centralisé polymorphique, tables métier distinctes, types/modèles propres à chaque module, compteur de références annuel par module (via `ReferenceService`, attribué seulement après approbation finale).

---

# PROMPT MAÎTRE — CONCEPTION ET DÉVELOPPEMENT DE L'ECOSYSTEME RH DLS
## Application professionnelle Laravel 12, Filament v5, MySQL sur xampp et automatisation des processus RH

---

# 1. TON RÔLE ET TA MISSION

Tu es un architecte logiciel senior, développeur expert Laravel 12, PHP 8.2+, MySQL, Eloquent ORM, Filament v5, Livewire, Blade, sécurité applicative, gestion électronique des documents (GED), automatisation RH et conception des workflows métier.

Ta mission est de concevoir, développer, tester et documenter une application web  ECOSYSTEME RH DLS complète, sécurisée, modulaire et évolutive pour la société **DLS — Djibouti Labour Service**.
Conçois une application web professionnelle, modulaire, maintenable et prête pour production.

Le projet doit respecter les bonnes pratiques :

SOLID

Repository Pattern

Service Layer

Policies

Notifications

Architecture modulaire

Audit trail

Responsive

Sécurité Laravel

NOM DU PROJET

 ECOSYSTEME RH DLS — Digitalisation du Département Ressources Humaines

Tu dois travailler directement dans le projet ouvert dans Claude Code.

**Tu ne dois pas te limiter à produire un cahier des charges, des exemples de code ou des recommandations. Tu dois implémenter progressivement une application fonctionnelle dans le dépôt existant.**

Avant toute modification :

1. Inspecte l'ensemble du projet et identifie son état actuel.
2. Vérifie les versions de PHP, Laravel, Composer, Filament v5, Livewire et les dépendances.
3. Examine les migrations, modèles, contrôleurs, services, policies, routes, ressources Filament, tests et configurations existantes.
4. Identifie les éléments déjà opérationnels et ceux qui sont incomplets.
5. Préserve les fonctionnalités existantes qui sont correctes.
6. Établis un plan d'implémentation priorisé.
7. Développe et teste les fonctionnalités par étapes cohérentes.

Ne remplace pas aveuglément le projet existant par une nouvelle application. Si le projet n'existe pas ou est vide, initialise-le avec les versions compatibles requises.

Ne prétends jamais qu'une fonctionnalité est terminée si son code, ses migrations et ses tests n'ont pas été vérifiés.

# 2. CONTEXTE DE LA SOCIÉTÉ DLS
Le contexte de la société DLS est une société de type juridique SA dont elle fournit des mains d'oeuvres qualifiés à la deamande des sociétés-clients à savoir SGDT,DMP et IDIPO. 
Dans ce cas, la société DLS est l'intermediaire entre les employés et les sociétés clients.
Mission de Djibouti Labour Services FZCO
La mission principale de Djibouti Labour Services FZCO (DLS) est de fournir des services de gestion et de mise à disposition de main-d’œuvre afin de répondre efficacement aux besoins en personnel de ses partenaires/clients notamment :
- SGTD ;
- DMP ;
- IDIPO.
DLS assure notamment le recrutement, la mobilisation, l’affectation et le suivi administratif des agents, en veillant au respect des exigences opérationnelles, des procédures internes et de la réglementation en vigueur.

À travers une gestion rigoureuse de ses ressources humaines, DLS contribue à garantir la disponibilité d’un personnel qualifié, fiable et adapté aux besoins des activités de ses partenaires.

L’entreprise veille également à assurer un cadre de travail professionnel, à promouvoir la discipline et à accompagner ses employés dans le respect de leurs obligations et de leurs droits.

La société DLS dispose de plusieurs départements internes, notamment :

- Direction ;
- Ressources humaines ;
- Finance ;
- Opérations.

L'application doit permettre de digitaliser les processus RH, de centraliser les dossiers des employés, d'automatiser les documents administratifs, de suivre les demandes et décisions, de gérer les bulletins de paie et d'assurer la traçabilité complète des opérations.

Les responsables RH de DLS travaillent notamment sur les sites et départements des sociétés clientes. Les chefs de département destinataires des bulletins de paie peuvent être des personnes externes à DLS et ne doivent pas être obligés de posséder un compte utilisateur interne.

L'application doit être conçue pour un déploiement sur un serveur d'entreprise ou un intranet, avec une base de données MySQL.

# 3. STACK TECHNIQUE OBLIGATOIRE

Utiliser :

- PHP compatible avec Laravel 12 ;
- Laravel 12 ;
- Composer ;
- MySQL sur xampp ;
- Laravel Breeze pour l'authentification si cette solution correspond à l'état du projet ;
- Filament v5 pour l'administration et les interfaces RH ;
- Livewire pour les interfaces interactives ;
- Eloquent ORM ;
- Blade pour les vues et les modèles HTML ;
- Laravel Notifications pour les notifications ;
- Laravel Policies, Gates et middleware pour les autorisations ;
- PHPUnit ou Pest, selon le framework de test déjà installé ;
- un moteur de génération PDF compatible avec l'environnement Laravel ;
- Laravel Storage pour la gestion sécurisée des fichiers.

Utilise une version de Filament compatible avec Laravel 12 et PHP installé. Ne force pas une version incompatible.

Ne réinstalle pas inutilement une dépendance déjà opérationnelle.

Le code doit respecter les conventions Laravel, le principe de séparation des responsabilités, les transactions SQL et les bonnes pratiques de sécurité.

# 4. OBJECTIFS FONCTIONNELS

L'application doit intégrer les modules suivants :

1. Authentification et gestion des utilisateurs.
2. Gestion des sociétés, sites et départements.
3. Gestion des employés et de leurs dossiers.
4. Gestion des documents administratifs.
5. Gestion des congés.
6. Gestion des sanctions.
7. Gestion des évaluations.
8. Gestion des promotions.
9. Gestion des mutations.
10. Importation et distribution des bulletins de paie via Paylist.
11. Moteur de workflow centralisé.
12. Référentiels et modèles HTML.
13. Référencement administratif par module.
14. Génération PDF et signature électronique interne.
15. Notifications.
16. Historique et journal d'audit.
17. Tableaux de bord et indicateurs RH.
18. Administration des paramètres métier.

Chaque module métier doit disposer de ses propres écrans, règles de validation, types, modèles et autorisations.

# 5. ARCHITECTURE GÉNÉRALE

L'architecture Global devient
                         ECOSYSTEME RH DLS
                              │
       ┌──────────────────────┼──────────────────────┐
       │                      │                      │
       ▼                      ▼                      ▼
 RÉFÉRENTIELS            MODULES RH              WORKFLOW
       │                      │                      │
       │          ┌───────────┼───────────┐          │
       │          │           │           │          │
       │       TYPES       MODÈLES    RÉFÉRENCES     │
       │          │           │           │          │
       │          └───────────┼───────────┘          │
       │                      │                      │
       │                ENREGISTREMENT               │
       │                      │                      │
       │                      ▼                      │
       │               Workflow polymorphique ◄──────┘
       │                      │
       │                 APPROUVE
       │                      │
       │                      ▼
       │              ReferenceService
       │                      │
       │                      ▼
       │             Référence officielle
       │                      │
       │                      ▼
       │                PDF / Signature
       │
       └── Sociétés / Sites / Départements / Contacts

Adopter une architecture modulaire et maintenir une distinction nette entre :

- les référentiels ;
- les modèles/templates ;
- les enregistrements métier ;
- les workflows ;
- les références administratives ;
- les fichiers générés ;
- les notifications ;
- les historiques et audits.

Architecture cible :

SOCIÉTÉS / SITES / DÉPARTEMENTS
        ↓
UTILISATEURS / EMPLOYÉS / CONTACTS
        ↓
MODULE MÉTIER
        ↓
TYPE MÉTIER
        ↓
MODÈLE MÉTIER
        ↓
ENREGISTREMENT MÉTIER
        ↓
WORKFLOW CENTRALISÉ
        ↓
APPROBATION FINALE
        ↓
RÉFÉRENCE ADMINISTRATIVE
        ↓
DOCUMENT PDF / SIGNATURE / ARCHIVAGE

Cette représentation décrit les dépendances métier. Le modèle exact des relations doit être implémenté de façon cohérente avec les migrations Laravel.

# 6. SOCIÉTÉS, SITES ET DÉPARTEMENTS

Créer ou adapter les tables suivantes.

## 6.1. societes

Champs principaux :

- id ;
- name ;
- code, unique et nullable si nécessaire ;
- description, nullable ;
- is_active ;
- timestamps.

Exemples : DLS, SGTD, DMP, IDIPO.

## 6.2. sites_affectation

Champs :

- id ;
- name ;
- description ;
- is_active ;
- timestamps.

Un site représente un périmètre opérationnel d'affectation RH.

## 6.3. departements

Champs :

- id ;
- name ;
- abrege ;
- societe_id ;
- is_active ;
- timestamps.

Chaque département appartient à une société.

## 6.4. site_departements

Champs :

- id ;
- site_id, lié à sites_affectation ;
- societe_id, lié à societes ;
- departement_id, lié à departements ;
- timestamps.

Cette table définit les départements accessibles dans un site pour une société donnée.

Ajouter les contraintes d'intégrité nécessaires pour empêcher les associations incohérentes entre société et département.

## 6.5. Affectations des utilisateurs

Ne pas limiter définitivement un utilisateur à un seul site.

Créer une table pivot `site_departement_user` ou une structure équivalente permettant d'affecter un utilisateur à un ou plusieurs périmètres site-département.

Les affectations doivent préciser le périmètre organisationnel auquel l'utilisateur a accès.

Un utilisateur RH affecté à DMP/DSI, DMP/OPS et DMP/TECHNIQUE doit pouvoir consulter et gérer uniquement les employés autorisés dans ces périmètres, selon son rôle.

# 7. AUTHENTIFICATION ET RÔLES

Ne pas créer de rôle `SUPER_ADMIN`.

Prévoir au minimum les rôles suivants :

- ADMIN ;
- RH_MANAGER ;
- RH_SENIOR ;
- RH_OFFICER ;
- RH_JUNIOR ;
- GESTIONNAIRE_PAIE.

Le rôle `GESTIONNAIRE_PAIE` doit être reconnu par l'application et par les mécanismes d'autorisation.

Un utilisateur peut posséder un ou plusieurs rôles si l'architecture retenue le permet. Si un champ enum est conservé temporairement, sa compatibilité avec les migrations, les validations et les interfaces doit être assurée.

## 7.1. Responsabilités

**ADMIN**
- Gérer les utilisateurs et leurs affectations.
- Administrer les référentiels, types et modèles.
- Consulter les journaux selon les permissions.
- Paramétrer l'application.
- Ne pas disposer d'un accès illimité aux données personnelles sans contrôle de permissions.

**RH_MANAGER**
- Approuver les documents et demandes qui lui sont transmis.
- Effectuer les évaluations, promotions et mutations.
- Superviser les opérations RH autorisées.
- Apposer la signature électronique interne prévue par le système après approbation.

**RH_SENIOR**
- Valider les dossiers soumis.
- Effectuer les évaluations, promotions et mutations.
- Examiner les pièces justificatives et formuler des observations.
- Transmettre les dossiers au Manager RH.

**RH_OFFICER**
- Créer et traiter les dossiers autorisés.
- Préparer les documents et demandes RH.
- Soumettre les dossiers au workflow.

**RH_JUNIOR**
- Saisir les informations autorisées.
- Créer des brouillons.
- Corriger les dossiers rejetés.
- Soumettre les dossiers selon les permissions.

**GESTIONNAIRE_PAIE**
- Importer les bulletins depuis Sage.
- Contrôler les imports.
- Associer les bulletins aux employés.
- Préparer et effectuer leur distribution aux contacts autorisés.
- Consulter l'historique de distribution.

Appliquer les autorisations dans les Policies, les Services et les requêtes de données. Masquer un bouton Filament ne suffit jamais à sécuriser une opération.

Les évaluations, promotions et mutations doivent être accessibles en création et en traitement aux rôles RH_MANAGER et RH_SENIOR, conformément aux règles de validation retenues.

# 8. GESTION DES EMPLOYÉS

Créer ou adapter la table `employes`.

Champs principaux :

- id ;
- matricule, unique ;
- name ;
- fonction ;
- date_naissance ;
- age, calculable ;
- date_embauche ;
- anciennete, calculable ;
- contrat ;
- statut_employe ;
- date_sortie ;
- telephone ;
- num_CNSS ;
- num_CNI ;
- niveau_etude ;
- adresse ;
- situation_familiale ;
- personne_contacter ;
- lien_parental ;
- telephone_parental ;
- formation_certificat ;
- permis_conduite ;
- categorie ;
- handicap ;
- societe_id ;
- departement_id ;
- dossier_path, si nécessaire ;
- cree_par ;
- timestamps.

Les contrats doivent pouvoir couvrir notamment :

- JOURNALIER SGTD ;
- JOURNALIER DMP ;
- PERMANENT DLS ;
- PERMANENT SGTD ;
- PERMANENT DMP ;
- FREE ZONE-PERMANENT.

Préférer des référentiels administrables plutôt que des enums difficiles à faire évoluer lorsque cela est pertinent.

La société et le département doivent être reliés par des clés étrangères, plutôt que dépendre uniquement de champs texte.

Si le site d'affectation varie dans le temps, créer une table d'historique des affectations des employés.

Prévoir un dossier numérique par employé, avec des catégories telles que :

- Personnel ;
- Contrats et attestations ;
- Diplômes et certificats ;
- Sanctions ;
- Congés et absences ;
- Bulletins de paie ;
- Formations ;
- Évaluations ;
- Promotions ;
- Mutations.

Les fichiers doivent être stockés dans un emplacement sécurisé, hors accès public direct. Les téléchargements doivent passer par des routes authentifiées et autorisées.

# 9. ARCHITECTURE DES MODULES MÉTIER

Les six modules principaux sont :

- Documents ;
- Congés ;
- Sanctions ;
- Évaluations ;
- Promotions ;
- Mutations.

**Chaque module possède ses propres types, modèles, enregistrements métier et règles de référencement.**

Ne pas fusionner toutes les tables métier en une seule table générique.

Ne pas fusionner tous les types dans une table unique si cela entraîne la perte des contraintes métier.

Pour chaque module, créer :

1. Une table de types.
2. Une table de modèles/templates.
3. Une table d'enregistrements métier.
4. Les relations vers les employés et les utilisateurs nécessaires.
5. Les règles de validation propres au module.
6. Un raccordement au workflow centralisé.
7. Un système de référence indépendant lorsque le module exige un numéro administratif.
8. Des ressources Filament.
9. Des tests fonctionnels et de sécurité.

# 10. MODULE DOCUMENTS

## 10.1. types_documents

Champs :

- id ;
- code unique ;
- name ;
- description ;
- requires_model ;
- requires_workflow ;
- is_active ;
- timestamps.

Exemples :

- Attestation de travail ;
- Certificat de travail ;
- Attestation de salaire ;
- Attestation de congé ;
- Décision administrative ;
- Certificat administratif.

## 10.2. model_documents

Champs :

- id ;
- type_document_id ;
- titre ;
- contenu, de type LONGTEXT ;
- version ;
- is_default ;
- is_active ;
- cree_par ;
- timestamps.

Le champ `contenu` contient un template HTML.

Un modèle est obligatoirement associé à un type de document précis.

Un modèle d'attestation de travail ne doit jamais être proposé pour un certificat de travail.

## 10.3. documents

Champs principaux :

- id ;
- matricule, clé étrangère vers employes.matricule ;
- type_document_id ;
- model_document_id ;
- cree_par ;
- reference, nullable au stade brouillon ;
- date_production ;
- contenu_genere ;
- fichier_path ;
- commentaire ;
- version du modèle utilisée, si nécessaire ;
- timestamps.

`contenu_genere` doit conserver une copie figée du contenu effectivement généré. Une modification ultérieure du modèle ne doit jamais altérer les documents déjà produits.

Le modèle sélectionné doit correspondre au type sélectionné.

## 10.4. Règle métier de délai

Pour les types de documents soumis à une règle de renouvellement, permettre de configurer un délai minimal de trois mois entre deux productions officielles concernant le même employé et le même type de document.

La règle doit être vérifiée côté serveur, au moment approprié du processus, et non seulement dans le formulaire.

Si le délai n'est pas respecté, bloquer la production officielle et afficher un message explicite.

Ne pas appliquer cette règle indistinctement à tous les types sans paramétrage.

# 11. MODULE CONGÉS

## 11.1. types_conges

Champs :

- id ;
- code unique ;
- name ;
- color ;
- default_days_per_year ;
- requires_justificatif ;
- requires_workflow ;
- is_active ;
- timestamps.

Prévoir notamment :

- Congé annuel ;
- Congé maladie ;
- Congé exceptionnel ;
- Congé sans solde ;
- Congé maternité ;
- Congé paternité.

## 11.2. model_conges

Champs :

- id ;
- type_conge_id ;
- titre ;
- contenu HTML ;
- version ;
- is_default ;
- is_active ;
- cree_par ;
- timestamps.

Chaque modèle de congé est associé à un seul type de congé.

## 11.3. conges

Champs principaux :

- id ;
- matricule ;
- user_id ;
- type_conge_id ;
- model_conge_id ;
- date_debut ;
- date_fin ;
- nbre_jours ;
- motif ;
- fichier_path ;
- reference ;
- timestamps.

Ajouter les champs nécessaires à la gestion des décisions et du solde de congés.

Vérifier que la date de fin est supérieure ou égale à la date de début.

Calculer le nombre de jours selon une règle configurable : jours calendaires ou jours ouvrés, avec gestion des éventuels jours fériés si nécessaire.

Prévoir le suivi des droits acquis, des jours consommés et du solde.

Le traitement d'une demande doit être raccordé au workflow centralisé.

# 12. MODULE SANCTIONS

## 12.1. types_sanctions

Champs :

- id ;
- code unique ;
- name ;
- description ;
- requires_conseil_discipline ;
- requires_workflow ;
- is_active ;
- timestamps.

Types initiaux obligatoires :

- ABSENCE_INJUSTIFIEE ;
- FAUTE_PROFESSIONNELLE ;
- FAUTE_INFRACTION.

Prévoir l'ajout ultérieur d'autres types sans modification structurelle de la base.

## 12.2. model_sanctions

Champs :

- id ;
- type_sanction_id ;
- titre ;
- contenu HTML ;
- version ;
- is_default ;
- is_active ;
- cree_par ;
- timestamps.

Chaque modèle est associé à un seul type de sanction.

## 12.3. sanctions

Champs principaux :

- id ;
- matricule ;
- type_sanction_id ;
- model_sanction_id ;
- cree_par ;
- date_sanction ;
- description ;
- motif ;
- sanction_precedente, si nécessaire ;
- sanction_appliquee, si nécessaire ;
- reference ;
- fichier_path ;
- timestamps.

Prévoir une liste administrable des niveaux de sanction : avertissement, blâme, mise à pied et autres mesures applicables.

Ne pas confondre le type de sanction avec le niveau de sanction appliqué.

L'accès au dossier disciplinaire doit être strictement contrôlé.

# 13. MODULE ÉVALUATIONS

## 13.1. types_evaluations

Champs :

- id ;
- code unique ;
- name ;
- description ;
- is_active ;
- timestamps.

Exemples :

- Évaluation annuelle ;
- Évaluation de période d'essai ;
- Évaluation de performance ;
- Évaluation exceptionnelle.

## 13.2. model_evaluations

Champs :

- id ;
- type_evaluation_id ;
- titre ;
- contenu HTML ;
- criteres, JSON ;
- version ;
- is_default ;
- is_active ;
- cree_par ;
- timestamps.

## 13.3. evaluations

Champs principaux :

- id ;
- matricule ;
- type_evaluation_id ;
- model_evaluation_id ;
- evaluateur_id ;
- date_evaluation ;
- periode_debut ;
- periode_fin ;
- note ;
- appreciation ;
- objectifs ;
- reference ;
- fichier_path ;
- timestamps.

Le système doit permettre de configurer les critères, coefficients, notes et appréciations.

L'évaluation est réalisée par un utilisateur autorisé de rôle RH_MANAGER ou RH_SENIOR.

Prévoir le workflow et la traçabilité des décisions.

# 14. MODULE PROMOTIONS

## 14.1. types_promotions

Champs :

- id ;
- code unique ;
- name ;
- description ;
- is_active ;
- timestamps.

Exemples :

- Promotion de grade ;
- Promotion de fonction ;
- Promotion salariale ;
- Promotion de grade et fonction.

## 14.2. model_promotions

Champs :

- id ;
- type_promotion_id ;
- titre ;
- contenu HTML ;
- version ;
- is_default ;
- is_active ;
- cree_par ;
- timestamps.

## 14.3. promotions

Champs principaux :

- id ;
- matricule ;
- type_promotion_id ;
- model_promotion_id ;
- cree_par ;
- ancienne_fonction ;
- nouvelle_fonction ;
- ancien_grade ;
- nouveau_grade ;
- ancien_salaire, si autorisé ;
- nouveau_salaire, si autorisé ;
- date_effet ;
- motif ;
- reference ;
- fichier_path ;
- timestamps.

Les champs relatifs au grade et au salaire doivent être adaptés au référentiel réel de DLS.

Les promotions sont initiées et traitées par RH_MANAGER ou RH_SENIOR, selon les autorisations applicables.

Aucune modification de la situation officielle de l'employé ne doit être effectuée avant la validation définitive requise.

# 15. MODULE MUTATIONS

## 15.1. types_mutations

Champs :

- id ;
- code unique ;
- name ;
- description ;
- is_active ;
- timestamps.

Exemples :

- Mutation de site ;
- Mutation de département ;
- Mutation de fonction ;
- Mutation de société ;
- Mutation de site-département.

## 15.2. model_mutations

Champs :

- id ;
- type_mutation_id ;
- titre ;
- contenu HTML ;
- version ;
- is_default ;
- is_active ;
- cree_par ;
- timestamps.

## 15.3. mutations

Champs principaux :

- id ;
- matricule ;
- type_mutation_id ;
- model_mutation_id ;
- ancien_site_id ;
- nouveau_site_id ;
- ancien_departement_id ;
- nouveau_departement_id ;
- ancienne_fonction ;
- nouvelle_fonction ;
- ancienne_societe_id ;
- nouvelle_societe_id ;
- date_effet ;
- motif ;
- cree_par ;
- reference ;
- fichier_path ;
- timestamps.

Les anciennes et nouvelles affectations doivent être conservées.

Ne jamais écraser l'historique des affectations précédentes.

Une mutation approuvée doit produire les changements opérationnels nécessaires uniquement après validation définitive, dans une transaction cohérente et traçable.

# 16. BIBLIOTHÈQUE DE MODÈLES HTML

Chaque module doit disposer de sa bibliothèque de modèles.

Tables attendues :

- model_documents ;
- model_conges ;
- model_sanctions ;
- model_evaluations ;
- model_promotions ;
- model_mutations.

Chaque modèle contient au minimum :

- titre ;
- type associé ;
- contenu HTML ;
- version ;
- état actif/inactif ;
- indicateur de modèle par défaut ;
- auteur ;
- dates de création et modification.

L'interface Filament doit permettre de créer, modifier, prévisualiser et administrer les modèles.

## 16.1. Variables dynamiques

Prévoir des variables telles que :

{{ employe.matricule }}
{{ employe.name }}
{{ employe.fonction }}
{{ employe.date_embauche }}
{{ employe.societe.name }}
{{ employe.departement.name }}

{{ document.reference }}
{{ document.date_production }}

{{ conge.date_debut }}
{{ conge.date_fin }}
{{ conge.nbre_jours }}

{{ sanction.date_sanction }}
{{ sanction.description }}

{{ evaluation.date_evaluation }}
{{ evaluation.note }}

{{ promotion.ancienne_fonction }}
{{ promotion.nouvelle_fonction }}

{{ mutation.ancienne_fonction }}
{{ mutation.nouvelle_fonction }}

{{ date_du_jour }}

Créer un service `TemplateRendererService` responsable du rendu des modèles.

Ne jamais exécuter directement du PHP arbitraire contenu dans un modèle HTML.

Échapper les valeurs provenant des utilisateurs et limiter les variables disponibles à une liste blanche autorisée.

Les documents déjà générés doivent conserver leur contenu final et la version du modèle utilisée.

# 17. RÈGLE FONDAMENTALE : RÉFÉRENCEMENT ADMINISTRATIF

Cette règle est obligatoire et constitue une exigence centrale de l'architecture DLS.

**Chaque module RH possède son propre compteur de références, indépendant des autres modules et commun à tous les types appartenant au même module.**

Le compteur est indépendant par année.

Le compteur n'est pas propre à chaque type.

## 17.1. Exemple obligatoire pour DOCUMENTS

Premier document :

- Type : Attestation de travail ;
- Référence : `0001/DA_DARH/DLS/2026`.

Deuxième document :

- Type : Certificat de travail ;
- Référence : `0002/DA_DARH/DLS/2026`.

Troisième document :

- Type : Attestation de salaire ;
- Référence : `0003/DA_DARH/DLS/2026`.

Les trois documents utilisent le même compteur du module DOCUMENTS.

Il est interdit de recommencer à 0001 à chaque changement de type de document.

Le compteur n'appartient ni au type_document, ni au modèle, ni à l'identifiant technique de la table documents.

## 17.2. Séquences indépendantes par module

Prévoir des préfixes configurables, par exemple :

- DOCUMENT : `DA_DARH/DLS` ;
- SANCTION : `DS_DARH/DLS` ;
- ÉVALUATION : `EV_DARH/DLS` ;
- PROMOTION : `PR_DARH/DLS` ;
- MUTATION : `MU_DARH/DLS` ;
- CONGÉ : `CG_DARH/DLS`.

Ces préfixes sont des valeurs de configuration initiales, modifiables par un administrateur autorisé.

Exemple :

DOCUMENT :
`0001/DA_DARH/DLS/2026`

SANCTION :
`0001/DS_DARH/DLS/2026`

PROMOTION :
`0001/PR_DARH/DLS/2026`

MUTATION :
`0001/MU_DARH/DLS/2026`

Chaque module possède sa propre séquence.

Si les références de congé ne doivent pas apparaître sur les documents administratifs, conserver au minimum un numéro de demande interne unique et configurable.

## 17.3. Table reference_sequences

Créer une table de séquences comportant au minimum :

- id ;
- module ;
- annee ;
- dernier_numero ;
- prefixe ;
- format ;
- timestamps.

Ajouter une contrainte d'unicité sur le couple module + année.

La conception doit permettre, si nécessaire, d'étendre ultérieurement le périmètre du compteur à une société ou une direction sans casser les références existantes.

Ne pas ajouter cette dimension tant qu'elle n'est pas requise par les règles métier.

## 17.4. ReferenceService

Créer :

`app/Services/ReferenceService.php`

Le service doit :

- accepter le module ;
- déterminer l'année applicable ;
- récupérer la configuration de référence ;
- verrouiller la ligne de séquence ;
- incrémenter le compteur dans une transaction ;
- produire la référence au format configuré ;
- garantir l'absence de doublons ;
- gérer correctement les requêtes concurrentes ;
- renvoyer la référence attribuée.

Utiliser `DB::transaction()` et `lockForUpdate()` avec un moteur de stockage transactionnel compatible, notamment InnoDB.

Gérer correctement le cas où la séquence n'existe pas encore, afin d'éviter deux créations concurrentes de la même séquence.

Ne pas utiliser une simple lecture suivie d'un incrément non protégé.

Ajouter une contrainte d'unicité sur la référence dans le périmètre métier approprié.

## 17.5. Attribution après approbation

Le processus attendu est :

BROUILLON
→ DOCUMENT_SOUMIS
→ EN_ATTENTE_VALIDATION
→ VALIDE
→ EN_ATTENTE_APPROBATION
→ APPROUVE
→ ATTRIBUTION DE LA RÉFÉRENCE
→ GÉNÉRATION DU PDF
→ SIGNATURE ÉLECTRONIQUE INTERNE
→ ARCHIVAGE.

La référence officielle est attribuée uniquement après l'approbation finale, pour les modules soumis à cette règle.

Un brouillon ou un dossier rejeté ne consomme aucune référence officielle.

En cas de réapprobation après correction, la référence est attribuée lorsque l'approbation définitive est obtenue.

Une référence attribuée ne doit jamais être recyclée, même si le dossier est ultérieurement annulé.

Ne pas supprimer un enregistrement approuvé simplement pour libérer un numéro.

## 17.6. Immutabilité

Une fois attribuée, la référence ne peut plus être modifiée par une action ordinaire de l'utilisateur.

Les anciens documents doivent conserver :

- leur référence ;
- leur date de production ;
- leur contenu généré ;
- la version du modèle utilisée ;
- leur historique de workflow ;
- leur fichier final.

## 17.7. Cohérence transactionnelle

L'approbation finale, l'attribution de la référence et la création des informations métier correspondantes doivent être conçues pour éviter les états incohérents.

Ne pas appeler aveuglément un générateur PDF ou envoyer un e-mail externe à l'intérieur d'une transaction SQL longue.

Enregistrer d'abord les changements métier dans une transaction, puis déclencher les opérations secondaires au moment approprié avec une stratégie de reprise en cas d'échec.

Prévoir un mécanisme permettant de relancer la génération du PDF sans générer une nouvelle référence.

# 18. WORKFLOW CENTRALISÉ ET POLYMORPHIQUE

Le workflow est centralisé, mais il ne remplace pas les tables métier.

Les documents, congés, sanctions, évaluations, promotions et mutations conservent leurs propres tables.

Créer un modèle de workflow polymorphique.

Tables recommandées :

- workflows ;
- workflow_histories ;
- workflow_definitions, si nécessaire ;
- workflow_steps, si nécessaire.

## 18.1. Table workflows

Champs recommandés :

- id ;
- workflowable_type ;
- workflowable_id ;
- cree_par ;
- valide_par ;
- approuve_par ;
- statut ;
- date_soumission ;
- date_validation ;
- date_approbation ;
- date_limite_validation ;
- date_limite_approbation ;
- commentaire ;
- timestamps.

La relation polymorphique doit utiliser `morphTo()` côté workflow et `morphOne()` ou `morphMany()` côté enregistrement métier, selon la cardinalité retenue.

Utiliser un morph map explicite pour éviter de stocker des noms de classes arbitraires dans la base.

Ne pas utiliser simultanément une clé étrangère `document_id` obligatoire et une relation polymorphique destinée à plusieurs modules.

Ne pas créer une relation circulaire inutile entre workflow et workflow_histories.

## 18.2. Statuts

Prévoir les statuts suivants :

- BROUILLON ;
- DOCUMENT_SOUMIS ;
- EN_ATTENTE_VALIDATION ;
- VALIDE ;
- EN_ATTENTE_APPROBATION ;
- APPROUVE ;
- REJETE ;
- EXPIRE.

Si nécessaire, ajouter des statuts techniques documentés sans mélanger le statut du workflow et celui de l'enregistrement métier.

## 18.3. Règles du workflow

RH_JUNIOR ou RH_OFFICER :
- crée un dossier ;
- enregistre un brouillon ;
- soumet le dossier ;
- corrige les dossiers rejetés autorisés.

RH_SENIOR :
- examine le dossier ;
- valide ou rejette ;
- ajoute un commentaire obligatoire en cas de rejet.

RH_MANAGER :
- examine les dossiers validés ;
- approuve ou rejette ;
- déclenche le processus de finalisation après approbation.

Les évaluations, promotions et mutations doivent suivre leurs règles de rôles et de traitement dédiées, tout en utilisant le moteur centralisé.

Le créateur ne doit pas pouvoir contourner les étapes requises.

Un utilisateur ne doit pas pouvoir approuver arbitrairement son propre dossier si les règles de séparation des responsabilités l'interdisent.

**RH_MANAGER possède les mêmes droits que ADMIN sur l'ensemble du système, y compris l'administration, l'historique et tous les périmètres.** La séparation des tâches (créateur ≠ validateur ≠ approbateur) reste appliquée à tous, y compris à ces deux rôles.

## 18.4. Délais

Prévoir :

- 48 heures pour la validation Senior RH ;
- 48 heures pour l'approbation Manager RH.

Ces délais doivent être configurables.

Créer les mécanismes de détection des dossiers en retard et les notifications correspondantes.

Ne pas dépendre uniquement de l'ouverture du tableau de bord pour vérifier les délais : utiliser des tâches planifiées Laravel ou un mécanisme de traitement périodique adapté à l'hébergement.

## 18.5. Historique

Créer une table `workflow_histories` contenant :

- id ;
- workflow_id ;
- user_id ;
- etape ;
- statut ;
- commentaire ;
- date_action ;
- timestamps.

Chaque action significative doit produire un historique :

- création ;
- modification ;
- soumission ;
- validation ;
- rejet ;
- approbation ;
- attribution de référence ;
- génération PDF ;
- signature ;
- archivage.

L'historique ne doit pas pouvoir être modifié librement par les utilisateurs ordinaires.

# 19. MODULE PAYLIST — IMPORTATION ET DISTRIBUTION DES BULLETINS DE PAIE

Remplacer complètement l'ancien concept de table `paies` par le module Paylist.

Le module doit gérer l'importation des bulletins de paie exportés depuis Sage et leur distribution aux responsables des départements des sociétés clientes.

**Le gestionnaire de paie est un utilisateur interne DLS. Les destinataires des bulletins sont des contacts externes, pas nécessairement des utilisateurs de l'application.**

## 19.1. Table contacts

Créer une table `contacts` contenant notamment :

- id ;
- nom ;
- prenom, si nécessaire ;
- fonction ;
- email ;
- telephone ;
- societe_id ;
- departement_id, nullable ;
- is_active ;
- timestamps.

Les contacts peuvent représenter les chefs de département de SGTD, DMP ou IDIPO.

Prévoir une interface Filament pour leur gestion.

Ne pas créer automatiquement un compte `users` pour chaque contact.

## 19.2. Table paylist_imports

Créer une table pour les lots d'importation :

- id ;
- nom_fichier ;
- chemin_fichier ;
- mois ;
- annee ;
- importe_par ;
- date_import ;
- statut_import ;
- total_lignes ;
- lignes_importees ;
- lignes_rejetees ;
- rapport_erreurs ;
- timestamps.

## 19.3. Table paylists

Créer une table pour les bulletins individuels :

- id ;
- paylist_import_id ;
- matricule ;
- mois ;
- annee ;
- fichier_path ;
- empreinte_fichier ;
- statut ;
- importe_par ;
- timestamps.

Prévoir une contrainte d'unicité adaptée pour éviter l'importation accidentelle du même bulletin pour le même employé et la même période.

Le système doit prendre en charge les formats d'export réellement disponibles depuis Sage : CSV, XLSX, PDF ou archive de PDF, selon le processus source confirmé.

Ne pas supposer que Sage fournit une API disponible.

Commencer par un import de fichiers avec aperçu, validation et rapport d'erreurs.

## 19.4. Table paylist_distributions

Créer une table de suivi des distributions :

- id ;
- paylist_id ;
- contact_id ;
- distribue_par ;
- date_distribution ;
- canal ;
- statut ;
- preuve_distribution, nullable ;
- commentaire ;
- timestamps.

Ajouter une contrainte empêchant les doublons de distribution lorsque la même opération est rejouée involontairement.

## 19.5. Processus d'importation

Le gestionnaire de paie :

1. sélectionne le fichier exporté depuis Sage ;
2. sélectionne le mois et l'année ;
3. lance l'analyse du fichier ;
4. consulte l'aperçu ;
5. vérifie les matricules ;
6. identifie les doublons ;
7. confirme l'importation ;
8. consulte le rapport final.

Les lignes invalides ne doivent pas bloquer silencieusement l'importation de toutes les lignes valides.

Enregistrer les erreurs et permettre la correction ou la reprise.

## 19.6. Distribution

Le gestionnaire de paie sélectionne :

- une période de paie ;
- une société cliente ;
- un ou plusieurs contacts autorisés ;
- les bulletins à distribuer.

L'application permet ensuite d'effectuer la distribution selon le canal configuré.

Si la distribution s'effectue par e-mail, utiliser un mécanisme d'envoi sécurisé avec journalisation.

Ne jamais envoyer un bulletin à un contact dont l'association avec la société ou le périmètre concerné n'a pas été vérifiée.

Les contacts externes ne doivent accéder qu'aux bulletins qui leur sont explicitement destinés.

Ne pas considérer l'adresse e-mail comme une preuve suffisante de l'identité du destinataire.

Prévoir un statut d'envoi, un suivi des erreurs et une procédure de nouvelle tentative.

# 20. NOTIFICATIONS

Créer un système de notifications pour les utilisateurs internes.

Prévoir notamment :

- nouveau dossier soumis ;
- dossier en attente de validation ;
- dossier validé et en attente d'approbation ;
- dossier rejeté ;
- dossier approuvé ;
- délai de traitement dépassé ;
- nouvelle tâche attribuée ;
- importation de paie terminée ;
- erreur d'importation ;
- échec de distribution.

Utiliser les notifications Laravel et les mécanismes Filament compatibles.

Prévoir les canaux appropriés : base de données et e-mail si configuré.

Ne pas déclarer qu'un e-mail a été livré simplement parce qu'il a été mis en file d'attente.

# 21. JOURNAL D'AUDIT

Créer une table `logs_actions` comportant :

- id ;
- user_id ;
- action ;
- description ;
- entite_type, si nécessaire ;
- entite_id, si nécessaire ;
- adresse IP, si justifiée et conformément aux règles de sécurité ;
- timestamps.

Tracer les opérations importantes :

- création et modification des dossiers ;
- changements de permissions ;
- validations et approbations ;
- références attribuées ;
- imports et distributions de paie ;
- génération de PDF ;
- accès sensibles, selon les règles retenues.

Ne jamais enregistrer les mots de passe, jetons d'accès, contenus confidentiels de bulletins ou données secrètes dans les logs.

# 22. GÉNÉRATION PDF ET SIGNATURE ÉLECTRONIQUE INTERNE

Après approbation définitive :

1. générer ou finaliser la référence ;
2. rendre le modèle HTML avec les données autorisées ;
3. conserver une copie figée du contenu ;
4. générer le PDF ;
5. intégrer le bloc de signature interne autorisé ;
6. archiver le fichier ;
7. mettre à jour le statut ;
8. journaliser l'opération.

La signature doit identifier l'approbateur, son rôle, la date et l'identifiant du dossier.

Ne pas présenter une image de signature ou un nom imprimé comme une signature électronique qualifiée au sens juridique.

Si une signature électronique juridiquement qualifiée est exigée, prévoir une intégration distincte avec un prestataire approprié.

La génération PDF doit être idempotente : relancer une opération échouée ne doit pas produire un nouveau numéro administratif.

# 23. INTERFACES FILAMENT

Créer des ressources Filament pour :

- Users ;
- Contacts ;
- Sociétés ;
- Sites d'affectation ;
- Départements ;
- Site-départements ;
- Affectations utilisateurs ;
- Employés ;
- Types et modèles de documents ;
- Documents ;
- Types et modèles de congés ;
- Congés ;
- Types et modèles de sanctions ;
- Sanctions ;
- Types et modèles d'évaluations ;
- Évaluations ;
- Types et modèles de promotions ;
- Promotions ;
- Types et modèles de mutations ;
- Mutations ;
- Imports Paylist ;
- Bulletins Paylist ;
- Distributions Paylist ;
- Workflows ;
- Historique des workflows ;
- Journaux d'audit ;
- Notifications.

## 23.1. Formulaires dynamiques

Dans les formulaires de création :

- sélectionner l'employé ;
- sélectionner le type métier ;
- filtrer automatiquement les modèles disponibles selon le type ;
- afficher les informations utiles de l'employé ;
- prévisualiser le modèle ;
- saisir les données métier nécessaires ;
- joindre les pièces justificatives ;
- enregistrer en brouillon ou soumettre selon les permissions.

Exemple obligatoire :

Type de document = Attestation de travail.

La liste des modèles ne doit afficher que les modèles associés à ce type.

Même comportement pour les congés, sanctions, évaluations, promotions et mutations.

Ne pas permettre au navigateur de forcer un identifiant de modèle incompatible.

La cohérence doit également être vérifiée côté serveur.

## 23.2. Actions de workflow

Prévoir les actions :

- Soumettre ;
- Valider ;
- Rejeter ;
- Approuver ;
- Consulter l'historique ;
- Prévisualiser ;
- Télécharger le PDF final lorsque l'accès est autorisé.

Les boutons doivent apparaître uniquement pour les rôles autorisés et les statuts compatibles.

Le service métier doit contrôler à nouveau les permissions et le statut au moment de l'action.

## 23.3. Historique visuel

Pour chaque enregistrement métier, afficher une timeline :

- date et heure ;
- utilisateur ;
- rôle ;
- étape ;
- statut ;
- commentaire ;
- durée de traitement ;
- indicateur de retard, si applicable.

# 24. TABLEAUX DE BORD

Créer un tableau de bord général avec des indicateurs utiles :

- nombre d'employés actifs ;
- effectifs par société ;
- effectifs par département et site ;
- répartition des contrats ;
- documents en attente de validation ;
- documents en attente d'approbation ;
- documents approuvés ;
- documents rejetés ;
- demandes de congés en attente ;
- sanctions en cours de traitement ;
- évaluations à effectuer ;
- promotions et mutations en cours ;
- bulletins importés ;
- distributions réalisées et en échec ;
- dossiers en retard.

Les indicateurs doivent respecter le périmètre d'accès de l'utilisateur connecté.

Ne jamais calculer les statistiques à partir de données que l'utilisateur n'est pas autorisé à consulter.

# 25. SÉCURITÉ ET PROTECTION DES DONNÉES

Appliquer systématiquement :

- validation serveur ;
- Policies Laravel ;
- contrôle des périmètres société/site/département ;
- protection CSRF ;
- protection contre les accès directs non autorisés ;
- stockage privé des fichiers ;
- téléchargement authentifié ;
- validation des types et tailles de fichiers ;
- requêtes Eloquent ou SQL paramétrées ;
- échappement des données dans les templates ;
- protection des opérations sensibles ;
- journalisation des actions importantes ;
- limitation des tentatives de connexion ;
- sauvegardes et stratégie de restauration.

Les clés étrangères et contraintes SQL doivent compléter les contrôles applicatifs.

Ne pas utiliser uniquement les filtres de formulaires Filament pour assurer la sécurité des données.

Les bulletins de paie et dossiers disciplinaires doivent bénéficier de contrôles d'accès renforcés.

# 26. ARCHITECTURE DES SERVICES

Créer des services métier dédiés, selon les besoins :

- `WorkflowService` ;
- `ReferenceService` ;
- `TemplateRendererService` ;
- `DocumentGenerationService` ;
- `PdfGenerationService` ;
- `PaylistImportService` ;
- `PaylistDistributionService` ;
- `NotificationService`, si nécessaire ;
- services de traitement des congés, sanctions, évaluations, promotions et mutations.

Les ressources Filament doivent rester responsables de l'interface utilisateur, et non porter toute la logique métier.

Les services doivent appliquer les règles métier, vérifier les autorisations appropriées et utiliser des transactions lorsqu'une opération implique plusieurs écritures liées.

Éviter les contrôleurs ou ressources trop volumineux.

Utiliser des Jobs Laravel pour les tâches longues : importation de fichiers volumineux, génération PDF en masse, envoi d'e-mails et distribution des bulletins.

Prévoir une gestion d'erreurs explicite et des possibilités de reprise.

# 27. ORGANISATION DES MIGRATIONS

Respecter l'ordre des dépendances.

Ordre indicatif :

1. societes ;
2. sites_affectation ;
3. departements ;
4. site_departements ;
5. users et rôles ;
6. affectations utilisateurs ;
7. contacts ;
8. employes ;
9. historique des affectations des employés ;
10. types_documents ;
11. model_documents ;
12. documents ;
13. types_conges ;
14. model_conges ;
15. conges ;
16. types_sanctions ;
17. model_sanctions ;
18. sanctions ;
19. types_evaluations ;
20. model_evaluations ;
21. evaluations ;
22. types_promotions ;
23. model_promotions ;
24. promotions ;
25. types_mutations ;
26. model_mutations ;
27. mutations ;
28. paylist_imports ;
29. paylists ;
30. paylist_distributions ;
31. workflows ;
32. workflow_histories ;
33. reference_sequences ;
34. notifications, selon le schéma Laravel retenu ;
35. logs_actions ;
36. tables complémentaires nécessaires.

L'ordre définitif doit être ajusté en fonction des dépendances réelles et des migrations existantes.

Ne pas créer de références étrangères vers des tables qui n'existent pas encore.

Éviter les relations circulaires inutiles.

Si le projet est déjà utilisé en production, ne jamais supprimer ou recréer la base sans stratégie de migration et sauvegarde validée.

# 28. MODÈLES ELOQUENT ET RELATIONS

Créer tous les modèles et leurs relations explicites.

Exemples :

- Document appartient à un Employe, un TypeDocument et un ModelDocument.
- ModelDocument appartient à un TypeDocument.
- Conge appartient à un Employe, un TypeConge et un ModelConge.
- Sanction appartient à un Employe, un TypeSanction et un ModelSanction.
- Evaluation appartient à un Employe, un TypeEvaluation et un ModelEvaluation.
- Promotion appartient à un Employe, un TypePromotion et un ModelPromotion.
- Mutation appartient à un Employe, un TypeMutation et un ModelMutation.
- Paylist appartient à un lot d'importation et à un employé.
- PaylistDistribution appartient à un bulletin et à un contact.
- Workflow appartient polymorphiquement à un enregistrement métier.

Créer les relations inverses et les méthodes d'accès nécessaires.

Utiliser des casts appropriés pour les dates, booléens, décimaux et JSON.

Ne pas exposer inutilement des champs sensibles dans les réponses ou exports.

# 29. SEEDERS INITIAUX

Créer des seeders pour les référentiels initiaux.

Types de documents :
- Attestation de travail ;
- Certificat de travail ;
- Attestation de salaire ;
- Attestation de congé ;
- Décision administrative.

Types de congés :
- Congé annuel ;
- Congé maladie ;
- Congé exceptionnel ;
- Congé sans solde ;
- Congé maternité ;
- Congé paternité.

Types de sanctions :
- Absence injustifiée ;
- Faute professionnelle ;
- Faute infraction.

Types d'évaluations :
- Évaluation annuelle ;
- Évaluation de période d'essai ;
- Évaluation de performance ;
- Évaluation exceptionnelle.

Types de promotions :
- Promotion de grade ;
- Promotion de fonction ;
- Promotion salariale ;
- Promotion de grade et fonction.

Types de mutations :
- Mutation de site ;
- Mutation de département ;
- Mutation de fonction ;
- Mutation de société ;
- Mutation site-département.

Créer les configurations initiales de références par module.

Ne pas créer de compte administrateur avec un mot de passe faible ou codé en dur dans les seeders de production.

Prévoir une méthode sécurisée pour créer le premier administrateur.

# 30. TESTS AUTOMATISÉS OBLIGATOIRES

Créer des tests couvrant au minimum les scénarios suivants.

## 30.1. Autorisations

- Un RH_JUNIOR ne peut pas consulter un employé hors de son périmètre.
- Un utilisateur ne peut pas modifier les affectations pour élargir lui-même ses droits.
- Un RH_SENIOR peut valider un dossier autorisé.
- Un RH_MANAGER peut approuver un dossier autorisé.
- Un GESTIONNAIRE_PAIE peut importer les bulletins.
- Un contact externe ne bénéficie pas automatiquement d'un compte utilisateur.

## 30.2. Types et modèles

- Un document ne peut pas utiliser un modèle d'un autre type.
- Un congé ne peut pas utiliser un modèle incompatible.
- Une sanction ne peut pas utiliser un modèle incompatible.
- Les formulaires filtrent les modèles correctement.
- Les contrôles côté serveur empêchent les incohérences.

## 30.3. Références

Tester impérativement :

1. Une attestation de travail reçoit `0001/DA_DARH/DLS/2026`.
2. Un certificat de travail créé ensuite reçoit `0002/DA_DARH/DLS/2026`.
3. Une attestation de salaire créée ensuite reçoit `0003/DA_DARH/DLS/2026`.
4. Une sanction utilise son propre compteur.
5. Le compteur redémarre à 0001 pour une nouvelle année.
6. Un brouillon ne consomme aucune référence.
7. Un dossier rejeté ne consomme aucune référence.
8. Une nouvelle tentative de génération PDF ne change pas la référence.
9. Deux approbations concurrentes ne peuvent pas attribuer le même numéro.
10. Une référence attribuée ne peut pas être modifiée par une action ordinaire.
11. Une référence annulée n'est pas réutilisée.

Utiliser une horloge contrôlée dans les tests pour simuler les années.

## 30.4. Workflow

- Les transitions de statut sont contrôlées.
- Un dossier ne peut pas passer directement de BROUILLON à APPROUVE en contournant les étapes obligatoires.
- Un rejet conserve l'historique.
- Chaque action significative génère une entrée d'historique.
- Les délais sont détectés correctement.
- Les opérations concurrentes ne créent pas de transitions contradictoires.

## 30.5. Paylist

- Importation d'un fichier valide.
- Détection d'un matricule inconnu.
- Détection des doublons.
- Gestion des fichiers invalides.
- Distribution au bon contact.
- Blocage d'un destinataire non autorisé.
- Journalisation des échecs et des nouvelles tentatives.
- Aucune exposition d'un bulletin à un autre contact.

# 31. LIVRABLES ATTENDUS

Tu dois livrer une application réellement exploitable comprenant :

1. Les migrations Laravel.
2. Les modèles Eloquent.
3. Les relations et casts.
4. Les seeders.
5. Les Policies et règles d'autorisation.
6. Les services métier.
7. Le moteur de workflow.
8. Le service de référencement.
9. Le moteur de rendu des templates.
10. La génération PDF.
11. Les interfaces Filament.
12. Le module Paylist.
13. Le module Contacts.
14. Les notifications.
15. Les journaux d'audit.
16. Les tableaux de bord.
17. Les tests automatisés.
18. Un fichier `.env.example` correctement documenté, sans secret réel.
19. Une documentation d'installation.
20. Une documentation utilisateur.
21. Une documentation technique de l'architecture.
22. Une procédure de sauvegarde et de restauration.
23. Une procédure de déploiement sur le serveur cible.

Ne pas déclarer le système prêt pour la production tant que les contrôles essentiels de sécurité, les tests et les procédures de sauvegarde n'ont pas été vérifiés.

# 32. MÉTHODE D'EXÉCUTION DANS CLAUDE CODE

Procède dans l'ordre suivant.

## Phase 1 — Audit du projet

- Inspecter les fichiers existants.
- Identifier les dépendances et les fonctionnalités opérationnelles.
- Examiner les migrations et détecter les incohérences.
- Produire un plan d'action priorisé.
- Signaler les risques de perte de données.

## Phase 2 — Fondations

- Finaliser les sociétés, sites et départements.
- Finaliser les utilisateurs, rôles et affectations.
- Finaliser les contacts et employés.
- Mettre en place les Policies.

## Phase 3 — Référentiels et modèles

- Créer les six familles de types.
- Créer les six familles de modèles.
- Créer les enregistrements métier.
- Implémenter les validations type/modèle.
- Ajouter les seeders.

## Phase 4 — Références et workflow

- Créer les tables de séquences.
- Implémenter ReferenceService.
- Implémenter WorkflowService.
- Implémenter l'historique et la timeline.
- Tester les transactions et les accès concurrents.

## Phase 5 — Interfaces métier

- Créer les ressources Filament.
- Implémenter les formulaires dynamiques.
- Ajouter les actions de workflow.
- Appliquer les autorisations et les filtres par périmètre.

## Phase 6 — Documents

- Implémenter les templates HTML.
- Créer le moteur de rendu.
- Générer les PDF.
- Intégrer la signature interne.
- Garantir l'immutabilité des documents finalisés.

## Phase 7 — Paylist

- Créer les imports.
- Valider les fichiers.
- Gérer les bulletins individuels.
- Gérer les contacts destinataires.
- Implémenter la distribution et le suivi.

## Phase 8 — Évaluations, promotions et mutations

- Finaliser les formulaires.
- Appliquer les règles métier.
- Implémenter les transitions de workflow.
- Mettre à jour les historiques uniquement après les décisions requises.

## Phase 9 — Notifications et tableaux de bord

- Ajouter les notifications.
- Ajouter les tâches planifiées.
- Ajouter les indicateurs.
- Vérifier les filtres d'accès.

## Phase 10 — Tests et validation

- Exécuter les migrations dans un environnement de test.
- Exécuter l'ensemble des tests.
- Corriger les erreurs.
- Vérifier les autorisations.
- Vérifier les références.
- Vérifier les fichiers générés.
- Documenter les éventuelles dépendances externes non configurées.

Après chaque phase, indique les fichiers créés ou modifiés, les tests exécutés, leurs résultats et les éventuels points bloquants.

Ne passe pas à une phase dépendante tant que les erreurs critiques de la phase précédente ne sont pas résolues.

Si une information manque, choisis une solution prudente et cohérente avec les règles ci-dessus, documente ton hypothèse et continue lorsque cela est possible. Ne bloque pas inutilement tout le développement pour des détails non essentiels.

Ne lance aucune opération destructive sur une base existante sans sauvegarde et confirmation explicite.

# 33. CRITÈRES D'ACCEPTATION FINAUX

Le projet sera considéré comme fonctionnel lorsque les conditions suivantes seront réunies :

- L'application démarre correctement.
- Les migrations passent sur une base de test propre.
- Les utilisateurs ne peuvent accéder qu'à leur périmètre autorisé.
- Chaque module dispose de ses types et modèles.
- Les modèles sont filtrés selon le type choisi.
- Les enregistrements métier utilisent le workflow centralisé.
- Les étapes de validation et d'approbation sont contrôlées côté serveur.
- Les historiques sont consultables.
- Les références sont attribuées après approbation selon les règles définies.
- Les compteurs sont indépendants par module et par année.
- Les références restent communes aux différents types d'un même module.
- Les PDF conservent leur référence et leur contenu historique.
- Les bulletins peuvent être importés et distribués aux contacts autorisés.
- Les évaluations, promotions et mutations sont traitées par les rôles autorisés.
- Les notifications et les journaux fonctionnent.
- Les tests critiques passent.
- La documentation permet l'installation et la maintenance de l'application.

# 34. INSTRUCTION FINALE

Commence maintenant par auditer le projet Laravel ouvert dans Claude Code.

Présente brièvement son état réel et ton plan d'exécution, puis commence à implémenter les fonctionnalités par phases.

Ne te contente pas de me répondre avec du code théorique ou un nouveau cahier des charges.

Crée et modifie les fichiers réellement nécessaires, vérifie leur cohérence avec le projet, exécute les tests appropriés et corrige les erreurs rencontrées.

Privilégie une architecture maintenable, sécurisée, testable et adaptée aux processus réels de DLS.

**RÈGLE NON NÉGOCIABLE : le workflow est centralisé, les tables métier restent distinctes, les types et modèles sont propres à chaque module, et chaque module dispose de son propre compteur de références annuel commun à tous ses types.**

Exemple de résultat obligatoire :

DOCUMENT — Attestation de travail :
`0001/DA_DARH/DLS/2026`

DOCUMENT — Certificat de travail :
`0002/DA_DARH/DLS/2026`

DOCUMENT — Attestation de salaire :
`0003/DA_DARH/DLS/2026`

SANCTION :
`0001/DS_DARH/DLS/2026`

Ces références doivent être produites par le service métier de référencement, jamais par une simple incrémentation de l'identifiant technique de la base de données.

Construis l'application en respectant cette règle dès la conception initiale.
