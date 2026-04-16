# Indian Medicine Database — Phase-by-Phase Build Plan
### Laravel 13 · Filament 3 · Sanctum API Keys · Drug Combination CMS

---

## Stack at a Glance

| Layer | Technology |
|---|---|
| Backend | Laravel 13 |
| Admin Panel | Filament 3 |
| API Auth | Laravel Sanctum (bearer tokens) |
| Admin Auth | Filament session-based login |
| Roles & Permissions | Spatie Laravel Permission |
| Primary Keys | UUID v7 (ordered, fast index) |
| Search | MySQL Full-Text → Meilisearch (Phase 5) |
| Queue | Laravel Queues + Redis |
| Cache | Redis |
| Storage | S3-compatible (import files, exports) |

---

## UUID Strategy

All primary entities use **UUID v7** (time-ordered). This means:

- Lexicographically sortable (no random fragmentation)
- Fast B-tree index performance
- Safe for public-facing APIs (no sequential ID guessing)
- Globally unique across systems

```php
// In every core model
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Medicine extends Model
{
    use HasUuids;

    protected $keyType = 'string';
    public $incrementing = false;
}
```

In migrations, use `$table->uuid('id')->primary()` instead of `$table->id()`.

---

## Phase Overview

| Phase | Focus | Outcome |
|---|---|---|
| 1 | Foundation | Laravel, Filament, Sanctum, roles, admin login |
| 2 | Medicine Catalog | Core medicine master, manufacturer, aliases, identifiers |
| 3 | Drug Combination CMS | Combination entity, structured sections, FAQs |
| 4 | API Surface | Public read APIs, token lifecycle, rate limiting |
| 5 | Import Pipeline | CSV/XLSX import, staging, validation, audit |
| 6 | Search & Performance | Full-text, caching, Meilisearch |
| 7 | Editorial Workflow | Review states, roles, publish controls |
| 8 | Hardening & Ops | 2FA, IP allowlist, audit logs, monitoring |

---

## Phase 1 — Foundation

**Goal:** Clean Laravel install with Filament admin, Sanctum, roles, and a hardened admin login.

### Step 1.1 — Create Laravel project

```bash
composer create-project laravel/laravel medicine-db
cd medicine-db
```

### Step 1.2 — Install core packages

```bash
# Admin panel
composer require filament/filament:"^3.0"

# Auth tokens
composer require laravel/sanctum

# Roles and permissions
composer require spatie/laravel-permission

# Dev tools
composer require --dev laravel/telescope
composer require --dev barryvdh/laravel-debugbar

# Import support (Phase 5, install now)
composer require maatwebsite/excel
```

### Step 1.3 — Publish and run migrations

```bash
php artisan filament:install --panels
php artisan vendor:publish --provider="Laravel\Sanctum\SanctumServiceProvider"
php artisan vendor:publish --provider="Spatie\Permission\PermissionServiceProvider"
php artisan migrate
```

### Step 1.4 — Admin User model

Extend the default User model:

```php
// app/Models/User.php
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Spatie\Permission\Traits\HasRoles;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class User extends Authenticatable implements FilamentUser
{
    use HasRoles, HasUuids;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'name', 'email', 'password',
        'is_active', 'last_login_at',
    ];

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->is_active && $this->hasAnyRole([
            'super-admin', 'admin', 'clinical-editor',
            'content-admin', 'data-operator', 'api-manager'
        ]);
    }
}
```

### Step 1.5 — Users migration

```php
Schema::create('users', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->string('name');
    $table->string('email')->unique();
    $table->string('password');
    $table->boolean('is_active')->default(true);
    $table->timestamp('last_login_at')->nullable();
    $table->rememberToken();
    $table->timestamps();
    $table->softDeletes();
});
```

### Step 1.6 — Filament panel configuration

```php
// app/Providers/Filament/AdminPanelProvider.php
->path('admin')
->login()
->authGuard('web')
->navigationGroups([
    NavigationGroup::make('Catalog'),
    NavigationGroup::make('Clinical Content'),
    NavigationGroup::make('Access Control'),
    NavigationGroup::make('System'),
])
->middleware(['web'])
->authMiddleware([Authenticate::class])
```

### Step 1.7 — Roles seeder

```php
// database/seeders/RoleSeeder.php
$roles = [
    'super-admin',
    'admin',
    'clinical-editor',
    'content-admin',
    'data-operator',
    'api-manager',
    'viewer',
];

foreach ($roles as $role) {
    Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
}

$admin = User::firstOrCreate(
    ['email' => env('ADMIN_EMAIL', 'admin@example.com')],
    [
        'name' => 'Super Admin',
        'password' => bcrypt(env('ADMIN_PASSWORD', 'changeme')),
        'is_active' => true,
    ]
);

$admin->assignRole('super-admin');
```

### Step 1.8 — Rate limiting for login

```php
// app/Providers/AppServiceProvider.php
RateLimiter::for('admin-login', function (Request $request) {
    return Limit::perMinute(5)->by($request->ip());
});

RateLimiter::for('api', function (Request $request) {
    return Limit::perMinute(120)->by($request->user()?->id ?: $request->ip());
});

RateLimiter::for('search', function (Request $request) {
    return Limit::perMinute(30)->by($request->bearerToken() ?: $request->ip());
});
```

### Step 1.9 — Telescope (dev only)

```bash
php artisan telescope:install
php artisan migrate
```

Gate in `TelescopeServiceProvider` to admin emails only.

### Phase 1 Checklist

- [ ] Laravel 13 installed
- [ ] Filament panel at `/admin` working
- [ ] Admin email/password login working
- [ ] Spatie Permission roles seeded
- [ ] Super admin user created and can log in
- [ ] Sanctum installed and personal_access_tokens table exists
- [ ] Login rate limiting active
- [ ] Telescope gated to admin

---

## Phase 2 — Medicine Catalog

**Goal:** Full medicine master data model with manufacturers, aliases, and identifier support.

### Step 2.1 — Folder structure

