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
| `admin`  | reste du site (dont `/admin`) | session + form login, `default_target_path` `admin` | back-office `ROLE_ADMIN` |

- `access_control` deny-by-default : publics = `^/api/login$`, `^/api/docs`, `^/login$`.
- Anti brute-force : `login_throttling` (5 tentatives/min par IP+identifiant) sur
  `login` et `admin`. Sur l'API, `App\Security\ApiAuthenticationFailureHandler`
  renvoie `429` en cas de depassement, sinon delegue a Lexik (`401`).
- Cles JWT : `backend/config/jwt/*.pem`, **non versionnees**.
  Regenerer : `docker compose exec backend php bin/console lexik:jwt:generate-keypair`.
  `JWT_PASSPHRASE` vient de `compose.yaml` (depuis le `.env` racine).
- Comptes : crees/geres via le back-office (`/admin/user`, Phase 3) ou
  `UserFixtures`. Admin de dev = `admin@jc-reno.com` / `Password!`.
- **CSRF : toujours en session** (`config/packages/csrf.yaml`), y compris pour
  EasyAdmin. La recipe `symfony/form` active par defaut un CSRF "stateless"
  qui exige un controleur JS absent de nos pages ; desactive volontairement
  (voir Phase 3 ci-dessous). Ne pas le reactiver sans ajouter le JS necessaire.

## Entites de reference (Phase 2)

| Entite | Cle | Contenu | API |
|--------|-----|---------|-----|
| `Tva` | `taux` unique (decimal 5,2) | libelle, actif, position | `/api/tvas` lecture seule, seedee par migration (20/10/5,5/0 %) |
| `Unite` | `code` unique | libelle, actif, position | `/api/unites` lecture seule, seedee par migration (u, forfait, ens, m2, ml, m3, kg, t, h, j, L) |
| `Entreprise` | singleton (1 ligne) | nom, siret, TVA intracom, `adresse` (embeddable), telephone, email, mentionsLegales, conditionsPaiement, logo, delaiValiditeDevisJours | `/api/entreprises` lecture seule, ligne creee par migration a completer via le back-office |

- `Adresse` (`src/Entity/Embeddable/Adresse.php`) : objet embarque reutilisable, prevu pour `Client` et `Chantier` en Phase 4.
- `actif`/`position` sur `Tva`/`Unite` : on ne supprime jamais une valeur utilisee par un devis historique, on la desactive.
- Format API par defaut = `application/ld+json` (Hydra) ; `application/json` disponible via l'en-tete `Accept` pour le frontend. Les champs `null` sont omis de la sortie.
- Ecriture de ces 3 entites : reservee au back-office (EasyAdmin, Phase 3), pas d'endpoint `/api` en ecriture.

## Module d'administration (Phase 3)

Back-office EasyAdmin (`easycorp/easyadmin-bundle`) sur `/admin`, firewall
`admin` (session, `ROLE_ADMIN`). Remplace le placeholder de la Phase 1.

- `src/Controller/Admin/DashboardController.php` : `#[AdminDashboard(routePath: '/admin', routeName: 'admin')]`.
  Le menu « Entreprise » pointe directement sur l'edition de l'unique ligne
  (`AdminUrlGenerator`), jamais sur une liste.
- CRUD : `TvaCrudController`, `UniteCrudController`, `EntrepriseCrudController`
  (NEW/DELETE/INDEX desactives — singleton), `UserCrudController`.
- `UserCrudController` : `plainPassword` (propriete transitoire non persistee,
  pas de colonne Doctrine) hashee dans `password` via
  `UserPasswordHasherInterface`, dans `persistEntity()`/`updateEntity()`.
  Confirmation du mot de passe (`RepeatedType`), longueur minimale 12
  caracteres (NIST 800-63B : longueur > complexite, pas de rotation forcee).
  Validation `UniqueEntity`/`Assert\Email`/`Assert\NotBlank` sur `User`.
  **Garde-fou anti-lockout** : impossible de se retirer soi-meme
  `ROLE_ADMIN`, de se supprimer soi-meme, ou de retirer le role au dernier
  administrateur (`UserRepository::countUsersWithRole()`).
- `lastLoginAt` : horodate uniquement les connexions au firewall `admin`
  (`EventListener/LastLoginListener.php`, filtre sur `getFirewallName()`) —
  pas les appels API JWT, qui re-authentifient a chaque requete.
- Ecarts EasyAdmin 5.x a connaitre si on reecrit du code sur ce modele :
  `MenuItem::linkTo(CrudControllerFqcn, ...)` (pas `linkToCrud`) ;
  `FormField::addFieldset()` (pas `addPanel`) ; `setHelp()` n'accepte pas `null`.

## Etat actuel

