# Déploiement sur le serveur cible (intranet / XAMPP)

## Configuration web

- Le `DocumentRoot` (ou l'alias Apache) doit pointer sur `public/` — **jamais** sur la racine du projet.
- Activer `mod_rewrite`, HTTPS recommandé même en intranet (certificat interne).
- `.env` de production :

```
APP_ENV=production
APP_DEBUG=false
APP_URL=https://rh.exemple.local
LOG_LEVEL=warning
SESSION_SECURE_COOKIE=true
SESSION_DRIVER=database
QUEUE_CONNECTION=database
CACHE_STORE=database
```

## Procédure de mise à jour

```bash
php artisan down
# sauvegarde préalable : voir SAUVEGARDE_RESTAURATION.md
git pull   # ou copie des fichiers
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan migrate --force
php artisan optimize          # config, routes, vues, événements
php artisan queue:restart
php artisan up
```

## Processus permanents

1. **Worker de file** (PDF, e-mails, notifications, distributions) — sous Windows, via NSSM ou le Planificateur
   (« au démarrage », redémarrage en cas d'échec) :
   `php artisan queue:work --tries=3 --sleep=3 --max-time=3600`
   Sans worker : aucun PDF ni aucune notification ne partent.
2. **Planificateur** (relances de retard, effets différés des promotions/mutations) — tâche Windows toutes les minutes :
   `php artisan schedule:run`

## Droits sur les dossiers

`storage/` et `bootstrap/cache/` en écriture pour l'utilisateur du serveur web uniquement. `storage/app/private`
ne doit pas être exposé par le serveur web (pas de `php artisan storage:link` pour ces fichiers).

## Checklist avant ouverture

- [ ] `php artisan test` vert ; `php artisan test --group=mysql` vert sur la base de test MySQL
- [ ] `APP_DEBUG=false`, `APP_KEY` propre à la production et sauvegardée
- [ ] Premier administrateur créé avec un mot de passe fort (`app:create-admin`), aucun compte de démonstration
- [ ] HTTPS actif, cookies sécurisés
- [ ] Worker de file et planificateur en place et redémarrent après reboot
- [ ] SMTP testé (si `RH_NOTIFICATIONS_MAIL` ou distribution par e-mail)
- [ ] Sauvegarde automatique + **restauration testée**
- [ ] Périmètres des utilisateurs saisis ; un RH hors périmètre ne voit aucun employé
- [ ] Format d'export Sage confirmé avec un vrai fichier (import de test sur une période fictive)
- [ ] Modèles HTML validés par la direction RH ; préfixes de références confirmés (`reference_sequences`)
- [ ] Déclaration du statut de la « signature interne » aux utilisateurs (non qualifiée juridiquement)

## Exploitation courante

| Besoin | Commande |
|---|---|
| Dossiers en retard | `php artisan workflow:retards` |
| Relancer un PDF | `php artisan dossiers:finaliser [module] [id]` (ou action « Relancer le PDF ») |
| Appliquer les effets échus | `php artisan rh:appliquer-effets` (code retour ≠ 0 = conflit à traiter) |
| Diagnostic des références | `php artisan references:next MODULE` (**consomme un numéro** : ne pas utiliser en production courante) |
| Jobs en échec | `php artisan queue:failed` / `queue:retry all` |