```
app/
├── Models/
│   ├── Medicine.php
│   ├── Manufacturer.php
│   ├── MedicineAlias.php
│   ├── MedicineIdentifier.php
│   └── MedicineType.php
├── Filament/Resources/
│   ├── MedicineResource.php
│   └── ManufacturerResource.php
├── Http/
│   ├── Controllers/Api/V1/
│   │   └── MedicineController.php
│   └── Resources/Api/
│       └── MedicineResource.php    ← API Resource (JSON transformer)
├── Enums/
│   ├── MedicineTypeEnum.php
│   ├── DosageFormEnum.php
│   ├── ApprovalStatusEnum.php
│   └── RouteOfAdministrationEnum.php
```

### Step 2.2 — Manufacturers migration

```php
// create_manufacturers_table
Schema::create('manufacturers', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->string('name');
    $table->string('slug')->unique();
    $table->string('country_code', 3)->default('IN');
    $table->string('city')->nullable();
    $table->string('state')->nullable();
    $table->string('website')->nullable();
    $table->string('license_number')->nullable();
    $table->text('notes')->nullable();
    $table->boolean('is_active')->default(true);
    $table->timestamps();
    $table->softDeletes();

    $table->index('slug');
    $table->index('name');
});
```

### Step 2.3 — Medicines migration

```php
// create_medicines_table
Schema::create('medicines', function (Blueprint $table) {
    $table->uuid('id')->primary();

    // ── Identity ──────────────────────────────────────────────
    $table->string('name')->index();
    $table->string('slug')->unique();

    // ── Composition ───────────────────────────────────────────
    $table->text('short_composition');                        // raw text e.g. "Metformin 500mg"
    $table->string('dosage_form')->nullable();                // tablet, capsule, syrup, injection
    $table->string('strength')->nullable();                   // "500mg", "10mg/5ml"
    $table->string('route_of_administration')->nullable();    // oral, topical, IV

    // ── Manufacturer ──────────────────────────────────────────
    $table->uuid('manufacturer_id')->nullable();
    $table->foreign('manufacturer_id')->references('id')->on('manufacturers')->nullOnDelete();

    // ── Classification ────────────────────────────────────────
    $table->string('type')->nullable()->index();              // tablet, syrup, injection
    $table->string('schedule')->nullable();                   // Schedule H, Schedule H1, OTC
    $table->boolean('rx_required')->default(false)->index();  // requires prescription
    $table->string('rx_required_header')->nullable();         // display label: "Rx", "OTC"
    $table->string('atc_code')->nullable();                   // WHO ATC code

    // ── Pricing ───────────────────────────────────────────────
    $table->decimal('price', 10, 2)->nullable();
    $table->string('currency', 3)->default('INR');
    $table->decimal('mrp', 10, 2)->nullable();

    // ── Packaging ─────────────────────────────────────────────
    $table->string('pack_size_label')->nullable();           // "10 tablets", "30ml bottle"
    $table->integer('quantity')->nullable();                 // numeric quantity
    $table->string('quantity_unit')->nullable();             // "tablets", "ml", "capsules"

    // ── Identifiers ───────────────────────────────────────────
    $table->string('barcode')->nullable()->unique();         // primary barcode
    $table->string('gs1_gtin', 14)->nullable()->unique();   // GS1 Global Trade Item Number
    $table->string('hsn_code', 8)->nullable();              // India HSN for GST
    $table->string('ndc_code')->nullable();                 // if applicable

    // ── Storage & Regulatory ──────────────────────────────────
    $table->string('storage_conditions')->nullable();        // "Store below 25°C"
    $table->string('shelf_life')->nullable();                // "24 months"
    $table->string('country_of_origin', 3)->default('IN');

    // ── Status ────────────────────────────────────────────────
    $table->boolean('is_discontinued')->default(false)->index();
    $table->string('approval_status')->default('draft');     // draft, reviewed, published, archived
    $table->timestamp('published_at')->nullable();

    // ── Content ───────────────────────────────────────────────
    $table->text('description')->nullable();
    $table->text('warnings')->nullable();

    // ── Import provenance ─────────────────────────────────────
    $table->string('source')->nullable();                    // "csv_import", "manual", "api_sync"
    $table->string('source_reference')->nullable();          // filename or external ID

    // ── Audit ─────────────────────────────────────────────────
    $table->uuid('created_by')->nullable();
    $table->uuid('updated_by')->nullable();
    $table->timestamps();
    $table->softDeletes();

    // ── Indexes ───────────────────────────────────────────────
    $table->index('manufacturer_id');
    $table->index('type');
    $table->index('is_discontinued');
    $table->index('approval_status');
    $table->index('rx_required');
    $table->index('barcode');
    $table->index('gs1_gtin');
    $table->index('hsn_code');
    $table->index('published_at');
    $table->fullText(['name', 'short_composition']);         // MySQL full-text
});
```

### Step 2.4 — Medicine Identifiers migration

```php
// create_medicine_identifiers_table
Schema::create('medicine_identifiers', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->uuid('medicine_id');
    $table->foreign('medicine_id')->references('id')->on('medicines')->cascadeOnDelete();

    $table->string('identifier_type');   // barcode, gtin, gs1, internal_sku, regulatory_code, ndc
    $table->string('identifier_value');
    $table->string('issuing_body')->nullable();  // GS1 India, CDSCO, etc.
    $table->boolean('is_primary')->default(false);
    $table->timestamps();

    $table->unique(['medicine_id', 'identifier_type', 'identifier_value']);
    $table->index(['identifier_type', 'identifier_value']);   // fast barcode lookup
    $table->index('medicine_id');
});
```

### Step 2.5 — Medicine Aliases migration

```php
// create_medicine_aliases_table
Schema::create('medicine_aliases', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->uuid('medicine_id');
    $table->foreign('medicine_id')->references('id')->on('medicines')->cascadeOnDelete();

    $table->string('alias');
    $table->string('alias_type');  // brand_name, generic_name, spelling_variant, local_name, alternate_pack
    $table->string('language_code', 10)->default('en');

    $table->timestamps();

    $table->index(['medicine_id']);
    $table->index(['alias']);
    $table->fullText(['alias']);
});
```

### Step 2.6 — Enums

