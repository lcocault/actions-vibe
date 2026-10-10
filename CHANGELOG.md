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
