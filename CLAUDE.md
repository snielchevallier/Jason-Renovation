# CLAUDE.md — Jason Renovation (application de creation de devis)

Application web de redaction et de suivi des devis d'une entreprise de
renovation : Next.js 16 (`frontend/`) + Symfony 7.4 / API Platform 4
(`backend/`) + PostgreSQL 16, orchestres par un unique `compose.yaml`.

- Installation, commandes, adresses, variables d'env, avancement detaille :
  `README.md` (source de verite pour l'humain, ne pas dupliquer ici).
- Conventions backend (Doctrine, API Platform, regles metier des devis) :
  `backend/CLAUDE.md`.

## Regles imperatives

Elles priment sur tout comportement par defaut.

### Securite = priorite

- Deny-by-default sur `/api` : tout endpoint exige un JWT, sauf `POST /api/login`
  et `/api/docs`. Cote web, seuls `/login` (public) et `/admin` (`ROLE_ADMIN`)
  sont couverts par `access_control` : **toute nouvelle route hors `/api` doit
  recevoir sa regle explicite**.
- Deux firewalls distincts : `api` (JWT, stateless) pour le front ; `admin`
  (session, form login sur `/login`, `ROLE_ADMIN`) pour le back-office.
- Aucun secret dans un fichier versionne : les valeurs reelles (APP_SECRET,
  JWT_PASSPHRASE, mots de passe) passent du `.env` racine (non versionne) aux
  conteneurs via `compose.yaml`. Cles JWT `backend/config/jwt/*.pem` non
  versionnees.
- Ne jamais faire confiance aux montants du client : prix, quantites et totaux
  sont recalcules et imposes cote serveur.
- Validation stricte des entrees. Hash de mot de passe natif Symfony.

### Git

- Branches : `develop` (integration), puis `feature/back/<n>-<sujet>` ou
  `feature/front/<n>-<sujet>`. Merge en `--no-ff` (pas de squash).
- Commits petits, chacun comprehensible seul, chacun laisse l'app demarrable.
- Jamais de backend et de frontend dans le meme commit.
- Conventional Commits : `feat(backend):`, `chore(frontend):`, `docs:`...

### Documentation a jour

Tout changement structurant (dependance, entite, endpoint, config securite,
service Docker) met a jour, **dans le meme commit**, `README.md` et la section
« Etat actuel » ci-dessous.

### Definition de fini (avant chaque commit)

- Backend : `docker compose exec backend composer test` vert et
  `docker compose exec backend php bin/console doctrine:schema:validate` vert.
- Frontend : `docker compose exec frontend pnpm lint` vert.
- README + « Etat actuel » a jour si changement structurant.
- Un seul perimetre (back **ou** front) par commit.

Pas d'analyse statique PHP (phpstan, cs-fixer) installee a ce jour.

## Conventions d'infrastructure

- composer / pnpm / bin/console **toujours** via `docker compose exec` ou
  `run --rm --no-deps`, jamais depuis l'hote (deps dans des volumes Linux).
- Code monte dans les conteneurs : pas de rebuild sauf si un `Dockerfile`
  change. `docker compose down -v` **efface la base** et les dependances.
- `DATABASE_URL` est construit dans `compose.yaml` depuis `POSTGRES_*` : ne
  jamais le coder en dur dans `backend/.env`.
- Frontend -> API : `http://backend` en SSR (reseau Docker, `INTERNAL_API_URL`),
  `http://localhost:8000` dans le navigateur (`NEXT_PUBLIC_API_URL`). Demander
  `Accept: application/json` pour une reponse sans enveloppe Hydra.
- Ne pas ajouter : service Docker (Nginx, Redis, Mailpit, worker...),
  `compose.override.yaml`, cle `name:` dans le compose, Caddyfile ou config PHP
  FrankenPHP (mode classique, pas worker) — sauf besoin reel et explicite.
- Second frontend = nouveau service sur le modele de `frontend` (autre dossier,
  autre port, meme Dockerfile).

## Pieges connus

- **Tests** : toujours `docker compose exec backend composer test`, jamais
  `bin/phpunit` nu (le conteneur a `APP_ENV=dev`, qui gagne sur
  `phpunit.dist.xml`). Base de test a creer une fois (README §8).
- **Timestamps** : une entite qui utilise `Trait\TimestampableTrait` doit porter
  `#[ORM\HasLifecycleCallbacks]`, sinon colonnes NULL sans erreur.
- **Identity map en test** : apres une requete qui echoue, l'entite en memoire
  reste mutee. `$entityManager->clear()` avant de relire l'etat pour asserter.
- **CSRF** : toujours en session (`config/packages/csrf.yaml`). Ne pas reactiver
  le CSRF « stateless » de la recipe `symfony/form` (exige un JS absent).
- **CSRF en test** (actions EasyAdmin type DELETE) : generer le jeton via le
  service du conteneur en poussant la requete courante sur `request_stack`
  (modele : `tests/Functional/UserCrudGuardTest.php`).
- **Hot reload front** : Next.js en webpack (`next dev --webpack`) +
  `WATCHPACK_POLLING=1000`. Turbopack ne voit pas les changements sur montage
  Windows : ne pas y revenir sans revalider.
- **EasyAdmin 5.x** : `MenuItem::linkTo(CrudFqcn, ...)` (pas `linkToCrud`),
  `FormField::addFieldset()` (pas `addPanel`), `setHelp()` refuse `null`.
- **JWT et `lastLoginAt`** : l'API re-authentifie a chaque requete ; tout
  traitement « a la connexion » doit filtrer sur le firewall (`admin`).

## Briques a reutiliser

- `Trait\TimestampableTrait` : `createdAt`/`updatedAt`, groupe `timestamps:read`,
  jamais ecrits par le client.
- `Embeddable\Adresse` (groupes `adresse:read`/`adresse:write`) : pour `Client`,
  prevue pour `Chantier`.
- Serialisation : groupes `<entite>:read` / `<entite>:write`, plus ceux des
  embeddables listes dans le contexte de la ressource.
- Entites metier (`Client`, puis Chantier, Devis...) : ecrites depuis l'API
  (`security: "is_granted('ROLE_USER')"`). Entites de reference (`Tva`, `Unite`,
  `Entreprise` singleton) : lecture seule sur l'API, ecriture via back-office.
- `actif`/`position` : on ne supprime jamais une valeur referencee par un devis,
  on la desactive.
- Back-office : un `*CrudController` par entite metier, meme perimetre que
  l'API, menu « Metier » / « Administration » ; champs d'embeddable en notation
  pointee (`adresse.ville`), `onlyOnForms()`.
- `App\Exception\AdminGuardException` : pour tout garde-fou metier dans
  `/admin` (converti en flash + redirection), jamais une `\RuntimeException`.
- Tests : `tests/Support/AuthenticatesApiClient.php` (client porteur d'un JWT),
  `tests/Support/CreatesUsers.php`, `tests/Support/ApiTestCase.php`
  (`$alwaysBootKernel = true`).
- Tests ecrits **en meme temps** que le code : `CalculateurDevis` et
  `GenerateurNumeroDevis` en tests unitaires purs (sans framework ni DB), le
  reste (entites, `/api/devis`) en tests fonctionnels.

## Etat actuel

```text
Phase courante : Phase 5 — Coeur metier (backend) — EN COURS
Fait en Phase 5 : Client (API CRUD, back-office, tests)
Reste Phase 5    : Chantier, Catalogue de prestations, Devis, Lignes de devis
Ensuite          : Phase 6 operations devis (statuts, duplication, verrou),
                   Phase 7 CMS leger, Phase 8 qualite finale
Hors perimetre   : generation PDF, envoi au client
```

Phases 1 a 4 terminees (socle Docker, auth, entites de reference, back-office,
tests et gestion d'erreurs) : detail dans README §12 et l'historique Git.
