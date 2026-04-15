# 🚚 Prompt — Ajouter la section "Ramassage" à LivrExpress

> **Objectif** : Créer un module **Ramassage** complet (header mega-menu + sidebar + 3 pages) dans l'application LivrExpress — un backend Symfony qui utilise le thème **Metronic KTUI (Tailwind)** et le moteur de template **Twig**.

---

## 📌 Contexte technique du projet

| Élément | Détail |
|---|---|
| **Framework** | Symfony (PHP 8+) avec Doctrine ORM |
| **Template engine** | Twig (`.html.twig`) |
| **UI Kit** | Metronic KTUI — composants préfixés `kt-` (classes CSS utilitaires Tailwind + kit Metronic) |
| **Layout commun** | `templates/base_dashboard.html.twig` — inclut sidebar, header, footer, extras |
| **Sidebar** | `templates/partials/_dashboard_sidebar.html.twig` |
| **Header mega‑menu** | `templates/partials/_dashboard_header.html.twig` |
| **CSS custom** | `assets/css/dashboard-custom.css` — classes `.dashboard-content-shift`, `.profile-content-shift` |
| **JS commun** | `assets/js/core.bundle.js`, `assets/vendors/ktui/ktui.min.js`, `assets/js/layouts/demo1.js` |
| **Entité existante** | `App\Entity\PickupRequest` — table `pickup_request` (**NE PAS créer de nouvelle table**) |
| **Repository** | `App\Repository\PickupRequestRepository` |

### Structure de l'entité `PickupRequest` (déjà existante)

```php
#[ORM\Table(name: 'pickup_request')]
class PickupRequest
{
    private ?int $id;
    private ?StockProduct $product;        // ManyToOne → StockProduct
    private string $productNameSnapshot;
    private string $city;
    private string $neighborhood;
    private string $address;
    private string $phone;
    private ?string $supplierPhone;
    private ?string $note;
    private bool $hasLabels = true;
    private \DateTimeImmutable $createdAt;
    private ?User $createdBy;              // ManyToOne → User
    private string $status = 'pending';    // 'pending' | 'confirmed' | 'picked_up' | 'cancelled'
}
```

> ⚠️ **Important** : Réutilise la table `pickup_request` existante. Si des champs manquent pour les besoins des pages (ex : `type`, `scheduled_at`, `livreur`, `suivi`…), **ajoute-les à l'entité `PickupRequest`** via une **migration Doctrine** — ne crée PAS une nouvelle entité/table.

---

## 🔧 Fichiers à modifier / créer

### 1. Navigation (Sidebar + Header)

#### Sidebar — `templates/partials/_dashboard_sidebar.html.twig`

Ajoute un **nouveau bloc menu accordéon "Ramassage"** au même niveau que les blocs "Colis" et "Stock". Utilise le même pattern :

```twig
{% set isRamassageRoute = currentRoute starts with 'app_ramassage_' %}
```

**Entrées du sous-menu :**
| Label sidebar | Route name | Icône (Keenicons) |
|---|---|---|
| Liste des ramassages | `app_ramassage_index` | `ki-filled ki-parcel` |
| Demander un ramassage | `app_ramassage_new` | `ki-filled ki-plus-circle` |
| Planification | `app_ramassage_planning` | `ki-filled ki-calendar` |

**Copie exactement le pattern HTML** utilisé par les blocs Colis et Stock (accordéon `data-kt-menu-item-toggle="accordion"`, bullets, classes `kt-menu-item`, `kt-menu-link`, `kt-menu-bullet`, etc.). Utilise l'icône parente `ki-filled ki-truck text-lg` pour le menu parent "Ramassage".

#### Header mega‑menu — `templates/partials/_dashboard_header.html.twig`

Ajoute un **nouveau Megamenu Item "Ramassage"** après le menu "Stock" (ligne ~177). Copie le pattern du menu "Colis" (lignes 55-114) :

