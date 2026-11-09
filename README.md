# SIRH Payroll API — Backend Laravel 11

Backend REST API pour un système de paie multi-pays (SaaS), construit autour
d'une architecture en couches (Domain / Application / Infrastructure / Engines)
et d'un moteur de calcul personnalisable et sûr (sans `eval`).

---

## 1. Architecture

```
app/
├── Domain/                # Modèles Eloquent + interfaces (cœur métier)
│   ├── Audit/Models/
│   ├── Company/Models/    # Country, Company
│   ├── Compliance/Models/ # ComplianceObligation, Declaration
│   ├── Employee/{Models,Repositories}/
│   ├── Payroll/{Models,Repositories}/
│   └── Tax/{Models,Repositories}/
│
├── Application/           # Cas d'usage et DTOs (pas de SQL ici)
│   ├── DTO/
│   └── Services/          # EmployeeService, PayrollService, AuditLogger…
│
├── Infrastructure/        # Implémentations techniques
│   └── Repositories/Eloquent/
│
├── Engines/               # Cœur de calcul, sans logique HTTP
│   ├── FormulaEngine.php  # Parser sécurisé (Shunting-Yard, sans eval)
│   ├── TaxEngine.php
│   ├── ModifierEngine.php
│   └── PayrollEngine.php
│
├── Http/
│   ├── Controllers/Api/   # Pas de logique métier — uniquement orchestration
│   ├── Requests/          # FormRequests pour validation
│   └── Resources/         # API resources (transformation de sortie)
│
└── Providers/
    └── DomainServiceProvider.php  # Bind des interfaces → impls Eloquent
```

Principes appliqués :
- **SRP** : chaque engine a une seule responsabilité (formules, taxes, modificateurs, orchestration).
- **OCP** : ajouter un pays = ajouter un seeder + des règles, sans toucher au moteur.
- **DIP** : les services dépendent d'interfaces (`*RepositoryInterface`), implémentées dans `Infrastructure/`.
- **Pas de logique métier dans les Controllers** : ils délèguent aux services applicatifs.

---

## 2. Stack & prérequis

- PHP 8.2+ (testé avec 8.3.16)
- Composer 2
- SQLite (par défaut) ou MySQL/PostgreSQL — voir `.env`
- Laravel 11 + Sanctum

---

## 3. Démarrage rapide

```bash
composer install
cp .env.example .env
php artisan key:generate

# La DB par défaut est SQLite (database/database.sqlite). Pour MySQL,
# configurez DB_* dans .env puis réinitialisez les données de démonstration :

php artisan demo:reset
php artisan serve
```

`demo:reset` supprime toutes les tables et recrée la base avec les migrations et
les seeders de démonstration enregistrés dans `DatabaseSeeder`. La commande est
limitée aux environnements `local` et `testing` et demande confirmation.
Pour l'exécuter sans invite dans ces environnements : `php artisan demo:reset --force`.

Les seeders fournissent notamment :
- Les pays et barèmes fiscaux configurés, dont le Maroc et la RDC.
- L'entreprise démo `Acme Maroc SARL`, plusieurs employés et des structures salariales.
- Des présences sur le mois en cours et le précédent, pauses, heures supplémentaires et régularisations.
- Des congés avec plusieurs statuts, soldes et ajustements, jours fériés et repos compensatoires.
- Des employés, départements, postes, équipes, sites et horaires de démonstration.
- Des notes de frais et prêts, ainsi que des bulletins et structures de paie.
- Des documents et demandes de documents, du matériel avec demandes et attributions.
- Des formations et inscriptions, des offres et candidatures de démonstration.
- Des abonnements, factures et données de paie de test multi-pays.

---

## 4. Endpoints API

Toutes les routes sont préfixées par `/api/v1`.

### Employés
| Méthode | URL | Description |
|---|---|---|
| `GET`    | `/employees`            | Liste paginée |
| `POST`   | `/employees`            | Créer |
| `GET`    | `/employees/{id}`       | Détail |
| `PUT`    | `/employees/{id}`       | Modifier |
| `DELETE` | `/employees/{id}`       | Soft-delete |

