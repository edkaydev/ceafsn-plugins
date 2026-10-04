# CE-AFSN Plugin Suite — Implementation Checklist

> Last verified against the working tree: all six plugin suites build clean
> (`php -l` across 84 tracked files) and pass their full assertion set —
> **1933 assertions, 0 failures**. Items below are only checked when the
> evidence exists in this repository or in `qa/`. Anything requiring the live
> WordPress install at `ceafsn.duckdns.org` stays unchecked until it is run
> against that site.

## Phase 0 — Inspect Before Modifying
- [x] Identify active theme, WP version, PHP version, active plugins — WordPress
      7.1.2, Blocksy, Yoast, Autoptimize, GTranslate. Plugin list is inferred
      from asset URLs and `ceafsn-me-dashboard` markup, **not** from wp-admin, so
      it is incomplete
- [x] Crawl and snapshot all target routes — `qa/crawl-baseline.php`, 18 routes
      plus sitemap/robots/feed
- [x] Inventory internal links, PDFs, CSVs, images, forms, iframes — 1817 rows in
      `qa/baseline-links.csv`; zero PDF/CSV/download links found site-wide
- [x] Save `qa/baseline-crawl.json`, `qa/baseline-links.csv`, `qa/baseline-content.md`
- [x] Report any conflicts before overwriting functionality — nine confirmed
      defects listed in `qa/final-report.md`

> PHP version could not be read from outside the site. Baseline captured
> 2026-10-04; re-run `php qa/crawl-baseline.php` before acting on it, because
> Phase 3 overwrites live content.

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
- [x] Branded admin UI — three-column app layout (WordPress admin menu, main
      canvas, right rail), alert banners, hero module, publishing-workflow
      stepper, KPI tiles, dot-and-pill status rows, check-marked guidance,
      export callout, circular help badge; every rule scoped to
      `.ceafsn-med-wrap`
- [x] Settings screen shows the exact `[ceafsn_me_dashboard]` shortcode on a
      read-only Display tab, and states plainly that it takes no attributes;
      also shown on the main Overview page
- [x] Tests — 203 assertions (`php plugins/ceafsn-me-dashboard/tests/run-tests.php`)

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
- [x] Settings screen shows the exact `[ceafsn_policy_table]` shortcode with
      its `per_page` and `topic` attributes on a read-only Display tab, and
      on the main Overview page so the shortcode is visible without opening
      settings
- [x] Tests — 275 assertions (`php plugins/ceafsn-nutrition-policy/tests/run-tests.php`)

> **Design direction (2026-10-04).** The first pass was deliberately flat —
> no shadows, square badges — after feedback that the gradient/shadow/pill
> styling looked machine-generated. That was then reversed: the target design
> is the raised-card, rounded, dot-and-pill layout above, with shadows and
> rounded corners. `ceafsn-nutrition-policy` is the reference implementation,
> and all six plugins now carry that treatment, `ceafsn-grants-funding`
> included.

### Cross-plugin: settings saved per tab

- [x] Settings screens with one tab per editable option each post their own
      form, and each form declares which options it owns with a hidden
      `ceafsn_<slug>_settings_scope` field. The shared `handle_save_settings()`
      used to rewrite *every* option with `isset()`, so saving the Placeholders
      tab silently unticked "delete data on uninstall"
- [x] The option writing was extracted into a private `persist_settings( array
      $post )` in each of the five plugins, which makes the behaviour testable
      without triggering the redirect and `exit` in the handler
- [x] Regression tests in all five suites: saving one tab leaves the other
      tabs' options untouched, and a missing or unknown scope writes nothing
- [x] `ceafsn-research-fellowships` gained the same tabbed settings screen,
      which is what exposed the bug in the other four

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
- [x] Branded admin UI — three-column app layout (WordPress admin menu, main
      canvas, right rail), alert banners, hero module, publishing-workflow
      stepper, KPI tiles, dot-and-pill status rows, check-marked guidance,
      export callout, circular help badge; dataset form split into detail /
      download-target / visibility cards; settings rebuilt on the shared tabs
      and checkbox pattern; every rule scoped to `.ceafsn-od-wrap`
- [x] Settings screen shows the exact `[ceafsn_open_datasets]` shortcode with
      its `per_page`, `category`, and `file_type` attributes on a read-only
      Display tab, and on the main Datasets page
