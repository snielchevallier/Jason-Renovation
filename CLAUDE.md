# CLAUDE.md — Jason Renovation (application de creation de devis)

Guide de travail pour Claude Code. Application web de redaction et de suivi des
devis d'une entreprise de renovation, batie sur une base Next.js + Symfony +
Docker. Vue d'ensemble utilisateur : `README.md`. Conventions backend :
`backend/CLAUDE.md`.

## Regles imperatives du projet

Ces regles priment sur tout comportement par defaut.

### Securite = priorite

- Deny-by-default : un endpoint est protege sauf raison explicite de l'ouvrir.
- Deux firewalls distincts : `/api` (JWT, stateless) pour le front ; `/admin`
  (session, form login, `ROLE_ADMIN`) pour le module d'administration.
- Aucun secret dans un fichier versionne (cles JWT, passphrases, mots de passe).
  Les valeurs reelles passent par `compose.yaml` depuis le `.env` racine
  (non versionne), sur le modele de `APP_SECRET`.
- Ne jamais faire confiance aux montants envoyes par le client : prix, quantites
  et totaux sont recalcules et imposes cote serveur.
- Validation stricte des entrees. Hash de mot de passe natif Symfony.

### Git

- Branches : `develop` (integration), puis `feature/back/<n>-<sujet>` ou
  `feature/front/<n>-<sujet>`. Merge en `--no-ff` (pas de squash).
- Commits petits, chacun comprehensible seul, chacun laisse l'app demarrable.
- Jamais de backend et de frontend dans le meme commit.
- Messages en Conventional Commits : `feat(backend):`, `chore(frontend):`, `docs:`...

### Documentation a jour

- Tout changement structurant (nouvelle dependance, nouvelle entite, nouvel
  endpoint, modif de config securite, nouveau service Docker) est accompagne,
  **dans le meme commit**, d'une mise a jour de `README.md` et de la section
  « Etat actuel » ci-dessous.

## Vue d'ensemble

Frontend Next.js, backend Symfony + API Platform, PostgreSQL, orchestres par un
unique `compose.yaml`. Perimetre metier : clients et chantiers, catalogue de
prestations, redaction de devis (lignes, TVA multi-taux, totaux), cycle de vie
du devis, parametres de l'entreprise, back-office d'administration. PDF et envoi
au client viendront ensuite.

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
- API : http://localhost:8000/api — **protegee par JWT** (deny-by-default).
  Publics : `POST /api/login` et `/api/docs` (Swagger UI).
- Back-office : http://localhost:8000/login (formulaire) -> `/admin` (`ROLE_ADMIN`).
- `http://localhost:8000/` = 404 attendu (pas de route racine).
- PostgreSQL : `127.0.0.1:5432` (non expose hors machine).

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

# Installation initiale (dans les volumes) :
docker compose run --rm --no-deps backend composer install
docker compose run --rm --no-deps frontend pnpm install
docker compose run --rm --no-deps backend php bin/console lexik:jwt:generate-keypair
docker compose exec backend php bin/console doctrine:migrations:migrate --no-interaction
docker compose exec backend php bin/console doctrine:fixtures:load --no-interaction
```

Verifications utiles :

```bash
curl -s -o /dev/null -w "%{http_code}\n" http://localhost:8000/api/docs.jsonld  # 200
curl -s -o /dev/null -w "%{http_code}\n" http://localhost:8000/api              # 401 (JWT requis)
curl -s -o /dev/null -w "%{http_code}\n" http://localhost:3000                  # 200
docker compose exec -T backend php bin/console dbal:run-sql "SELECT 1"          # connexion DB

# Connexion (compte admin des fixtures) -> doit renvoyer un token :
curl -s -X POST http://localhost:8000/api/login -H "Content-Type: application/json" \
  -d '{"email":"admin@jc-reno.com","password":"Password!"}'
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

## Authentification

Deux firewalls (`backend/config/packages/security.yaml`) :

| Firewall | Portee | Auth | Pour |
|----------|--------|------|------|
| `login`  | `^/api/login$` | `json_login` -> token JWT (Lexik) | obtention du token |
| `api`    | `^/api` | JWT, `stateless: true` | le frontend Next.js |
| `admin`  | reste du site | session + form login, `default_target_path` `/admin` | back-office `ROLE_ADMIN` |

- `access_control` deny-by-default : publics = `^/api/login$`, `^/api/docs`, `^/login$`.
- Anti brute-force : `login_throttling` (5 tentatives/min par IP+identifiant) sur
  `login` et `admin`. Sur l'API, `App\Security\ApiAuthenticationFailureHandler`
  renvoie `429` en cas de depassement, sinon delegue a Lexik (`401`).
- Cles JWT : `backend/config/jwt/*.pem`, **non versionnees**.
  Regenerer : `docker compose exec backend php bin/console lexik:jwt:generate-keypair`.
  `JWT_PASSPHRASE` vient de `compose.yaml` (depuis le `.env` racine).
- Comptes : crees par `UserFixtures`. Admin de dev = `admin@jc-reno.com` / `Password!`.

## Etat actuel

```text
Phase courante : Phase 1 — Socle de securite (backend) — TERMINEE

Fait :
- Docker Compose (3 services), environnement verifie
- Auth API : entite User, POST /api/login -> JWT, firewall /api stateless
- access_control deny-by-default
- Firewall /admin (form login sur /login, ROLE_ADMIN) + page placeholder
- CORS restreint au frontend local sur ^/api
- Anti brute-force (login_throttling, 429)
- Swagger UI / ReDoc actives
- Fixtures de dev (compte admin)

A faire (phases suivantes) :
- Phase 2 : entites de reference (Entreprise, TVA, Unite) + fixtures
- Phase 3 : module d'administration (EasyAdmin)
- Phase 4 : coeur metier (Client, Chantier, Catalogue, Devis, Lignes)
- Phase 5 : operations devis (statuts, duplication, verrou)
- Phase 6 : CMS leger
- Phase 7 : tests
- Hors perimetre backend initial : generation PDF, envoi au client
```

## Installation

Voir la section 1 du `README.md` (procedure complete : `.env`, build, deps,
cles JWT, migrations, fixtures). Ne pas commencer le travail metier avant que
`docker compose ps` montre les 3 services up et que les verifications ci-dessus
repondent.
