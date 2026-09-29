=== CE-AFSN Projects & Publications ===
Contributors: ceafsn
Tags: publications, reports, library, documents, ceafsn
Requires at least: 6.0
Tested up to: 6.7
Requires PHP: 8.1
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Projects and publications library for CE-AFSN. Every published record must have its own validated, readable PDF.

== Description ==

The CE-AFSN Projects & Publications plugin replaces a broken publication library in which many records pointed at the same placeholder file, or at no file at all. It ships with **no sample records**: the public page says so plainly until real records are published.

Features:

* One record per publication or project: title, content type, executive summary, author or institution, publication date, project status, optional cover image with required alt text, PDF attachment, page count, DOI / citation URL, and access level.
* **Document validation gates publishing.** A record cannot be set to Published unless its PDF is readable, non-empty, has a readable page count that matches the admin-entered value, and contains extractable text. Attempts to publish a document that fails are downgraded to Draft with an explanation, never silently dropped.
* A scanned, image-only PDF is allowed, but the administrator has to say so explicitly.
* The placeholder file the old site used everywhere (`ceafsn.pdf`, and any extra names listed in Settings) is blocked until an administrator confirms it is intentional and writes a note explaining why.
* Two records may not share one PDF silently. Sharing has to be confirmed and justified with a note, and the flag is shown to visitors on the card and in the list view.
* Page count and file size are measured from the file, not taken from the form, so a card never advertises a number the document does not have.
* Grid and list views from the same query. The list view is a table with a caption, row headers, and sorting that reports its state through `aria-sort`.
* Filters by content type, project status, year, and free-text search across title, summary, institution, and citation. Search terms are bound as parameters and LIKE wildcards escaped.
* Document links open in a new tab with `rel="noopener noreferrer"` and an `aria-label` that names the record instead of repeating "View Document" on every card.
* A record whose document has been deleted from the media library shows an honest "Document currently unavailable" message rather than a broken link.
* Members-only records are excluded from the query unless the visitor is signed in and holds the membership capability, and the document link is replaced with a sign-in message. Sites with their own membership system can grant access through the `ceafsn_pp_can_view_members_only` filter.
* **Route migration:** `/privacy-policy-2/` permanently redirects to `/publications/` with a 301, and the redirect can be switched off in Settings.
* Upload types are restricted to PDF and cover images **on this plugin's screen only**, so uploads for the rest of the site are unaffected. The restriction is registered on every admin request, not once at activation.
* JSON export from the settings screen.
* Data is only removed on uninstall when the administrator explicitly opts in. Media Library files are never deleted.

Shortcode: `[ceafsn_projects_pubs]`

= Usage =

1. Activate the plugin.
2. Create the `/publications/` page and place the `[ceafsn_projects_pubs]` shortcode in it.
3. Add records under **CE-AFSN → Projects & Publications**.
4. Attach a real PDF and set the record to **Published**.

The page starts with "No publications or projects have been published yet." until the first record is published.

Shortcode attributes:

* `per_page` — Records shown per page. Default 12. Clamped to a safe range.
* `content_type` — Pre-filter by a single content type. Optional.
* `view` — `grid` (default) or `list`. A view chosen in the address bar takes priority, so a shared link behaves the same for every visitor.

= Document validation =

A PDF may only be published when all of the following hold:

* It is attached through the Media Library and its MIME type is `application/pdf`.
* The file starts with the `%PDF-` signature and ends with `%%EOF`.
* It is not zero bytes.
* A page count can be read from the file, and it matches the page count entered in the admin — or the field was left empty, in which case the measured value is stored.
* It contains extractable text, unless the record is flagged as a scanned document.
* It is not a known placeholder, unless the record is flagged as intentional and a note explains why.
* No other record already uses the same file, unless sharing is confirmed and a note explains why.

= Record states =

Two separate fields are stored, because they answer different questions:

* **Publication state** — Draft, Published, or Archived. Only Published records appear on the public page.
* **Project status** — In Progress, Completed, Under Review, or Archived. This describes the work, not the record, and is what the public status filter offers.

== Installation ==

1. Upload the `ceafsn-projects-publications` folder to `/wp-content/plugins/`.
2. Activate the plugin through the **Plugins** screen. This creates the table, seeds the placeholder list, and enables the legacy redirect.
3. Create the `/publications/` page and add the shortcode.
4. Add records under **CE-AFSN → Projects & Publications**.

== Frequently Asked Questions ==

= Why can I not publish this record? =

Open it for editing: the reason is shown in the notice above the form, and the record was saved as a draft instead. The usual causes are a document that is not a real PDF, a page count that disagrees with the file, a PDF with no readable text, a placeholder file, or another record already using the same file.

= The old page `/privacy-policy-2/` is not redirecting =

The redirect is active by default. Check that it has not been switched off under **CE-AFSN → Projects & Publications → Settings**, and that no real page has been created at the legacy slug — a real page always wins over the redirect.

= Does the redirect keep working while the plugin is deactivated? =

No. A deactivated plugin does not run, so the legacy path simply returns 404 until the plugin is switched back on. The redirect preference itself is never deleted, so reactivating restores it exactly as it was.

= Can two records use the same PDF? =

Yes, but only deliberately. Attach the file, tick "This document is intentionally shared", and write a note saying why. The flag is then shown to visitors on both records.

= Can I see members-only records? =

The public query excludes them unless the visitor is signed in and passes the membership check, which defaults to the `manage_options` capability. A site with its own membership plugin can hook in:

`add_filter( 'ceafsn_pp_can_view_members_only', fn( $allowed ) => my_membership_check() );`

Members-only records are a way of withholding the *link*, not the file. A document in the Media Library can still be fetched directly by anyone who already knows its URL, so restricted documents must not be treated as confidential files.

= What is deleted when I uninstall? =

Nothing, unless the opt-in box is ticked in the Uninstall section of the settings screen first. Files in the Media Library are never removed, and a deactivated plugin deletes no data at all.

= Can I get my records out before uninstalling? =

Yes. Use the JSON export in Settings. It includes every record, including members-only and unpublished ones.

== Screenshots ==

1. The public library in grid view with filters and a result count.
2. The list view table with sorting and a shared-document flag.
3. The record editor with the document checks and the shared-document confirmation.

== Changelog ==

= 1.0.0 =
* Initial release.

== Upgrade Notice ==

= 1.0.0 =
Initial release.
