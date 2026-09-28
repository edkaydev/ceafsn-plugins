# ceafsn-open-datasets

Open data repository for CE-AFSN food security and nutrition datasets.

---

## Target Route

`/open-datasets/`

## Shortcode

`[ceafsn_open_datasets]`

---

## Purpose

Provides an admin-managed, publicly accessible repository of open datasets. Every download link must point to a real, validated CSV or ZIP file. No fake metrics or placeholder download buttons are ever shown.

---

## Admin-Managed Entities

### Dataset Records
| Field | Type | Required |
|-------|------|----------|
| Dataset Name | Text | Yes |
| Description | Textarea | Yes |
| Category / Sector | Text | Yes |
| Coverage Area | Text | Yes |
| Last Updated | Date | Yes |
| File Attachment or Download URL | File / URL | Yes (one or the other) |
| File Type | Enum: CSV, ZIP, XLSX, Other | Yes |
| File Size | Auto-calculated / Text | Yes |
| Data License | Text | Yes |
| Methodology / Data Dictionary URL | URL | No |
| Contact / Owner | Text | No |
| Status | Enum: Draft, Published, Archived | Yes |

---

## Front-End Behavior

- Accessible repository table with columns: Dataset Name, Category/Sector, Coverage Area, Last Updated, File Type/Size, License, Download
- "Download CSV" action must link to a real, validated file
- If no public dataset is available: **"No public dataset is currently available for download."**
- Never display fake HDDS, household, yield, or similar metrics

---

## File Validation Rules

### CSV / ZIP Validator
- HTTP 200 when external URL
- Valid CSV or ZIP MIME type / file signature
- Non-zero file size
- Readable header row (for CSV)
- File size, update date, license, and data dictionary link displayed where available

---

## Security

- Admin capability: `manage_options`
- Nonces required on all state-changing requests
- File upload restricted to CSV, ZIP, XLSX MIME types
- All inputs sanitized; all outputs escaped
- Private contact data never exposed through public endpoints

---

## Accessibility

- WCAG 2.2 AA
- Accessible table with `<th scope>` and column headers
- Download links include descriptive `aria-label` with file type and size
- No color-only status indicators

---

## Folder Structure

```
ceafsn-open-datasets/
├── ceafsn-open-datasets.php
├── README.md
├── uninstall.php
├── includes/
├── admin/
├── public/
├── assets/
│   ├── css/
│   └── js/
└── tests/
```

---

## Uninstall Behavior

Deactivation does not delete data. Full removal only on explicit admin confirmation in the Uninstall tab. Export records before uninstalling.

---

## Troubleshooting

| Symptom | Likely Cause | Resolution |
|---------|-------------|------------|
| Table empty | No published datasets | Add and publish dataset records in admin |
| Download link broken | File deleted or URL changed | Re-upload file or update URL in admin |
| File fails validation | Wrong MIME type or empty file | Replace with a valid, non-empty CSV or ZIP |
