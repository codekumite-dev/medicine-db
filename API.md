# Indian Medicine Database — API Reference

**Base URL:** `http://127.0.0.1:8001/api/v1`  
**Version:** v1  
**Auth:** Bearer token (Laravel Sanctum)

---

## Authentication

All endpoints (except System) require a valid API token issued through the Filament admin panel.

```http
Authorization: Bearer <your_token>
```

Tokens carry **abilities** that gate access to specific endpoints. The required ability is listed on each endpoint.

### Token Abilities

| Ability | Description |
|---|---|
| `medicines:read` | List and retrieve medicine records |
| `medicines:search` | Full-text search across medicines |
| `manufacturers:read` | List and retrieve manufacturer records |
| `combinations:read` | List and retrieve drug combination pages |

### Rate Limits

| Limiter | Limit | Scope |
|---|---|---|
| `api` (default) | 120 req / min | per token or IP |
| `search` | 30 req / min | per token or IP |
| `admin-login` | 5 req / min | per IP |

### Error Responses

All errors follow a consistent shape:

```json
{
  "message": "Human-readable description",
  "errors": { }
}
```

| HTTP Code | Meaning |
|---|---|
| `401` | Missing or invalid token |
| `403` | Token lacks the required ability or IP not allowlisted |
| `404` | Record not found |
| `422` | Validation failed (see `errors` key) |
| `429` | Rate limit exceeded |

---

## Common Response Envelope

Every endpoint wraps its payload in:

```json
{
  "data": { ... },
  "meta": {
    "request_id": "uuid-v4",
    "version": "v1",
    "timestamp": "2026-04-16T09:58:00.000000Z"
  }
}
```

Paginated list endpoints additionally include a `links` and `meta.pagination` block (Laravel default pagination structure).

---

## Medicines

### `GET /medicines`

List all published, active medicines. Supports filtering, sorting, and pagination.

**Required ability:** `medicines:read`

#### Query Parameters

| Parameter | Type | Default | Description |
|---|---|---|---|
| `manufacturer` | `uuid` | — | Filter by manufacturer UUID |
| `type` | `string` | — | Filter by medicine type (e.g. `allopathy`) |
| `rx_required` | `boolean` | — | `true` / `false` — filter by prescription requirement |
| `discontinued` | `boolean` | — | Pass any truthy value to include discontinued medicines |
| `sort_by` | `string` | `name` | Column to sort by |
| `sort_dir` | `asc\|desc` | `asc` | Sort direction |
| `per_page` | `integer` | `25` | Results per page (max 100 recommended) |
| `page` | `integer` | `1` | Page number |

#### Example Request

```http
GET /api/v1/medicines?type=allopathy&rx_required=true&per_page=10
Authorization: Bearer <token>
```

#### Example Response

```json
{
  "data": [
    {
      "id": "019600a4-...",
      "name": "Admenta 10 Tablet",
      "slug": "admenta-10-tablet",
      "short_composition": "Memantine (10mg)",
      "dosage_form": "tablet",
      "strength": null,
      "route": null,
      "type": "allopathy",
      "schedule": null,
      "rx_required": true,
      "rx_required_header": "Rx",
      "manufacturer": {
        "id": "019600a4-...",
        "name": "Sun Pharmaceutical Industries Ltd."
      },
      "pricing": {
        "price": "150.00",
        "mrp": "175.00",
        "currency": "INR"
      },
      "packaging": {
        "pack_size_label": "10 tablets",
        "quantity": 10,
        "quantity_unit": null
      },
      "identifiers": {
        "barcode": null,
        "gs1_gtin": null,
        "hsn_code": null
      },
      "is_discontinued": false,
      "storage": null,
      "published_at": "2026-04-16T09:00:00.000000Z"
    }
  ],
  "links": { ... },
  "meta": {
    "current_page": 1,
    "per_page": 10,
    "total": 2028584,
    "request_id": "...",
    "version": "v1",
    "timestamp": "2026-04-16T09:58:00.000000Z"
  }
}
```

---

### `GET /medicines/search`

Full-text search across medicine `name`, `short_composition`, and aliases.

**Required ability:** `medicines:search`  
**Rate limit:** `search` — 30 req/min

#### Query Parameters

| Parameter | Type | Required | Description |
|---|---|---|---|
| `q` | `string` | ✅ | Search query. Min 2 chars, max 100 chars |

#### Example Request

```http
GET /api/v1/medicines/search?q=Metformin
Authorization: Bearer <token>
```

#### Example Response

```json
{
  "data": [
    {
      "id": "...",
      "name": "Glucophage 500mg Tablet",
      "slug": "glucophage-500mg-tablet",
      "short_composition": "Metformin (500mg)",
      ...
    }
  ],
  "meta": { ... }
}
```

