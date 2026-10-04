=== CE-AFSN Grants & Funding ===
Contributors: ceafsn
Author: edkaydev
Author URI: https://www.linkedin.com/in/edkaydev
Tags: grants, scholarships, funding, opportunities, ceafsn
Requires at least: 6.0
Tested up to: 6.7
Requires PHP: 8.1
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Grants and scholarship listings for CE-AFSN. No funding amounts, statuses, or deadlines are ever invented, and every record requires its own validated, unique Official Call PDF.

== Description ==

The CE-AFSN Grants & Funding plugin closes the last gap in the CE-AFSN plugin suite: a grants listing where every claim is backed by something real. A record cannot be published without a genuine deadline and a readable, non-duplicated call document — there is no path to "Open" on the strength of a missing PDF or an invented date.

Features:

* One record per grant or scholarship: title, award range, deadline (date and time), target beneficiaries, eligibility, an Official Call PDF, an application URL, funding institution, and an optional contact.
* **The Official Call PDF is required, not optional.** Publishing is blocked, with an explanation, unless the attached file is a real, readable PDF with a non-zero size and a readable page count.
* **Deadlines are timezone-aware.** An administrator enters the deadline as a wall-clock date and time in the site's own timezone; it is converted to UTC for storage and converted back to the site timezone (named explicitly) for display, so a deadline closes on the moment printed on the page even if the site's timezone setting changes later.
* Two records may not share one call PDF silently. Sharing has to be confirmed and justified with a note, and the flag is shown to visitors on the card and in the list view.
* The status shown to visitors — Open, Closed, Upcoming, or Archived — is set directly by the administrator rather than calculated, matching the specification. The front end never overrides it, but it does honestly flag a deadline that has already passed even if the status still says Open, so the mismatch is visible rather than hidden.
* The Apply button is a real, working link only when the status is Open and a valid http(s) application URL exists. Otherwise it is shown as an inert, `aria-disabled` element with the reason, never as a link to nowhere.
* The contact is stored freely but shown publicly only once an administrator has explicitly approved it for display.
* Cards or a sortable, accessible table from one query, with filtering by funding institution, status, and free-text search.
* JSON export from the settings screen.
* Data is only removed on uninstall when the administrator explicitly opts in. Media Library files are never deleted.

Shortcode: `[ceafsn_grants]`

= Usage =

1. Activate the plugin.
2. Create the `/scholarships-grants/` page and place the `[ceafsn_grants]` shortcode in it.
3. Add grants under **Grants & Funding → Add New**.
4. Attach the real Official Call PDF, set a real deadline, and set the status to Published when ready.

The page starts with "No funding opportunities are currently open." until the first grant is published.

Shortcode attributes:

* `per_page` — Grants shown per page. Default 12. Clamped to a safe range.
* `funding_institution` — Pre-filter by a single institution. Optional.
* `status` — Pre-filter by status (`open`, `closed`, `upcoming`, `archived`). Optional.
* `view` — `cards` (default) or `table`. A view chosen in the address bar takes priority, so a shared link behaves the same for every visitor.

= Call PDF validation =

An Official Call PDF may only be published when all of the following hold:

* It is attached through the Media Library and its MIME type is `application/pdf`.
* The file starts with the `%PDF-` signature and ends with `%%EOF`.
* It is not zero bytes, and a page count can be read from it.
* No other record already uses the same file, unless sharing is confirmed and a note explains why.

== Installation ==

1. Upload the `ceafsn-grants-funding` folder to `/wp-content/plugins/`.
2. Activate the plugin through the **Plugins** screen. This creates the table and seeds the default display option.
3. Create the `/scholarships-grants/` page and add the shortcode.
4. Add grants under **Grants & Funding**.

== Frequently Asked Questions ==

= Why can I not publish this record? =

Open it for editing: the reason is shown in the notice above the form, and the record was saved as a draft instead. The usual causes are a missing deadline, a missing or broken call PDF, or another record already using the same document.

= Does the status update itself when the deadline passes? =

No. Status is a field the administrator sets, matching the specification, not a calculation. The public page will honestly show a "deadline has passed" note if the status still says Open past the deadline, so visitors are not misled while the record is updated.

= Can two records use the same call PDF? =

Yes, but only deliberately. Attach the file, tick "This document is intentionally shared", and write a note saying why. The flag is then shown to visitors on both records.

= What is deleted when I uninstall? =

Nothing, unless the opt-in box is ticked in the Uninstall section of the settings screen first. Files in the Media Library are never removed, and a deactivated plugin deletes no data at all.

= Can I get my records out before uninstalling? =

Yes. Use the JSON export in Settings. It includes every record, including drafts.

== Screenshots ==

1. The public listing in card view with the institution and status filters.
2. The table view with sortable columns and status badges.
3. The record editor showing the call PDF picker and the shared-document confirmation.

== Changelog ==

= 1.0.0 =
* Initial release.

== Upgrade Notice ==

= 1.0.0 =
Initial release.
