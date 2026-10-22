# Installation

## Prérequis

- PHP 8.2+ avec extensions : `mbstring`, `pdo_mysql`, `zip` (import Paylist), `gd`, `intl`, `fileinfo`, `openssl`.
- Composer 2, Node 20+ et npm.
- MySQL 8 ou MariaDB 10.4+ (XAMPP) avec le moteur **InnoDB** (obligatoire : verrous de références et de workflow).

## Étapes

1. Récupérer le code dans `C:\xampp\htdocs\eco-rh-cms`.
2. Créer la base : `CREATE DATABASE eco_rh_dls CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;`
3. `composer setup` (installe les dépendances, crée `.env`, génère la clé, migre, compile les assets).
4. Ouvrir `.env` et renseigner `DB_*`, `APP_URL`, `MAIL_*` (voir ci-dessous).
5. Charger les référentiels : `php artisan db:seed`
   (sociétés/sites/départements de départ, types et modèles des six modules, séquences de références
   `DA_DARH/DLS`, `CG_DARH/DLS`, `DS_DARH/DLS`, `EV_DARH/DLS`, `PR_DARH/DLS`, `MU_DARH/DLS`).
6. Créer le premier administrateur (mot de passe saisi en interactif, jamais codé en dur) :
   `php artisan app:create-admin "Prénom Nom" admin@exemple.dj`
7. Lancer : `composer dev` (développement) ou voir [DEPLOIEMENT.md](DEPLOIEMENT.md) (production).

## Variables propres à l'application

| Variable | Défaut | Rôle |
|---|---|---|
| `RH_DELAI_VALIDATION_HEURES` | 48 | Délai de validation Senior RH |
| `RH_DELAI_APPROBATION_HEURES` | 48 | Délai d'approbation Manager RH |
| `RH_SEPARATION_DES_TACHES` | true | Le créateur ne valide/approuve pas son dossier |
| `RH_CONGES_MODE` | calendaires | `calendaires` ou `ouvres` (jours fériés dans `config/rh.php`) |
| `RH_NOTIFICATIONS_MAIL` | false | Envoi d'e-mails en plus des notifications internes |
| `RH_PAYLIST_MAX_FICHIER_KO` / `_MAX_ENTREES` / `_MAX_TOTAL_MO` | 5120 / 2000 / 200 | Limites d'import Paylist |
| `RH_PDF_DISK`, `RH_PAYLIST_DISK` | local | Disque **privé** des PDF et bulletins |

## Messagerie

Par défaut `MAIL_MAILER=log` (aucun envoi réel). Pour la distribution de bulletins par e-mail,
configurer un serveur SMTP réel puis tester avec un contact de confiance avant tout usage. Le statut
« Envoyé » signifie « remis au serveur de messagerie », pas « lu par le destinataire ».

## Vérification

```bash
php artisan test                 # doit être entièrement vert
php artisan schedule:list        # workflow:check-delays (horaire), rh:appliquer-effets (00:10)
php artisan about                # APP_DEBUG doit être false en production
```
