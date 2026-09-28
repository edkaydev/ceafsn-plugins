# CE-AFSN Modular WordPress Suite — Mega-Pro Implementation Prompt

> **Purpose:** Use this prompt with Kiro CLI or another senior coding agent inside the CE-AFSN WordPress workspace. It is designed from the previous crawl and follow-up QA audits. Do not treat the website as a blank installation: inspect the existing theme, pages, plugins, media, redirects, and content before changing anything.

---

## Role and operating standard

Act as a **Principal WordPress Engineer, Plugin Architect, Security Engineer, Accessibility Specialist, SEO Engineer, Data-Product Designer, and QA Lead**.

Build, migrate, test, and document a production-ready suite of six lightweight WordPress plugins for:

`https://ceafsn.duckdns.org/`

You must work conservatively on the existing site. Preserve existing content and theme behavior unless a change is explicitly required below. Never overwrite or delete content blindly. Create backups or exportable migration files before destructive changes.

Do not report a task as complete because files were generated. A task is complete only when the implementation passes the acceptance tests, routes return the expected status codes, uploaded files are readable, and the final QA report contains evidence.

---

## Known defects that this implementation must fix

The prior audits found the following public defects. Treat them as mandatory regression tests:

1. `/appy` returns HTTP 404 and is still used by Apply Now actions.
2. `/privacy-policy-2/` is still the publication page and `/publications/` does not exist.
3. Twelve publication cards all point to the same one-page, 8,951-byte, textless PDF named `ceafsn.pdf`.
4. Lorem ipsum and Latin/demo posts remain publicly accessible.
5. `/contact/` still contains `304 North Cardinal St. Dorchester Center, MA 02124`, `1-555-123-4567`, `1-800-123-4567`, and Lorem ipsum.
6. `/volunteer/` is titled `Alumin Network` and contains template content.
7. The `edward_admin` author archive and `A WordPress Commenter` comment remain public.
8. `/our-team/` contains generic/template staff names and shallow “Support Staff” profiles.
9. `/me-dashboard/` displays scanning/initializing states and ellipses instead of honest data or a clearly labeled preview.
10. `/open-datasets/` displays “Download CSV” but does not expose real CSV files; metrics remain empty/initializing.
11. The homepage still points to `/privacy-policy-2/`, `/hello-world/`, Latin-slug articles, and other legacy/demo destinations.
12. The homepage contains the malformed heading `How you’re changing children’s lives” with:`.

No placeholder content, fake numbers, fake identities, or fake files may be introduced to make the UI look populated.

---

## Non-negotiable engineering principles

### Theme compatibility

- Do not hardcode font families, primary colors, container widths, or global typography.
- Inherit the active theme’s typography and colors.
- Use CSS custom properties only for local component behavior and progressive enhancement.
- Prefix every CSS class, option, function, action, filter, shortcode, database key, and JavaScript namespace with a unique CE-AFSN prefix.
- Do not reset or globally override the active theme.
- Use semantic HTML5 and minimal, component-scoped CSS.

### WordPress compatibility

- Target PHP 8.1+ and current supported WordPress versions.
- Use WordPress APIs wherever possible: Settings API, Options API, Media API, Custom Post Types, taxonomy APIs, metadata APIs, shortcode APIs, REST APIs only when necessary, and WP-Cron only when necessary.
- Avoid external frameworks, remote JavaScript, remote CSS, CDNs, iframes, and unnecessary dependencies.
- Do not require a build step for the production plugin ZIPs unless a development build is also supplied.
- Each plugin must activate, deactivate, and uninstall safely.
- Uninstall behavior must be documented and must never delete user content unless the administrator explicitly enables deletion.

### Security

- Check capabilities on every admin operation.
- Use nonces for every state-changing request.
- Sanitize on input and escape on output using context-appropriate WordPress functions.
- Validate URLs, MIME types, file extensions, IDs, dates, numeric ranges, and enumerated statuses.
- Prevent arbitrary file upload, SSRF, stored XSS, SQL injection, CSRF, insecure direct object references, and privilege escalation.
- Do not expose private admin data through public endpoints.
- Do not display private contact or applicant data publicly.
- Use prepared SQL statements if custom SQL is unavoidable.