```php
// app/Enums/DosageFormEnum.php
enum DosageFormEnum: string
{
    case Tablet = 'tablet';
    case Capsule = 'capsule';
    case Syrup = 'syrup';
    case Suspension = 'suspension';
    case Injection = 'injection';
    case Ointment = 'ointment';
    case Cream = 'cream';
    case Gel = 'gel';
    case Drops = 'drops';
    case Inhaler = 'inhaler';
    case Patch = 'patch';
    case Suppository = 'suppository';
    case Powder = 'powder';
    case Lotion = 'lotion';
    case Spray = 'spray';
}

// app/Enums/ApprovalStatusEnum.php
enum ApprovalStatusEnum: string
{
    case Draft = 'draft';
    case Reviewed = 'reviewed';
    case Published = 'published';
    case Archived = 'archived';
}
```

### Step 2.7 — Medicine model

```php
// app/Models/Medicine.php
class Medicine extends Model
{
    use HasUuids, SoftDeletes;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $casts = [
        'rx_required'     => 'boolean',
        'is_discontinued' => 'boolean',
        'price'           => 'decimal:2',
        'mrp'             => 'decimal:2',
        'quantity'        => 'integer',
        'published_at'    => 'datetime',
        'dosage_form'     => DosageFormEnum::class,
        'approval_status' => ApprovalStatusEnum::class,
    ];

    public function manufacturer(): BelongsTo
    {
        return $this->belongsTo(Manufacturer::class);
    }

    public function aliases(): HasMany
    {
        return $this->hasMany(MedicineAlias::class);
    }

    public function identifiers(): HasMany
    {
        return $this->hasMany(MedicineIdentifier::class);
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('approval_status', ApprovalStatusEnum::Published)
                     ->whereNotNull('published_at');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_discontinued', false);
    }
}
```

### Step 2.8 — MedicineResource (Filament)

Create a Filament resource with these form sections:

**Tab: Basic Info**
- name (TextInput, required)
- slug (TextInput, auto-generated from name)
- short_composition (Textarea, required)
- description (RichEditor)
- warnings (Textarea)

**Tab: Classification**
- type (Select with DosageFormEnum options)
- dosage_form (Select)
- strength (TextInput)
- route_of_administration (Select)
- schedule (Select: OTC, Schedule H, Schedule H1, Schedule X)
- rx_required (Toggle)
- rx_required_header (TextInput)
- atc_code (TextInput)

**Tab: Manufacturer & Pricing**
- manufacturer_id (Select with search)
- price (TextInput numeric)
- mrp (TextInput numeric)
- currency (Select)

**Tab: Packaging**
- pack_size_label (TextInput)
- quantity (TextInput numeric)
- quantity_unit (Select: tablets, capsules, ml, mg, units)

**Tab: Identifiers & Regulatory**
- barcode (TextInput)
- gs1_gtin (TextInput)
- hsn_code (TextInput)
- ndc_code (TextInput)
- storage_conditions (TextInput)
- shelf_life (TextInput)
- country_of_origin (Select)

**Tab: Publishing**
- approval_status (Select)
- is_discontinued (Toggle)
- published_at (DateTimePicker)

**Relation Managers:**
- AliasesRelationManager
- IdentifiersRelationManager

```bash
php artisan make:filament-resource Medicine --generate
php artisan make:filament-resource Manufacturer --generate
php artisan make:filament-relation-manager MedicineResource aliases alias
php artisan make:filament-relation-manager MedicineResource identifiers identifier_value
```

**Table columns for MedicineResource:**
- name
- manufacturer (with link)
- type badge
- pack_size_label
- rx_required_header badge
- price (INR formatted)
- approval_status badge (color-coded)
- is_discontinued icon
- updated_at

**Filters:**
- SelectFilter: manufacturer
- SelectFilter: type / dosage_form
- SelectFilter: approval_status
- TernaryFilter: rx_required
- TernaryFilter: is_discontinued

**Bulk Actions:**
- BulkAction: Publish selected
- BulkAction: Mark discontinued
- BulkAction: Export to CSV

### Phase 2 Checklist

- [ ] Manufacturers table and CRUD working in Filament
- [ ] Medicines table migrated with all columns
- [ ] Medicine identifiers table working
- [ ] Medicine aliases table working
- [ ] Full-text indexes on name and short_composition
- [ ] UUID on all primary keys
- [ ] MedicineResource form with all tabs working
- [ ] Relation managers for aliases and identifiers working
- [ ] Filters working in medicine table
- [ ] Bulk publish and discontinue actions working

---

## Phase 3 — Drug Combination CMS

**Goal:** Rich editorial CMS for drug combination pages with structured sections, FAQs, and linked medicines.

### Step 3.1 — CMS folder structure

```
app/
├── Models/
│   ├── DrugCombination.php
│   ├── DrugCombinationSection.php
│   ├── DrugCombinationItem.php
│   └── Faq.php
├── Filament/Resources/
│   └── DrugCombinationResource/
│       ├── DrugCombinationResource.php
│       └── RelationManagers/
│           ├── SectionsRelationManager.php
│           ├── FaqsRelationManager.php
│           └── ItemsRelationManager.php
├── Enums/
│   ├── EditorialStatusEnum.php
│   ├── EvidenceLevelEnum.php
│   └── SectionKeyEnum.php
```

### Step 3.2 — Drug Combinations migration

```php
// create_drug_combinations_table
Schema::create('drug_combinations', function (Blueprint $table) {
    $table->uuid('id')->primary();

    // ── Identity ──────────────────────────────────────────────
    $table->string('title');
    $table->string('slug')->unique();
    $table->string('canonical_name');            // canonical molecule name
    $table->string('short_name')->nullable();    // abbreviated label

    // ── Core content ──────────────────────────────────────────
    $table->text('summary')->nullable();
    $table->json('alternate_names')->nullable(); // array of strings

    // ── Editorial metadata ────────────────────────────────────
    $table->string('editorial_status')->default('draft');  // draft, in_review, medically_reviewed, published, retired
    $table->string('evidence_level')->nullable();           // A, B, C, expert_opinion
    $table->boolean('is_featured')->default(false);

    // ── SEO ───────────────────────────────────────────────────
    $table->string('seo_title')->nullable();
    $table->text('seo_description')->nullable();
    $table->string('canonical_url')->nullable();
    $table->json('schema_markup')->nullable();

    // ── Workflow ──────────────────────────────────────────────
    $table->timestamp('published_at')->nullable();
    $table->timestamp('reviewed_at')->nullable();
    $table->uuid('reviewed_by')->nullable();
    $table->uuid('created_by')->nullable();
    $table->uuid('updated_by')->nullable();

    // ── Source ────────────────────────────────────────────────
    $table->string('source')->nullable();
    $table->string('source_reference')->nullable();

    $table->timestamps();
    $table->softDeletes();

    $table->index('slug');
    $table->index('editorial_status');
    $table->index('published_at');
    $table->index('is_featured');
    $table->fullText(['title', 'canonical_name', 'summary']);
});
```

