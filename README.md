# ECOSYSTEME RH DLS

Digitalisation du département Ressources Humaines de **DLS — Djibouti Labour Service** :
dossiers employés, documents administratifs, congés, sanctions, évaluations, promotions, mutations,
bulletins de paie (Paylist), workflow de validation, références officielles, PDF signés, notifications,
journal d'audit et tableaux de bord.

Stack : Laravel 12 · Filament 5 · Livewire 4 · PHP 8.2+ · MySQL/InnoDB (XAMPP).

## Documentation

| Document | Contenu |
|---|---|
| [docs/INSTALLATION.md](docs/INSTALLATION.md) | Prérequis, installation, premier administrateur, jeux de données initiaux |
| [docs/UTILISATEUR.md](docs/UTILISATEUR.md) | Guide par rôle : workflow, documents, Paylist, notifications |
| [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md) | Architecture, services, règles métier, sécurité, hypothèses |
| [docs/SAUVEGARDE_RESTAURATION.md](docs/SAUVEGARDE_RESTAURATION.md) | Sauvegarde et restauration (base + fichiers privés) |
| [docs/DEPLOIEMENT.md](docs/DEPLOIEMENT.md) | Mise en production, worker, planificateur, durcissement, checklist |
| [CLAUDE.md](CLAUDE.md) | État technique par phase et cahier des charges |

## Démarrage rapide

```bash
composer setup                       # dépendances, .env, clé, migrations, assets
php artisan db:seed                  # référentiels, types, modèles, séquences
php artisan app:create-admin "Nom" admin@exemple.dj
composer dev                         # serveur + file + logs + vite
```

Panneau unique : `/rh` (équipes RH, paie et administrateurs).

## Tests

```bash
composer test                        # SQLite en mémoire (rapide)
php artisan test --group=mysql       # concurrence des références (MySQL, base eco_rh_dls_test)
vendor/bin/pint                      # style
```

## Modèles RH unifiés (46 actifs)

### Design

- En-tête bleu `#002060` pleine largeur et pied de page : `resources/views/pdf/dossier.blade.php` (police `DejaVu Sans`, 12 px, identique pour tous les modules).
- Signature DARH : le marqueur `[[SIGNATURE_DARH]]` est remplacé par l'image de signature (hauteur 120 px) dans `app/Services/DocumentGenerationService.php`.

### Répartition des modèles actifs

| Table | Actifs |
|---|---|
| `model_documents` | 5 |
| `model_conges` | 8 |
| `model_sanctions` | 9 |
| `model_evaluations` | 6 |
| `model_promotions` | 8 |
| `model_mutations` | 10 |
| **Total** | **46** |

### Points notables

- **Documents** : attestation de travail, attestation de mission de travail, certificat de travail (`{{DATE_SORTIE}}`, `{{NUM_CNSS}}`), attestation de salaire v10 (`{{MONTANT_SALAIRE}}` saisi à la main), certificat administratif v2 (`{{ANCIENNETE}}`, `{{ANCIENNETE_MOIS}}`, `{{CATEGORIE}}`, `{{SALAIRE_BRUT}}`, `{{MONTANT_SAISIE_MANUELLE_1..4}}`).
- **Congés** : « Titre de congé » v2 (annuel et maternité, par défaut) avec l'article 103 du Code du travail ; `{{JOURS_PRIS}}` et `{{JOURS_RESTANT}}` saisis à la main ; absence en heures avec `{{NOMBRE_HEURES}}`.
- **Sanctions** : « Décision sanctionnant un agent » est le modèle par défaut des 3 types (`{{MOTIF}}`, `{{DATE_MOTIF}}`, `{{SANCTION_PRECEDENTE}}`, `{{SANCTION_SUIVANTE}}`). L'ancien « Décision de sanction » v1 est inactif.
- **Évaluations, promotions, mutations** : mêmes en-tête, pied et `[[SIGNATURE_DARH]]`.

### Variables

- Liste blanche dans `app/Services/TemplateRendererService.php` ; champs saisis à la main dans `app/Services/ChampsSaisieService.php`.
- `{{ANCIENNETE}}` = années, `{{ANCIENNETE_MOIS}}` = mois restants.

### Migrations

- `2026_10_25_080000` et `2026_10_25_090000` : structure et unification des modèles (d'après les modèles Word).
- `2026_10_25_100000` : textes complets (certificat administratif, titre de congé, attestation de salaire).
- `2026_10_25_110000` : désactivation des anciens modèles en doublon (sans suppression ; ignorée en environnement de test).
- `2026_10_25_120000` et `2026_10_25_130000` : « Titre de congé » par défaut (annuel, maternité).

### Références

La référence officielle n'est attribuée qu'à l'approbation finale (voir `docs/ARCHITECTURE.md`).