> **Note:** Returns up to **50** results. For paginated results use `GET /medicines` with filters.

---

### `GET /medicines/{uuid}`

Retrieve a single published, active medicine by its UUID.

**Required ability:** `medicines:read`  
**Cache:** Response is cached for **24 hours** and invalidated on update.

#### Path Parameters

| Parameter | Type | Description |
|---|---|---|
| `uuid` | `string` | UUID v4/v7 of the medicine |

#### Example Request

```http
GET /api/v1/medicines/019600a4-1234-7000-8000-abcdef012345
Authorization: Bearer <token>
```

#### Response

Single medicine object wrapped in `data`. Same schema as list items above.

---

### `GET /medicines/slug/{slug}`

Retrieve a medicine by its URL-friendly slug.

**Required ability:** `medicines:read`  
**Cache:** 24 hours

#### Path Parameters

| Parameter | Type | Description |
|---|---|---|
| `slug` | `string` | URL slug (e.g. `admenta-10-tablet`) |

#### Example Request

```http
GET /api/v1/medicines/slug/admenta-10-tablet
Authorization: Bearer <token>
```

---

### `GET /medicines/barcode/{barcode}`

Look up a medicine by its primary barcode.

**Required ability:** `medicines:read`

#### Path Parameters

| Parameter | Type | Description |
|---|---|---|
| `barcode` | `string` | Barcode string |

#### Example Request

```http
GET /api/v1/medicines/barcode/8901030951346
Authorization: Bearer <token>
```

---

### `GET /medicines/gtin/{gtin}`

Look up a medicine by its GS1 GTIN (Global Trade Item Number).

**Required ability:** `medicines:read`

#### Path Parameters

| Parameter | Type | Description |
|---|---|---|
| `gtin` | `string` | 14-digit GS1 GTIN |

#### Example Request

```http
GET /api/v1/medicines/gtin/08901030951346
Authorization: Bearer <token>
```

---

## Manufacturers

### `GET /manufacturers`

List all active manufacturers.

**Required ability:** `manufacturers:read`

#### Query Parameters

| Parameter | Type | Default | Description |
|---|---|---|---|
| `sort_by` | `string` | `name` | Column to sort by |
| `sort_dir` | `asc\|desc` | `asc` | Sort direction |
| `per_page` | `integer` | `25` | Results per page |
| `page` | `integer` | `1` | Page number |

#### Example Request

```http
GET /api/v1/manufacturers?per_page=50
Authorization: Bearer <token>
```

#### Example Response

```json
{
  "data": [
    {
      "id": "019600a4-...",
      "name": "Sun Pharmaceutical Industries Ltd.",
      "slug": "sun-pharmaceutical-industries-ltd",
      "country_code": "IN",
      "city": "Mumbai",
      "state": "Maharashtra",
      "website": null,
      "license_number": null
    }
  ],
  "meta": { ... }
}
```

---

### `GET /manufacturers/{uuid}`

Retrieve a single manufacturer by UUID.

**Required ability:** `manufacturers:read`

#### Path Parameters

| Parameter | Type | Description |
|---|---|---|
| `uuid` | `string` | Manufacturer UUID |

#### Example Request

```http
GET /api/v1/manufacturers/019600a4-1234-7000-8000-abcdef012345
Authorization: Bearer <token>
```

---

## Drug Combinations

### `GET /drug-combinations`

List all published drug combination pages.

**Required ability:** `combinations:read`

#### Query Parameters

| Parameter | Type | Default | Description |
|---|---|---|---|
| `sort_by` | `string` | `title` | Column to sort by |
| `sort_dir` | `asc\|desc` | `asc` | Sort direction |
| `per_page` | `integer` | `25` | Results per page |
| `page` | `integer` | `1` | Page number |

#### Example Request

```http
GET /api/v1/drug-combinations?per_page=10
Authorization: Bearer <token>
```

#### Example Response

```json
{
  "data": [
    {
      "id": "...",
      "title": "Metformin + Glibenclamide",
      "slug": "metformin-glibenclamide",
      "canonical_name": "Metformin + Glibenclamide",
      "short_name": null,
      "summary": "A fixed-dose combination used in type 2 diabetes...",
      "alternate_names": ["Glucovance"],
      "evidence_level": "A",
      "is_featured": false,
      "seo": {
        "title": "Metformin + Glibenclamide — Uses, Dosage, Side Effects",
        "description": "...",
        "canonical": "https://example.com/drug-combinations/metformin-glibenclamide"
      },
      "published_at": "2026-04-16T09:00:00.000000Z"
    }
  ],
  "meta": { ... }
}
```

---

### `GET /drug-combinations/{slug}`