### Step 3.3 — Drug Combination Sections migration

This is the core CMS content storage. Each section is a separate row, making content reorderable and togglable.

```php
// create_drug_combination_sections_table
Schema::create('drug_combination_sections', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->uuid('drug_combination_id');
    $table->foreign('drug_combination_id')
          ->references('id')->on('drug_combinations')
          ->cascadeOnDelete();

    $table->string('section_key');       // canonical key e.g. 'overview', 'dosage'
    $table->string('section_title');     // display title, can be customized
    $table->longText('content');         // rich HTML or markdown
    $table->string('content_format')->default('html');  // html, markdown, plain
    $table->integer('display_order')->default(0);
    $table->boolean('is_visible')->default(true);

    $table->timestamps();

    $table->unique(['drug_combination_id', 'section_key']);
    $table->index(['drug_combination_id', 'display_order']);
    $table->index('section_key');
    $table->index('is_visible');
});
```

**All section_key values:**

| section_key | Display Title |
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

### Step 3.4 — FAQs migration

```php
// create_faqs_table
Schema::create('faqs', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->uuid('drug_combination_id');
    $table->foreign('drug_combination_id')
          ->references('id')->on('drug_combinations')
          ->cascadeOnDelete();

    $table->text('question');
    $table->longText('answer');
    $table->integer('display_order')->default(0);
    $table->boolean('is_published')->default(true);
    $table->uuid('created_by')->nullable();
    $table->timestamps();

    $table->index(['drug_combination_id', 'display_order']);
    $table->index('is_published');
});
```

### Step 3.5 — Drug Combination Items migration

Link a combination to specific medicines or raw ingredients.

```php
// create_drug_combination_items_table
Schema::create('drug_combination_items', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->uuid('drug_combination_id');
    $table->foreign('drug_combination_id')
          ->references('id')->on('drug_combinations')
          ->cascadeOnDelete();

    $table->uuid('medicine_id')->nullable();  // link to medicine record if exists
    $table->foreign('medicine_id')->references('id')->on('medicines')->nullOnDelete();

    $table->string('ingredient_name');        // always store plain text too
    $table->string('strength')->nullable();   // "500mg", "10mg"
    $table->string('role')->nullable();       // primary, adjuvant, inactive
    $table->integer('display_order')->default(0);

    $table->timestamps();

    $table->index('drug_combination_id');
    $table->index('medicine_id');
});
```

### Step 3.6 — SectionKeyEnum

```php
// app/Enums/SectionKeyEnum.php
enum SectionKeyEnum: string
{
    case Overview              = 'overview';
    case Usage                 = 'usage';
    case AlternateNames        = 'alternate_names';
    case HowItWorks            = 'how_it_works';
    case Dosage                = 'dosage';
    case StandardDosage        = 'standard_dosage';
    case ClinicalUseCases      = 'clinical_use_cases';
    case DosageAdjustments     = 'dosage_adjustments';
    case SideEffects           = 'side_effects';
    case CommonSideEffects     = 'common_side_effects';
    case RareSeriousSideEffects = 'rare_serious_side_effects';
    case LongTermEffects       = 'long_term_effects';
    case Adr                   = 'adr';
    case Contraindications     = 'contraindications';
    case DrugInteractions      = 'drug_interactions';
    case PregnancyBreastfeeding = 'pregnancy_breastfeeding';
    case DrugProfileSummary    = 'drug_profile_summary';
    case PopularCombinations   = 'popular_combinations';
    case Precautions           = 'precautions';

    public function label(): string
    {
        return match($this) {
            self::Overview               => 'Overview',
            self::Usage                  => 'Usage',
            self::AlternateNames         => 'Alternate Names',
            self::HowItWorks             => 'How It Works',
            self::Dosage                 => 'Dosage',
            self::StandardDosage         => 'Standard Dosage',
            self::ClinicalUseCases       => 'Clinical Use Cases',
            self::DosageAdjustments      => 'Dosage Adjustments',
            self::SideEffects            => 'Side Effects',
            self::CommonSideEffects      => 'Common Side Effects',
            self::RareSeriousSideEffects => 'Rare but Serious Side Effects',
            self::LongTermEffects        => 'Long-Term Effects',
            self::Adr                    => 'Adverse Drug Reactions (ADR)',
            self::Contraindications      => 'Contraindications',
            self::DrugInteractions       => 'Drug Interactions',
            self::PregnancyBreastfeeding => 'Pregnancy and Breastfeeding',
            self::DrugProfileSummary     => 'Drug Profile Summary',
            self::PopularCombinations    => 'Popular Combinations',
            self::Precautions            => 'Precautions',
        };
    }
}
```

### Step 3.7 — DrugCombination model

```php
class DrugCombination extends Model
{
    use HasUuids, SoftDeletes;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $casts = [
        'alternate_names' => 'array',
        'schema_markup'   => 'array',
        'is_featured'     => 'boolean',
        'published_at'    => 'datetime',
        'reviewed_at'     => 'datetime',
        'editorial_status' => EditorialStatusEnum::class,
    ];

    public function sections(): HasMany
    {
        return $this->hasMany(DrugCombinationSection::class)
                    ->orderBy('display_order');
    }

    public function faqs(): HasMany
    {
        return $this->hasMany(Faq::class)->orderBy('display_order');
    }

    public function items(): HasMany
    {
        return $this->hasMany(DrugCombinationItem::class)->orderBy('display_order');
    }

    public function getSection(SectionKeyEnum $key): ?DrugCombinationSection
    {
        return $this->sections->firstWhere('section_key', $key->value);
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('editorial_status', EditorialStatusEnum::Published)
                     ->whereNotNull('published_at');
    }
}
```