```html
<!--Megamenu Item-->
<div class="kt-menu-item" data-kt-menu-item-placement="bottom-start" ...>
  <div class="kt-menu-link ...">
    <span class="kt-menu-title text-nowrap">Ramassage</span>
    ...
  </div>
  <div class="kt-menu-dropdown w-full gap-0 lg:max-w-[700px]">
    <div class="pt-4 pb-2 lg:p-7.5">
      <div class="kt-menu kt-menu-default kt-menu-fit flex-col">
        <h3 class="...">Ramassage</h3>
        <div class="grid lg:grid-cols-2 lg:gap-5">
          <!-- Col 1 -->
          <div class="flex flex-col gap-0.5">
            <div class="kt-menu-item">
              <a class="kt-menu-link" href="{{ path('app_ramassage_index') }}">
                <span class="kt-menu-icon"><i class="ki-filled ki-parcel text-lg"></i></span>
                <span class="kt-menu-title grow-0">Liste des ramassages</span>
              </a>
            </div>
            <div class="kt-menu-item">
              <a class="kt-menu-link" href="{{ path('app_ramassage_new') }}">
                <span class="kt-menu-icon"><i class="ki-filled ki-plus-circle text-lg"></i></span>
                <span class="kt-menu-title grow-0">Demander un ramassage</span>
              </a>
            </div>
          </div>
          <!-- Col 2 -->
          <div class="flex flex-col gap-0.5">
            <div class="kt-menu-item">
              <a class="kt-menu-link" href="{{ path('app_ramassage_planning') }}">
                <span class="kt-menu-icon"><i class="ki-filled ki-calendar text-lg"></i></span>
                <span class="kt-menu-title grow-0">Planification</span>
              </a>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
<!--End of Megamenu Item-->
```

---

### 2. Contrôleur — `src/Controller/RamassageController.php` [NEW]

Crée un nouveau contrôleur dédié :

```php
#[IsGranted('IS_AUTHENTICATED_FULLY')]
#[Route('/ramassage')]
final class RamassageController extends AbstractController
{
    // Route 1: Liste des ramassages
    #[Route('', name: 'app_ramassage_index', methods: ['GET'])]
    public function index(Request $request, PickupRequestRepository $repo): Response { ... }

    // Route 2: Nouvelle demande de ramassage (GET = form, POST = submit)
    #[Route('/new', name: 'app_ramassage_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em, CityRepository $cityRepo, ...): Response { ... }

    // Route 3: Planification
    #[Route('/planning', name: 'app_ramassage_planning', methods: ['GET'])]
    public function planning(Request $request, PickupRequestRepository $repo): Response { ... }

    // Route 4 (optionnelle) : Modifier le statut d'un ramassage
    #[Route('/{id}/status', name: 'app_ramassage_update_status', methods: ['POST'])]
    public function updateStatus(Request $request, PickupRequest $pickup, EntityManagerInterface $em): Response { ... }
}
```

---

### 3. Page 1 — Liste des ramassages

**Template** : `templates/ramassage/index.html.twig` [NEW]

**Design** : Copie **exactement** le design de `templates/colis/index.html.twig` (même structure kt-card, kt-card-grid, kt-card-header, datatable, kt-card-footer avec pagination).

#### En-tête de page
- Titre `h1` : "Liste des ramassages"
- Sous-titre avec compteurs : "Total ramassages: X"
- Bouton d'action : "Demander un ramassage" → lien vers `app_ramassage_new`

#### Barre de filtres (dans kt-card-header)
- Champ de recherche textuel (même `kt-input` avec icône `ki-magnifier`)
- Dropdown filtre par **Statut** : Tous / En attente / Confirmé / Ramassé / Annulé
- Bouton "Filtrer" + lien "Réinitialiser"

#### Tableau — Colonnes (`<th>`) dans cet ordre exact :

| # | Nom de colonne | Source dans PickupRequest | min-width |
|---|---|---|---|
| 1 | Téléphone | `phone` | `min-w-[140px]` |
| 2 | Type | À déterminer (ex: "Stock" si product != null, "Simple" sinon) — ou ajouter un champ `type` à l'entité | `min-w-[120px]` |
| 3 | Détails | `productNameSnapshot` | `min-w-[180px]` |
| 4 | Ville | `city` | `min-w-[140px]` |
| 5 | Adresse | `address` (+ `neighborhood`) | `min-w-[180px]` |
| 6 | Note | `note` | `min-w-[150px]` |
| 7 | Date | `createdAt` (format `d/m/Y H:i`) | `min-w-[150px]` |
| 8 | Statut | `status` avec badge coloré | `min-w-[130px]` |
| 9 | Suivi | Lien ou info de tracking — ajouter un champ `trackingInfo` si nécessaire, ou afficher `hasLabels` | `min-w-[120px]` |
| 10 | Livreur | `createdBy.fullName` ou champ dédié `assignedDriver` | `min-w-[140px]` |

#### Badges de statut (même style que colis) :

```twig
{% set statusBadgeClass = 'kt-badge-warning' %}
{% if pickup.status == 'confirmed' %}
  {% set statusBadgeClass = 'kt-badge-info' %}
{% elseif pickup.status == 'picked_up' %}
  {% set statusBadgeClass = 'kt-badge-success' %}
{% elseif pickup.status == 'cancelled' %}
  {% set statusBadgeClass = 'kt-badge-destructive' %}
{% endif %}

<span class="kt-badge {{ statusBadgeClass }} kt-badge-outline rounded-[30px]">
  <span class="kt-badge-dot size-1.5"></span>
  {{ statusLabel }}
</span>
```

