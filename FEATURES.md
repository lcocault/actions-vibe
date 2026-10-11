# FEATURES

États possibles : `À faire`, `En cours`, `Terminé`, `Bloqué`.

Le squelette (1), le schéma SQL (2) et le modèle de tâche (3), les dépendances (4) et l'API REST (5) sont en place ; le reste reste à développer.

| # | Fonctionnalité | État |
|---|----------------|------|
| 1 | Squelette PHP (structure, configuration, connexion PDO PostgreSQL) | Terminé |
| 2 | Schéma SQL : tables `task` et `task_dependency` (migration) | Terminé |
| 3 | Modèle de tâche et validation (titre, catégorie `Pro`/`Perso`, date butée, périodicité, durée, description) et cycle de vie (`a_faire` → `en_cours` → `terminee`, périodicité) | Terminé |
| 4 | Actions requises (dépendances) avec détection des cycles | Terminé |
| 5 | API REST CRUD (`/api/tasks`), prérequis inclus dans les réponses | Terminé |
| 6 | Portail web CRUD (liste, création, édition, suppression, affichage des prérequis) | À faire |
| 7 | Interface MCP (HTTP, outils : créer, lister, consulter, modifier, supprimer) | À faire |
| 8 | Authentification / protection de base (jeton API, CSRF, requêtes préparées, échappement HTML) | À faire |
| 9 | Documentation de déploiement AlwaysData et prérequis | À faire |

## Plan proposé (en attente de validation)

1. **Squelette & base** : arborescence (`public/`, `src/`, `migrations/`), connexion PDO `pgsql`, config via variables d'environnement. Vérification : script de connexion.
2. **Schéma SQL** : `task` + `task_dependency` (clé composite, `CHECK` sur la catégorie). Vérification : exécution de la migration sur PostgreSQL.
3. **Domaine** : validation, règle anti-cycle sur les dépendances. Vérification : tests PHPUnit.
4. **API REST** : CRUD JSON, codes HTTP corrects, prérequis dans les réponses.
5. **Portail web** : pages PHP simples, protégées par CSRF.
6. **MCP** : endpoint HTTP JSON-RPC réutilisant la couche domaine.
7. **Sécurité & déploiement** : authentification, guide AlwaysData.

## Prérequis à vérifier sur AlwaysData

- Version PHP récente et extensions `pdo_pgsql` et `json` activées.
- Base PostgreSQL créée et accessible depuis le compte.
- Pour MCP/Claude : un endpoint HTTPS public (transport HTTP) ; un transport stdio n'est pas adapté à l'hébergement mutualisé.
- Composer disponible en SSH (sinon, éviter les dépendances).

## Questions ouvertes (ambiguïtés avant décision)

1. **Périodicité** : proposition simple = valeurs fixes (`quotidienne`, `hebdomadaire`, `mensuelle`, `annuelle`). À la clôture d'une tâche périodique, faut-il recréer la suivante, ou décaler sa date butée ? Une périodicité sans date butée est-elle autorisée ?
2. **Statut** : le modèle n'a pas de statut « fait / à faire ». Faut-il en ajouter un (nécessaire pour la périodicité et les prérequis) ?
3. **Estimation de durée** : unité souhaitée (minutes ?).
4. **Suppression** d'une tâche prérequise : bloquer, ou retirer le lien automatiquement ?
5. **Authentification** : un seul utilisateur avec jeton suffisant ?