### Step 3.8 — DrugCombinationResource (Filament)

```bash
php artisan make:filament-resource DrugCombination --generate
php artisan make:filament-relation-manager DrugCombinationResource sections section_key
php artisan make:filament-relation-manager DrugCombinationResource faqs question
php artisan make:filament-relation-manager DrugCombinationResource items ingredient_name
```

**Form tabs:**

**Tab: General**
- title (TextInput)
- slug (TextInput, auto from title)
- canonical_name (TextInput)
- short_name (TextInput)
- summary (Textarea)
- alternate_names (TagsInput)
- evidence_level (Select: A, B, C, Expert Opinion)
- is_featured (Toggle)

**Tab: Sections**
- SectionsRelationManager with:
  - section_key (Select from SectionKeyEnum)
  - section_title (auto-filled from section_key, overridable)
  - content (RichEditor with full toolbar)
  - display_order (TextInput numeric)
  - is_visible (Toggle)

**Tab: FAQs**
- FaqsRelationManager with:
  - question (Textarea)
  - answer (RichEditor)
  - display_order (TextInput)
  - is_published (Toggle)

**Tab: Related Medicines**
- ItemsRelationManager:
  - ingredient_name (TextInput)
  - medicine_id (Select, searchable, nullable)
  - strength (TextInput)
  - role (Select: primary, adjuvant, inactive)
  - display_order (TextInput)

**Tab: SEO**
- seo_title (TextInput, 60 char hint)
- seo_description (Textarea, 160 char hint)
- canonical_url (TextInput)
- schema_markup (Textarea / JSON editor)

**Tab: Review Workflow**
- editorial_status (Select with color badges)
- reviewed_at (DateTimePicker)
- reviewed_by (Select from admin users)
- published_at (DateTimePicker)

### Step 3.9 — Section auto-scaffold action

Add a Filament action on DrugCombinationResource to auto-create all standard sections for a new combination:

```php
Action::make('scaffold_sections')
    ->label('Add All Standard Sections')
    ->action(function (DrugCombination $record) {
        $order = 0;
        foreach (SectionKeyEnum::cases() as $key) {
            DrugCombinationSection::firstOrCreate(
                ['drug_combination_id' => $record->id, 'section_key' => $key->value],
                [
                    'section_title' => $key->label(),
                    'content'       => '',
                    'display_order' => $order++,
                    'is_visible'    => true,
                ]
            );
        }
    })
```

### Phase 3 Checklist

- [ ] drug_combinations table migrated
- [ ] drug_combination_sections table migrated
- [ ] faqs table migrated
- [ ] drug_combination_items table migrated
- [ ] All SectionKeyEnum values defined with labels
- [ ] DrugCombinationResource with all tabs working
- [ ] Sections repeater with RichEditor working
- [ ] FAQs relation manager working
- [ ] Related medicines relation manager working
- [ ] Auto-scaffold all sections action working
- [ ] SEO tab fields saving correctly
- [ ] Editorial workflow status working

---

## Phase 4 — API Surface

**Goal:** Public read APIs for medicines and combinations. API key lifecycle in Filament. Rate limiting and token abilities.

### Step 4.1 — API clients table

```php
// create_api_clients_table
Schema::create('api_clients', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->string('name');
    $table->string('owner_email')->nullable();
    $table->text('description')->nullable();
    $table->boolean('is_active')->default(true);
    $table->timestamp('last_used_at')->nullable();
    $table->json('allowed_ips')->nullable();      // IP allowlist
    $table->json('abilities')->nullable();         // token ability list
    $table->integer('rate_limit_per_minute')->default(120);
    $table->string('environment')->default('production'); // production, sandbox
    $table->uuid('created_by')->nullable();
    $table->timestamps();
    $table->softDeletes();
});
```

### Step 4.2 — ApiClient model

```php
class ApiClient extends Model
{
    use HasUuids, SoftDeletes;

    protected $casts = [
        'allowed_ips' => 'array',
        'abilities'   => 'array',
        'is_active'   => 'boolean',
        'last_used_at' => 'datetime',
    ];

    public function tokens(): MorphMany
    {
        return $this->morphMany(PersonalAccessToken::class, 'tokenable');
    }

    public function issueToken(string $tokenName): NewAccessToken
    {
        return $this->tokens()->create([
            'name'      => $tokenName,
            'token'     => hash('sha256', $plaintext = Str::random(40)),
            'abilities' => $this->abilities ?? ['medicines:read'],
        ]);
        // Return $plaintext — show ONCE to admin
    }
}
```

### Step 4.3 — ApiClientResource (Filament)

```bash
php artisan make:filament-resource ApiClient --generate
```

Form fields:
- name (TextInput)
- owner_email (TextInput)
- description (Textarea)
- environment (Select: production, sandbox)
- abilities (CheckboxList with all ability options)
- rate_limit_per_minute (TextInput numeric)
- allowed_ips (TagsInput, one IP per tag)
- is_active (Toggle)

**Custom action: Issue Token**

```php
Action::make('issue_token')
    ->label('Issue New API Key')
    ->requiresConfirmation()
    ->action(function (ApiClient $record) {
        $token = $record->createToken(
            $record->name . '_' . now()->format('Ymd'),
            $record->abilities ?? ['medicines:read']
        );

        // Display the plaintext token in a modal — shown only once
        Notification::make()
            ->title('API Key Issued')
            ->body('Token: ' . $token->plainTextToken . ' — Copy this now, it will not be shown again.')
            ->warning()
            ->persistent()
            ->send();
    })
```

**Table columns:**
- name
- owner_email
- environment badge
- is_active toggle
- last_used_at
- rate_limit_per_minute
- Revoke All Tokens action

### Step 4.4 — Route structure

