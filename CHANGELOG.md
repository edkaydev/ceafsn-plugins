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
- Plugin 5: `ceafsn-research-fellowships`
- Plugin 6: `ceafsn-grants-funding`
- Fix `/appy` 404
- Demo content cleanup
- Full QA suite and documentation