### Accessibility

- Meet WCAG 2.2 AA as far as the plugin-controlled UI is concerned.
- Keyboard-accessible tabs, filters, buttons, dialogs, and tables.
- Correct heading hierarchy, visible focus states, labels, descriptions, status announcements, and table headers.
- Every meaningful image requires editable alt text; decorative images must use empty alt text intentionally.
- Charts must have a text/table alternative. Never make a chart the only way to understand data.
- Do not use color alone to communicate status.

### SEO and canonical URLs

- Use one canonical URL per content type.
- Add redirects and update internal links when a route changes.
- Do not index demo, author, comment-query, or retired routes.
- Use correct page titles, headings, canonical tags, Open Graph metadata where appropriate, and Schema.org only where the data is real.
- Do not output fake publication dates, authors, numbers, organizations, or citations.

---

## Required execution phases

### Phase 0 — Inspect before modifying

1. Inspect the repository and existing WordPress installation structure.
2. Identify the active theme, WordPress version, PHP version, active plugins, existing shortcodes, page IDs, permalink settings, and current route ownership.
3. Crawl and snapshot the following routes before changes:
   - `/`
   - `/appy`
   - `/privacy-policy-2/`
   - `/publications/`
   - `/contact/`
   - `/volunteer/`
   - `/our-team/`
   - `/me-dashboard/`
   - `/open-datasets/`
   - `/news/`
   - `/category/opportunities/`
   - `/category/research/`
   - `/author/edward_admin/`
   - `/hello-world/`
   - `/lobortis-elementum-nibhtellus-molestie-adipiscing/`
   - `/duis-tristique-sollicitudin-nibh-sit-amet-commodo-nulla/`
4. Inventory all internal links, PDF links, CSV links, images, forms, iframes, tables, scripts, and redirects.
5. Save the baseline as `qa/baseline-crawl.json`, `qa/baseline-links.csv`, and `qa/baseline-content.md`.
6. Stop and report any conflict with the existing theme or plugins before overwriting functionality.

### Phase 1 — Create project structure

Create:

```text
TODO.md
README.md
CHANGELOG.md
LICENSE.txt
qa/
docs/
plugins/
  ceafsn-me-dashboard/
  ceafsn-nutrition-policy/
  ceafsn-open-datasets/
  ceafsn-projects-publications/
  ceafsn-research-fellowships/
  ceafsn-grants-funding/
```

Each plugin folder must contain:

```text
plugin-name.php
README.md
uninstall.php
includes/
admin/
public/
assets/css/
assets/js/
tests/
```

If shared code is necessary, create a small namespaced shared library under `plugins/ceafsn-shared/` or duplicate only minimal code. Do not create hidden coupling that prevents individual plugin activation.

### Phase 2 — Build the six plugins

Each plugin must provide:

- A valid WordPress plugin header.
- Namespaced PHP classes or uniquely prefixed procedural code.
- Activation/deactivation hooks.
- Admin menu and CRUD screens.
- Capability checks and nonces.
- Sanitized settings and validated records.
- A documented shortcode.
- Empty-state UI that is honest and useful.
- Responsive, semantic front-end output.
- README with installation, shortcode, data model, security, accessibility, and troubleshooting instructions.
- PHPUnit or focused integration tests where the workspace supports them.

#### Plugin 1 — `ceafsn-me-dashboard`

**Target:** `/me-dashboard/`  
**Shortcode:** `[ceafsn_me_dashboard]`

Purpose: institutional monitoring and evaluation hub.

Admin-managed entities:

- Core metrics: label, value, unit, definition, source, reporting period, visibility.
- Demographic groups: label, count or percentage, reporting period, source.
- Projects: title, principal investigator, target region, status, verification status, last updated, source URL.

Front end:

- Accessible tabs: Overview, Demographics, Project Registry.
- Stat cards only when a real value exists.
- SVG/HTML charts with a complete accessible data table beneath or beside each chart.
- Filterable project table with pagination and no-data state.
- Visible “Last updated” and “Data source” fields.
- If there is no approved data, show: **“No public metrics have been published yet.”** Do not show fake zeroes, ellipses, scanning animations, or fabricated live-sync claims.
- If the installation is deliberately a preview, display a clear **Preview** badge and explain that the values are not live.

