# CE-AFSN Plugin Suite — Implementation Checklist

> Last verified against the working tree: all six plugin suites build clean
> (`php -l`) and pass their full assertion set — **1500 assertions, 0 failures** —
> both in place and again from each extracted release ZIP. Items below are only
> checked when the evidence exists in this repository. Anything requiring the live
> WordPress install at `ceafsn.duckdns.org` stays unchecked until it is run against
> that site.

## Phase 0 — Inspect Before Modifying
- [ ] Identify active theme, WP version, PHP version, active plugins
- [ ] Crawl and snapshot all target routes
- [ ] Inventory internal links, PDFs, CSVs, images, forms, iframes
- [ ] Save `qa/baseline-crawl.json`, `qa/baseline-links.csv`, `qa/baseline-content.md`
- [ ] Report any conflicts before overwriting functionality

> Not started. None of the `qa/baseline-*` artifacts exist yet. This phase is a
> prerequisite for Phase 3, since Phase 3 overwrites live content.

## Phase 1 — Project Structure
- [x] Root `README.md`, `CHANGELOG.md`
- [x] Root `LICENSE.txt` — verbatim GPL v2 text from gnu.org, sha256
      `edaef632cbb643e4e7a221717a6c441a4c1a7c918e6e4d56debc3d8739b233f6`
- [x] All plugin folders with sub-directories
- [x] `docs/` and `qa/` directories
- [x] `plugins/ceafsn-shared/` library

> `ceafsn-shared/` is intentionally a README-only stub. Every plugin duplicates
> the small amount of validation/sanitizing code it needs so it stays
> independently installable; no plugin `require`s the shared library.

## Phase 2 — Plugin Development

### Plugin 1: ceafsn-me-dashboard
- [x] Plugin header and bootstrap file
- [x] Admin CRUD for metrics, demographics, projects
- [x] Front-end shortcode `[ceafsn_me_dashboard]`
- [x] Accessible tabs (Overview, Demographics, Project Registry)
- [x] SVG/HTML charts with accessible data tables
- [x] Empty state / Preview badge
- [x] Branded admin UI — Overview landing page, KPI tiles, card-and-table records,
      restyled forms, honest empty states
- [x] Tests — 183 assertions (`php plugins/ceafsn-me-dashboard/tests/run-tests.php`)

### Plugin 2: ceafsn-nutrition-policy
- [x] Plugin header and bootstrap file
- [x] Admin CRUD for policy records
- [x] Front-end shortcode `[ceafsn_policy_table]`
- [x] Filterable, sortable accessible table
- [x] PDF validation on publish
- [x] Empty state
- [x] Scoped PDF-only upload restriction, registered per request
- [x] `topic` shortcode attribute honoured as the default filter
- [x] Branded admin UI — three-column app layout (WordPress admin menu, main
      canvas, right rail), alert banners, hero module, publishing-workflow
      stepper, KPI tiles, dot-and-pill status rows, check-marked guidance,
      promotional callouts, circular help badge; every rule scoped to
      `.ceafsn-np-wrap`
- [x] Tests — 247 assertions (`php plugins/ceafsn-nutrition-policy/tests/run-tests.php`)

> **Design direction (2026-10-04).** The first pass was deliberately flat —
> no shadows, square badges — after feedback that the gradient/shadow/pill
> styling looked machine-generated. That was then reversed: the target design
> is the raised-card, rounded, dot-and-pill layout above, with shadows and
> rounded corners. `ceafsn-nutrition-policy` is the reference implementation;
> `ceafsn-me-dashboard` and the remaining plugins still carry the earlier flat
> styling and need the same pass.

### Plugin 3: ceafsn-open-datasets
- [x] Plugin header and bootstrap file
- [x] Admin CRUD for dataset records
- [x] Front-end shortcode `[ceafsn_open_datasets]`
- [x] Repository table with download links
- [x] CSV/ZIP/XLSX structural validation on publish
- [x] External URL validation (HTTP 200, size, content type, http/https only)
- [x] File size measured or reported, never taken from the form
- [x] No-download empty state and honest unavailable row
- [x] Contact privacy, "other" file type opt-in, scoped upload restriction
- [x] JSON export and opt-in uninstall that never touches the Media Library
- [x] Tests — 381 assertions (`php plugins/ceafsn-open-datasets/tests/run-tests.php`)

### Plugin 4: ceafsn-projects-publications
- [x] Plugin header and bootstrap file
- [x] Admin CRUD for publications and projects
- [x] Front-end shortcode `[ceafsn_projects_pubs]`
- [x] Grid/list view with filters, sorting, and search
- [x] PDF validation per record (signature, EOF, size, page count, extractable text)
- [x] Placeholder document block with opt-in confirmation and note
- [x] Duplicate-document flag surfaced on the card and in the list
- [x] Publication state separated from project status
- [x] Members-only access with a filterable capability check
- [x] 301 redirect `/privacy-policy-2/` → `/publications/`, configurable
- [x] JSON export and opt-in uninstall that never touches the Media Library
- [x] `readme.txt` and `languages/ceafsn-pp.pot`
- [x] Tests — 332 assertions (`php plugins/ceafsn-projects-publications/tests/run-tests.php`)

