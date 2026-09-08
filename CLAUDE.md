# CLAUDE.md — Template Next.js + Symfony

Guide de travail pour Claude Code sur les projets bases sur ce template.

## Vue d'ensemble

Base de depart pour une application web : frontend Next.js, backend Symfony +
API Platform, PostgreSQL, le tout orchestre par un unique `compose.yaml`.
Le template ne contient aucune logique metier.

## Architecture reelle

```text
Navigateur ──HTTP:3000──> frontend (Next.js 16)
frontend  ──HTTP:8000 (INTERNAL_API_URL=http://backend)──> backend (Symfony 7.4 + API Platform)
backend   ──reseau Docker: database:5432──> database (PostgreSQL 16)
```

- Un seul `compose.yaml` a la racine. Pas de `compose.override.yaml`.
- Le code de l'hote est monte dans les conteneurs (`./backend`, `./frontend`).
  Les dependances et le cache sont dans des volumes Docker nommes (perf Windows).
- Modifier le code ne demande pas de rebuild. Rebuild uniquement si un
  `Dockerfile` change.
- Le nom de projet Docker = nom du dossier (pas de cle `name:` dans le compose).

## Services Docker

| Service    | Image / build                          | Port hote (defaut) | Detail |
|------------|----------------------------------------|--------------------|--------|
| `database` | `postgres:16-alpine`                    | `127.0.0.1:5432`   | volume `database_data` |
| `backend`  | `docker/backend/Dockerfile` (FrankenPHP `1.12-php8.4-bookworm`) | `8000` -> 80 | sert `backend/public`, `SERVER_NAME=:80` (HTTP seul) |
| `frontend` | `docker/frontend/Dockerfile` (`node:22-alpine`) | `3000` | `pnpm dev` = `next dev --webpack` |

Ports configurables via `.env` : `FRONTEND_PORT`, `BACKEND_PORT`, `POSTGRES_PORT`.

Volumes nommes : `database_data`, `backend_vendor`, `backend_var`,
`frontend_node_modules`, `frontend_next`.

## Versions (epinglees)

- PHP 8.4 (FrankenPHP 1.12) · Symfony 7.4 LTS · API Platform 4.x
- Node.js 22 LTS · Next.js 16 · React 19 · TypeScript 5 · pnpm 12
- PostgreSQL 16

Figees par `backend/composer.lock` et `frontend/pnpm-lock.yaml`.

## Ports

- Frontend : http://localhost:3000
- API : http://localhost:8000 — point d'entree API Platform : http://localhost:8000/api
- `http://localhost:8000/` = 404 attendu (pas de route racine)
- PostgreSQL : `127.0.0.1:5432` (non expose hors machine)

## Commandes reellement disponibles

```bash
docker compose up -d                 # demarrer
docker compose down                  # arreter (garde les volumes)
docker compose down -v               # arreter + EFFACER la base et les deps
docker compose ps                    # etat
docker compose logs -f [service]     # logs
docker compose build                 # reconstruire les images
docker compose up -d --build         # reconstruire + demarrer

docker compose exec backend php bin/console <cmd>
docker compose exec backend composer <cmd>
docker compose exec frontend pnpm <cmd>
docker compose exec backend sh       # shell dans le conteneur

# Installation initiale des dependances (dans les volumes) :
docker compose run --rm --no-deps backend composer install
docker compose run --rm --no-deps frontend pnpm install
```

Verifications utiles :

```bash
curl -s -o /dev/null -w "%{http_code}\n" http://localhost:8000/api             # 200
curl -s -o /dev/null -w "%{http_code}\n" http://localhost:8000/api/docs.jsonld # 200
curl -s -o /dev/null -w "%{http_code}\n" http://localhost:3000                 # 200
docker compose exec -T backend php bin/console dbal:run-sql "SELECT 1"         # connexion DB
```

## Conventions importantes

- **Toujours** lancer composer / pnpm / bin/console **via `docker compose exec`
  ou `run`**, jamais depuis l'hote (versions de Node/PHP differentes, deps dans
  des volumes Linux).
- `DATABASE_URL` est construit dans `compose.yaml` a partir de `POSTGRES_*`.
  Ne pas le coder en dur dans `backend/.env`.
- `APP_SECRET` vient de `.env` (non versionne) via `compose.yaml`. Aucun secret
  dans un fichier suivi par Git.
- Le frontend appelle l'API via `http://backend` (SSR, reseau Docker) ou
  `http://localhost:8000` (navigateur, `NEXT_PUBLIC_API_URL`).
- Next.js tourne avec **webpack** (`next dev --webpack`) et
  `WATCHPACK_POLLING=1000` : Turbopack ne detecte pas les changements de
  fichiers sur un montage Windows. Ne pas repasser a Turbopack sans revalider
  le hot reload.
- FrankenPHP en mode classique (pas worker) : pas de Caddyfile ni de config PHP
  personnalisee tant qu'un besoin reel n'apparait pas.
- Ne pas ajouter de services (Nginx, Redis, Mailpit, worker, reverse proxy...)
  sans besoin reel et explicite.
- Ajouter un second frontend = un nouveau service dans `compose.yaml` sur le
  modele de `frontend` (autre dossier, autre port, meme Dockerfile).

## Etat actuel

```text
Current Phase:
Phase 0 — Development environment (template)

Status:
Template pret a l'emploi, environnement de developpement verifie.

Implemented:
- Docker Compose (3 services)
- Symfony + API Platform (vierge)
- FrankenPHP
- Next.js + TypeScript (vierge)
- PostgreSQL

Not implemented (a faire dans chaque projet):
- Authentication
- Domain / entites
- Business features
- Tests
```

## Demarrer un projet depuis ce template

Voir la section 1 du `README.md`. En resume : copier le dossier, `rm -rf .git`,
`git init`, `cp .env.example .env`, `docker compose build`, installer les deps,
`docker compose up -d`. Ne pas commencer le code metier avant que
`docker compose ps` montre les 3 services up et que les verifications ci-dessus
repondent.
