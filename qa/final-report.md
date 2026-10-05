# CE-AFSN Plugin Suite — Final QA & Completion Report

**Date:** 2026-10-05  
**Scope:** Wave 1 (Critical Bug Fixes) + Wave 2 (Platform Integrity) across all 6 plugins  
**Baseline:** 2026-10-04 crawl of `ceafsn.duckdns.org`

---

## Test Harness Summary — All Green

| Plugin | Assertions | Passed | Failed |
|--------|-----------|--------|--------|
| `ceafsn-shared` | 121 | 121 | 0 |
| `ceafsn-me-dashboard` | 260 | 260 | 0 |
| `ceafsn-nutrition-policy` | 309 | 309 | 0 |
| `ceafsn-open-datasets` | 482 | 482 | 0 |
| `ceafsn-projects-publications` | 516 | 516 | 0 |
| `ceafsn-research-fellowships` | 356 | 356 | 0 |
| `ceafsn-grants-funding` | 345 | 345 | 0 |
| **Total** | **2389** | **2389** | **0** |

---

## Wave 1 — Critical Bug Fixes

### 1. $wpdb Format Corruption in Projects & Publications
**File:** `ceafsn-projects-publications/includes/class-ceafsn-pp-db.php`

`row_formats()` was mapping `project_status` (pos 6) and `access_level` (pos 12) to `%d`, coercing ENUM strings to `0` on every insert and update.

**Fix:** Updated both format specifiers to `%s`.

**Tests:** `FakePpWpdb::insert()` updated to honour `$format` strictly. New assertions verify `project_status = 'in_progress'` and `access_level = 'public'` survive a round-trip without corruption.

### 2. Pagination & Filtering in Fellowships and Grants
**Files:** `class-ceafsn-rf-public.php`, `class-ceafsn-gf-public.php`, `public/partials/fellowships.php`, `public/partials/grants.php`

Filtering was running AFTER `LIMIT/OFFSET`, making `$total` equal to the current page count rather than the full filtered set.

**Fix:**
- `COUNT(*)` query with all `WHERE` clauses runs before `LIMIT/OFFSET` to compute the true total.
- `<nav class="ceafsn-pagination">` block with Previous / Next / page links rendered in both partials.
- All `$_GET` values in pagination loops validated as scalars before casting.

### 3. Undefined Variable in Nutrition Policy Tests
**File:** `ceafsn-nutrition-policy/tests/run-tests.php` line 1140

`$np_is_general` was referenced before assignment.

**Fix:** Variable initialised to `false` before the conditional block.

---

## Wave 2 — Platform Integrity & Localization

### 4. Database Migrations Engine
**All 6 plugins + ceafsn-shared**

`maybe_upgrade()` implemented in every DB class:
- Reads `ceafsn_{plugin}_db_version` option.
- Compares with `SCHEMA_VERSION` constant using `version_compare()`.
- Calls `create_tables()` (which runs `dbDelta()`) if behind, then steps through ordered `migrations()` callbacks.
- Hooked to `admin_init` in each plugin's bootstrap file so upgrades reach existing sites without requiring a fresh activation.
- `SCHEMA_VERSION` bumped from `1.0.0` → `1.1.0` across all 6 plugins to reflect the schema additions in this wave.

### 5. Translation & Text Domain Consistency
**All 6 plugins**

- `load_plugin_textdomain()` added for `ceafsn-nutrition-policy` and `ceafsn-me-dashboard`.
- `ceafsn-open-datasets` textdomain loading moved from `plugins_loaded` to `init`.
- All 17 instances of `ucfirst($status)` rendering raw database values replaced with localised label maps (`status_labels()`, `grant_status_labels()`, etc.) returning strings via `__('...', 'domain')`.

### 6. Custom Capabilities & Audit Logging
**`ceafsn-shared` library**

- `CEAFSN_Caps` class registers `ceafsn_manage`, `ceafsn_edit`, `ceafsn_approve`.
- Assigned to `administrator` role and a new `ceafsn_editor` role; all admin screens fall back to `manage_options` when the shared library is absent.
- `CEAFSN_Audit_Log` class creates `wp_ceafsn_audit_log` table: `[id, user_id, action, entity_type, entity_id, payload_before, payload_after, created_at]`.
- `maybe_upgrade()` on `admin_init` keeps the audit table schema current.

### 7. Database Indexing & Search Performance (Phase 2.7)

#### 7a. Composite Indexes — MED Dashboard

New indexes on `wp_ceafsn_med_projects`:

```sql
FULLTEXT KEY search_prose (title, principal_investigator)
KEY status_verification (status, verification_status)
KEY last_updated (last_updated)
```

New indexes on `wp_ceafsn_med_metrics` and `wp_ceafsn_med_demographics`:

```sql
KEY visibility_reporting (visibility, reporting_period)
```

`CEAFSN_MED_DB::SCHEMA_VERSION` bumped to `1.1.0`; `maybe_upgrade()` triggers `dbDelta()` on the next `admin_init` to add indexes to existing installations.

#### 7b. FULLTEXT Indexes — All 5 Other Plugins

Each plugin's table gained a `FULLTEXT KEY search_prose (...)` covering its main searchable prose columns:

| Plugin | FULLTEXT columns |
|--------|-----------------|
| NP | `title, description, authoring_institution` |
| OD | `name, description, coverage_area` |
| PP | `title, executive_summary, author_institution` |
| RF | `title, track_domain, eligibility, host_supervisor` |
| GF | `title, eligibility, funding_institution, target_beneficiaries` |

#### 7c. Search Query Strategy

All 6 DB classes use a two-tier search approach:

