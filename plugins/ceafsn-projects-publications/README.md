# ceafsn-projects-publications

Projects and publications library for CE-AFSN.

---

## Target Route

`/publications/`

## Legacy Redirect

`/privacy-policy-2/` → permanent 301 redirect → `/publications/`

## Shortcode

`[ceafsn_projects_pubs]`

---

## Purpose

Replaces the broken publication library. Every published record must have its own unique, validated document. No record may silently share the same placeholder PDF as another unless the admin explicitly flags it as a shared document.

---

## Admin-Managed Entities

### Publication / Project Records
| Field | Type | Required |
|-------|------|----------|
| Title | Text | Yes |
| Content Type | Enum: Report, Annual Report, Policy Brief, Working Paper, Strategic Document, Project | Yes |
| Executive Summary | Textarea | No |
| Author(s) / Institution | Text | Yes |
| Publication Date | Date | Yes |
| Status | Enum: In Progress, Completed, Under Review, Archived | Yes |
| Cover Image | Image (Media Library) | No |
| Cover Image Alt Text | Text | If image set |
| PDF Attachment | File (Media Library) | Yes |
| Page Count | Number | No |
| DOI / Citation / Source URL | URL | No |
| Access Level | Enum: Public, Members Only | Yes |

---

## Front-End Behavior

- Grid and list view toggle
- Filters by type, topic, year, and status
- Each card shows: title, content type, author, date, status, file size, page count
- "View Document" opens PDF in new tab with `target="_blank" rel="noopener noreferrer"`
- Duplicate-document flag: if two records share a PDF, admin must explicitly confirm and the flag is displayed on both records
- Clear empty state when no published records exist

---

## PDF Validation Rules

A PDF is considered valid when:
- HTTP 200 (if external)
- Valid PDF MIME type and `%PDF` file signature
- Non-zero file size
- Readable page count that matches the admin-entered value
- Contains extractable text (or admin flags it as a scanned document)
- Not the known placeholder `ceafsn.pdf` unless admin explicitly confirms and it passes all other checks
- Not silently shared with another record without the duplicate-document flag

---

## Route Migration

| Old Route | New Route | Redirect Type |
|-----------|-----------|---------------|
| `/privacy-policy-2/` | `/publications/` | 301 Permanent |

All homepage links, menu items, and footer links referencing `/privacy-policy-2/` must be updated to `/publications/`.

---

## Security

- Admin capability: `manage_options`
- Nonces required on all create/update/delete actions
- File upload restricted to PDF MIME type
- All inputs sanitized; all outputs escaped

---

## Accessibility

- WCAG 2.2 AA
- Grid/list toggle keyboard accessible
- Filter controls properly labeled
- Card headings maintain correct hierarchy
- No color-only status indicators

---

## Folder Structure

```
ceafsn-projects-publications/
├── ceafsn-projects-publications.php
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

Deactivation does not delete data. Full removal only on explicit admin confirmation in the Uninstall tab. Export records before uninstalling. The 301 redirect for `/privacy-policy-2/` remains active until the admin explicitly removes it.

---

## Troubleshooting

| Symptom | Likely Cause | Resolution |
|---------|-------------|------------|
| `/publications/` returns 404 | Page not created | Create page and add shortcode |
| `/privacy-policy-2/` not redirecting | Redirect rule not registered | Activate plugin and flush permalinks |
| Record cannot be published | PDF fails validation | Attach a valid, readable, unique PDF |
| Page count mismatch | Wrong value entered in admin | Correct the page count field |
