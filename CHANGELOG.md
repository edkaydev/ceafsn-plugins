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

### Changed
- `ceafsn-nutrition-policy`: the PDF-only upload restriction is now scoped to
  the plugin's own admin screen instead of replacing every upload type site-wide,
  and is registered on each admin request rather than only at activation.
- `ceafsn-nutrition-policy`: the `[ceafsn_policy_table]` `topic` attribute is
  honoured as the default filter, with the query string still taking precedence.
- `ceafsn-open-datasets`: the public table shows file type and size in one
  column, matching the seven columns in the specification.

### Planned
- Plugin 4: `ceafsn-projects-publications`
- Plugin 5: `ceafsn-research-fellowships`
- Plugin 6: `ceafsn-grants-funding`
- Route migration: `/privacy-policy-2/` → `/publications/`
- Fix `/appy` 404
- Demo content cleanup
- Full QA suite and documentation
