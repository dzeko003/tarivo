# CLAUDE.md — Moteur de tarification automobile (zone CIMA)

## Le projet

Application qui calcule une prime d'assurance auto à partir des facteurs de risque, avec :
- une **API de calcul** de devis ;
- une **interface de devis en ligne** (formulaire en plusieurs étapes et résultat détaillé) ;
- un **historique des simulations** ;
- un **back-office** pour gérer les versions de tarif.

Contexte métier : zone **CIMA** (Afrique francophone), où la **RC auto est obligatoire**.
Projet de portfolio : il doit montrer qu'on sait traduire un barème en **règles testables et versionnées**.

### Périmètre : une seule compagnie fictive

- L'application est l'**outil interne de tarification d'une seule compagnie d'assurance fictive**,
  **« Sahel Assurances »**. Tous les tarifs, devis, agents et tarificateurs sont les siens.
- Ce **n'est pas un comparateur** : on n'interroge pas d'autres assureurs et on n'affiche pas plusieurs prix
  concurrents. Un devis = le prix de Sahel Assurances.
- **Pas de multi-compagnies (multi-tenant) pour l'instant.** Ne pas ajouter de `company_id` ni de logique
  multi-compagnies. C'est une évolution possible plus tard, hors du périmètre actuel.

### Utilisateurs

| Rôle | Usage |
|---|---|
| Client (particulier) | Simulation de devis en ligne |
| Agent commercial | Devis pour ses clients, historique, PDF |
| Tarificateur | Saisie des nouvelles versions de tarif, comparaison entre deux versions |
| Administrateur | Gestion des comptes et des rôles, consultation de l'audit |

> Les barèmes utilisés sont **fictifs mais réalistes** et appartiennent à Sahel Assurances. Ne jamais
> présenter des montants ou des taux comme des valeurs CIMA officielles ou comme les tarifs d'un assureur
> réel. Le README doit le préciser.

## Stack

Deux applications séparées dans le même dépôt : une **API Laravel** (sans vues Blade) et une **SPA React**.