- [x] Tests — 440 assertions (`php plugins/ceafsn-open-datasets/tests/run-tests.php`)

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
- [x] Branded admin UI — three-column app layout (WordPress admin menu, main
      canvas, right rail), alert banners, hero module, publishing-workflow
      stepper, KPI tiles, dot-and-pill status rows, check-marked guidance,
      promotional callouts, circular help badge; record form split into
      details / document / cover image / visibility / document-flag cards;
      settings rebuilt on the shared tabs and checkbox pattern with the
      Display, Placeholders, Routes, Export, and Uninstall sections; every
      rule scoped to `.ceafsn-pp-wrap`
- [x] Settings screen shows the exact `[ceafsn_projects_pubs]` shortcode with
      its `per_page`, `content_type`, and `view` attributes, so the paste-in
      step is documented in the admin rather than only in `readme.txt`; the
      shortcode is also on the main All records page
- [x] Tests — 421 assertions (`php plugins/ceafsn-projects-publications/tests/run-tests.php`)

### Plugin 5: ceafsn-research-fellowships
- [x] Plugin header and bootstrap file
- [x] Admin CRUD for fellowship opportunities
- [x] Front-end shortcode `[ceafsn_fellowships]`
- [x] Cards/table with date-driven status (`includes/class-ceafsn-rf-status.php`)
- [x] Apply button validation — `is_valid_application_url()` enforced on save
      and re-checked at render, which blanks an invalid link
- [x] Admin view redesigned to the raised-card target — hero, split main/rail
      layout, workflow stepper, KPI row, alerts, empty state, table, and split
      record-form cards; every rule scoped to `.ceafsn-rf-wrap`
- [x] Media picker kept inside a single `<td>` and covered by a rendered-HTML
      test, because `assets/js/ceafsn-rf-admin.js` resolves the filename box and
      the clear button with `.closest( 'td' )`
- [x] Settings screen shows the exact `[ceafsn_fellowships]` shortcode with
      `per_page`, `track_domain`, `status`, and `view` on a Display tab, and on
      the main opportunities page so the shortcode is visible without opening
      settings
- [x] Export nonce bug fixed — the link put the nonce in `_wpnonce` while the
      handler read the `ceafsn_rf_nonce` field, so Export always died on a nonce
      failure; a static check now pairs every `wp_nonce_url()` link with the
      field its handler checks
- [x] Tests — 308 assertions (`php plugins/ceafsn-research-fellowships/tests/run-tests.php`)

### Plugin 6: ceafsn-grants-funding
- [x] Plugin header and bootstrap file
- [x] Admin CRUD for grants and scholarships
- [x] Front-end shortcode `[ceafsn_grants]`
- [x] Accessible opportunity cards/table
- [x] Deadline and timezone display — `deadline_timezone` column plus
      `normalize_deadline_to_utc()` so stored deadlines are timezone-correct
- [x] Official Call PDF validation
- [x] Branded admin UI — three-column app layout (WordPress admin menu, main
      canvas, right rail), alert banners, hero module, publishing-workflow
      stepper, KPI tiles, dot-and-pill status rows, check-marked guidance, export
      callout, circular help badge; the opportunity status and record state render
      as separate badges rather than one ambiguous column; every rule scoped to
      `.ceafsn-gf-wrap`
- [x] Counts on the summary tiles are computed from the records actually on
      screen, so a tile cannot claim something the table below contradicts
- [x] Tests — 286 assertions (`php plugins/ceafsn-grants-funding/tests/run-tests.php`)

## Phase 3 — Migration and Cleanup

> Baseline captured 2026-10-04 (`qa/baseline-content.md`). Every item below is
> confirmed still open on the live site: `/appy` returns 404, `/privacy-policy-2/`
> answers 200 instead of redirecting, `/publications/` returns 404, Lorem ipsum is
> live on `/contact/` and `/volunteer/`, four Latin demo posts are indexable, and
> `A WordPress Commenter` is visible on `/hello-world/`. Two of the items are
> already *implemented in code* and only need the plugin activated:
> the `/privacy-policy-2/` → `/publications/` 301 redirect and the configurable
> `/appy` destination both ship in `ceafsn-projects-publications`
> (`tests/redirect-cases.php` covers the redirect logic). That plugin is not
> currently active on the site.

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