#### Mapping des labels de statut :
- `pending` → "En attente"
- `confirmed` → "Confirmé"
- `picked_up` → "Ramassé"
- `cancelled` → "Annulé"

#### Colonne Actions
Menu dropdown (`kt-menu`) avec :
- "Voir" (icône `ki-search-list`)
- "Annuler" (icône `ki-cross-circle`) — uniquement si status = `pending`

#### Pagination
Même footer datatable que `colis/index.html.twig` avec `data-kt-datatable-size`, `data-kt-datatable-info`, `data-kt-datatable-pagination`.

---

### 4. Page 2 — Demander un ramassage

**Template** : `templates/ramassage/new.html.twig` [NEW]

**Design** : Copie **exactement** le design de `templates/colis/new.html.twig` (layout 2 cartes en grille `xl:grid-cols-2`, formulaire en table `kt-table` avec labels et inputs, carte options, boutons en haut).

#### En-tête de page
- Titre `h1` : "Nouvelle demande de ramassage"
- Sous-titre : "Remplissez les informations pour planifier un ramassage"
- Boutons : "Retour à la liste" (outline → `app_ramassage_index`) + "Soumettre la demande" (primary, type submit)

#### Carte gauche — "Informations du ramassage"

Formulaire avec les champs suivants (rendu en table `kt-table` comme `colis/new.html.twig`) :

| Label | Champ | Type HTML | Placeholder | Requis |
|---|---|---|---|---|
| Téléphone | `phone` | `input` text (kt-input) | "Numéro de téléphone" | ✅ |
| Téléphone fournisseur | `supplierPhone` | `input` text | "Téléphone du fournisseur (optionnel)" | ❌ |
| Ville | `city` | `select` (kt-select avec recherche) — alimenté par `CityRepository` | "Choisir une ville" | ✅ |
| Quartier | `neighborhood` | `input` text | "Quartier" | ✅ |
| Adresse | `address` | `input` text | "Adresse complète" | ✅ |

#### Carte droite — "Informations complémentaires"

| Label | Champ | Type HTML | Placeholder | Requis |
|---|---|---|---|---|
| Nature du produit | `productNameSnapshot` | `input` text | "Description du produit à ramasser" | ✅ |
| Note | `note` | `textarea` ou `input` text | "Instructions spéciales (optionnel)" | ❌ |
| Étiquettes | `hasLabels` | Toggle switch (`kt-switch kt-switch-sm`) | — | ❌ (défaut: oui) |

#### Carte information en haut (même design que colis/new)
Affiche 2-3 bullet points avec icônes ✅ (`ki-check-circle text-green-500`) :
- "Veuillez fournir une adresse précise pour faciliter le ramassage"
- "Le ramassage sera planifié dans les 24h suivant la demande"
- "Vous serez notifié par SMS lorsque le livreur sera en route"

