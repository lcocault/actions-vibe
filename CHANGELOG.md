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
- API REST CRUD `/api/tasks` : `GET` (filtres `category`, `status`), `POST` (201 + `Location`), `GET/PUT/PATCH/DELETE /api/tasks/{id}`. Réponses JSON `{"data": ...}` avec prérequis directs (`requirements`: id, titre, statut) ; erreurs `{"error": {code, message, details}}` (400 JSON invalide, 404, 405, 409 transition ou cycle, 422 validation).
- `PUT` remplace les champs métier (omis = effacés), `PATCH` modifie seulement ceux fournis ; `status` déclenche une transition ; `requirements` (liste d'ids), si présent, remplace les prérequis, en transaction. Clôture d'une tâche périodique : l'occurrence suivante est créée et renvoyée dans `next_occurrence`.
- Suppression d'une tâche : les liens de dépendance sont retirés automatiquement (choix par défaut de la question ouverte 4, à valider).
- Ajout de `TaskRepository`, `Api\TaskApi` (indépendant de HTTP), routage dans `public/index.php`, `public/.htaccess` ; `DependencyRepository` rejoint désormais la transaction en cours.
- Test d'intégration `tests/ApiTest.php` (`docker compose exec app php tests/ApiTest.php http://localhost`) : 25 vérifications OK. Pas d'authentification pour l'instant (fonctionnalité 8).
- Fonctionnalité 5 passée à « Terminé ».

- Portail web (`src/Web/` : `Portal`, `Session`, `View`, gabarits `views/`, `public/assets/portal.css`) : liste filtrable (catégorie, statut) avec prérequis et badge « Bloquée », fiche détaillée, création, édition (cases à cocher des prérequis), suppression avec confirmation, boutons de transition de statut. Le portail appelle `TaskApi` en mémoire : mêmes validations, cycles et périodicité que l'API REST.
- Protection : jeton CSRF en session sur tous les POST, échappement HTML systématique. Vérifié manuellement sous Docker (parcours complet, 403 sans jeton, cycle refusé, occurrence suivante créée). Pas de tests automatisés du portail.
- Fonctionnalité 6 passée à « Terminé » (l'authentification reste à faire, fonctionnalité 8).

## 2026-10-12

- Interface MCP : `src/Mcp/McpServer.php` (JSON-RPC 2.0, transport Streamable HTTP, endpoint `POST /mcp`), indépendante de HTTP et réutilisant `TaskApi` en mémoire (mêmes validations, cycles et périodicité). Méthodes : `initialize` (négociation de version), `ping`, `tools/list`, `tools/call`, notifications (202), lots ; `GET` -> 405.
- Outils : `create_task`, `list_tasks` (filtres `category`, `status`), `get_task`, `update_task` (modification partielle, `status`, `requirements`), `delete_task`. Les erreurs métier sont renvoyées avec `isError: true` et le même corps d'erreur que l'API REST.
- Test d'intégration `tests/McpTest.php` (`docker compose exec app php tests/McpTest.php http://localhost`) : 22 vérifications OK ; `ApiTest` toujours OK. Pas d'authentification pour l'instant (fonctionnalité 8).
- Fonctionnalité 7 passée à « Terminé ».

## 2026-10-13

- Modèle de tâche : ajout des champs optionnels `project` (texte, 255 caractères max) et `conclusions` (texte libre), propagés dans la persistance, l'API REST, le portail (formulaire et fiche) et les outils MCP. L'occurrence suivante d'une tâche périodique conserve le projet mais pas les conclusions.
- Migration `migrations/003_task_project_conclusions.sql` (à appliquer à la main sur une base existante) ; tests unitaires ajoutés dans `tests/TaskTest.php`.
