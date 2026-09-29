=== CE-AFSN M&E Dashboard ===
Contributors: ceafsn
Tags: dashboard, monitoring, evaluation, metrics, ceafsn
Requires at least: 6.0
Tested up to: 6.7
Requires PHP: 8.1
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Institutional monitoring and evaluation dashboard. Admin-managed metrics, demographics, and a project registry.

== Description ==

The CE-AFSN M&E Dashboard replaces the static M&E section with a small, admin-managed data store. Every number on the page comes from a record an administrator entered and published. There is no demo data, no seeded placeholder, and no "live sync" indicator.

Features:

* Metric cards with label, value, unit, definition, source, and reporting period.
* Demographic indicators rendered as accessible charts plus a full data table.
* Project registry with status, dates, and paginated, searchable listing.
* Private/draft records are never returned to public queries.
* Empty states that say no data has been published yet, instead of fake figures.
* Optional preview mode that renders draft content to logged-in administrators only.
* CSV export of all records from the settings screen.
* Data is only removed on uninstall when the administrator explicitly opts in.

= Usage =

1. Activate the plugin.
2. Create a page and place the `[ceafsn_me_dashboard]` shortcode in it.
3. Add records under **CE-AFSN → M&E Dashboard**.
4. Set a record to **Published** and **Public** to make it visible on the page.

Shortcode attributes:

* `per_page` — Projects shown per page. Default 12. Range 1-100.
* `preview` — `1` to show draft content to logged-in administrators. Default `0`.

= Accessibility =

The dashboard uses the WAI-ARIA tabs pattern with full keyboard support (arrow keys, Home, End). Every chart is backed by a data table so the information is available without sight of the graphic. No colour is used as the only means of conveying status.

= Privacy =

This plugin stores no personal data. It does not create public user profiles, does not add comment forms, and does not transmit anything to external services. All data lives in the site's own database and is only displayed to anonymous visitors when an administrator has marked it public.

== Installation ==

1. Upload the `ceafsn-me-dashboard` folder to `/wp-content/plugins/`.
2. Activate the plugin through the **Plugins** screen.
3. Follow the on-screen link to the settings screen.

== Frequently Asked Questions ==

= Does this plugin create sample data? =

No. The plugin ships with empty tables. An empty dashboard displays a message telling visitors that data has not been published yet. Nothing is invented or estimated.

= Who can edit the data? =

Only users with the `manage_options` capability, which is the Administrator role by default. Every write action also requires a valid nonce.

= What happens to my data if I delete the plugin? =

Nothing, unless you first tick **Delete all data on uninstall** in the plugin settings and then delete the plugin. Deactivation never deletes data.

== Screenshots ==

1. Public dashboard with metric cards and tabbed panels.
2. Demographics charts with accompanying data tables.
3. Project registry listing with search and pagination.
4. Admin screen for adding a metric.

== Changelog ==

= 1.0.0 =
* Initial release.
* Metric, demographic, and project CRUD.
* Accessible tabbed front end with charts and data tables.
* CSV export and opt-in uninstall.

== Upgrade Notice ==

= 1.0.0 =
Initial release.