```php
// routes/api.php
Route::prefix('v1')->middleware(['throttle:api'])->group(function () {

    // ── Public read endpoints (token required) ────────────────
    Route::middleware(['auth:sanctum'])->group(function () {

        // Medicines
        Route::get('/medicines', [MedicineController::class, 'index']);
        Route::get('/medicines/search', [MedicineController::class, 'search'])
             ->middleware('throttle:search');
        Route::get('/medicines/{uuid}', [MedicineController::class, 'show']);
        Route::get('/medicines/slug/{slug}', [MedicineController::class, 'showBySlug']);
        Route::get('/medicines/barcode/{barcode}', [MedicineController::class, 'showByBarcode']);
        Route::get('/medicines/gtin/{gtin}', [MedicineController::class, 'showByGtin']);

        // Manufacturers
        Route::get('/manufacturers', [ManufacturerController::class, 'index']);
        Route::get('/manufacturers/{uuid}', [ManufacturerController::class, 'show']);

        // Drug Combinations
        Route::get('/drug-combinations', [DrugCombinationController::class, 'index']);
        Route::get('/drug-combinations/{slug}', [DrugCombinationController::class, 'show']);
        Route::get('/drug-combinations/{slug}/faqs', [DrugCombinationController::class, 'faqs']);
        Route::get('/drug-combinations/{slug}/sections/{key}', [DrugCombinationController::class, 'section']);
    });

    // ── Internal / write endpoints ────────────────────────────
    Route::middleware(['auth:sanctum', 'ability:admin:sync'])
         ->prefix('internal')
         ->group(function () {
        Route::post('/medicines/import', [ImportController::class, 'medicines']);
        Route::patch('/medicines/{uuid}', [MedicineController::class, 'update']);
        Route::patch('/drug-combinations/{uuid}', [DrugCombinationController::class, 'update']);
    });

    // System
    Route::get('/health', [SystemController::class, 'health']);
    Route::get('/version', [SystemController::class, 'version']);
});
```

### Step 4.5 — Token ability middleware

```php
// app/Http/Middleware/CheckTokenAbility.php
// Already provided by Sanctum — use:
Route::middleware(['auth:sanctum', 'ability:medicines:read'])
```

Available abilities:

| Ability | Access |
|---|---|
| `medicines:read` | List and fetch medicines |
| `medicines:search` | Full-text search |
| `manufacturers:read` | Manufacturer data |
| `combinations:read` | Drug combinations and sections |
| `combinations:search` | Combination search |
| `content:export` | Bulk export endpoints |
| `admin:sync` | Write/import operations |

### Step 4.6 — Medicine API controller

```php
class MedicineController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->checkAbility($request, 'medicines:read');

        $medicines = Medicine::published()
            ->active()
            ->with(['manufacturer'])
            ->when($request->manufacturer, fn($q) => $q->where('manufacturer_id', $request->manufacturer))
            ->when($request->type, fn($q) => $q->where('type', $request->type))
            ->when($request->rx_required, fn($q) => $q->where('rx_required', $request->boolean('rx_required')))
            ->when($request->discontinued, fn($q) => $q->where('is_discontinued', true))
            ->orderBy($request->sort_by ?? 'name', $request->sort_dir ?? 'asc')
            ->paginate($request->per_page ?? 25);

        return MedicineResource::collection($medicines)->response();
    }

    public function search(Request $request): JsonResponse
    {
        $this->checkAbility($request, 'medicines:search');

        $q = $request->validate(['q' => 'required|string|min:2|max:100'])['q'];

        $results = Medicine::published()
            ->whereFullText(['name', 'short_composition'], $q)
            ->orWhereHas('aliases', fn($a) => $a->whereFullText(['alias'], $q))
            ->limit(50)
            ->get();

        return MedicineResource::collection($results)->response();
    }
}
```

### Step 4.7 — API Resource shape

```php
// app/Http/Resources/Api/MedicineResource.php
class MedicineResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                  => $this->id,
            'name'                => $this->name,
            'slug'                => $this->slug,
            'short_composition'   => $this->short_composition,
            'dosage_form'         => $this->dosage_form?->value,
            'strength'            => $this->strength,
            'route'               => $this->route_of_administration,
            'type'                => $this->type,
            'schedule'            => $this->schedule,
            'rx_required'         => $this->rx_required,
            'rx_required_header'  => $this->rx_required_header,
            'manufacturer'        => [
                'id'   => $this->manufacturer?->id,
                'name' => $this->manufacturer?->name,
            ],
            'pricing' => [
                'price'    => $this->price,
                'mrp'      => $this->mrp,
                'currency' => $this->currency,
            ],
            'packaging' => [
                'pack_size_label' => $this->pack_size_label,
                'quantity'        => $this->quantity,
                'quantity_unit'   => $this->quantity_unit,
            ],
            'identifiers' => [
                'barcode'  => $this->barcode,
                'gs1_gtin' => $this->gs1_gtin,
                'hsn_code' => $this->hsn_code,
            ],
            'is_discontinued' => $this->is_discontinued,
            'storage'         => $this->storage_conditions,
            'published_at'    => $this->published_at?->toISOString(),
        ];
    }
}
```

### Step 4.8 — Standard API response wrapper

```php
// app/Http/Resources/Api/ApiResponse.php
// Use this pattern in all controllers:
return response()->json([
    'data' => $data,
    'meta' => [
        'request_id' => (string) Str::uuid(),
        'version'    => 'v1',
        'timestamp'  => now()->toISOString(),
    ],
]);

// For lists — Laravel pagination auto-includes:
// current_page, per_page, total, last_page, from, to
```

### Phase 4 Checklist

- [ ] api_clients table migrated
- [ ] ApiClientResource in Filament working
- [ ] Issue token action working (shows token once)
- [ ] Revoke tokens action working
- [ ] All medicine API routes returning correct JSON
- [ ] Drug combination API routes working
- [ ] Sanctum ability checks on all routes
- [ ] Rate limiting on search and general API routes
- [ ] Barcode and GTIN lookup endpoint working
- [ ] Section-specific endpoint returning single section

---

## Phase 5 — Import Pipeline

**Goal:** Bulk import medicines from CSV/XLSX, with validation, staging, preview, and error reporting.

### Step 5.1 — Import tables