Retrieve a full drug combination page including all visible sections and linked medicine items.

**Required ability:** `combinations:read`

#### Path Parameters

| Parameter | Type | Description |
|---|---|---|
| `slug` | `string` | Drug combination slug |

#### Example Request

```http
GET /api/v1/drug-combinations/metformin-glibenclamide
Authorization: Bearer <token>
```

#### Additional fields in response (not in list)

```json
{
  "data": {
    ...
    "sections": [
      {
        "key": "overview",
        "title": "Overview",
        "content": "<p>...</p>"
      },
      {
        "key": "dosage",
        "title": "Dosage",
        "content": "<p>...</p>"
      }
    ],
    "items": [
      {
        "ingredient_name": "Metformin",
        "strength": "500mg",
        "role": "primary",
        "medicine": { ... }
      }
    ]
  }
}
```

#### Available Section Keys

| `section_key` | Display Title |
|---|---|
| `overview` | Overview |
| `usage` | Usage |
| `alternate_names` | Alternate Names |
| `how_it_works` | How It Works |
| `dosage` | Dosage |
| `standard_dosage` | Standard Dosage |
| `clinical_use_cases` | Clinical Use Cases |
| `dosage_adjustments` | Dosage Adjustments |
| `side_effects` | Side Effects |
| `common_side_effects` | Common Side Effects |
| `rare_serious_side_effects` | Rare but Serious Side Effects |
| `long_term_effects` | Long-Term Effects |
| `adr` | Adverse Drug Reactions (ADR) |
| `contraindications` | Contraindications |
| `drug_interactions` | Drug Interactions |
| `pregnancy_breastfeeding` | Pregnancy and Breastfeeding |
| `drug_profile_summary` | Drug Profile Summary |
| `popular_combinations` | Popular Combinations |
| `precautions` | Precautions |

---

### `GET /drug-combinations/{slug}/faqs`

Retrieve all published FAQs for a drug combination.

**Required ability:** `combinations:read`

#### Example Request

```http
GET /api/v1/drug-combinations/metformin-glibenclamide/faqs
Authorization: Bearer <token>
```

#### Example Response

```json
{
  "data": [
    {
      "question": "Can I take this medicine on an empty stomach?",
      "answer": "No, it is recommended to take this medicine with food..."
    }
  ],
  "meta": { ... }
}
```

---

### `GET /drug-combinations/{slug}/sections/{key}`

Retrieve a single content section of a drug combination.

**Required ability:** `combinations:read`

#### Path Parameters

| Parameter | Type | Description |
|---|---|---|
| `slug` | `string` | Drug combination slug |
| `key` | `string` | Section key (see table above) |

#### Example Request

```http
GET /api/v1/drug-combinations/metformin-glibenclamide/sections/dosage
Authorization: Bearer <token>
```

#### Example Response

```json
{
  "data": {
    "key": "dosage",
    "title": "Dosage",
    "content": "<p>The usual adult dose is...</p>"
  },
  "meta": { ... }
}
```

---

## System

These endpoints are **public** — no authentication required.

### `GET /health`

Returns the API health status.

```http
GET /api/v1/health
```

```json
{
  "status": "ok",
  "timestamp": "2026-04-16T09:58:00.000000Z"
}
```

---

### `GET /version`

Returns the current API version.

```http
GET /api/v1/version
```

```json
{
  "version": "1.0.0"
}
```

---

## Quick Start

### 1. Get your API token

Log in to the admin panel at `/admin`, navigate to **API Clients**, and create a client. Copy the generated bearer token.

### 2. Test connectivity

```bash
curl http://127.0.0.1:8001/api/v1/health
```

### 3. Make your first authenticated request

```bash
curl -H "Authorization: Bearer <your_token>" \
     "http://127.0.0.1:8001/api/v1/medicines?per_page=5"
```

### 4. Search for a medicine

```bash
curl -H "Authorization: Bearer <your_token>" \
     "http://127.0.0.1:8001/api/v1/medicines/search?q=metformin"
```

---

## Data Types

| Type | Format |
|---|---|
| `id` | UUID v7 string |
| `price` / `mrp` | Decimal string, 2 decimal places |
| `published_at` | ISO 8601 UTC — `2026-04-16T09:00:00.000000Z` |
| `rx_required` | Boolean |
| `is_discontinued` | Boolean |
| `evidence_level` | `A`, `B`, `C`, or `expert_opinion` |
| `dosage_form` | See enum below |

### `dosage_form` values

`tablet` · `capsule` · `syrup` · `suspension` · `injection` · `ointment` · `cream` · `gel` · `drops` · `inhaler` · `patch` · `suppository` · `powder` · `lotion` · `spray`
