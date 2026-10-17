# Sauvegarde et restauration

À sauvegarder **ensemble** (une base sans ses fichiers, ou l'inverse, est incohérente) :

1. La base MySQL `eco_rh_dls`.
2. `storage/app/private/` : PDF finaux (`dossiers/`), bulletins (`paylists/`), justificatifs, archives importées.
3. Le fichier `.env` (stocké séparément, chiffré : contient `APP_KEY` et les accès base). **Sans `APP_KEY`,
   les données chiffrées/sessions ne sont pas récupérables.**

## Sauvegarde quotidienne (Windows / XAMPP)

`C:\xampp\htdocs\eco-rh-cms\scripts\sauvegarde.ps1` (à créer sur le serveur, adapter les chemins) :

```powershell
$date = Get-Date -Format 'yyyyMMdd_HHmm'
$dest = "D:\sauvegardes\eco-rh\$date"
New-Item -ItemType Directory -Force $dest | Out-Null
& "C:\xampp\mysql\bin\mysqldump.exe" -uroot --single-transaction --routines --default-character-set=utf8mb4 eco_rh_dls |
  Set-Content "$dest\base.sql" -Encoding utf8
Compress-Archive -Path "C:\xampp\htdocs\eco-rh-cms\storage\app\private" -DestinationPath "$dest\fichiers.zip"
# Conserver 30 jours
Get-ChildItem D:\sauvegardes\eco-rh | Where-Object { $_.LastWriteTime -lt (Get-Date).AddDays(-30) } | Remove-Item -Recurse -Force
```

Planifier via le Planificateur de tâches Windows (tous les jours, hors heures de bureau) et **copier
les sauvegardes hors du serveur** (autre machine ou support externe). `--single-transaction` évite de
bloquer l'application (InnoDB).

## Restauration

1. Mettre l'application en maintenance : `php artisan down`.
2. Arrêter le worker de file.
3. Recréer une base vide puis importer : `mysql -uroot eco_rh_dls < base.sql`.
4. Restaurer les fichiers : extraire `fichiers.zip` vers `storage/app/` (le dossier `private` doit retrouver son contenu).
5. Restaurer `.env` (même `APP_KEY`).
6. `php artisan migrate --force` (applique d'éventuelles migrations plus récentes que la sauvegarde), `php artisan optimize:clear`.
7. Redémarrer le worker, puis `php artisan up`.
8. Vérifier : connexion, ouverture d'un dossier approuvé, téléchargement de son PDF, `php artisan tinker --execute="echo App\Models\ReferenceSequence::count();"`.

Les PDF manquants d'un dossier approuvé se régénèrent sans nouvelle référence : `php artisan dossiers:finaliser`.

## Test de restauration

Au moins une fois par trimestre, restaurer la dernière sauvegarde sur une machine de test et exécuter
la vérification de l'étape 8. Une sauvegarde jamais restaurée n'est pas une sauvegarde.

## Avant toute opération risquée

Ne jamais lancer `migrate:fresh`, `migrate:rollback` ou une suppression sur la base de production sans
sauvegarde vérifiée. Les tests n'utilisent que SQLite en mémoire ou la base `eco_rh_dls_test`.