### Backend : `backend/` (API REST uniquement)
- **PHP 8.3+ / Laravel 13**, en mode API (`php artisan install:api`, à faire à l'étape 4)
- **PostgreSQL**
- **Pest** pour les tests
- `brick/math` 1.x pour les calculs monétaires (`BigDecimal`, `RoundingMode::HalfUp` est un enum)
- `dedoc/scramble` pour la documentation OpenAPI (servie sur `/docs/api`)
- API Resources Laravel pour les réponses JSON et Form Requests pour la validation
- `maatwebsite/excel` pour importer les grilles, `spatie/laravel-pdf` pour les devis en PDF
- **Sanctum** pour l'authentification (mode SPA par cookie, même domaine parent) et
  `spatie/laravel-permission` pour les rôles
- `spatie/laravel-activitylog` pour l'audit des changements de tarif

### Frontend : `frontend/` (SPA)
- **React + TypeScript**, avec **Vite**
- **React Router** pour la navigation
- **TanStack Query** pour les appels API (cache, chargement, erreurs)
- **React Hook Form + Zod** pour le formulaire de devis en plusieurs étapes
- **Tailwind CSS + shadcn/ui** pour l'interface
- Types TypeScript **générés depuis l'OpenAPI** de l'API (`openapi-typescript`), pour ne pas les recopier à la main
- **Vitest + Testing Library** pour les tests ; Playwright pour un ou deux parcours de bout en bout (optionnel)

### Infra
- Docker Compose (api, postgres, frontend), CI avec GitHub Actions (tests back et front)

## Règles non négociables

1. **Jamais de `float` pour de l'argent.** Montants finaux en **entiers (francs CFA, XAF/XOF)**, calculs
   intermédiaires en `BigDecimal` (brick/math). Arrondi **uniquement aux étapes définies** et documentées
   (par défaut : arrondi au franc, `RoundingMode::HALF_UP`, sur la prime de chaque garantie puis sur les taxes).
2. **Les tarifs sont des données, pas du code.** Bases, coefficients et tranches sont en base de données,
   rattachés à une `tariff_version`. Le code contient uniquement la logique de calcul.
3. **Une version de tarif `published` ne peut plus être modifiée**, grilles comprises (blocage par observer
   Eloquent et, si possible, par contrainte ou trigger en base). Pour corriger, on crée une nouvelle version.
4. **Un devis enregistré est figé** : il conserve les données saisies (`input`), l'id de la version de tarif,
   le détail complet du calcul (`breakdown`) et le total. Il n'est jamais recalculé silencieusement.
5. **Reproductibilité** : recalculer un devis avec sa version de tarif doit donner **exactement** le même
   résultat. C'est garanti par un test.
6. **Explicabilité** : chaque étape du calcul ajoute une ligne au détail (libellé, coefficient ou montant,
   sous-total). Aucune transformation n'est cachée.
7. **Moteur découplé de Laravel** : `app/Pricing/` contient des classes PHP pures (aucun Eloquent, aucune
   façade). Elles reçoivent un DTO d'entrée et une grille de tarif chargée, puis renvoient un résultat.
8. **Le frontend ne calcule jamais de prime.** Il affiche ce que renvoie l'API (`POST /api/quotes/calculate`).
   Sa validation (Zod) sert au confort de l'utilisateur ; la validation qui fait foi est celle de l'API.

## Modèle métier

### Formule de calcul (ordre du pipeline)

```
Prime de base (catégorie × tranche de puissance fiscale)
  × coef. usage
  × coef. âge du conducteur
  × coef. ancienneté du permis
  × coef. zone
  × coef. bonus-malus
= Prime RC nette
+ garanties optionnelles (défense-recours, incendie, vol, bris de glace, DTA, individuelle conducteur, assistance)
× prorata de durée (1, 3, 6 ou 12 mois)
+ accessoires (coût de police)
+ taxes et contributions (paramétrées par pays)
= Prime TTC
```

### Facteurs de risque (entrées)

- Catégorie de véhicule (usage CIMA) : promenade/affaires, transport pour propre compte, transport public
  de marchandises, transport public de voyageurs/taxi, deux-roues…
- Puissance fiscale (CV), en tranches
- Date de naissance du conducteur, d'où l'âge à la date d'effet
- Date d'obtention du permis, d'où l'ancienneté à la date d'effet
- Zone géographique
- Coefficient bonus-malus
- Date d'effet et durée du contrat
- Pays (devise, taxes)

### Points d'attention

- Âge et ancienneté se calculent **à la date d'effet du contrat**, pas à la date du jour.
- Toutes les tranches (âge, permis, puissance fiscale) suivent la convention **`[min, max)`** : min inclus,
  max exclu, `max = null` = sans limite. Ex. tranche âge `[21, 25)` = 21 à 24 ans. Tester les valeurs pile aux bornes.
- Âge et ancienneté = **années révolues**. Né un 29 février : l'anniversaire compte le 1er mars les années non bissextiles.
- La version de tarif appliquée est celle dont `valid_from <= date_effet < valid_to` (`valid_to` nul = en cours).
  Les périodes d'un même pays ne doivent pas se chevaucher.
- Validations : âge minimum, permis obtenu après l'âge légal, puissance fiscale > 0, bonus-malus dans les bornes.

## Architecture

```
backend/
  app/
    Pricing/                 # Moteur pur, sans dépendance Laravel
      Data/                  # DTO : QuoteInput, TariffGrid, PricingResult, BreakdownLine
      Steps/                 # Une classe par étape du pipeline
      PricingContext.php     # Objet transporté à travers le pipeline
      Pricer.php             # Point d'entrée : price(QuoteInput, TariffGrid): PricingResult
    Models/                  # TariffVersion, BasePremium, Coefficient, Tax, Quote…
    Observers/               # Blocage des modifications sur les versions publiées
    Http/
      Controllers/Api/       # QuoteController, TariffVersionController…
      Requests/              # Validation des entrées (Form Requests)
      Resources/             # Format JSON des réponses (API Resources)
  routes/api.php
  tests/
    Unit/Pricing/            # Tests par étape, sans base de données
    Feature/                 # API, versions de tarif, recalcul
    Fixtures/                # Profils de référence et primes attendues
frontend/
  src/
    api/                     # Client HTTP, types générés depuis l'OpenAPI, hooks TanStack Query
    features/
      quote/                 # Formulaire de devis en étapes, affichage du détail du calcul
      history/               # Historique des simulations
      tariffs/               # Back-office des versions de tarif (rôle tarificateur/admin)
    components/ui/           # Composants shadcn/ui
    routes/                  # Pages et routing
```

### Endpoints de l'API

| Méthode | Route | Rôle |
|---|---|---|
| POST | `/api/quotes/calculate` | Calcule une prime sans l'enregistrer |
| POST | `/api/quotes` | Calcule et enregistre un devis |
| GET | `/api/quotes` | Historique (filtres, pagination) |
| GET | `/api/quotes/{id}` | Détail d'un devis |
| POST | `/api/quotes/{id}/recompute` | Recalcule avec sa version de tarif et compare |
| GET | `/api/quotes/{id}/pdf` | Devis en PDF |
| GET | `/api/reference-data` | Listes pour le formulaire (catégories, zones, garanties…) |
| GET/POST | `/api/tariff-versions` | Lister et créer des versions (back-office) |
| POST | `/api/tariff-versions/{id}/publish` | Publier une version (elle devient non modifiable) |
| GET | `/api/tariff-versions/{a}/compare/{b}` | Comparer deux versions |

Format d'erreur : réponse 422 standard de Laravel (`message` + `errors` par champ), que le formulaire React
affiche sous chaque champ.

### API (état actuel, étape 4)

- Faits : `quotes/calculate`, `quotes` (index, store, show), `quotes/{id}/recompute`, `reference-data`.
  Contrôleurs `Api\QuoteController` et `Api\ReferenceDataController` ; doc OpenAPI (Scramble) sur `/docs/api`.
  Pas encore d'authentification (étape 8) : tout est ouvert, `user_id` reste vide.
- Chemin d'un calcul : `QuoteRequest::quoteInput()` (saisie normalisée) → `TariffVersion::applicableOn()` →
  `toGrid()` → `Pricer::price()` → `PricingResultResource`.
- Validation en deux temps, même format 422 : la forme dans `QuoteRequest`, puis les règles du barème par le
  moteur. `InvalidQuoteInput` et `NoApplicableTariffVersion` (sur `effective_date`) sont converties en
  `ValidationException` dans `bootstrap/app.php`. Messages en français : `lang/fr/validation.php`.
- `country` facultatif (`TariffVersion::DEFAULT_COUNTRY` = CM) ; devise tirée de la version de tarif.
- `bonus_malus` et tous les coefficients/sous-totaux circulent en **chaînes décimales** (un nombre JSON est
  refusé pour `bonus_malus`) ; les montants en entiers.
- Durée : `Quote::AVAILABLE_DURATIONS` = [12] tant que le prorata (étape 6) n'existe pas.
- Devis enregistré : `input` = saisie normalisée, `breakdown` = `PricingResultResource::payload()` (la réponse
  de calcul complète), référence `DEV-<année>-<id sur 6 chiffres>` attribuée à la création (`Quote::booted()`).
- Recalcul : avec la version **du devis** (même archivée), sans jamais modifier le devis ; `identical`
  compare détail et total (clés triées, car jsonb ne garde pas l'ordre) et un écart est journalisé.

Le pipeline utilise `Illuminate\Pipeline\Pipeline` dans la couche applicative, ou une simple boucle sur les
étapes dans `Pricer` pour rester sans dépendance Laravel.

### Moteur (état actuel, étape 1)

- Entrée : `QuoteInput` + `TariffGrid` → `Pricer::price()` → `PricingResult` (montants entiers + `lines`).
- `QuoteInputValidator` vérifie tout avant le calcul et lève `InvalidQuoteInput` avec **toutes** les erreurs
  par champ (clés snake_case de l'API).
- `PricingContext` est le seul à modifier le montant : chaque opération ajoute sa `BreakdownLine`.
  Phase 1 = base + coefficients (exact, non arrondi) ; arrondi au franc ; phase 2 = accessoires + taxes.
- Barème de test : `tests/Fixtures/SahelTariff2025.php`. Les primes attendues des profils de référence sont
  calculées par un script Python **indépendant** (`reference_oracle_2025.py`) : ne jamais les recopier depuis
  la sortie du moteur PHP.

### Schéma de données (grandes lignes)

- `tariff_versions` : id, code (= `TariffGrid::$version`), country, currency, label, valid_from, valid_to,
  status (draft|published|archived), published_at, policy_fee, bonus_malus_min/max, minimum_driver_age,
  minimum_license_age
- `base_premiums` : tariff_version_id, vehicle_category, cv_min, cv_max, amount
- `coefficients` : tariff_version_id, factor (usage|zone|age|license), `key` (usage, zone) ou
  `band_min`/`band_max` (âge, permis), value, label. Le bonus-malus est une saisie : seules ses bornes
  sont au barème, dans `tariff_versions`.
- `optional_covers` : tariff_version_id, code, mode de calcul, valeur, franchise (étape 6)
- `taxes` : tariff_version_id, position (ordre d'application), code, label, rate + base, ou fixed_amount
- `quotes` : id, reference, effective_date, duration_months, tariff_version_id, input (jsonb),
  breakdown (jsonb), total_ttc, created_at, user_id (nullable)

Choix d'implémentation (PostgreSQL uniquement) :
- coefficients, taux et bornes de bonus-malus en `numeric` **sans échelle imposée** (+ `CHECK scale() <= 4`) :
  « 1.15 » se relit « 1.15 », d'où un détail de calcul identique au fixture. Cast Eloquent `AsBigDecimal`
  (refuse les `float`).
- Règles du barème aussi garanties par la base : `CHECK` et `EXCLUDE USING gist` (extension `btree_gist`)
  contre les tranches qui se chevauchent et deux versions **publiées** d'un même pays sur la même période.
- Seule passerelle Eloquent → moteur : `TariffVersion::toGrid()`. Barèmes seedés depuis
  `database/seeders/tariffs/*.php` (`TariffSeeder`).
- Cycle de vie (`TariffVersion`) : `publish()` (brouillon → publiée ; refuse un barème incomplet via
  `App\Pricing\TariffGridValidator` et une période qui chevauche une version publiée), `archive()`
  (publiée → archivée), `closeOn(date)` (ferme une période ouverte pour la version suivante, sans laisser un
  devis hors période). Ce sont les **seuls** changements permis après publication.
- Immutabilité à deux niveaux : observers (`TariffVersionObserver`, `TariffGridRowObserver`, exception
  `TariffVersionLocked`) et triggers PostgreSQL (message `tariff_version_locked: …`) pour ce qui contourne
  Eloquent (`->update()` en masse, `DB::table()`).
- Sélection : `TariffVersion::applicableOn($country, $effectiveDate)` (publiée uniquement), sinon
  `NoApplicableTariffVersion`.
- Le barème 2025 est volontairement saisi deux fois (seeder et `tests/Fixtures/SahelTariff2025.php`) : un
  test de parité vérifie que la base le restitue à l'identique.

## Stratégie de tests

- **Unitaires** : une classe de test par étape, avec les cas aux bornes (25 ans pile, 2 ans de permis pile…).
- **Tests de référence** : dataset Pest de profils avec la prime TTC attendue (calculée à la main ou dans
  Excel, fichier source versionné dans `tests/Fixtures/`). Un écart casse le test.
- **Tests de propriétés** : plus de sinistres ⇒ prime jamais plus basse ; prime ≥ minimum ; total = somme
  des lignes du détail.
- **Non-régression** : un devis enregistré puis recalculé avec sa version donne exactement le même total.
- **Immutabilité** : modifier une version publiée ou ses grilles lève une exception.

## Commandes

```bash
# base de données (depuis la racine) : PostgreSQL sur le port 5435, bases tarivo et tarivo_test
docker compose up -d --wait

# backend (depuis backend/)
composer install
php artisan migrate:fresh --seed          # schéma + barèmes 2025 et 2026
./vendor/bin/pest                         # tous les tests
./vendor/bin/pest tests/Unit/Pricing      # tests du moteur uniquement
./vendor/bin/pint                         # formatage du code
php artisan serve                         # API sur http://localhost:8000/api, doc sur /docs/api
php artisan scramble:export               # exporter l'OpenAPI dans backend/api.json

# profils de référence (depuis backend/tests/Fixtures/) : régénérer les primes attendues
python3 reference_oracle_2025.py reference_profiles_2025.input.csv > reference_profiles_2025.csv
python3 reference_oracle_2026.py reference_profiles_2026.input.csv > reference_profiles_2026.csv

# frontend
# installation :      npm install
# lancer :            npm run dev
# tests :             npm run test
# régénérer les types de l'API
```

## Conventions

- Code, noms de classes et de colonnes **en anglais** ; libellés de l'interface et du détail du calcul **en français**.
- `declare(strict_types=1);` dans tous les fichiers du moteur.
- Les DTO du moteur sont `readonly`.
- Enums PHP pour les catégories, les facteurs et les statuts.
- Toute nouvelle règle tarifaire = une nouvelle étape et ses tests.
- JSON de l'API en `snake_case` ; montants en **entiers** (francs CFA), formatés uniquement à l'affichage côté React.
- Côté React : TypeScript strict, pas de `any`, composants fonctionnels, un dossier par fonctionnalité.

## Feuille de route

1. [x] Moteur pur (`app/Pricing`) et tests unitaires et de référence sur un barème fictif
2. [x] Migrations, modèles et seeders (barème 2025 + barème 2026)
3. [x] Sélection de la version de tarif par date d'effet et immutabilité des versions publiées
4. [x] API : calcul, enregistrement, historique, recalcul ; doc OpenAPI
5. [ ] Frontend React : initialisation, types générés, formulaire de devis en étapes, historique
6. [ ] Garanties optionnelles, prorata de durée, PDF
7. [ ] Back-office React : versions, import Excel, comparaison entre deux versions
8. [ ] Authentification Sanctum, rôles, audit, simulation d'impact d'un nouveau tarif
9. [ ] Docker, CI, déploiement, README
