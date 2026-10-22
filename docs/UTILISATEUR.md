# Guide utilisateur

Connexion : `/rh` (équipes RH et paie) (les administrateurs y trouvent aussi le groupe Administration). Cinq tentatives de connexion par minute au maximum.
La cloche en haut de l'écran affiche vos notifications (dossier à valider, rejet, approbation, retard…).

## Rôles

| Rôle | Peut |
|---|---|
| **RH_JUNIOR / RH_OFFICER** | Créer des brouillons (documents, congés, sanctions), corriger les dossiers rejetés, soumettre |
| **RH_SENIOR** | Valider ou rejeter (commentaire obligatoire au rejet) ; créer évaluations, promotions, mutations |
| **RH_MANAGER** | Approuver ou rejeter ; créer évaluations, promotions, mutations ; relancer un PDF |
| **GESTIONNAIRE_PAIE** | Importer et distribuer les bulletins (Paylist), gérer les contacts |
| **ADMIN** | Référentiels, types, modèles, utilisateurs et périmètres, journal d'audit. Pas d'accès aux dossiers RH ni aux bulletins |

Vous ne voyez que les employés de **vos périmètres** (site/département) définis par un administrateur.
Vous ne pouvez pas traiter un dossier que vous avez créé (ni, pour l'approbation, que vous avez validé).

## Cycle d'un dossier

`BROUILLON → soumis → en attente de validation (Senior) → validé → en attente d'approbation (Manager) → APPROUVÉ`
(ou `REJETÉ`, corrigeable puis re-soumis).

1. Créer le dossier : choisir l'employé, le **type**, puis le **modèle** (seuls les modèles du type sont proposés).
   « Enregistrer en brouillon » ou « Créer et soumettre ».
2. Le Senior valide ou rejette ; le Manager approuve ou rejette. Délai : 48 h par étape (relance automatique).
3. À l'approbation, la **référence officielle** est attribuée (ex. `0001/DA_DARH/DLS/2026`) : un compteur
   par module et par année, commun à tous les types du module. Brouillons et rejets n'en consomment pas.
4. Le PDF est ensuite généré en arrière-plan avec le bloc de signature interne (approbateur, date, SHA-256)
   puis archivé. En cas d'échec : action **Relancer le PDF** (même référence).
5. La timeline du dossier affiche chaque étape, l'auteur, le rôle, la durée et les retards.

La signature interne n'est **pas** une signature électronique qualifiée.

## Règles par module

- **Documents** : un délai minimal (3 mois) entre deux productions du même type pour un employé peut être
  configuré par type (renouvellement) ; il est contrôlé à la soumission et à l'approbation.
- **Congés** : nombre de jours calculé par le serveur (calendaires ou ouvrés) ; justificatif selon le type.
- **Sanctions** : accès renforcé (Junior = ses propres dossiers uniquement).
- **Évaluations** : saisir la note de chaque critère ; la note finale pondérée est calculée par le serveur.
- **Promotions / Mutations** : champs obligatoires selon le type. L'employé n'est modifié **qu'après
  approbation** et à la date d'effet ; l'historique des affectations est conservé. Une mutation dont
  l'employé a changé d'affectation entre-temps est refusée : en créer une nouvelle.

## Paylist (gestionnaire de paie)

1. **Imports → Nouvel import** : choisir mois, année et le fichier Sage (archive ZIP de PDF nommés
   `MATRICULE.pdf`, ou un PDF unique).
2. Lire l'**aperçu** (matricule inconnu, doublon, fichier invalide) puis **Confirmer l'import**.
   Les lignes valides sont importées, les autres listées dans le rapport ; « Reprendre l'import » rejoue les rejets.
3. **Bulletins** : sélectionner des bulletins → **Distribuer** → société, contact, canal (e-mail ou remise
   en main propre avec n° de bordereau). Le contact doit appartenir à la société (et au département) de l'employé.
4. **Distributions** : suivre les statuts ; **Relancer** une distribution en échec.

Les contacts sont des destinataires externes : aucun compte utilisateur n'est créé pour eux.

## Administration (ADMIN, panneau /rh)

Référentiels (sociétés, sites, départements, contrats), types et modèles HTML de chaque module (variables
autorisées uniquement, par ex. `{{ employe.name }}`, `{{ document.reference }}`), niveaux de sanction,
utilisateurs (rôles et périmètres), **Journal d'audit** (lecture seule).
