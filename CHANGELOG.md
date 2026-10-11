# CHANGELOG

## 2026-10-08

- Examen du dépôt : il ne contenait que `README.md`, aucun code.
- Création de `FEATURES.md` (fonctionnalités, états, plan en petites étapes, prérequis AlwaysData, questions ouvertes).
- Création de `CHANGELOG.md`.
- Aucun développement réalisé ; en attente de validation du plan.

## 2026-10-10

- Création du squelette PHP : `public/index.php`, `src/` (autoloader, `Config`, `Database` PDO pgsql), `bin/check-db.php`, dossiers `migrations/` et `tests/`.
- Ajout de `.env.example`, `.gitignore` et mise à jour du `README.md`.
- Fonctionnalité 1 passée à « Terminé ».
- Ajout de l'environnement Docker local : `Dockerfile` (PHP 8.3 + Apache + `pdo_pgsql`), `docker-compose.yml` (app + PostgreSQL 16), `.dockerignore`, section README.
- Ajout du schéma SQL `migrations/001_create_tasks.sql` : tables `task` (catégorie `Pro`/`Perso`, périodicité à valeurs fixes, durée en minutes) et `task_dependency` (clé composite, suppression en cascade, auto-dépendance interdite). La détection des cycles reste à faire (fonctionnalité 4).
- Fonctionnalité 2 passée à « Terminé ».

## 2026-10-11

- Modèle de tâche `src/Task/` : `Task` (immuable, validation de tous les champs avec erreurs par champ), enums `Category`, `Recurrence`, `Status`, exceptions `ValidationException` et `InvalidTransitionException`.
- Cycle de vie : `à faire` → `en cours` → `terminée` (retours autorisés vers `à faire`), date de clôture renseignée à la terminaison.
- Périodicité : `nextOccurrence()` crée la tâche suivante avec date butée décalée (fin de mois conservée). Une périodicité exige une date butée.
- Migration `migrations/002_task_lifecycle.sql` : colonnes `status`, `completed_at` et contraintes associées.
- Tests sans dépendance `tests/TaskTest.php` (`php tests/TaskTest.php`) : 25 vérifications OK.
- Fonctionnalité 3 passée à « Terminé ». Choix par défaut pour les questions ouvertes 1 et 2 (à valider).
- Dépendances entre tâches : `DependencyGraph` (ajout/retrait, prérequis directs et indirects, détection de cycle avant ajout avec chemin fautif, `findCycle()`, ordre d'exécution topologique), `CycleException`, `DependencyRepository` (PDO, transaction + verrou de table, `replaceRequirements` en tout ou rien).
- Tests `tests/DependencyTest.php` (`php tests/DependencyTest.php`) : 12 vérifications OK (exécutées via `php:8.3-cli`). `DependencyRepository` non testé (nécessite PostgreSQL).
- Fonctionnalité 4 passée à « Terminé ».