#### Plugin 2 — `ceafsn-nutrition-policy`

**Target:** `/nutrition-policy-modeling/`  
**Shortcode:** `[ceafsn_policy_table]`

Admin-managed policy records:

- Policy title
- Description
- Publication date
- Topic/domain
- Authoring institution
- PDF attachment
- Source/citation URL
- Status

Front end:

- Filterable, sortable, accessible table.
- “Read Policy” opens the verified PDF in a new tab using `target="_blank" rel="noopener noreferrer"`.
- Validate that the attachment is a readable PDF before publishing.
- Show a clear empty state instead of sample policies.

#### Plugin 3 — `ceafsn-open-datasets`

**Target:** `/open-datasets/`  
**Shortcode:** `[ceafsn_open_datasets]`

Admin-managed dataset records:

- Dataset name
- Description
- Category/sector
- Coverage area
- Last updated
- File attachment or approved download URL
- File type and size
- Data license
- Methodology/data dictionary URL
- Contact/owner
- Status: Draft, Published, Archived

Front end:

- Accessible repository table with Dataset Name, Category/Sector, Coverage Area, Last Updated, File Type/Size, License, and Download CSV.
- A Download CSV action must point to a real CSV or ZIP file and be tested with an HTTP request.
- Never show “Download CSV” without a real target.
- If no public file exists, show **“No public dataset is currently available for download.”**
- Never use fake HDDS, household, yield, or other metrics.

#### Plugin 4 — `ceafsn-projects-publications`

**Target:** `/publications/`  
**Legacy route:** `/privacy-policy-2/` must permanently redirect to `/publications/`.

Shortcode: `[ceafsn_projects_pubs]`

Purpose: replaces the broken publication library and provides a project/publication repository.

Admin-managed records:

- Title
- Content type: Report, Annual Report, Policy Brief, Working Paper, Strategic Document, Project
- Executive summary
- Author(s)/institution
- Publication date
- Status: In Progress, Completed, Under Review, Archived
- Cover image and alt text
- PDF attachment
- Page count
- DOI/citation/source URL
- Access level

Front end:

- Grid/list view with filters by type, topic, year, and status.
- Every published record must have its own unique attachment or approved external document URL.
- Never allow multiple records to silently share the same placeholder PDF unless the administrator explicitly confirms that they are the same document.
- Validate each PDF: HTTP 200, correct PDF MIME/signature, non-zero file size, readable by `pdfinfo`/equivalent, and non-empty text or a documented scanned-PDF exception.
- Show file size, page count, and publication metadata.
- Use `target="_blank" rel="noopener noreferrer"` for external document viewing.
- Do not advertise page counts that do not match the actual file.

#### Plugin 5 — `ceafsn-research-fellowships`

**Target:** `/research-fellowships/`  
**Shortcode:** `[ceafsn_fellowships]`

Admin-managed opportunities:

- Fellowship title
- Track/domain
- Duration
- Eligibility criteria
- Host/supervisor
- Stipend or funding information, if approved
- Opening and closing dates
- Status: Open, Closed, Upcoming, Archived
- Application URL
- Call PDF
- Contact email

Front end:

- Cards or table with all required fields.
- Apply button must be a valid URL or a clearly disabled state.
- Automatically show the status based on dates only if dates are configured and timezone behavior is documented.
- Never claim an opportunity is Open without an approved closing date or explicit admin override with audit note.

#### Plugin 6 — `ceafsn-grants-funding`

**Target:** `/scholarships-grants/`  
**Shortcode:** `[ceafsn_grants]`

Admin-managed records:

- Grant/scholarship title
- Award range or “Not disclosed”
- Deadline
- Target beneficiaries
- Eligibility
- Status
- Official call PDF
- Application URL
- Funding institution
- Contact

Front end:

- Accessible opportunity cards/table.
- Display deadline with timezone and status.
- Official Call PDF must be unique, readable, and validated.
- Do not invent funding amounts, open statuses, or deadlines.

---

## Required migration and cleanup work

### Route migration

Implement and test:

- Create `/publications/`.
- Add a permanent 301 redirect from `/privacy-policy-2/` to `/publications/`.
- Update the homepage, menus, footer, and all internal links to `/publications/`.
- Replace or remove every `/appy` link. The destination must be configurable from the relevant admin screen and must never default to a 404.
- Preserve query parameters only when safe and necessary.
- Add canonical tags for slash conventions and redirect duplicate slashless routes.

### Demo-content cleanup

Do not delete records silently. First export a migration report. Then, based on the site owner’s approved cleanup list:

- Unpublish, redirect, or archive Latin/Lorem Ipsum posts.
- Remove `hello-world` from public navigation and redirect it if replaced.
- Disable or noindex the `edward_admin` author archive unless a real public author profile is approved.
- Remove default WordPress comments and disable comments on institutional publication pages unless moderation is configured.
- Remove template names and replace them only with approved staff records.
- Correct `Alumin Network` to the owner-approved title, such as `Alumni Network` or `Volunteer Network`.
- Remove the malformed homepage heading and replace it with approved copy.
- Do not use stock or unrelated media as institutional proof without license/credit and owner approval.

### Contact data

Create an admin-managed institutional contact block. Do not seed Massachusetts addresses, `1-555` numbers, Lorem ipsum, or other demo values. If verified values are not supplied in the workspace:

- Show an empty-state notice in admin.
- Do not display invented public contact details.
- Keep the existing verified UEM/CE-AFSN contact values only if they are confirmed in the workspace.
- Configure and test the form recipient, sender policy, spam protection, privacy notice, success/error messages, and data retention behavior.

### Team directory

Create authenticated staff profiles with:

- Name
- Role/title
- Department
- Biography
- Research areas
- Institutional email, only if approved for public display
- ORCID/profile URL, if available
- Headshot and alt text
- Publications/projects
- Display order and active/inactive status

Do not seed generic names. An empty team profile is preferable to fake personnel data.

---

## Admin UX requirements

- Use clear menu labels and help text.
- Provide add/edit/delete/archive flows.
- Provide bulk actions where safe.
- Provide validation messages beside invalid fields.
- Provide preview links.
- Show last modified time and modifying user for managed records.
- Use WordPress Media Library for PDFs, CSVs, and images where appropriate.
- Prevent publishing a record with missing required files/URLs.
- Provide an import/export JSON or CSV function for migration and backup.
- Provide a reset/restore path for plugin settings without deleting content.

---

## Data and file validation rules

Implement reusable validators:

### PDF validator

- Must resolve with HTTP 200 when external.
- Must have a valid PDF MIME type and `%PDF` signature.
- Must have a non-zero file size.
- Must have a readable page count.
- Must contain extractable text unless explicitly marked as a scanned document.
- Must not be the known placeholder `ceafsn.pdf` unless the admin explicitly confirms its identity and it passes validation.
- Must not be reused across distinct publications without an explicit duplicate-document flag.

### CSV validator

- Must resolve with HTTP 200.
- Must have a CSV/ZIP MIME type or recognized file signature.
- Must have a non-zero file size.
- Must contain a readable header row.
- Must display file size, update date, license, and data dictionary link where available.

### URL validator

- Must reject empty URLs, `javascript:` URLs, malformed URLs, and same-domain routes known to return 404.
- Run a pre-publish link check for application links, document links, CSV links, and source links.

---

## Testing and QA deliverables

Create automated or scripted tests under `qa/` and run them before reporting completion.

### HTTP route tests

Assert:

- `/appy` is not 404 and resolves to the configured application destination.
- `/publications/` returns HTTP 200.
- `/privacy-policy-2/` returns HTTP 301/308 and redirects to `/publications/`.
- Homepage publication CTAs point to `/publications/` or approved canonical article routes.
- No internal link on the tested public pages returns HTTP 4xx/5xx.

### Publication tests

Assert:

- Exactly 12 or the configured number of publication records exist.
- Each published record has a unique document URL unless explicitly marked as a shared document.
- Each document returns HTTP 200 and is readable.
- Page count and title metadata match the admin record.
- No card points to the old generic `ceafsn.pdf` placeholder.

### Content cleanliness tests

Search public HTML and XML/sitemap output for:

- `Lorem ipsum`
- `lorem ipsum`
- `hello-world`
- `lobortis-elementum`
- `duis-tristique`
- `mauris-cursus`
- `aenean-tortor`
- `Alumin Network`
- `edward_admin`
- `A WordPress Commenter`
- `1-555-123-4567`
- `1-800-123-4567`
- `304 North Cardinal St.`
- `Scanning Kobo Ecosystem`
- `Initializing Stream`
- placeholder output such as `...` or `…` in live metric fields

Every match must be removed, redirected, noindexed, or explicitly justified in the QA report.

### Dynamic module tests

- `/me-dashboard/` must show real admin-entered data or a clear Preview/No public data badge.
- `/open-datasets/` must show working CSV/ZIP links or a clear no-download state.
- No loading animation may remain indefinitely when data is absent.
- Charts must have accessible tables.

### Accessibility tests

Run an automated accessibility scan if available and manually verify:

- Keyboard navigation
- Focus visibility
- Labels and headings
- Tables and chart alternatives
- Color contrast of plugin-controlled elements
- Screen-reader status messages

### Security tests

Verify:

- Unauthenticated users cannot perform CRUD operations.
- Nonces are required and invalid nonces are rejected.
- Unauthorized roles cannot access admin screens.
- HTML, URLs, file uploads, and rich text are sanitized/escaped.
- Direct PHP file access is blocked where appropriate.

### Responsive tests

Test at minimum:

- 320px wide mobile
- 768px tablet
- 1280px desktop

Capture screenshots or structured evidence for each major shortcode page.

---

## Required documentation output

Generate:

1. `TODO.md` — implementation checklist with status.
2. `README.md` — suite installation, activation order, dependencies, and rollback procedure.
3. One README per plugin.
4. `docs/data-model.md` — entities, fields, statuses, and relationships.
5. `docs/content-migration.md` — old route to new route mapping and cleanup decisions.
6. `docs/admin-runbook.md` — how staff publish dashboards, datasets, publications, fellowships, grants, and team profiles.
7. `docs/security-privacy.md` — capabilities, data retention, form handling, and public/private data rules.
8. `qa/final-report.md` — pass/fail evidence for every requirement.
9. `qa/routes.csv` — URL, expected status, actual status, final URL, title, and notes.
10. `qa/assets.csv` — asset URL, type, HTTP status, MIME type, size, readability, unique/shared status, and notes.
11. ZIP packages for each plugin under `dist/`.

The final report must include:

- Files created and changed.
- Database/content migrations performed.
- Routes created, redirected, archived, or removed.
- Test commands and outputs.
- Known limitations.
- Any decisions requiring owner-provided real data.

---

## Definition of done

Do not say “complete” until all are true:

- Six plugins activate without fatal errors.
- Existing theme styling remains intact.
- `/publications/` exists and `/privacy-policy-2/` redirects permanently to it.
- `/appy` no longer returns 404.
- Publication files are unique, readable, and correctly linked.
- No Lorem ipsum, Latin/demo routes, default author/commenter, placeholder contact details, or fake metrics remain publicly indexed.
- Team profiles use approved authentic data or honest empty states.
- M&E and datasets show real published data or clear Preview/No public data states.
- CSV and PDF links have been tested end to end.
- Admin CRUD is secured with capabilities, nonces, validation, and escaping.
- Accessibility, responsive, security, and regression tests have been run.
- `qa/final-report.md` contains evidence rather than assurances.

If real institutional content, PDFs, CSVs, staff records, application URLs, or contact details are not available in the workspace, **do not invent them**. Implement the admin fields, validation, and empty states, then list the missing owner inputs clearly in the final report.

---

## Start now

1. Inspect the current workspace and existing site/theme/plugin state.
2. Create the baseline crawl and backup/export artifacts.
3. Create `TODO.md` and the project structure.
4. Implement the shared security, validation, admin, and rendering patterns.
5. Build the six plugins one at a time.
6. Perform the route migration and content cleanup safely.
7. Run the full QA suite.
8. Fix failures and rerun tests.
9. Produce the ZIP packages and all documentation.
10. Finish with a concise pass/fail report that names every remaining owner-provided input.