### Composants de paie
| Méthode | URL | Description |
|---|---|---|
| `GET`    | `/salary-components`    | Liste paginée (`?company_id=` optionnel) |
| `POST`   | `/salary-components`    | Créer (validation incluse pour les formules) |
| `GET`    | `/salary-components/{id}` | Détail |
| `PUT`    | `/salary-components/{id}` | Modifier |
| `DELETE` | `/salary-components/{id}` | Soft-delete |

### Structure salariale d'un employé
| Méthode | URL | Description |
|---|---|---|
| `GET`  | `/employees/{id}/structure` | Historique des versions |
| `POST` | `/employees/{id}/structure` | Créer une nouvelle version (la précédente est automatiquement clôturée) |

### Taxes légales
| Méthode | URL | Description |
|---|---|---|
| `GET`    | `/legal-taxes`     | Liste avec barèmes (`?country_id=` optionnel) |
| `POST`   | `/legal-taxes`     | Créer (avec `brackets[]` pour les barèmes progressifs) |
| `GET`    | `/legal-taxes/{id}` | Détail |
| `PUT`    | `/legal-taxes/{id}` | Mettre à jour (remplace les `brackets[]` si fournis) |
| `DELETE` | `/legal-taxes/{id}` | Soft-delete |

### Bulletins de paie
| Méthode | URL | Description |
|---|---|---|
| `GET`    | `/payrolls`             | Liste paginée (`?period=YYYY-MM` & `?company_id=`) |
| `POST`   | `/payrolls/preview`     | Prévisualiser le calcul SANS persister |
| `POST`   | `/payrolls/generate`    | Calculer et persister |
| `GET`    | `/payrolls/{id}`        | Détail avec lignes |
| `DELETE` | `/payrolls/{id}`        | Soft-delete |

---

## 5. Exemples d'appels

### Générer une paie
```bash
curl -X POST http://127.0.0.1:8000/api/v1/payrolls/generate \
  -H 'Content-Type: application/json' \
  -d '{"employee_id": 1, "period": "2026-04"}'
```

Réponse résumée pour l'employé démo :

```
base_salary             = 12 000.00 MAD
gross                   = 14 900.00 MAD   (12000 + 500 + 1200 + 1200)
taxable                 = 13 794.46 MAD   (gross taxable - cotisations CNSS/AMO)
net                     = 11 537.67 MAD   (avec bonus Ramadan +1500)
total_employer_charges  =    791.65 MAD
total_employee_charges  =  3 862.33 MAD
```

### Créer un composant de paie avec formule
```bash
curl -X POST http://127.0.0.1:8000/api/v1/salary-components \
  -H 'Content-Type: application/json' \
  -d '{
    "company_id": 1,
    "country_id": 1,
    "code": "PRIME_PERFORMANCE",
    "name": "Prime de performance",
    "type": "earning",
    "calculation_type": "formula",
    "formula": "IF(BASE_SALARY > 10000, BASE_SALARY * 0.15, BASE_SALARY * 0.10)",
    "is_taxable": true,
    "affects_gross": true,
    "affects_net": true,
    "sequence": 40
  }'
```

### Affecter une nouvelle structure salariale
```bash
curl -X POST http://127.0.0.1:8000/api/v1/employees/1/structure \
  -H 'Content-Type: application/json' \
  -d '{
    "name": "Structure 2027",
    "base_salary": 14000,
    "currency": "MAD",
    "effective_from": "2027-01-01",
    "status": "active",
    "lines": [
      {"salary_component_id": 1, "sequence": 10},
      {"salary_component_id": 2, "rate_override": 0.12, "sequence": 20},
      {"salary_component_id": 3, "sequence": 30}
    ]
  }'
```

---

## 6. Le moteur de paie en détail

### 6.1 Pipeline (`PayrollEngine`)

```
1. Charger structure active de l'employé pour la période
2. Initialiser : BASE = GROSS = TAXABLE = NET = base_salary
3. Modificateurs sur BASE  (proration, retro)
4. Composants en ordre topologique (depends_on) :
     - earnings  → GROSS +=
     - deductions → GROSS -=
5. Modificateurs sur GROSS
6. TAXABLE = somme des composants taxables impactant le GROSS
7. Taxes BASE_BASE / BASE_GROSS  (CNSS, AMO, INPP, ONEM…)
       certaines réduisent TAXABLE (reduces_taxable=true)
8. Modificateurs sur TAXABLE
9. Taxes BASE_TAXABLE  (IR, IPR — généralement progressives)
10. Modificateurs sur NET (avances, saisies, primes exceptionnelles)
11. Récapitulatif + audit trail complet (tableau `audit` du PayrollResult)
```

