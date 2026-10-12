# actions-vibe
Actions manager implemented in Vibe Coding

## Structure

- `public/` : racine web (point d'entrée `index.php`)
- `src/` : code applicatif (espace de noms `App\`, autoloader sans Composer)
- `migrations/` : scripts SQL
- `bin/` : scripts en ligne de commande (`php bin/check-db.php` teste la connexion)
- `tests/` : tests

## Configuration

Copier `.env.example` en `.env` (ou définir les variables d'environnement) : `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASSWORD`.

## Lancement local avec Docker

```
docker compose up --build
```

- Application : http://localhost:8080
- PostgreSQL : `localhost:5432` (base, utilisateur et mot de passe : `actions`)
- Test de connexion : `docker compose exec app php bin/check-db.php`
- Les scripts `.sql` de `migrations/` sont joués à la première création de la base ; pour les rejouer : `docker compose down -v`.
- Port déjà utilisé ? Changer les ports publiés : `APP_PORT=8081 DB_HOST_PORT=5433 docker compose up --build`.

## Portail web

http://localhost:8080/ : liste filtrable des tâches, création, édition, suppression, changement de statut et prérequis. Code dans `src/Web/`, styles dans `public/assets/portal.css`.

## API REST

`/api/tasks` (JSON) : `GET` liste (filtres `?category=Pro|Perso`, `?status=a_faire|en_cours|terminee`), `POST` création, `GET|PUT|PATCH|DELETE /api/tasks/{id}`.
Champs : `title`, `category`, `due_date` (AAAA-MM-JJ), `recurrence`, `duration_minutes`, `description`, `status`, `requirements` (ids des prérequis). Test : `docker compose exec app php tests/ApiTest.php http://localhost`.

## MCP

Endpoint `POST /mcp` (MCP, transport HTTP, JSON-RPC 2.0). Outils : `create_task`, `list_tasks`, `get_task`, `update_task`, `delete_task` (mêmes champs que l'API REST). Test : `docker compose exec app php tests/McpTest.php http://localhost`.
