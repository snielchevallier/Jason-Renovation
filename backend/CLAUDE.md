# CLAUDE.md — Backend (Symfony 7.4 + API Platform)

Regles specifiques au dossier `backend/`. Complete le `CLAUDE.md` racine.

## Executer les commandes

Toujours via le conteneur, jamais depuis l'hote :

```bash
docker compose exec backend php bin/console <cmd>
docker compose exec backend composer <cmd>
```

## Authentification

- Firewall `login` : `POST /api/login`, `json_login` sur `{email, password}`,
  renvoie un token JWT (handlers Lexik).
- Firewall `api` : `^/api`, `stateless: true`, `jwt: ~`. Auth par
  `Authorization: Bearer <token>`.
- Firewall `admin` (a venir) : `^/admin`, session + form login, `ROLE_ADMIN`.
- Cles JWT : `backend/config/jwt/*.pem`, **non versionnees**. Regeneration :
  `php bin/console lexik:jwt:generate-keypair`. `JWT_PASSPHRASE` vient de l'env
  (compose.yaml -> .env racine), jamais ecrite en dur dans `backend/.env`.
- `access_control` : seuls `^/api/login$` et `^/api/docs` sont publics ;
  tout le reste de `^/api` exige `IS_AUTHENTICATED_FULLY`.
- Prevoir un rate limiting sur `/api/login`.

## Doctrine / migrations

- Une migration par changement de schema. Renseigner `getDescription()`.
- `php bin/console doctrine:schema:validate` doit etre vert avant de committer.
- PostgreSQL : les identifiants reserves (`user`...) sont quotes par Doctrine.

## API Platform

- Pas d'entite exposee sans intention : `#[ApiResource]` ajoute explicitement.
- Groupes de serialisation `read` / `write` explicites, jamais l'expo par defaut
  de tous les champs.
- Securite par operation (`security:` sur les operations), en coherence avec
  le deny-by-default.
- Prix / quantites / totaux : calcules par un service cote serveur (State
  Processor ou listener Doctrine), jamais acceptes depuis la requete.

## Modele de donnees

- Reference : `../../DEVIS-ARTISAN/ETUDE/modele-donnees-devis.drawio` et
  `wireframes-appli-devis.html` (hors repo).
- Lignes de devis : figer (copier) designation, prix unitaire, unite et taux
  TVA au moment de l'ajout depuis le catalogue. `catalogue_prestation_id` reste
  une simple trace d'origine.
- Numero de devis : `AAAA-NNNN`, sequentiel sans trou, remis a zero chaque
  annee, attribue a la creation.
- Regle d'arrondi (identique front/back) : 2 decimales half-up, total ligne
  arrondi d'abord, TVA calculee sur base arrondie, puis somme.

## Scaffolding

`maker-bundle` est autorise pour generer (entites, migrations, users), mais
relire et ajuster systematiquement le code genere.