### 6.2 FormulaEngine — sécurité

- **Aucun `eval()`**, `call_user_func`, `Reflection` ou closure externe.
- Tokenizer + Shunting-Yard + interpréteur pile RPN.
- Variables résolues via le contexte injecté (`BASE_SALARY`, `GROSS`, `TAXABLE`, `NET`, et tout
  code de composant/taxe déjà calculé : `PRIME_LOGEMENT`, `TAX_CNSS`, etc.).
- Variables manquantes → 0 (tolérant), évite de casser des bulletins en production.
- Opérateurs : `+ - * / % ^`, `< <= > >= == != && || !`.
- Fonctions : `MIN`, `MAX`, `SUM`, `IF(cond,a,b)`, `ROUND`, `CEIL`, `FLOOR`, `ABS`.

Exemple d'expressions valides :
```
BASE_SALARY * 0.05
MIN(BASE_SALARY * 0.10, 2000)
IF(GROSS > 10000, GROSS * 0.20, GROSS * 0.10)
ROUND((BASE - 3000) * 0.34 - 1433.33, 2)
```

### 6.3 TaxEngine — barèmes

Deux modes auto-détectés :
- **Per-slice** (vrai progressif, ex. IPR) : taxe chaque tranche individuellement.
- **Bracket-with-deduction** (style IR Maroc) : `taxe = base × taux − déduction_fixe`
  selon la tranche dans laquelle tombe la base.

Surcharges entreprise : table `company_taxes` permet d'override `employer_rate`,
`employee_rate`, `ceiling` pour une société donnée.

### 6.4 ModifierEngine

Cibles : `BASE`, `GROSS`, `TAXABLE`, `NET`.
Types : `fixed`, `percent`, `formula`.
Portée : `global` (entreprise) ou `employee`.
Restriction temporelle via `period_from` / `period_to` (`YYYY-MM`).

---

## 7. Cycle recrutement et intégration

Un candidat à l’étape `offer` peut être embauché avec
`POST /api/v1/candidates/{candidate}/hire`. Cette opération crée le dossier
employé et une checklist d’intégration générique dans la même transaction ;
elle ne crée pas automatiquement de compte utilisateur ni de règles légales
propres à un pays.

La checklist est consultable avec `GET /api/v1/employees/{employee}/onboarding`
et chaque étape se met à jour avec
`PATCH /api/v1/employees/{employee}/onboarding/{task}` (`pending` ou
`completed`). Le statut employé passe à `active` lorsque toutes les étapes sont
terminées. Les actions d’embauche et d’intégration sont réservées aux comptes
autorisés à gérer les employés.

---

## 8. Audit

Chaque opération métier (création/édition d'employé, génération de paie,
modification d'une taxe…) est tracée dans la table `audit_logs` via
`AuditLogger`. Pour les bulletins, le `snapshot` JSON contient en plus
le détail variable par variable au moment du calcul.

---

## 9. Tests rapides

```bash
# Lister les routes
php artisan route:list --path=api

# Re-seed
php artisan migrate:fresh --seed

# Faire une preview en console (via tinker)
php artisan tinker
>>> app(App\Application\Services\PayrollService::class)
>>>     ->preview(App\Domain\Employee\Models\Employee::first(), '2026-04')
>>>     ->summary();
```

---

## 10. Pour aller plus loin

- Authentification : Sanctum est pré-installé (`/api/user`). Ajoutez le middleware
  `auth:sanctum` sur les groupes de routes en production.
- Multi-tenancy SaaS : ajouter un scope `tenant_id` sur les modèles principaux,
  ou utiliser un package comme `stancl/tenancy`.
- Génération PDF des bulletins : brancher `dompdf` ou `Browsershot` sur le
  `PayrollResource`.
- Frontend Vue 3 : tous les endpoints sont REST, paginés, et utilisent le
  format `data` standard de Laravel API Resources.
