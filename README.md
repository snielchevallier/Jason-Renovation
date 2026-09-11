# Jason Renovation — Application de creation de devis

Application web pour rediger, suivre et editer les devis d'une entreprise de
renovation.

- **Frontend** : Next.js 16 + TypeScript (Node.js 22, pnpm)
- **Backend** : Symfony 7.4 LTS + API Platform, servi par FrankenPHP (PHP 8.4)
- **Base de donnees** : PostgreSQL 16
- **Orchestration** : un seul `compose.yaml` (3 conteneurs)

Perimetre fonctionnel cible : gestion des clients et de leurs chantiers,
catalogue de prestations reutilisables, redaction de devis (lignes, TVA
multi-taux, totaux), cycle de vie du devis (brouillon -> envoye -> accepte /
refuse), parametres de l'entreprise, et un module d'administration back-office.
La generation PDF et l'envoi au client viendront dans un second temps.

Le detail des choix et de l'avancement est dans [`CLAUDE.md`](./CLAUDE.md).

---

## 1. Installation

Prerequis : [Docker Desktop](https://www.docker.com/products/docker-desktop/)
installe et demarre. Rien d'autre (ni PHP, ni Node, ni PostgreSQL sur la machine).

```bash
# 1. Fichier d'environnement local
copy .env.example .env        # PowerShell / CMD
# ou :  cp .env.example .env   # Git Bash

# 2. Construire les images (1re fois, puis seulement si un Dockerfile change)
docker compose build

# 3. Installer les dependances (1re fois)
docker compose run --rm --no-deps backend composer install
docker compose run --rm --no-deps frontend pnpm install

# 4. Generer les cles JWT (1re fois — elles ne sont pas versionnees)
docker compose run --rm --no-deps backend php bin/console lexik:jwt:generate-keypair

# 5. Demarrer
docker compose up -d

# 6. Creer le schema de base de donnees
docker compose exec backend php bin/console doctrine:migrations:migrate --no-interaction

# 7. Charger le jeu de donnees de developpement (PURGE la base puis reinsere)
docker compose exec backend php bin/console doctrine:fixtures:load --no-interaction
```

Compte administrateur de developpement cree par les fixtures :
`admin@jc-reno.com` / `Password!`.

Verifications rapides :

```bash
curl -s -o /dev/null -w "%{http_code}\n" http://localhost:8000/api/docs.jsonld  # 200
curl -s -o /dev/null -w "%{http_code}\n" http://localhost:3000                   # 200
docker compose exec -T backend php bin/console dbal:run-sql "SELECT 1"           # connexion DB
```

## 2. Les trois conteneurs

| Conteneur  | Role              | Techno                                             |
|------------|-------------------|----------------------------------------------------|
| `database` | Base de donnees   | PostgreSQL 16                                       |
| `backend`  | API HTTP          | Symfony 7.4 + API Platform, via FrankenPHP (PHP 8.4)|
| `frontend` | Interface web     | Next.js 16 + TypeScript (Node.js 22, pnpm)          |

Le `frontend` parle au `backend` par HTTP. Le `backend` parle a `database` par
le reseau interne de Docker. Tout est decrit dans [`compose.yaml`](./compose.yaml),
qui est commente.

Modifier le code dans `backend/` ou `frontend/` est pris en compte
**immediatement**, sans reconstruire les images. Un `docker compose build` n'est
necessaire que si un `Dockerfile` change ; un `composer install` / `pnpm install`
que si on ajoute une dependance.

## 3. Commandes du quotidien

| Action                                 | Commande                                    |
|----------------------------------------|---------------------------------------------|
| Demarrer                               | `docker compose up -d`                       |
| Arreter (les donnees sont conservees)  | `docker compose down`                        |
| Voir les logs (live)                   | `docker compose logs -f [service]`           |
| Etat des conteneurs                    | `docker compose ps`                          |
| Reconstruire + demarrer                | `docker compose up -d --build`               |
| Terminal dans le backend               | `docker compose exec backend sh`             |
| Commande Symfony                       | `docker compose exec backend php bin/console <cmd>` |
| Creer / jouer une migration            | `docker compose exec backend php bin/console make:migration` puis `doctrine:migrations:migrate` |
| Ajouter une dependance PHP             | `docker compose exec backend composer require <paquet>` |
| Ajouter une dependance JS              | `docker compose exec frontend pnpm add <paquet>` |
| Tout supprimer, Y COMPRIS la base      | `docker compose down -v`  *(efface les donnees)* |

## 4. Adresses (valeurs par defaut)

| Service            | URL                                          |
|--------------------|----------------------------------------------|
| Frontend (Next.js) | http://localhost:3000                         |
| API (Symfony)      | http://localhost:8000/api                     |
| Documentation API  | http://localhost:8000/api/docs (Swagger UI)   |
| PostgreSQL         | `127.0.0.1:5432` (acces local optionnel)      |

`http://localhost:8000/` renvoie une 404 (pas de page d'accueil). Le point
d'entree de l'API est `/api`. Ces ports sont configurables dans `.env`.

## 5. Authentification

Deux firewalls distincts, un par usage.

**API (`/api/*`)** — jeton JWT, sans session, deny-by-default. Seuls `/api/login`
et `/api/docs` sont publics. `/api/login` est protege contre le brute-force :
au-dela de 5 tentatives echouees par minute (par IP + identifiant), reponse
`429 Too Many Requests`. Meme protection sur le formulaire `/login`.

```bash
# 1. Obtenir un token
curl -X POST http://localhost:8000/api/login \
  -H "Content-Type: application/json" \
  -d '{"email":"<email>","password":"<mot de passe>"}'
# -> {"token":"eyJ..."}

# 2. Appeler un endpoint protege
curl http://localhost:8000/api/... -H "Authorization: Bearer eyJ..."
```

**Back-office (`/admin`)** — session + formulaire de connexion sur
`http://localhost:8000/login`, reserve a `ROLE_ADMIN`. Detail en §7.

## 6. Donnees de reference

Trois ressources en lecture seule (authentification requise, ecriture reservee
au back-office) :

| Ressource | Contenu |
|-----------|---------|
| `GET /api/tvas` | taux de TVA (20 / 10 / 5,5 / 0 %) |
| `GET /api/unites` | unites de mesure (u, forfait, ens, m2, ml, m3, kg, t, h, j, L) |
| `GET /api/entreprises` | fiche entreprise (singleton, entete des devis) |

Format par defaut : `application/ld+json` (Hydra). Le frontend peut demander
`application/json` via l'en-tete `Accept` pour une reponse simple, sans
enveloppe. Les champs `null` sont omis de la reponse.

```bash
curl http://localhost:8000/api/tvas -H "Authorization: Bearer <token>" -H "Accept: application/json"
```

## 7. Back-office

`http://localhost:8000/admin` (EasyAdmin), formulaire de connexion sur `/login`,
reserve a `ROLE_ADMIN`. Identifiants de dev : `admin@jc-reno.com` / `Password!`.

| Section | Pour |
|---------|------|
| Entreprise | edition de la fiche entreprise (singleton, pas de creation/suppression) |
| Taux de TVA | gestion des taux (`actif`/`position`) |
| Unites | gestion des unites de mesure (`actif`/`position`) |
| Utilisateurs | comptes du back-office (email, nom, roles, mot de passe) |

Gestion des utilisateurs : mot de passe avec confirmation (12 caracteres
minimum), impossible de se retirer soi-meme le role administrateur, de se
supprimer soi-meme, ou de retirer le role au dernier administrateur restant.
La derniere connexion au back-office est affichee dans la liste.

## 8. Ou est le code

```text
.
├── compose.yaml              <-- description des 3 conteneurs (commente)
├── .env.example              <-- modele de variables d'environnement
├── CLAUDE.md                 <-- regles projet + etat d'avancement
├── docker/
│   ├── backend/Dockerfile        <-- image PHP / FrankenPHP
│   └── frontend/Dockerfile       <-- image Node.js
├── backend/                  <-- application Symfony + API Platform
│   ├── CLAUDE.md                 conventions backend
│   ├── src/Entity/              entites Doctrine
│   ├── src/Controller/Admin/    back-office EasyAdmin (dashboard + CRUD)
│   ├── config/packages/         configuration (security.yaml, api_platform.yaml...)
│   ├── config/jwt/              cles JWT (non versionnees)
│   └── migrations/             migrations de schema
└── frontend/                 <-- application Next.js
    ├── app/                    pages et composants React
    └── next.config.ts
```

## 9. Variables d'environnement

Regroupees dans `.env` a la racine (copie de `.env.example`), lu automatiquement
par Docker Compose. `.env` n'est **pas** versionne.

| Variable              | Role                                             | Defaut (`.env.example`) |
|-----------------------|-------------------------------------------------|-------------------------|
| `POSTGRES_DB`         | nom de la base                                   | `app`  |
| `POSTGRES_USER`       | utilisateur PostgreSQL                           | `app`  |
| `POSTGRES_PASSWORD`   | mot de passe PostgreSQL                          | `app`  |
| `APP_SECRET`          | cle interne Symfony (non sensible en dev)        | `dev_...` |
| `JWT_PASSPHRASE`      | passphrase de la cle privee JWT (Lexik)          | `dev_...` |
| `FRONTEND_PORT`       | port du frontend sur l'hote                      | `3000` |
| `BACKEND_PORT`        | port de l'API sur l'hote                         | `8000` |
| `POSTGRES_PORT`       | port PostgreSQL sur l'hote                       | `5432` |

`DATABASE_URL`, `NEXT_PUBLIC_API_URL` et `CORS_ALLOW_ORIGIN` sont construites
automatiquement dans `compose.yaml` a partir des variables ci-dessus
(`CORS_ALLOW_ORIGIN = http://localhost:<FRONTEND_PORT>`, seule origine acceptee
par l'API en CORS). En production, ces valeurs sont fournies par le serveur,
jamais par Git.

## 10. Versions

Epinglees (Symfony 7.4 LTS, API Platform 4.x, PostgreSQL 16, Node 22 LTS,
FrankenPHP 1.12, Next 16) et figees par `backend/composer.lock` et
`frontend/pnpm-lock.yaml`.

## 11. Etat d'avancement

| Domaine | Etat |
|---------|------|
| Environnement Docker (3 services)         | ✅ operationnel |
| Auth API : entite User, login JWT, firewall `/api` stateless, deny-by-default | ✅ fait |
| Firewall `/admin` (form login sur `/login`, `ROLE_ADMIN`) | ✅ fait |
| Anti brute-force (login throttling, 429) | ✅ fait |
| Fixtures de developpement (compte admin) | ✅ fait |
| Entites de reference (Entreprise, TVA, Unite) + embeddable Adresse | ✅ fait |
| Module d'administration (EasyAdmin : Entreprise, TVA, Unites, Utilisateurs) | ✅ fait |
| Tests (socle PHPUnit, regression Phases 1-3) + gestion des erreurs `/admin` | ⬜ a faire (prochaine phase) |
| Coeur metier (Client, Chantier, Catalogue, Devis, Lignes) | ⬜ a faire |
| Operations devis (statuts, duplication, verrou) | ⬜ a faire |
| CMS leger                                 | ⬜ a faire |
| Qualite finale (couverture de tests, fixtures realistes) | ⬜ a faire |
| Generation PDF / envoi au client          | ⬜ hors perimetre backend initial |

Detail des phases et des decisions : [`CLAUDE.md`](./CLAUDE.md).
