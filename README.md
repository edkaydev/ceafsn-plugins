# CE-AFSN Modular WordPress Plugin Suite

A suite of six lightweight, production-ready WordPress plugins for `https://ceafsn.duckdns.org/`.

---

## Plugins

| Plugin | Target Route | Shortcode |
|--------|-------------|-----------|
| `ceafsn-me-dashboard` | `/me-dashboard/` | `[ceafsn_me_dashboard]` |
| `ceafsn-nutrition-policy` | `/nutrition-policy-modeling/` | `[ceafsn_policy_table]` |
| `ceafsn-open-datasets` | `/open-datasets/` | `[ceafsn_open_datasets]` |
| `ceafsn-projects-publications` | `/publications/` | `[ceafsn_projects_pubs]` |
| `ceafsn-research-fellowships` | `/research-fellowships/` | `[ceafsn_fellowships]` |
| `ceafsn-grants-funding` | `/scholarships-grants/` | `[ceafsn_grants]` |

---

## Requirements

- PHP 8.1+
- WordPress 6.0+ (current supported version)
- No external dependencies or build steps required for production ZIPs

---

## Activation Order

1. `ceafsn-shared` (if used as shared library)
2. Any of the six plugins in any order
3. Each plugin activates, deactivates, and uninstalls independently

---

## Installation

1. Upload the plugin folder to `/wp-content/plugins/`.
2. Activate via **Plugins → Installed Plugins** in wp-admin.
3. Navigate to the plugin's admin menu to configure.
4. Add the shortcode to the target page.

---

## Rollback Procedure

1. Deactivate the plugin from **Plugins → Installed Plugins**.
2. Plugin deactivation does **not** delete any data.
3. To fully remove data: go to the plugin's admin settings → **Uninstall** tab → confirm deletion.
4. Before uninstalling, use the plugin's **Export** function to save a JSON/CSV backup of all records.

---

## Known Issues Fixed by This Suite

See `TODO.md` for the full checklist. Key defects addressed:

- `/appy` 404 → replaced with admin-configurable destination
- `/privacy-policy-2/` → 301 redirect to `/publications/`
- Placeholder PDFs shared across 12 publication cards → each record requires a unique, validated file
- Lorem ipsum and demo content → removed or noindexed
- Fake contact, team, and metric data → replaced with honest empty states pending real data from site owner

---

## Documentation

| File | Description |
|------|-------------|
| `docs/data-model.md` | Entities, fields, statuses, and relationships |
| `docs/content-migration.md` | Route mapping and cleanup decisions |
| `docs/admin-runbook.md` | Staff guide for publishing content |
| `docs/security-privacy.md` | Capabilities, data retention, form handling |
| `qa/final-report.md` | Pass/fail evidence for every requirement |
| `qa/routes.csv` | Route test results |
| `qa/assets.csv` | Asset validation results |

---

## License

GPL v2 or later. See `LICENSE.txt`.