1. **Primary (when `ceafsn-shared` is active):** `CEAFSN_Search::clause()` builds an indexed `MATCH(...) AGAINST(... IN BOOLEAN MODE)` query for prose columns. Short words (< 3 chars) also get a prefix `LIKE 'term%'` branch so they are still findable.
2. **Fallback (shared library absent):** Per-column `LIKE '%term%'` across prose columns. This is narrower than full-text but deliberately preserved so search still works on sites without the shared library. The FULLTEXT index is present on the table; the query path is chosen at runtime.

**DDL syntax fix:** All 6 DB files had a stray leading comma before `FULLTEXT KEY` in the `CREATE TABLE` statement (`,FULLTEXT KEY ...`). This would have made `dbDelta()` emit malformed SQL to MySQL. Fixed in all 6 files.

---

## Git Diff Summary (Phase 2.7 files)

### DB Schema files — `,FULLTEXT KEY` → `FULLTEXT KEY`

```diff
# All 6 includes/class-ceafsn-*-db.php files
-			PRIMARY KEY (...),
-			,FULLTEXT KEY search_prose (...)
+			PRIMARY KEY (...),
+			FULLTEXT KEY search_prose (...),
```

### Test files — hard-coded version → constant

```diff
# ceafsn-research-fellowships/tests/run-tests.php
-is_same( '1.0.0', get_option( 'ceafsn_rf_db_version' ), 'the schema version option is recorded' );
+is_same( CEAFSN_RF_DB::SCHEMA_VERSION, get_option( 'ceafsn_rf_db_version' ), 'the schema version option is recorded' );

# ceafsn-grants-funding/tests/run-tests.php
-is_same( '1.0.0', get_option( 'ceafsn_gf_db_version' ), 'the schema version option is recorded' );
+is_same( CEAFSN_GF_DB::SCHEMA_VERSION, get_option( 'ceafsn_gf_db_version' ), 'the schema version option is recorded' );

# ceafsn-projects-publications/tests/run-tests.php
-is_same( '1.0.0', get_option( 'ceafsn_pp_db_version' ), 'the schema version is recorded' );
+is_same( CEAFSN_PP_DB::SCHEMA_VERSION, get_option( 'ceafsn_pp_db_version' ), 'the schema version is recorded' );
```

---

## Modified Files (Phase 2.7 only)

| File | Change |
|------|--------|
| `ceafsn-me-dashboard/includes/class-ceafsn-med-db.php` | Fixed `,FULLTEXT KEY` syntax; added composite/single-column indexes; `SCHEMA_VERSION` → `1.1.0`; `maybe_upgrade()` implemented |
| `ceafsn-nutrition-policy/includes/class-ceafsn-np-db.php` | Fixed `,FULLTEXT KEY` syntax; `SCHEMA_VERSION` → `1.1.0`; `maybe_upgrade()` implemented |
| `ceafsn-open-datasets/includes/class-ceafsn-od-db.php` | Fixed `,FULLTEXT KEY` syntax; `SCHEMA_VERSION` → `1.1.0`; `maybe_upgrade()` implemented |
| `ceafsn-projects-publications/includes/class-ceafsn-pp-db.php` | Fixed `,FULLTEXT KEY` syntax; `SCHEMA_VERSION` → `1.1.0`; `maybe_upgrade()` implemented |
| `ceafsn-research-fellowships/includes/class-ceafsn-rf-db.php` | Fixed `,FULLTEXT KEY` syntax; `SCHEMA_VERSION` → `1.1.0`; `maybe_upgrade()` implemented |
| `ceafsn-grants-funding/includes/class-ceafsn-gf-db.php` | Fixed `,FULLTEXT KEY` syntax; `SCHEMA_VERSION` → `1.1.0`; `maybe_upgrade()` implemented |
| `ceafsn-projects-publications/tests/run-tests.php` | Hard-coded `'1.0.0'` → `CEAFSN_PP_DB::SCHEMA_VERSION` |
| `ceafsn-research-fellowships/tests/run-tests.php` | Hard-coded `'1.0.0'` → `CEAFSN_RF_DB::SCHEMA_VERSION` |
| `ceafsn-grants-funding/tests/run-tests.php` | Hard-coded `'1.0.0'` → `CEAFSN_GF_DB::SCHEMA_VERSION` |

---

## Deployment Checklist

Before activating on `ceafsn.uem.mz`:

1. **Activate `ceafsn-shared` first** — capabilities, audit log, and search helpers depend on it.
2. **Activate each plugin** — `register_activation_hook` runs `create_tables()` for fresh installs.
3. For existing installs of `ceafsn-me-dashboard`: on first admin page load after deploy, `admin_init` fires `maybe_upgrade()` which adds the new indexes via `dbDelta()`. No manual SQL needed.
4. Verify MySQL engine is InnoDB (required for FULLTEXT on InnoDB, default since MySQL 5.6). If MyISAM is in use, `dbDelta()` will still create the table but FULLTEXT will behave differently.
5. `ft_min_word_len` / `innodb_ft_min_token_size` should be ≤ 3 (default is 4 on some configs). The shared `CEAFSN_Search` class adds a fallback `LIKE` branch for tokens shorter than 3 characters to mitigate this.

---

## Known Remaining Items (out of scope for this phase)

See `README.md` for the live-site issues that require deployment before they can be resolved:

- `/appy` 404 still live (redirect rule needs activating the plugins on the live site)
- `/privacy-policy-2/` → `/publications/` redirect requires the PP plugin to be active
- Placeholder PDFs on 12 publication cards — content owners need to provide real files
- Lorem ipsum on `/contact/` and `/volunteer/` — requires CMS edits by site owner
- Demo records on `/me-dashboard/` (`Students 200`, `Females`, `Test project`) — requires the admin to replace with real data
