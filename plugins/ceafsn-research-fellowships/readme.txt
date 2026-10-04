=== CE-AFSN Research Fellowships ===
Contributors: ceafsn
Author: edkaydev
Author URI: https://www.linkedin.com/in/edkaydev
Tags: fellowships, research, opportunities, scholarships, ceafsn
Requires at least: 6.0
Tested up to: 6.7
Requires PHP: 8.1
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Research fellowship and opportunity listings for CE-AFSN. Status is never shown as Open without a closing date or a documented override.

== Description ==

The CE-AFSN Research Fellowships plugin replaces a listing that could say "Open" on nothing more than an editor's say-so. Every opportunity's status is worked out from real dates every time the page loads, and the one way around that calculation — a manual override — has to be written down and shown to visitors.

Features:

* One record per opportunity: title, track/domain, duration, eligibility criteria, host or supervisor, optional stipend/funding information, opening date, closing date, application URL, an optional call PDF, and a contact email.
* **Status is derived from dates, not stored as an opinion.** Open only appears when today falls between the opening and closing dates, or when an administrator has ticked the override box and written a note explaining why. An opportunity whose opening date has passed but which has no closing date is shown as "Status not confirmed" rather than Open — a missing deadline is not evidence that applications are still being accepted.
* Dates are compared against the WordPress site timezone, not the server's or the visitor's, so a deadline closes on the day printed on the page.
* The Apply button is a real, working link only when the opportunity is open and a valid `http(s)` application URL exists. Otherwise it is shown as an inert, `aria-disabled` element with the reason, never as a link to nowhere.
* Publishing is blocked, with an explanation, for a record that would show Open on the strength of a missing closing date, a manual "Open" status with no supporting dates, an override with no note, or a call PDF that is not a real, readable file.
* Stipend/funding information and the contact email are stored freely but only ever shown on the public page once an administrator has explicitly approved each one for display.
* Cards or a sortable, accessible table from the same query, with filtering by track/domain, status, and free-text search.
* JSON export from the settings screen.
* Data is only removed on uninstall when the administrator explicitly opts in. Media Library files are never deleted.

Shortcode: `[ceafsn_fellowships]`

= Usage =

1. Activate the plugin.
2. Create the `/research-fellowships/` page and place the `[ceafsn_fellowships]` shortcode in it.
3. Add opportunities under **Research Fellowships → Add New**.
4. Set an opening and closing date so the status can be calculated automatically, or use the override with a note if applications are genuinely open outside the usual dates.

The page starts with "There are no opportunities to show right now." until the first opportunity is published.

Shortcode attributes:

* `per_page` — Opportunities shown per page. Default 12. Clamped to a safe range.
* `track_domain` — Pre-filter by a single track or domain. Optional.
* `status` — Pre-filter by status (`open`, `upcoming`, `closed`, `archived`, `unconfirmed`). Optional.
* `view` — `cards` (default) or `table`. A view chosen in the address bar takes priority, so a shared link behaves the same for every visitor.

= Date-driven status =

| Condition | Displayed status |
|---|---|
| Opening date in the future | Upcoming |
| Today is between the opening and closing dates | Open |
| Closing date has passed | Closed |
| Opening date has passed (or is unset) and no closing date exists | Status not confirmed |
| No dates at all | The administrator's manual status |
| Override ticked, with a note | Open |

A manual status of Open with no dates and no override is refused at publish time: the record is saved as a draft instead, with an explanation.

== Installation ==

1. Upload the `ceafsn-research-fellowships` folder to `/wp-content/plugins/`.
2. Activate the plugin through the **Plugins** screen. This creates the table and seeds the default display option.
3. Create the `/research-fellowships/` page and add the shortcode.
4. Add opportunities under **Research Fellowships**.

== Frequently Asked Questions ==

= Why does this opportunity show "Status not confirmed" instead of Open? =

Its opening date has passed but it has no closing date, so there is nothing to prove applications are still being accepted. Add a closing date, set the status to Upcoming or Closed by hand, or tick the override and explain why it is open regardless.

= Why can I not publish this record as Open? =

Open it for editing: the reason is shown in the notice above the form. The usual causes are a missing closing date, a manual "Open" status with no supporting dates, or an override with no note.

= Can I show an opportunity as open after its closing date? =

Yes, but only with a written reason. Tick "Ignore the dates and show this as Open" and fill in the override note — for example, after a deadline extension agreed with the funder. The note is shown to visitors as an "Override" badge.

= What is deleted when I uninstall? =

Nothing, unless the opt-in box is ticked in the Uninstall section of the settings screen first. Files in the Media Library are never removed, and a deactivated plugin deletes no data at all.

= Can I get my records out before uninstalling? =

Yes. Use the JSON export in Settings. It includes every record, including drafts.

== Screenshots ==

1. The public listing in card view with the track and status filters.
2. The table view with sortable columns and status badges.
3. The record editor showing the calculated status and the override note field.

== Changelog ==

= 1.0.0 =
* Initial release.

== Upgrade Notice ==

= 1.0.0 =
Initial release.
