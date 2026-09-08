# Template Next.js + Symfony + Docker

Base de depart pour demarrer une application web avec :

- **Frontend** : Next.js 16 + TypeScript (Node.js 22, pnpm)
- **Backend** : Symfony 7.4 LTS + API Platform, servi par FrankenPHP (PHP 8.4)
- **Base de donnees** : PostgreSQL 16
- **Orchestration** : un seul `compose.yaml` (3 conteneurs)

Le template ne contient **aucune fonctionnalite metier** : ni entite, ni
authentification, ni page applicative. Juste l'infrastructure de developpement,
prete a l'emploi et deja verifiee.

---

## 1. Demarrer un nouveau projet a partir de ce template

```bash
# 1. Copier le dossier du template sous un nouveau nom
cp -r APPLITEMPLATE mon-projet
cd mon-projet

# 2. Repartir d'un historique Git vierge
rm -rf .git
git init -b main

# 3. Creer le fichier d'environnement local
copy .env.example .env        # PowerShell / CMD
# ou :  cp .env.example .env   # Git Bash

# 4. (optionnel) Personnaliser
#    - .env               : POSTGRES_DB, et les ports si besoin
#    - frontend/package.json  -> champ "name"
#    - backend/config/packages/api_platform.yaml -> "title"

# 5. Construire les images (1re fois, puis seulement si un Dockerfile change)
docker compose build

# 6. Installer les dependances (1re fois)
docker compose run --rm --no-deps backend composer install
docker compose run --rm --no-deps frontend pnpm install

# 7. Demarrer
docker compose up -d
```

Le nom de projet Docker (prefixe des conteneurs et volumes) est
automatiquement celui du dossier. Deux projets issus du template sont donc
isoles l'un de l'autre, a condition de leur donner des **ports differents**
dans `.env` (`FRONTEND_PORT`, `BACKEND_PORT`, `POSTGRES_PORT`) s'ils doivent
tourner en meme temps.

## 2. Les trois conteneurs

| Conteneur  | Role              | Techno                                             |
|------------|-------------------|----------------------------------------------------|
| `database` | Base de donnees   | PostgreSQL 16                                       |
| `backend`  | API HTTP          | Symfony 7.4 + API Platform, via FrankenPHP (PHP 8.4)|
| `frontend` | Interface web     | Next.js 16 + TypeScript (Node.js 22, pnpm)          |

Le `frontend` parle au `backend` par HTTP. Le `backend` parle a `database` par
le reseau interne de Docker. Tout est decrit dans [`compose.yaml`](./compose.yaml),
qui est commente : c'est le point de depart pour comprendre le projet.

## 3. A quoi sert Docker ici

Docker installe et fait tourner PHP, Symfony, FrankenPHP, Node.js, Next.js et
PostgreSQL **dans des conteneurs**, sans rien installer directement sur la
machine (a part Docker). Le meme environnement pourra plus tard etre deploye
tel quel sur un serveur (VPS avec Docker).

## 4. Prerequis