```php
// create_import_jobs_table
Schema::create('import_jobs', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->string('type');              // medicine, manufacturer, combination
    $table->string('status');            // pending, validating, staging, complete, failed
    $table->string('filename');
    $table->string('storage_path');
    $table->integer('total_rows')->default(0);
    $table->integer('valid_rows')->default(0);
    $table->integer('error_rows')->default(0);
    $table->integer('imported_rows')->default(0);
    $table->json('column_map')->nullable();
    $table->json('settings')->nullable();
    $table->text('error_summary')->nullable();
    $table->uuid('created_by');
    $table->timestamps();

    $table->index('status');
    $table->index('created_by');
});

// create_import_rows_table
Schema::create('import_rows', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->uuid('import_job_id');
    $table->foreign('import_job_id')->references('id')->on('import_jobs')->cascadeOnDelete();
    $table->integer('row_number');
    $table->json('raw_data');
    $table->json('mapped_data')->nullable();
    $table->string('status');            // pending, valid, error, imported, skipped
    $table->json('errors')->nullable();
    $table->uuid('resulting_medicine_id')->nullable();
    $table->timestamps();

    $table->index(['import_job_id', 'status']);
    $table->index('row_number');
});
```

### Step 5.2 — Expected CSV columns

The import should accept this standard column set:

```
name, short_composition, manufacturer_name, type, dosage_form,
strength, price, mrp, currency, pack_size_label, quantity, quantity_unit,
rx_required, schedule, barcode, gs1_gtin, hsn_code,
storage_conditions, shelf_life, is_discontinued, description,
source, source_reference
```

### Step 5.3 — Import flow in Filament

```bash
php artisan make:filament-resource ImportJob --generate
```

**Import Wizard steps:**

1. **Upload** — file upload (CSV or XLSX), select import type
2. **Map Columns** — map CSV headers to model fields
3. **Validate** — run validation rules on all rows, show error summary
4. **Preview** — show first 20 rows, counts, error breakdown
5. **Import** — dispatch import job to queue
6. **Report** — show results: imported, skipped, errors

### Step 5.4 — Import Job

```php
// app/Jobs/ProcessMedicineImport.php
class ProcessMedicineImport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        $rows = $this->importJob->rows()->where('status', 'valid')->cursor();

        foreach ($rows as $row) {
            try {
                $medicine = Medicine::updateOrCreate(
                    ['slug' => Str::slug($row->mapped_data['name'])],
                    array_merge($row->mapped_data, [
                        'created_by' => $this->importJob->created_by,
                        'source'     => 'csv_import',
                        'source_reference' => $this->importJob->filename,
                    ])
                );

                $row->update([
                    'status' => 'imported',
                    'resulting_medicine_id' => $medicine->id,
                ]);
            } catch (\Exception $e) {
                $row->update(['status' => 'error', 'errors' => ['import' => $e->getMessage()]]);
            }
        }
    }
}
```

### Phase 5 Checklist

- [ ] import_jobs table migrated
- [ ] import_rows table migrated
- [ ] File upload to storage working
- [ ] Column mapper UI working
- [ ] Row validation against medicine rules working
- [ ] Preview showing valid vs error rows
- [ ] Import dispatched to queue
- [ ] Queue worker processing rows
- [ ] Error report downloadable as CSV
- [ ] Import provenance saved on medicine records

---

## Phase 6 — Search & Performance

**Goal:** Fast search for 100k+ medicine records. Caching for API responses.

### Step 6.1 — MySQL Full-Text (already in Phase 2)

Already added in migrations:
```php
$table->fullText(['name', 'short_composition']);  // medicines
$table->fullText(['alias']);                       // aliases
$table->fullText(['title', 'canonical_name', 'summary']); // combinations
```

### Step 6.2 — Redis caching for API

```php
// app/Http/Controllers/Api/V1/MedicineController.php

public function show(string $uuid): JsonResponse
{
    $medicine = Cache::remember(
        "medicine:{$uuid}",
        now()->addHour(),
        fn() => Medicine::published()->with(['manufacturer', 'identifiers', 'aliases'])->findOrFail($uuid)
    );

    return (new MedicineResource($medicine))->response();
}

// Clear cache on medicine update:
protected static function booted(): void
{
    static::saved(function (Medicine $medicine) {
        Cache::forget("medicine:{$medicine->id}");
        Cache::forget("medicine:slug:{$medicine->slug}");
    });
}
```

### Step 6.3 — Database indexes review

Before going live, run:

```sql
-- Verify indexes exist
SHOW INDEX FROM medicines;
SHOW INDEX FROM drug_combinations;
SHOW INDEX FROM drug_combination_sections;

-- Slow query log
SET GLOBAL slow_query_log = 'ON';
SET GLOBAL long_query_time = 1;
```

### Step 6.4 — Meilisearch (optional, for large datasets)

Install when medicine count exceeds ~50,000 records and full-text starts feeling slow.

```bash
composer require laravel/scout
composer require meilisearch/meilisearch-php
```

```php
// .env
SCOUT_DRIVER=meilisearch
MEILISEARCH_HOST=http://127.0.0.1:7700

// In Medicine model:
use Laravel\Scout\Searchable;

public function toSearchableArray(): array
{
    return [
        'id'                => $this->id,
        'name'              => $this->name,
        'short_composition' => $this->short_composition,
        'type'              => $this->type,
        'manufacturer'      => $this->manufacturer?->name,
        'barcode'           => $this->barcode,
        'gs1_gtin'          => $this->gs1_gtin,
    ];
}
```

### Phase 6 Checklist

- [ ] Full-text search on medicines returning correct results
- [ ] Barcode and GTIN exact lookups fast (<50ms)
- [ ] Redis caching on medicine show endpoint
- [ ] Cache invalidated on medicine save
- [ ] Slug-based combination lookup cached
- [ ] Slow query log checked, no full table scans
- [ ] Scout/Meilisearch decision made based on record count

---

## Phase 7 — Editorial Workflow

**Goal:** Review states, role-gated publishing, audit trail.

### Step 7.1 — Audit logs table

