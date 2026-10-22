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
