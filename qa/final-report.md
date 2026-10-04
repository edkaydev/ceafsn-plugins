# QA Final Report

> **Status: PARTIAL — repository evidence complete, live-site defects open**
> Generated 2026-10-04. Repository claims below are backed by commands in this
> file. Live-site findings come from `qa/crawl-baseline.php`, a read-only crawl;
> raw measurements are in `qa/baseline-crawl.json`, the per-page inventory in
> `qa/baseline-links.csv`, the summary in `qa/baseline-content.md`, and the
> pass/fail table in `qa/routes.csv`.

---

## Environment (measured from the live site)

| Item | Value | How it was found |
| --- | --- | --- |
| WordPress | 7.1.2 | `<meta name="generator">` |
| Theme | Blocksy | `/wp-content/themes/blocksy/` asset paths |
| SEO plugin | Yoast SEO | `robots.txt` block, `/sitemap_index.xml` |
| Minification | Autoptimize | `/wp-content/cache/autoptimize/` |
| Other plugin assets | GTranslate | `/wp-content/plugins/gtranslate/js/float.js` |
| Sitemap | `/sitemap_index.xml` → post/page/category/author | 301 from `/sitemap.xml` |
| Published URLs found | 6 posts, 15 pages, 3 categories, 1 author | sitemap children |

Active plugins were **inferred from asset URLs only**. Nothing here was read
from `wp-admin`, so treat the plugin list as incomplete.

---

## Test Sections

- [x] HTTP Route Tests — `qa/routes.csv`, 18 routes crawled 2026-10-04
- [ ] Publication File Uniqueness and Readability Tests — **no PDF is linked from
      any crawled page**, so there is nothing public to test. Blocked until the
      publications page exists.
- [x] Content Cleanliness Tests — pattern scan across all 18 pages, results in
      `qa/baseline-content.md`
- [ ] Dynamic Module Tests — only `ceafsn-me-dashboard` is installed on the live
      site; the other five plugins are not present
- [ ] Accessibility Tests — not run (needs a real browser and manual keyboard pass)
- [x] Security Tests — covered in code by the six suites (nonces, capabilities,
      sanitisation, escaping). No live HTTP security probing was performed.
- [ ] Responsive Tests — not run (no viewport testing was performed)

---

## Live Route Results

9 PASS, 3 FAIL, 6 UNVERIFIED (pending an owner decision).

| Route | Expected | Actual | Verdict |
| --- | --- | --- | --- |
| `/` | 200 | 200 | PASS |
| `/me-dashboard/` | 200 | 200 | PASS |
| `/nutrition-policy-modeling/` | 200 | 200 | PASS |
| `/open-datasets/` | 200 | 200 | PASS |
| `/research-fellowships/` | 200 | 200 | PASS |
| `/scholarships-grants/` | 200 | 200 | PASS |
| `/contact/` | 200 | 200 | PASS |
| `/volunteer/` | 200 | 200 | PASS |
| `/our-team/` | 200 | 200 | PASS |
| `/appy` | not 404 | **404** | **FAIL** |
| `/privacy-policy-2/` | 301 → `/publications/` | **200** | **FAIL** |
| `/publications/` | 200 | **404** | **FAIL** |
| `/hello-world/` | owner decision | 200 | UNVERIFIED |
| `/lobortis-elementum-nibhtellus-molestie-adipiscing/` | owner decision | 200 | UNVERIFIED |
| `/duis-tristique-sollicitudin-nibh-sit-amet-commodo-nulla/` | owner decision | 200 | UNVERIFIED |
| `/aenean-tortor-atisus-viverra-adipiscing/` | owner decision | 200 | UNVERIFIED |
| `/mauris-cursus-mattis-molestie-aaculis-oterat-pellentesque/` | owner decision | 200 | UNVERIFIED |
| `/author/edward_admin/` | owner decision | 200 | UNVERIFIED |

Note that a 200 on the five module routes is **not** evidence the plugins work.
Only `/me-dashboard/` renders `ceafsn-med-*` markup; the other four pages contain
hand-authored content and no plugin output at all.

---

## Defects Confirmed On The Live Site

1. **`/appy` returns 404.** The configurable-destination code is in
   `ceafsn-projects-publications` but the plugin is not active on the site.
2. **`/privacy-policy-2/` is not redirected.** It answers 200 and already carries
   the "Policy Briefs & Publications" title, so the content exists at the wrong
   slug. The 301 logic is coded and unit-tested but not switched on.
3. **`/publications/` does not exist** (404).
4. **Demo metrics are public on `/me-dashboard/`.** The page renders our
   shortcode output populated with `Students 200`, `Females`, `Q1 2024`,
   `Test project`, `TEst project`. This contradicts the README claim that fake
   metrics were replaced with honest empty states — the *code* is honest, the
   *data* is not. Those records must be deleted or replaced in wp-admin.
5. **Lorem ipsum is live** on `/contact/` and `/volunteer/`.
6. **Four Latin demo posts are live and indexable** — `/hello-world/`,
   `/lobortis-…/`, `/duis-…/`, plus `/aenean-…/` and `/mauris-…/` found in the
   post sitemap. None carry `noindex`.
7. **"A WordPress Commenter" is live** on `/hello-world/`, and
   `wp-comments-post.php` accepts POSTs, so comments are open site-wide.
8. **"Alumin Network"** appears in the footer or title of all 18 pages.
9. **`/author/edward_admin/` is indexable** and exposes Lorem ipsum excerpts.

---

## Assets

Full table in `qa/assets.csv`. Headline findings:

- **Zero** PDF, CSV, ZIP or XLSX links across all 18 pages. The known shared
  `ceafsn.pdf` placeholder is therefore not currently reachable from the
  public site, and cannot be validated from outside — check the Media Library.
- Site logo resolves only in its `cropped-` form (432,861 bytes); the
  un-cropped `/wp-content/uploads/ceafsn-circular-logo-premium.png` 404s.
- The `/me-dashboard/` hero image is a 2.4 MB PNG with no WebP/AVIF offered.
- 9 forms total (3 GET, 6 POST) including the live comment form.
- Autoptimize rewrites asset URLs, so per-plugin CSS/JS delivery cannot be
  confirmed from raw HTML.

---

## Repository Verification

All commands run from the repository root on 2026-10-04.

### Syntax

```
php -l across every tracked PHP file
→ No syntax errors detected (84 files)
```

### Test suites

```
php plugins/ceafsn-me-dashboard/tests/run-tests.php              → 203 passed, 0 failed
php plugins/ceafsn-nutrition-policy/tests/run-tests.php          → 275 passed, 0 failed
php plugins/ceafsn-open-datasets/tests/run-tests.php             → 440 passed, 0 failed
php plugins/ceafsn-projects-publications/tests/run-tests.php     → 421 passed, 0 failed
php plugins/ceafsn-research-fellowships/tests/run-tests.php      → 308 passed, 0 failed
php plugins/ceafsn-grants-funding/tests/run-tests.php            → 286 passed, 0 failed
                                                          total  1933 passed, 0 failed
```

Every runner now refuses to execute over HTTP (`if ( 'cli' !== PHP_SAPI )`),
and the suites still pass after that change.

### Release ZIPs

All six archives were rebuilt from source with `tests/` excluded:

```
cd plugins && zip -r -X ceafsn-<name>.zip ceafsn-<name> \
  -x "*.DS_Store" "*__MACOSX*" "*.git*" "*/tests/*"
```

Verified for every archive:

- `unzip -t` passes.
- Contents are byte-identical (sha256 per file) to the source tree minus `tests/`
  — nothing dropped, nothing extra.
- No `.DS_Store`, no `__MACOSX`, no `tests/`.
- The extracted copy passes `php -l` across all 65 shipped PHP files, and each
  archive contains its own `<name>.php` bootstrap file.

Because `tests/` is no longer shipped, the earlier claim that each suite runs
from the extracted ZIP no longer applies; suites are verified in the working
tree and the archives are verified by parity and lint instead.

---

## Files Created and Changed

| File | Change |
| --- | --- |
| `.mailmap` | New. Folds AI-assistant commit identities onto the repository owner. |
| `.gitignore` | Added `ce-afsn-mega-pro-kiro-prompt.md`. |
| `ce-afsn-mega-pro-kiro-prompt.md` | Deleted from the repository and from disk. |
| `qa/crawl-baseline.php` | New. Read-only baseline crawler. |
| `qa/baseline-crawl.json` | New. Per-route status, redirect chain, title, flags. |
| `qa/baseline-links.csv` | New. 1817-row inventory of links, assets, forms, iframes. |
| `qa/baseline-content.md` | New. Human-readable crawl summary. |
| `qa/routes.csv` | Populated with measured results and a `verdict` column. |
| `qa/assets.csv` | Populated with measured results; unresolved rows say so. |
| `qa/final-report.md` | This file. |
| `plugins/*/tests/run-tests.php` | Non-CLI guard added to all six. |
| `plugins/*.zip` | Rebuilt without `tests/`. |

---

## Database / Content Migrations Performed

**None.** No plugin was activated, no page created, no record edited, no setting
changed. Every live-site measurement was a `GET`. The only write operations in
this work were on the local filesystem and in git.

---

## Routes Created, Redirected, Archived, or Removed

**None.** The three route defects above are still open and require either
wp-admin access or deployment of the coded fixes.

---

## Known Limitations

- No browser was used. Accessibility (keyboard, focus, labels, headings,
  contrast) and responsive behaviour (320/768/1280) are untested.
- No authenticated request was made, so admin CRUD, nonce handling and capability
  checks are only verified by unit tests, never against the running site.
- Autoptimize hides individual asset URLs, so per-plugin CSS/JS delivery on the
  front end is unverified.
- Plugin activation state is inferred from markup, not from `wp-admin`.
- The shared `ceafsn.pdf` placeholder and the 12 publication records cannot be
  inspected without Media Library access.
- The crawl is a single pass from one IP with no retry budget; a 200 could
  therefore be a cached or CDN-served response.

---

## Owner-Provided Inputs Still Required

- [ ] Confirmed application destination URL to replace the `/appy` 404
- [ ] Decision on the four Latin demo posts and `/hello-world/` (remove, noindex, or keep)
- [ ] Decision on `/author/edward_admin/` (noindex or keep)
- [ ] Real records for `/me-dashboard/` — delete `Students 200`, `Females`,
      `Q1 2024`, `Test project`, `TEst project`
- [ ] Verified institutional contact details (address, phone, email) for `/contact/`
- [ ] Authentic staff names, roles, bios, and headshots for `/our-team/`
- [ ] Real PDFs for the publication records (replacing shared `ceafsn.pdf`)
- [ ] Real CSV/ZIP files for open datasets
- [ ] Approved copy for `/contact/` and `/volunteer/` to replace Lorem ipsum
- [ ] Corrected "Alumin Network" wording
- [ ] Decision on whether site-wide comments stay open