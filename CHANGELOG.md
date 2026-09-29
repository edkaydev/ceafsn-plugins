# Changelog

All notable changes to the CE-AFSN plugin suite will be documented here.

## [Unreleased]

### Added
- Initial project structure and specification documents
- Six plugin folder scaffolds
- Shared library scaffold
- `docs/`, `qa/`, `dist/` directories
- `ceafsn-me-dashboard` v1.0.0 — metrics, demographics, and project registry with
  accessible tabbed front end, CSV export, opt-in uninstall, and a 177-assertion
  dependency-free test suite
- `ceafsn-nutrition-policy` v1.0.0 — policy record library where PDF validation
  gates the Published status, with an accessible filterable/sortable table,
  JSON export, and a 241-assertion test suite
- `ceafsn-open-datasets` v1.0.0 — dataset repository where a working download
  gates the Published status. CSV/ZIP/XLSX structural checks, external URL
  validation (HTTP 200, reported size, matching content type, http/https only),
  measured file sizes, a seven-column accessible table, contact privacy behind
  an opt-in, scoped upload restrictions, JSON export, and a 381-assertion
  dependency-free test suite
- `ceafsn-projects-publications` v1.0.0 — publication and project library where
  a record's own validated PDF gates the Published status. Per-record checks for
  the `%PDF-` signature, `%%EOF` terminator, non-zero size, a readable page count
  that must match the admin-entered value, and extractable text unless the
  record is flagged as scanned. The known placeholder file is blocked until it
  is confirmed with a note, and sharing one document between records must be
  confirmed the same way and is then shown to visitors. Publication state is
  stored separately from project status. Grid and list views from one query,
  with filters, sorting, and search; members-only records are excluded unless
  the visitor passes the `ceafsn_pp_can_view_members_only` filter. Ships with
  `readme.txt`, `languages/ceafsn-pp.pot`, JSON export, opt-in uninstall, and a
  332-assertion dependency-free test suite
- `ceafsn-research-fellowships` v1.0.0 — fellowship and opportunity listing
  where status is derived from the opening and closing dates on every request
  rather than stored as an opinion. An opportunity is never shown as Open
  without a closing date proving applications are still accepted, or an
  explicit admin override with a written audit note; a passed opening date with
  no closing date shows as "Status not confirmed" instead. Dates are compared
  against the WordPress site timezone. The Apply button is a real link only
  when the opportunity is open and a valid http(s) URL exists, otherwise an
  inert `aria-disabled` element with the reason. Stipend information and the
  contact email are stored freely but shown publicly only once approved per
  record. Cards and a sortable, accessible table from one query, with track,
  status, and free-text filtering. Ships with `readme.txt`,
  `languages/ceafsn-rf.pot`, JSON export, opt-in uninstall, and a 212-assertion
  dependency-free test suite
- `ceafsn-grants-funding` v1.0.0 — the sixth and final plugin, closing the
  suite. Grants and scholarships require a real deadline and their own
  validated Official Call PDF before publishing; the call PDF is required,
  not optional, unlike the fellowships plugin. Deadlines are entered and
  displayed in the site timezone (named explicitly) and stored as UTC, with a
  round-trip conversion helper for the edit form. Status (Open, Closed,
  Upcoming, Archived) is set directly by the administrator rather than
  derived, matching the specification, but the public page still honestly
  flags a deadline that has already passed even when the status still says
  Open, so the mismatch is visible rather than hidden. Sharing one call PDF
  between records must be confirmed with a note and is then shown to
  visitors, mirroring the projects-publications duplicate-document pattern.
  Cards and a sortable, accessible table from one query, with institution,
  status, and free-text filtering. Ships with `readme.txt`,
  `languages/ceafsn-gf.pot`, JSON export, opt-in uninstall, and a
  157-assertion dependency-free test suite

### Changed
- `ceafsn-nutrition-policy`: the PDF-only upload restriction is now scoped to
  the plugin's own admin screen instead of replacing every upload type site-wide,
  and is registered on each admin request rather than only at activation.
- `ceafsn-nutrition-policy`: the `[ceafsn_policy_table]` `topic` attribute is
  honoured as the default filter, with the query string still taking precedence.
- `ceafsn-open-datasets`: the public table shows file type and size in one
  column, matching the seven columns in the specification.
- `ceafsn-projects-publications`: the legacy redirect is registered on every
  request rather than only at activation, is enabled by default, and can be
  switched off in Settings without the preference being deleted on
  deactivation.
- `ceafsn-projects-publications`: the upload restriction is scoped to the
  plugin's own screen and allows the cover image types as well as PDF, so
  images for the rest of the site are unaffected.
- `ceafsn-projects-publications`: the page count is measured from the document
  when the field is left empty, and the cover attachment is checked server-side
  for an image MIME type and required alt text.

### Planned
- Fix `/appy` 404
- Demo content cleanup
- Full QA suite and documentation