### Plugin 5: ceafsn-research-fellowships
- [x] Plugin header and bootstrap file
- [x] Admin CRUD for fellowship opportunities
- [x] Front-end shortcode `[ceafsn_fellowships]`
- [x] Cards/table with date-driven status (`includes/class-ceafsn-rf-status.php`)
- [x] Apply button validation — `is_valid_application_url()` enforced on save
      and re-checked at render, which blanks an invalid link
- [x] Tests — 212 assertions (`php plugins/ceafsn-research-fellowships/tests/run-tests.php`)

### Plugin 6: ceafsn-grants-funding
- [x] Plugin header and bootstrap file
- [x] Admin CRUD for grants and scholarships
- [x] Front-end shortcode `[ceafsn_grants]`
- [x] Accessible opportunity cards/table
- [x] Deadline and timezone display — `deadline_timezone` column plus
      `normalize_deadline_to_utc()` so stored deadlines are timezone-correct
- [x] Official Call PDF validation
- [x] Tests — 157 assertions (`php plugins/ceafsn-grants-funding/tests/run-tests.php`)

## Phase 3 — Migration and Cleanup

> Not started. All items require the live site. Two of them are already
> *implemented in code* and only need activation plus verification:
> the `/privacy-policy-2/` → `/publications/` 301 redirect and the configurable
> `/appy` destination both ship in `ceafsn-projects-publications`
> (`tests/redirect-cases.php` covers the redirect logic).

- [ ] Create `/publications/` page
- [ ] 301 redirect `/privacy-policy-2/` → `/publications/` — code ready, needs activation
- [ ] Fix `/appy` route (configurable destination, not 404) — code ready, needs activation
- [ ] Update homepage, menus, footer internal links
- [ ] Remove/redirect Latin/Lorem Ipsum posts
- [ ] Fix `Alumin Network` title
- [ ] Remove default WP comment and `A WordPress Commenter`
- [ ] Noindex `edward_admin` author archive
- [ ] Fix malformed homepage heading
- [ ] Replace placeholder contact block with admin-managed block
- [ ] Replace template team profiles with real data or honest empty states

## Phase 4 — QA

> Not started. The `qa/` files exist but are **unpopulated placeholders**:
> `qa/final-report.md` is headed "Status: NOT YET RUN", every `actual_status` in
> `qa/routes.csv` is `PENDING`, and the one row in `qa/assets.csv` is `PENDING`
> with the known shared-placeholder PDF still flagged.

- [ ] HTTP route tests
- [ ] Publication file uniqueness and readability tests
- [ ] Content cleanliness tests (Lorem ipsum, demo slugs, placeholder strings)
- [ ] Dynamic module tests (dashboard, datasets)
- [ ] Accessibility tests (keyboard, focus, labels, headings, contrast)
- [ ] Security tests (nonces, capabilities, sanitization, escaping)
- [ ] Responsive tests (320px, 768px, 1280px)

## Phase 5 — Documentation and Packaging
- [x] `docs/data-model.md`
- [x] `docs/content-migration.md`
- [x] `docs/admin-runbook.md`
- [x] `docs/security-privacy.md`
- [ ] `qa/final-report.md` — exists but still a placeholder
- [ ] `qa/routes.csv` — exists but every result is `PENDING`
- [ ] `qa/assets.csv` — exists but every result is `PENDING`
- [x] ZIP packages for each plugin — all six built and committed alongside their
      plugin folder as `plugins/ceafsn-<name>.zip` (**not** under `dist/`, which is
      empty and unused)

> Packaging note: all six ZIPs are now built the same way and contain no
> `__MACOSX` resource-fork entries or `.DS_Store` files. Each archive was verified
> to hold exactly its source tree (no dropped files), to pass `unzip -t`, and to
> run its own test suite green from the extracted copy. Rebuild command:
> `cd plugins && zip -r -X ceafsn-<name>.zip ceafsn-<name> -x "*.DS_Store" "*__MACOSX*" "*.git*"`

## Definition of Done

Code-complete, awaiting live-site verification:

- [x] Six plugins activate without fatal errors — lint-clean and test-green in
      isolation; still needs an activation pass on the real install
- [ ] Theme styling intact
- [ ] `/publications/` exists and `/privacy-policy-2/` redirects permanently
- [ ] `/appy` no longer returns 404
- [ ] Publication files are unique, readable, correctly linked
- [ ] No Lorem ipsum, demo routes, placeholder contact, or fake metrics public
- [ ] Team profiles use approved authentic data or honest empty states
- [ ] CSV and PDF links tested end to end
- [x] Admin CRUD secured (capabilities, nonces, validation, escaping) — covered by
      the per-plugin suites
- [ ] Accessibility, responsive, security, and regression tests passed
- [ ] `qa/final-report.md` contains evidence

## Remaining Work, In Order

Phase 1 and Phase 2 are now complete, and all six plugins are packaged. What
remains is entirely live-site work on `ceafsn.duckdns.org`:

1. Run Phase 0 baseline crawl, then Phase 3 site migration.
2. Activate all six plugins, create the six target pages, add the shortcodes.
3. Run Phase 4 QA for real and replace the `qa/` placeholders with evidence.