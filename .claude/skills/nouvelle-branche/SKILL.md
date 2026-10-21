---
name: nouvelle-branche
description: Crée une branche git dédiée avant de commencer une nouvelle fonctionnalité, une correction ou une étape de la feuille de route. À utiliser AVANT d'écrire le moindre code dès que l'utilisateur demande quelque chose de nouveau (« commence l'étape 5 », « ajoute… », « corrige… », « passe à… »), ou quand il tape /nouvelle-branche.
argument-hint: "[description courte de la fonctionnalité]"
---

# Nouvelle branche avant une fonctionnalité

But : chaque fonctionnalité vit sur sa propre branche, partie d'une base à jour, avec un nom prévisible.
Ce skill **crée la branche, et rien d'autre** : il ne commite pas, ne pousse pas, ne fusionne pas.

Sujet de la branche : `$ARGUMENTS` (sinon, déduis-le de la demande de l'utilisateur).

## 1. Est-ce bien utile ?

Ne crée pas de branche et continue directement si :
- la demande est une question, une explication ou une revue, sans modification de code ;
- la branche courante est **déjà** dédiée à ce sujet (ex. on est sur `feature/etape-5-frontend` et on continue l'étape 5).

## 2. État du dépôt

```bash
git status --porcelain
git branch --show-current
git fetch origin --prune        # si un remote existe ; en cas d'échec réseau, continuer en local et le signaler
```

S'il y a des modifications non commitées : **arrête-toi et demande** à l'utilisateur ce qu'il veut :
1. les commiter d'abord sur la branche courante (il le fera, ou te le demandera explicitement) ;
2. les emporter sur la nouvelle branche (`git switch -c` les garde telles quelles) ;
3. les mettre de côté (`git stash push -u -m "<sujet>"`).

Ne jette jamais de modification (`checkout -- .`, `reset --hard`, `clean`).

## 3. Choisir la base

- Par défaut : `main`, mise à jour (`git switch main && git pull --ff-only`).
- Si la branche courante contient des commits **absents de `main`** (`git log --oneline main..HEAD`) et que la
  nouvelle fonctionnalité en dépend (c'est le cas des étapes de la feuille de route, qui s'enchaînent) :
  propose de partir de la branche courante, et dis combien de commits ne sont pas encore dans `main`.
  L'utilisateur choisit ; recommande de fusionner d'abord dans `main` si le travail précédent est terminé.
- Si `pull --ff-only` échoue (historiques divergents) : arrête-toi et explique, sans rebase ni merge.

## 4. Nommer la branche

Format : `<type>/<sujet>`

| Type | Quand |
|---|---|
| `feature/` | nouvelle fonctionnalité, étape de la feuille de route |
| `fix/` | correction de bug |
| `refactor/` | restructuration sans changement de comportement |
| `docs/` | documentation seule |
| `chore/` | outillage, dépendances, configuration, CI |
| `test/` | tests seuls |

Sujet : minuscules, mots séparés par des tirets, **sans accents ni caractères spéciaux**, 3 à 5 mots,
40 caractères au plus. Pour une étape de la feuille de route (`CLAUDE.md`), préfixe par son numéro.

Exemples : `feature/etape-5-frontend-react`, `feature/etape-6-garanties-optionnelles`,
`fix/arrondi-taxe-timbre`, `chore/ci-github-actions`.

Vérifie que le nom est libre, localement et sur le remote :

```bash
git rev-parse --verify --quiet "refs/heads/<nom>"
git ls-remote --heads origin "<nom>"
```

S'il existe déjà : demande s'il faut reprendre cette branche (`git switch <nom>`) ou en choisir un autre.

## 5. Créer la branche

Annonce en une ligne le nom et la base retenus, puis :

```bash
git switch -c <nom> <base>
```

Ne pousse pas la branche. Si l'utilisateur le demande ensuite : `git push -u origin <nom>`.

## 6. Compte rendu

Termine par trois lignes au plus : la branche créée, sa base (et le nombre de commits d'avance sur `main`
si elle ne part pas de `main`), et ce qui a été fait des modifications en cours. Puis passe à la
fonctionnalité demandée.

## Règles

- Jamais de `push --force`, `reset --hard`, `branch -D`, `rebase` ni suppression de branche.
- Jamais de commit pendant ce skill.
- Aucune mention de Claude dans les noms de branche, les commits ou les PR (voir `.claude/settings.json`).