> Partially run. `qa/final-report.md` now holds measured evidence, and
> `qa/routes.csv` / `qa/assets.csv` carry real results instead of `PENDING`. The
> outstanding sections are the ones that need a browser or an authenticated
> session, and the ones blocked by content that does not exist yet.

- [x] HTTP route tests — 18 routes measured 2026-10-04; 9 PASS, 3 FAIL
      (`/appy`, `/privacy-policy-2/`, `/publications/`), 6 UNVERIFIED pending an
      owner decision
- [ ] Publication file uniqueness and readability tests — blocked: zero PDFs are
      linked from any crawled page, so there is nothing public to test
- [x] Content cleanliness tests (Lorem ipsum, demo slugs, placeholder strings) —
      pattern scan across all 18 pages; Lorem ipsum found on `/contact/` and
      `/volunteer/`, four indexable Latin demo posts found
- [ ] Dynamic module tests (dashboard, datasets) — only `ceafsn-me-dashboard` is
      installed on the live site, and it is serving demo records
- [ ] Accessibility tests (keyboard, focus, labels, headings, contrast) — needs a
      real browser
- [x] Security tests (nonces, capabilities, sanitization, escaping) — covered by
      the six suites in code; no live HTTP security probing was performed
- [ ] Responsive tests (320px, 768px, 1280px) — no viewport testing was performed

## Phase 5 — Documentation and Packaging
- [x] `docs/data-model.md`
- [x] `docs/content-migration.md`
- [x] `docs/admin-runbook.md`
- [x] `docs/security-privacy.md`
- [x] `qa/final-report.md` — written 2026-10-04 with measured evidence and the
      nine confirmed live-site defects
- [x] `qa/routes.csv` — 18 routes with measured status and a `verdict` column
- [x] `qa/assets.csv` — measured rows for the logo, hero image, plugin and
      minified assets; the shared-placeholder PDF row is marked unverifiable
      rather than `PENDING`, because no PDF is linked from any crawled page
- [x] ZIP packages for each plugin — all six built alongside their plugin folder
      as `plugins/ceafsn-<name>.zip` (**not** under `dist/`, which is empty and
      unused). They are tracked in git so a release can be downloaded straight
      from the repository; the `plugins/*.zip` line in `.gitignore` only stops a
      *new* archive path from being added, it does not untrack these six

> Packaging note: all six ZIPs are built the same way and contain no
> `__MACOSX` resource-fork entries, no `.DS_Store`, and no `tests/` directory —
> the test harness is not shipped, and each `tests/run-tests.php` additionally
> refuses to run over HTTP. Each archive was verified to hold exactly its source
> tree minus `tests/` (sha256 per file, nothing dropped or extra), to pass
> `unzip -t`, and to lint clean from the extracted copy across all 65 shipped
> PHP files. Rebuild command:
> `cd plugins && zip -r -X ceafsn-<name>.zip ceafsn-<name> -x "*.DS_Store" "*__MACOSX*" "*.git*" "*/tests/*"`

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
- [ ] Accessibility, responsive, security, and regression tests passed — the
      security and regression halves are covered by the suites; the accessibility
      and responsive halves still need a browser
- [x] `qa/final-report.md` contains evidence

## Remaining Work, In Order

Phase 0, Phase 1 and Phase 2 are complete and all six plugins are packaged. What
remains is live-site work on `ceafsn.duckdns.org`:

1. Deploy and activate all six plugins. Only `ceafsn-me-dashboard` is installed
   today, and the other five pages currently show hand-authored demo content.
2. Create `/publications/`, move the existing "Policy Briefs & Publications"
   page onto that slug, and switch on the coded 301 from `/privacy-policy-2/`.
3. Set the application destination so `/appy` stops returning 404.
4. Clear the demo records out of `/me-dashboard/` (`Students 200`, `Females`,
   `Q1 2024`, `Test project`, `TEst project`).
5. Decide on the four Latin demo posts, `/hello-world/`, and
   `/author/edward_admin/`; close or moderate site-wide comments.
6. Replace Lorem ipsum on `/contact/` and `/volunteer/`, and correct
   "Alumin Network".
7. Re-run `php qa/crawl-baseline.php` to prove the fixes, then finish the browser
   QA sections that cannot be automated from the command line.