Icône décorative : `ki-filled ki-truck` (80px, comme l'icône info de colis/new)

#### Soumission (POST)
- Valide côté serveur (ville existe, champs requis non vides)
- Crée un `PickupRequest` avec `status = 'pending'`
- Flash success : "Demande de ramassage créée avec succès."
- Redirect vers `app_ramassage_index`
- Protection CSRF : `csrf_token('ramassage_new')`

---

### 5. Page 3 — Planification (page créative)

**Template** : `templates/ramassage/planning.html.twig` [NEW]

**Design** : Cette page est une **vue calendrier / timeline** des ramassages à venir. Tu as carte blanche pour le design tant qu'il respecte le design system Metronic/KTUI existant.

#### En-tête de page
- Titre `h1` : "Planification des ramassages"
- Sous-titre : "Vue d'ensemble de vos demandes de ramassage"

#### Contenu — Vue en cartes regroupées par statut (Kanban-style) ou Timeline

**Option recommandée : Vue en 3 colonnes Kanban**

```
┌──────────────────┐  ┌──────────────────┐  ┌──────────────────┐
│  🟡 En attente   │  │  🔵 Confirmé     │  │  🟢 Ramassé      │
│  (pending)       │  │  (confirmed)     │  │  (picked_up)     │
│                  │  │                  │  │                  │
│ ┌──────────────┐ │  │ ┌──────────────┐ │  │ ┌──────────────┐ │
│ │ Card pickup  │ │  │ │ Card pickup  │ │  │ │ Card pickup  │ │
│ │ - Ville      │ │  │ │ - Ville      │ │  │ │ - Ville      │ │
│ │ - Adresse    │ │  │ │ - Adresse    │ │  │ │ - Adresse    │ │
│ │ - Date       │ │  │ │ - Date       │ │  │ │ - Date       │ │
│ │ - Téléphone  │ │  │ │ - Téléphone  │ │  │ │ - Téléphone  │ │
│ └──────────────┘ │  │ └──────────────┘ │  │ └──────────────┘ │
│ ┌──────────────┐ │  │                  │  │                  │
│ │ Card pickup  │ │  │                  │  │                  │
│ └──────────────┘ │  │                  │  │                  │
└──────────────────┘  └──────────────────┘  └──────────────────┘
```

#### Chaque carte de ramassage affiche :
- **Produit** : `productNameSnapshot` (tronqué si trop long)
- **Ville** : badge ou texte
- **Adresse** : `address`, `neighborhood`
- **Téléphone** : `phone`
- **Date** : `createdAt` formatée
- **Statut** : badge coloré (même mapping que page Liste)
- **Actions rapides** : boutons pour changer le statut (Confirmer / Marquer ramassé / Annuler)

#### Stats en haut de page (4 mini-cartes KPI)
Utilise des `kt-card` compacts avec icônes et compteurs :

| KPI | Icône | Couleur |
|---|---|---|
| Total demandes | `ki-filled ki-parcel` | Primary |
| En attente | `ki-filled ki-time` | Warning (orange) |
| Confirmés | `ki-filled ki-check-circle` | Info (bleu) |
| Ramassés | `ki-filled ki-truck` | Success (vert) |

#### CSS & JS
- Utilise le grid CSS Tailwind : `grid grid-cols-1 lg:grid-cols-3 gap-5 lg:gap-7.5`
- Les cartes dans chaque colonne scrollent verticalement si nombreuses
- Ajoute un fichier JS si nécessaire : `assets/js/ramassage-planning.js` — pour les actions de changement de statut en AJAX (ou simple form POST)
- CSS custom si nécessaire : `assets/css/ramassage-planning.css`

---

## 📐 Conventions de code à respecter

### Twig
- Toujours `{% extends 'base_dashboard.html.twig' %}`
- Block `{% block title %}...{% endblock %}`
- Block `{% block body %}...{% endblock %}`
- Blocks optionnels : `{% block stylesheets %}`, `{% block javascripts %}`
- Container : `<main class="grow pt-5 dashboard-content-shift" id="content" role="content">`
- Inner container : `<div class="kt-container-fixed">`

### Contrôleur
- Attribut `#[IsGranted('IS_AUTHENTICATED_FULLY')]` sur la classe
- Attribut `#[Route('/ramassage')]` sur la classe
- Protection CSRF sur toutes les actions POST
- Flash messages : `success`, `error`, `warning`
- Injection des dépendances en paramètre de méthode

### Entité PickupRequest
- Si tu ajoutes des champs, crée une migration Doctrine
- Garde la rétrocompatibilité (nullable pour les nouveaux champs)
- Status possibles : `pending`, `confirmed`, `picked_up`, `cancelled`

---

## ✅ Checklist de livraison

- [ ] **Sidebar** : Bloc "Ramassage" avec 3 sous-menus, pattern identique à Colis/Stock
- [ ] **Header mega-menu** : Entrée "Ramassage" avec dropdown, pattern identique à Colis/Stock
- [ ] **`RamassageController.php`** : 3 routes minimum (index, new, planning)
- [ ] **`ramassage/index.html.twig`** : Liste avec colonnes (Téléphone, Type, Détails, Ville, Adresse, Note, Date, Statut, Suivi, Livreur), filtres, pagination — design copié de `colis/index.html.twig`
- [ ] **`ramassage/new.html.twig`** : Formulaire de demande — design copié de `colis/new.html.twig`
- [ ] **`ramassage/planning.html.twig`** : Vue Kanban/calendrier créative avec KPIs
- [ ] **Migration Doctrine** si des champs sont ajoutés à `PickupRequest`
- [ ] **Flash messages** fonctionnels (succès, erreur)
- [ ] **Liens de navigation** fonctionnels entre toutes les pages
- [ ] **Filtres et recherche** fonctionnels sur la page liste
- [ ] **CSRF protection** sur tous les formulaires POST

---

## 🚫 À ne PAS faire

- ❌ Ne crée **PAS** de nouvelle table / entité pour les ramassages
- ❌ Ne modifie **PAS** les pages Colis ou Stock existantes
- ❌ N'utilise **PAS** de framework JS (React, Vue, etc.) — JS vanilla uniquement
- ❌ Ne change **PAS** le layout `base_dashboard.html.twig`
- ❌ Ne supprime **PAS** de code ou commentaire existant dans les fichiers modifiés (sidebar, header)
