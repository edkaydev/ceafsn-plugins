=== CE-AFSN Open Datasets ===
Contributors: ceafsn
Author: edkaydev
Author URI: https://www.linkedin.com/in/edkaydev
Tags: open data, datasets, csv, food security, ceafsn
Requires at least: 6.0
Tested up to: 6.7
Requires PHP: 8.1
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Open data repository for food security and nutrition datasets. Every download link points to a real, validated file.

== Description ==

The CE-AFSN Open Datasets plugin replaces a hand-maintained file list with a small, admin-managed record store. It ships with **no sample records** — the public table says so plainly until real datasets are added.

Features:

* One record per dataset: name, description, category/sector, coverage area, last updated date, file or download URL, file type, size, license, and an optional methodology / data dictionary URL.
* **Download validation gates publishing.** A record cannot be set to Published unless it has a working download: an attached CSV, ZIP, or XLSX that passes its type's checks, or an external URL that returns HTTP 200, reports a file size, and serves a content type matching the declared file type. Attempts to publish a broken download are downgraded to Draft with an explanation, never silently dropped.
* CSV files must have a readable header row. ZIP archives must have a central directory and at least one entry. XLSX workbooks must contain `[Content_Types].xml`. A binary file renamed to `.csv` is rejected.
* The file size is measured on disk, never taken from the form, so a record never advertises a size the file does not have.
* Seven-column table — dataset, category, last updated, file type / size, coverage area, license, and download — with a caption, row headers, and sorting on four columns that reports its current state through `aria-sort`. Sorting returns to page 1.
* Filter by category and file type, plus free-text search across name, description, coverage area, and license. Search terms are bound as parameters and LIKE wildcards escaped.
* Download links open in a new tab with `rel="noopener noreferrer"` and an `aria-label` that names the dataset, the file type, and the size.
* A record whose file has been deleted from the media library shows an honest "download currently unavailable" message rather than a broken link.
* An unknown file size reads "Not available" instead of showing a fabricated number.
* Contact details stay private unless an administrator enables them in Settings.
* Upload types are restricted to dataset file types **on the dataset screen only**, so images and files for the rest of the site are unaffected. The restriction is registered on every admin request, not once at activation.
* The "other" file type is hidden and rejected on the server unless an administrator enables it in Settings, because it bypasses every structural check.
* A URL with a non-HTTP scheme is rejected before any request is sent.
* JSON export from the settings screen.
* Data is only removed on uninstall when the administrator explicitly opts in.

This plugin displays no metrics. It never shows household counts, HDDS values, yields, or any other figure it has not been given.

Shortcode: [ceafsn_open_datasets]

= Usage =

1. Activate the plugin.
2. Create a page and place the `[ceafsn_open_datasets]` shortcode in it.
3. Add records under **CE-AFSN → Open Datasets**.
4. Attach a real file (or paste a working URL) and set the record to **Published**.

The table starts with "No public dataset is currently available for download." until the first record is published.

Shortcode attributes:

* `per_page` — Datasets shown per page. Default 20. Clamped to 1-100.
* `category` — Pre-filter the table by a single category. Optional.
* `file_type` — Pre-filter by `csv`, `zip`, `xlsx`, or `other`. Optional.

= File validation =

| Type | Checks |
|------|--------|
| CSV  | Non-empty, readable header row, delimiter detected (`,` `;` tab `\|`), binary payloads rejected |
| ZIP  | `PK` signature, end-of-central-directory present, at least one entry |
| XLSX | ZIP container plus `[Content_Types].xml` |
| Other | Non-zero size only |

MIME types are also checked against the type declared in the form, so an XLSX declared as a CSV is rejected before the bytes are even read.

== Installation ==

1. Upload the `ceafsn-open-datasets` folder to `/wp-content/plugins/`.
2. Activate the plugin through the **Plugins** screen.
3. Add dataset records under **CE-AFSN → Open Datasets**.

== Frequently Asked Questions ==

= Does this plugin download the datasets itself? =

No. External URLs are checked with a single HEAD request when a record is saved, so a large file is never downloaded during an admin save.

= Does this plugin check links after publishing? =

Only when a record is saved. A large file is never downloaded during an admin save, and a file that disappears afterwards is reported honestly as "Download currently unavailable" on the next page load. The front end does not delay every click behind a network check, and JavaScript cannot un-follow a link the browser has already opened.

= A dataset file fails validation =

Check that the declared file type matches the actual file. A `.csv` that is really a spreadsheet export with a binary body is rejected; so is a `.zip` that is really a text file. Use the "Other" file type only when you have enabled it in Settings and accept that its contents are not inspected.

= What happens if a file is deleted after publishing? =

The record keeps its place in the table and shows "Download currently unavailable. Please contact the publisher." No dead link is rendered.

= Can I see who to contact about a dataset? =

Not by default. The contact is stored but not rendered until you enable "Show the contact / owner on the public page" in Settings.

= What is deleted when I uninstall? =

Nothing, unless you tick the opt-in box in the Uninstall tab first. Files in the Media Library are never removed.

== Screenshots ==

1. The public repository table with search, filters, and sorting.
2. The dataset editor with the media picker and validation result.
3. Settings: contact privacy, file types, export, and the uninstall opt-in.

== Changelog ==

= 1.0.0 =
* Initial release.

== Upgrade Notice ==

= 1.0.0 =
Initial release.
