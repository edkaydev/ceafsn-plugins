# Content Migration

Route mapping, redirect decisions, and content cleanup decisions for the CE-AFSN site.

---

## Route Changes

| Old Route | New Route | Redirect Type | Notes |
|-----------|-----------|---------------|-------|
| `/privacy-policy-2/` | `/publications/` | 301 Permanent | Legacy publications page |
| `/appy` | Admin-configurable | 301 or direct link | Was 404; destination set in plugin settings |
| `/hello-world/` | TBD (owner decision) | Redirect or noindex | Default WP post |

---

## New Routes Created

| Route | Created By | Shortcode |
|-------|-----------|-----------|
| `/me-dashboard/` | Admin creates page | `[ceafsn_me_dashboard]` |
| `/nutrition-policy-modeling/` | Admin creates page | `[ceafsn_policy_table]` |
| `/open-datasets/` | Admin creates page | `[ceafsn_open_datasets]` |
| `/publications/` | Admin creates page | `[ceafsn_projects_pubs]` |
| `/research-fellowships/` | Admin creates page | `[ceafsn_fellowships]` |
| `/scholarships-grants/` | Admin creates page | `[ceafsn_grants]` |

---

## Internal Link Updates Required

All the following must be updated to point to the new canonical routes:

| Location | Old Link | New Link |
|----------|---------|---------|
| Homepage CTAs | `/privacy-policy-2/` | `/publications/` |
| Navigation menus | `/privacy-policy-2/` | `/publications/` |
| Footer links | `/privacy-policy-2/` | `/publications/` |
| Any "Apply Now" actions | `/appy` | Admin-configured destination |

---

## Demo Content Cleanup

### Posts to Remove / Archive
| Slug | Action | Reason |
|------|--------|--------|
| `/hello-world/` | Redirect or delete | Default WP post |
| `/lobortis-elementum-nibhtellus-molestie-adipiscing/` | Noindex + redirect or delete | Lorem ipsum demo post |
| `/duis-tristique-sollicitudin-nibh-sit-amet-commodo-nulla/` | Noindex + redirect or delete | Lorem ipsum demo post |

Action for each must be confirmed with the site owner before execution. Export a migration report first.

### Content Strings to Remove
| Location | String | Action |
|----------|--------|--------|
| `/contact/` | `304 North Cardinal St. Dorchester Center, MA 02124` | Replace with admin-managed contact block |
| `/contact/` | `1-555-123-4567`, `1-800-123-4567` | Replace or remove |
| `/contact/` | Lorem ipsum text | Replace with honest empty state |
| `/volunteer/` | `Alumin Network` (title) | Correct to `Alumni Network` or owner-approved title |
| `/volunteer/` | Template/placeholder body content | Replace with real or empty state |
| `/our-team/` | Generic/template staff names | Replace with authentic records or empty state |
| `/me-dashboard/` | Scanning/initializing ellipses | Replace with honest empty state or real data |
| Homepage | Malformed heading containing `"` | Correct to approved copy |
| Homepage | Links to `/privacy-policy-2/`, `/hello-world/`, Latin-slug articles | Update to canonical routes |
| Sitewide | `A WordPress Commenter` | Delete default comment |
| Author archive | `edward_admin` | Noindex unless approved real author profile |

---

## PDF Cleanup

| Issue | Records Affected | Action |
|-------|-----------------|--------|
| 12 publication cards all pointing to the same `ceafsn.pdf` (8,951 bytes, textless) | All 12 legacy publication cards | Each record must receive a unique, validated PDF. Existing `ceafsn.pdf` must not be reused unless admin explicitly confirms it is the same document and it passes validation. |

---

## Owner Inputs Required

The following items cannot be resolved without real data from the site owner:

- Verified institutional contact details (address, phone, email)
- Authentic staff names, roles, bios, and headshots for `/our-team/`
- Real PDFs for each of the 12 publication records
- Real CSV/ZIP files for open datasets
- Confirmed application destination URL for `/appy`
- Approved copy for the corrected homepage heading
- Decision on `/hello-world/` and Latin-slug posts (redirect target or delete)
- Approval for public display of contact email on fellowships and grants