```text
Phase courante : Phase 3 — Module d'administration (backend) — TERMINEE

Fait (Phase 1) :
- Docker Compose (3 services), environnement verifie
- Auth API : entite User, POST /api/login -> JWT, firewall /api stateless
- access_control deny-by-default
- Firewall /admin (form login sur /login, ROLE_ADMIN) + page placeholder
- CORS restreint au frontend local sur ^/api
- Anti brute-force (login_throttling, 429)
- Swagger UI / ReDoc actives
- Fixtures de dev (compte admin)

Fait (Phase 2) :
- Entites de reference Tva, Unite, Entreprise (voir tableau ci-dessus)
- Embeddable Adresse
- symfony/expression-language (securite par operation API Platform)
- Format JSON disponible a cote de JSON-LD

Fait (Phase 3) :
- Back-office EasyAdmin sur /admin (voir section ci-dessus)
- CRUD Tva, Unite, Entreprise (singleton), User
- Garde-fou anti-lockout, validation propre, lastLoginAt
- CSRF de session partout (stateless desactive)

A faire (phases suivantes) :
- Phase 4 : gestion des tests et des erreurs (voir detail ci-dessous)
- Phase 5 : coeur metier (Client, Chantier, Catalogue, Devis, Lignes)
- Phase 6 : operations devis (statuts, duplication, verrou)
- Phase 7 : CMS leger
- Phase 8 : qualite finale (compléter la couverture de tests, fixtures realistes)
- Hors perimetre backend initial : generation PDF, envoi au client
```

### Phase 4 — Gestion des tests et des erreurs (detail)

Inseree avant le coeur metier : on ne veut pas empiler la logique la plus
critique (calculs de devis) sur un backend sans filet, et le module d'admin
laisse deja passer des erreurs brutes (500) qu'il faut rendre propres.

**A. Socle de tests** — FAIT
- `symfony/test-pack` (PHPUnit 13 + browser-kit + css-selector), `symfony/http-client`
  (requis par le client de test API Platform)
- `dama/doctrine-test-bundle` (recipe en `recipes-contrib`, ignoree par notre
  `allow-contrib: false` : bundle enregistre a la main dans `bundles.php`,
  `test` uniquement, + `<extensions><bootstrap class="DAMA\...\PHPUnitExtension"/>`
  dans `phpunit.dist.xml`). Chaque test tourne dans une transaction annulee.
- `ApiTestCase`/`Client` d'API Platform (deja fournis par `api-platform/core`).
  Notre `App\Tests\Support\ApiTestCase` fixe `$alwaysBootKernel = true`
  (evite un avertissement de depreciation qui fait echouer la suite avec
  `failOnDeprecation="true"`).
- **Piege a connaitre** : `docker compose exec backend php bin/phpunit` tourne
  en `APP_ENV=dev`, pas `test` — `KernelTestCase::createKernel()` lit
  `$_ENV['APP_ENV']` avant `$_SERVER['APP_ENV']`, et le `force="true"` de
  `phpunit.dist.xml` ne pose que `$_SERVER`. La vraie variable d'env du
  conteneur (`dev`, via `compose.yaml`) gagne. Toujours lancer
  `docker compose exec backend composer test` (script `composer.json` qui
  fixe `APP_ENV=test` correctement), jamais `bin/phpunit` nu.
- Base de test : `doctrine:database:create --env=test` puis
  `doctrine:migrations:migrate --env=test` (a faire une fois).

**B. Tests de regression (Phases 1-3)** — FAIT
- `tests/Functional/AuthenticationTest.php` : `/api/login` (succes, echec,
  throttle -> 429), deny-by-default sur `/api`, `/api/docs` public
- `tests/Functional/AdminAccessTest.php` : deny-by-default sur `/admin`,
  formulaire `/login`, acces admin vs 403 non-admin
- `tests/Functional/UserCrudGuardTest.php` : garde-fous anti-lockout —
  **y compris le DELETE**, jamais verifiable manuellement (le bouton
  d'EasyAdmin recupere son jeton CSRF en JS, que `curl` ne simule pas). Le
  client de test genere le jeton via le service du conteneur en repoussant
  temporairement la requete courante sur le `request_stack` (le
  `CsrfTokenManager` a besoin d'une requete "active" pour trouver la session).
- `tests/Support/CreatesUsers.php` : trait partage pour creer des users de
  test directement en base (sans passer par le back-office).
- Piege identite-map : apres une requete qui echoue (garde-fou), l'entite en
  memoire reste mutee bien que rien n'ait ete flushe. Toujours
  `$entityManager->clear()` avant de relire l'etat reel pour une assertion.

**C. Gestion des erreurs dans le back-office**
- Aujourd'hui, les garde-fous (`UserCrudController::guardAgainstLockout()`,
  longueur du mot de passe, mot de passe obligatoire a la creation) levent un
  `\RuntimeException` brut -> page d'erreur 500 Symfony, pas un message
  clair pour l'utilisateur.
- Cible : une exception dediee (ex. `App\Exception\AdminGuardException`) +
  un listener `kernel.exception` scope a `/admin` qui transforme cette
  exception en message flash (`addFlash('danger', ...)`) et redirige vers la
  page precedente, au lieu de crasher.
- S'applique a tous les garde-fous deja en place et aux futurs (ex. Phase 6 :
  empecher de desactiver un `Tva`/`Unite` reference par un devis existant).
- **A faire en meme temps** : `UserCrudGuardTest` attend aujourd'hui un `500`
  (comportement reel actuel) sur les cas bloques — mettre a jour ces
  assertions vers le comportement propre (redirection + flash) une fois C
  implementee.

**D. Ensuite, a partir de la Phase 5**
- Tests ecrits **en meme temps** que le code, pas apres :
  `CalculateurDevis` et `GenerateurNumeroDevis` en tests unitaires purs
  (aucune dependance framework/DB), le reste (entites, endpoints `/api/devis`)
  en tests fonctionnels.

## Installation

Voir la section 1 du `README.md` (procedure complete : `.env`, build, deps,
cles JWT, migrations, fixtures). Ne pas commencer le travail metier avant que
`docker compose ps` montre les 3 services up et que les verifications ci-dessus
repondent.