- [Docker Desktop](https://www.docker.com/products/docker-desktop/) installe et demarre.
- Rien d'autre (pas besoin de PHP, Node ou PostgreSQL sur la machine).

## 5. Commandes du quotidien

| Action                                 | Commande                                    |
|----------------------------------------|---------------------------------------------|
| Demarrer                               | `docker compose up -d`                       |
| Arreter (les donnees sont conservees)  | `docker compose down`                        |
| Voir les logs des 3 services (live)    | `docker compose logs -f`                     |
| Voir les logs d'un seul service        | `docker compose logs -f backend`             |
| Etat des conteneurs                    | `docker compose ps`                          |
| Reconstruire les images                | `docker compose build` puis `docker compose up -d` |
| Reconstruire + demarrer                | `docker compose up -d --build`               |
| Terminal dans le backend               | `docker compose exec backend sh`             |
| Terminal dans le frontend              | `docker compose exec frontend sh`            |
| Commande Symfony                       | `docker compose exec backend php bin/console <cmd>` |
| Ajouter une dependance PHP             | `docker compose exec backend composer require <paquet>` |
| Ajouter une dependance JS              | `docker compose exec frontend pnpm add <paquet>` |
| Tout supprimer, Y COMPRIS la base      | `docker compose down -v`  *(efface les donnees)* |

Modifier le code dans `backend/` ou `frontend/` est pris en compte
**immediatement**, sans reconstruire les images :
- Next.js recompile la page automatiquement (hot reload, via webpack + polling) ;
- Symfony relit les fichiers a chaque requete.

Un `docker compose build` n'est necessaire que si un `Dockerfile` change.
Un `composer install` / `pnpm install` n'est necessaire que si on ajoute une
dependance.

## 6. Adresses (valeurs par defaut)

| Service            | URL                                          |
|--------------------|----------------------------------------------|
| Frontend (Next.js) | http://localhost:3000                         |
| API (Symfony)      | http://localhost:8000                         |
| Documentation API  | http://localhost:8000/api                     |
| PostgreSQL         | `127.0.0.1:5432` (acces local optionnel)      |

`http://localhost:8000/` renvoie une erreur 404 : c'est normal, aucune page
d'accueil n'est definie. Le point d'entree de l'API est `/api`.

Ces ports sont configurables dans `.env` (`FRONTEND_PORT`, `BACKEND_PORT`,
`POSTGRES_PORT`).

## 7. Ou est le code

```text
.
├── compose.yaml          <-- description des 3 conteneurs (commente)
├── .env.example          <-- modele de variables d'environnement
├── docker/
│   ├── backend/Dockerfile    <-- image PHP / FrankenPHP
│   └── frontend/Dockerfile   <-- image Node.js
├── backend/              <-- application Symfony + API Platform (vierge)
│   ├── src/                  code PHP
│   ├── config/              configuration Symfony
│   └── composer.json
└── frontend/             <-- application Next.js (vierge)
    ├── app/                 pages et composants React
    ├── package.json
    └── next.config.ts
```

## 8. Variables d'environnement

Regroupees dans `.env` a la racine (copie de `.env.example`), lu automatiquement
par Docker Compose.

| Variable              | Role                                             | Defaut |
|-----------------------|-------------------------------------------------|--------|
| `POSTGRES_DB`         | nom de la base                                   | `app`  |
| `POSTGRES_USER`       | utilisateur PostgreSQL                           | `app`  |
| `POSTGRES_PASSWORD`   | mot de passe PostgreSQL                          | `app`  |
| `APP_SECRET`          | cle interne Symfony (non sensible en dev)        | `dev_...` |
| `FRONTEND_PORT`       | port du frontend sur l'hote                      | `3000` |
| `BACKEND_PORT`        | port de l'API sur l'hote                         | `8000` |
| `POSTGRES_PORT`       | port PostgreSQL sur l'hote                       | `5432` |

`.env` n'est **pas** versionne. En production, ces variables sont fournies par
le serveur. Deux valeurs sont construites automatiquement dans `compose.yaml`
a partir des precedentes : `DATABASE_URL` (connexion a la base) et
`NEXT_PUBLIC_API_URL` (`http://localhost:<BACKEND_PORT>`, URL de l'API vue
depuis le navigateur).

## 9. Mettre le template a jour

Les versions sont epinglees (Symfony 7.4 LTS, PostgreSQL 16, Node 22 LTS,
FrankenPHP 1.12, Next 16) et figees par les lockfiles
(`backend/composer.lock`, `frontend/pnpm-lock.yaml`). Pour rafraichir :

```bash
docker compose exec backend composer update
docker compose exec frontend pnpm update
```

et, si besoin, ajuster les tags d'images dans `docker/*/Dockerfile`.

## 10. Etat du template

Voir [`CLAUDE.md`](./CLAUDE.md).
