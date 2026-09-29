# CE-AFSN Plugin Suite — Implementation Checklist

## Phase 0 — Inspect Before Modifying
- [ ] Identify active theme, WP version, PHP version, active plugins
- [ ] Crawl and snapshot all target routes
- [ ] Inventory internal links, PDFs, CSVs, images, forms, iframes
- [ ] Save `qa/baseline-crawl.json`, `qa/baseline-links.csv`, `qa/baseline-content.md`
- [ ] Report any conflicts before overwriting functionality

## Phase 1 — Project Structure
- [ ] Root `README.md`, `CHANGELOG.md`, `LICENSE.txt`
- [ ] All plugin folders with sub-directories
- [ ] `docs/` and `qa/` directories
- [ ] `plugins/ceafsn-shared/` library

## Phase 2 — Plugin Development

### Plugin 1: ceafsn-me-dashboard
- [x] Plugin header and bootstrap file
- [x] Admin CRUD for metrics, demographics, projects
- [x] Front-end shortcode `[ceafsn_me_dashboard]`
- [x] Accessible tabs (Overview, Demographics, Project Registry)
- [x] SVG/HTML charts with accessible data tables
- [x] Empty state / Preview badge
- [x] Tests (177 assertions, `php plugins/ceafsn-me-dashboard/tests/run-tests.php`)

### Plugin 2: ceafsn-nutrition-policy
- [x] Plugin header and bootstrap file
- [x] Admin CRUD for policy records
- [x] Front-end shortcode `[ceafsn_policy_table]`
- [x] Filterable, sortable accessible table
- [x] PDF validation on publish
- [x] Empty state
- [x] Scoped PDF-only upload restriction, registered per request
- [x] `topic` shortcode attribute honoured as the default filter
- [x] Tests (241 assertions, `php plugins/ceafsn-nutrition-policy/tests/run-tests.php`)

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
- [x] Tests (381 assertions, `php plugins/ceafsn-open-datasets/tests/run-tests.php`)

### Plugin 4: ceafsn-projects-publications
- [ ] Plugin header and bootstrap file
- [ ] Admin CRUD for publications and projects
- [ ] Front-end shortcode `[ceafsn_projects_pubs]`
- [ ] Grid/list view with filters
- [ ] PDF validation per record
- [ ] Duplicate-document flag
- [ ] Tests

### Plugin 5: ceafsn-research-fellowships
- [ ] Plugin header and bootstrap file
- [ ] Admin CRUD for fellowship opportunities
- [ ] Front-end shortcode `[ceafsn_fellowships]`
- [ ] Cards/table with date-driven status
- [ ] Apply button validation
- [ ] Tests

### Plugin 6: ceafsn-grants-funding
- [ ] Plugin header and bootstrap file
- [ ] Admin CRUD for grants and scholarships
- [ ] Front-end shortcode `[ceafsn_grants]`
- [ ] Accessible opportunity cards/table
- [ ] Deadline and timezone display
- [ ] Official Call PDF validation
- [ ] Tests

## Phase 3 — Migration and Cleanup
- [ ] Create `/publications/` page
- [ ] 301 redirect `/privacy-policy-2/` → `/publications/`
- [ ] Fix `/appy` route (configurable destination, not 404)
- [ ] Update homepage, menus, footer internal links
- [ ] Remove/redirect Latin/Lorem Ipsum posts
- [ ] Fix `Alumin Network` title
- [ ] Remove default WP comment and `A WordPress Commenter`
- [ ] Noindex `edward_admin` author archive
- [ ] Fix malformed homepage heading
- [ ] Replace placeholder contact block with admin-managed block
- [ ] Replace template team profiles with real data or honest empty states

## Phase 4 — QA
- [ ] HTTP route tests
- [ ] Publication file uniqueness and readability tests
- [ ] Content cleanliness tests (Lorem ipsum, demo slugs, placeholder strings)
- [ ] Dynamic module tests (dashboard, datasets)
- [ ] Accessibility tests (keyboard, focus, labels, headings, contrast)
- [ ] Security tests (nonces, capabilities, sanitization, escaping)
- [ ] Responsive tests (320px, 768px, 1280px)

## Phase 5 — Documentation and Packaging
- [ ] `docs/data-model.md`
- [ ] `docs/content-migration.md`
- [ ] `docs/admin-runbook.md`
- [ ] `docs/security-privacy.md`
- [ ] `qa/final-report.md`
- [ ] `qa/routes.csv`
- [ ] `qa/assets.csv`
- [ ] ZIP packages under `dist/` for each plugin

## Definition of Done
- [ ] Six plugins activate without fatal errors
- [ ] Theme styling intact
- [ ] `/publications/` exists and `/privacy-policy-2/` redirects permanently
- [ ] `/appy` no longer returns 404
- [ ] Publication files are unique, readable, correctly linked
- [ ] No Lorem ipsum, demo routes, placeholder contact, or fake metrics public
- [ ] Team profiles use approved authentic data or honest empty states
- [ ] CSV and PDF links tested end to end
- [ ] Admin CRUD secured (capabilities, nonces, validation, escaping)
- [ ] Accessibility, responsive, security, and regression tests passed
- [ ] `qa/final-report.md` contains evidence