```php
// create_audit_logs_table
Schema::create('audit_logs', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->uuid('user_id')->nullable();
    $table->string('action');                // created, updated, published, archived, deleted
    $table->string('auditable_type');        // App\Models\Medicine, etc.
    $table->uuid('auditable_id');
    $table->json('old_values')->nullable();
    $table->json('new_values')->nullable();
    $table->string('ip_address', 45)->nullable();
    $table->text('user_agent')->nullable();
    $table->timestamp('created_at');

    $table->index(['auditable_type', 'auditable_id']);
    $table->index('user_id');
    $table->index('action');
    $table->index('created_at');
});
```

### Step 7.2 — Role-gated Filament actions

```php
// In MedicineResource
Action::make('publish')
    ->visible(fn() => auth()->user()->hasAnyRole(['admin', 'clinical-editor']))
    ->action(function (Medicine $record) {
        $record->update([
            'approval_status' => ApprovalStatusEnum::Published,
            'published_at'    => now(),
            'updated_by'      => auth()->id(),
        ]);
        AuditLog::record('published', $record);
    });

Action::make('archive')
    ->visible(fn() => auth()->user()->hasRole('admin'))
    ->requiresConfirmation()
    ->action(fn(Medicine $record) => $record->update(['approval_status' => ApprovalStatusEnum::Archived]));
```

### Step 7.3 — Editorial statuses

**Medicine workflow:**
`draft` → `reviewed` → `published` → `archived`

**Drug Combination workflow:**
`draft` → `in_review` → `medically_reviewed` → `published` → `retired`

Only `clinical-editor` and above can move to `medically_reviewed`.
Only `admin` and `super-admin` can publish or retire.

### Step 7.4 — AuditLog resource in Filament

```bash
php artisan make:filament-resource AuditLog --generate
```

Read-only resource. Columns: user, action, model type, record ID, timestamp. With filters by action, model type, user, and date range.

### Phase 7 Checklist

- [ ] audit_logs table migrated
- [ ] AuditLog::record() called on publish, archive, delete
- [ ] Role-gated publish action working
- [ ] Role-gated archive action working
- [ ] DrugCombination medically_reviewed status gated to clinical-editor
- [ ] Audit log resource visible in Filament System group
- [ ] Audit logs filtering by user and action working

---

## Phase 8 — Hardening & Ops

**Goal:** 2FA for admins, IP allowlist on API, monitoring, environment hardening.

### Step 8.1 — Two-Factor Authentication for Filament

```bash
composer require filament/spatie-laravel-media-library-plugin
```

Enable Filament's built-in 2FA:

```php
// AdminPanelProvider
->twoFactorAuthentication(
    policy: TwoFactorAuthenticationPolicy::Mandatory,
)
```

### Step 8.2 — IP allowlist middleware for API clients

```php
// app/Http/Middleware/CheckApiClientIpAllowlist.php
public function handle(Request $request, Closure $next): Response
{
    $token = $request->user()?->currentAccessToken();
    $client = ApiClient::where('id', $token?->tokenable_id)->first();

    if ($client && !empty($client->allowed_ips)) {
        $clientIp = $request->ip();
        if (!in_array($clientIp, $client->allowed_ips)) {
            abort(403, 'IP not allowed for this API key.');
        }
    }

    return $next($request);
}
```

### Step 8.3 — last_used_at tracking

```php
// app/Http/Middleware/TrackApiKeyUsage.php
public function handle(Request $request, Closure $next): Response
{
    $response = $next($request);

    if ($token = $request->user()?->currentAccessToken()) {
        ApiClient::where('id', $token->tokenable_id)
                 ->update(['last_used_at' => now()]);
    }

    return $response;
}
```

### Step 8.4 — Filament Dashboard widgets

```bash
php artisan make:filament-widget StatsOverviewWidget --stats-overview
```

Show:
- Total medicines
- Published medicines
- Total drug combinations
- Active API clients
- Last import date and row count

### Step 8.5 — Environment hardening checklist

```env
# Production .env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-domain.com

LOG_CHANNEL=stack
LOG_LEVEL=error

DB_HOST=your-rds-host
DB_PASSWORD=strong-random-password

CACHE_DRIVER=redis
SESSION_DRIVER=redis
QUEUE_CONNECTION=redis

SANCTUM_STATEFUL_DOMAINS=
```

### Step 8.6 — Production commands

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan optimize

# Queue worker (supervisor)
php artisan queue:work redis --queue=imports,default --sleep=3 --tries=3
```

### Phase 8 Checklist

- [ ] 2FA mandatory for all Filament admin users
- [ ] IP allowlist middleware applied on API routes
- [ ] last_used_at updated on every token-authenticated request
- [ ] Filament dashboard showing live stats
- [ ] APP_DEBUG=false in production
- [ ] All caches warmed after deploy
- [ ] Queue worker running under supervisor
- [ ] Slow query log disabled in production
- [ ] Log rotation configured

---

## Complete Database Schema Summary

| Table | Primary Key | Purpose |
|---|---|---|
| `users` | UUID | Admin users |
| `personal_access_tokens` | bigint | Sanctum tokens |
| `api_clients` | UUID | API key metadata |
| `manufacturers` | UUID | Drug manufacturers |
| `medicines` | UUID | Core medicine catalog |
| `medicine_identifiers` | UUID | Extra barcodes, GTINs per medicine |
| `medicine_aliases` | UUID | Alternate names, brand names |
| `drug_combinations` | UUID | Editorial parent per combination |
| `drug_combination_sections` | UUID | One row per section per combination |
| `drug_combination_items` | UUID | Ingredient/medicine links |
| `faqs` | UUID | FAQ rows per combination |
| `import_jobs` | UUID | Import batch metadata |
| `import_rows` | UUID | Individual import row with status |
| `audit_logs` | UUID | Admin action audit trail |
| `roles` | bigint | Spatie roles |
| `permissions` | bigint | Spatie permissions |

---

## Filament Navigation Summary

**Catalog group**
- Medicines
- Manufacturers
- Medicine Aliases
- Medicine Identifiers

**Clinical Content group**
- Drug Combinations
- FAQs (global view)

**Access Control group**
- Admin Users
- Roles
- Permissions
- API Clients

**System group**
- Audit Logs
- Import Jobs
- Settings

---

*Complete one phase checklist fully before starting the next phase.*
