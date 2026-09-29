=== CE-AFSN Nutrition Policy ===
Contributors: ceafsn
Tags: policy, nutrition, food security, documents, ceafsn
Requires at least: 6.0
Tested up to: 6.7
Requires PHP: 8.1
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Admin-managed library of nutrition and food security policy documents. A record is only publishable when its PDF is validated.

== Description ==

The CE-AFSN Nutrition Policy plugin replaces a hand-maintained document list with a small, admin-managed record store. It ships with **no sample records** — the public table says so plainly until real documents are added.

Features:

* One record per policy: title, description, publication date, topic, authoring institution, PDF, and an optional citation URL.
* **PDF validation gates publishing.** A record cannot be set to Published unless its attachment is a real, readable PDF with a readable page count and is not a known placeholder file. Attempts to publish an invalid document are downgraded to Draft with an explanation, never silently dropped.
* Filter by topic and free-text search across title, description, and institution.
* Server-side sorting on four columns with correct `aria-sort` state.
* "Read Policy" opens the document in a new tab with `rel="noopener noreferrer"`.
* A record whose file has been deleted from the media library shows an honest "document unavailable" message rather than a broken link.
* Uploads are restricted to `application/pdf` **on the policy screen only**, so images and files for the rest of the site are unaffected. The restriction is registered on every admin request, not once at activation.
* JSON export from the settings screen.
* Data is only removed on uninstall when the administrator explicitly opts in.

= Usage =

1. Activate the plugin.
2. Create a page and place the `[ceafsn_policy_table]` shortcode in it.
3. Add records under **CE-AFSN → Nutrition Policy**.
4. Attach a real PDF and set the record to **Published**.

Shortcode attributes:

* `per_page` — Records shown per page. Default 20. Clamped to 1-100.
* `topic` — Pre-filter the table by a single topic. Optional.

= Placeholder files =

A file named `ceafsn.pdf` is treated as a known placeholder and can never be published. Administrators can add more blocked names in **Nutrition Policy → Settings**.

= Accessibility =

Column headers are real `<th scope="col">` elements with `aria-sort` describing the current state. The table is wrapped in a keyboard-focusable scroll region, each control has a label, and focus moves to the results region after a filter or sort so keyboard and screen reader users keep their place. On narrow screens each row becomes a labelled card. All of this works without JavaScript.

== Installation ==

1. Upload the `ceafsn-nutrition-policy` folder to `/wp-content/plugins/`.
2. Activate the plugin through the **Plugins** screen.
3. Visit **CE-AFSN → Nutrition Policy → Settings** to review the placeholder file list.

== Frequently Asked Questions ==

= Does this plugin create sample policy records? =

No. The table starts empty and the public page displays "No policies have been published yet." Nothing is invented to fill the page.

= What exactly does PDF validation check? =

That the attachment is `application/pdf`, that the file exists and is not zero bytes, that the bytes begin with `%PDF-` and end with `%%EOF` (a truncated upload is rejected), that a page count can be read, and that the file name is not on the placeholder list.

= Can I still save a record whose PDF is invalid? =

Yes — it is saved as a Draft. Only the Published status is blocked, so a record can be prepared before its document is final.

= Who can edit the records? =

Only users with the `manage_options` capability, which is the Administrator role by default. Every write action also requires a valid nonce.

= What happens to my data if I delete the plugin? =

Nothing, unless you first tick the delete option in **Settings → Uninstall** and then delete the plugin. Deactivation never deletes data, and files in the Media Library are never removed.

== Screenshots ==

1. The public policy table with filters, sorting, and pagination.
2. The empty state shown before any policy is published.
3. The admin record editor with the media picker and validation result.
4. Settings showing the placeholder file list.

== Changelog ==

= 1.0.0 =
* Initial release.
* Policy record CRUD with topic and status fields.
* PDF validation that gates the Published status.
* Accessible filterable, sortable table with pagination.
* JSON export and opt-in uninstall.

== Upgrade Notice ==

= 1.0.0 =
Initial release.
