# ceafsn-nutrition-policy

Nutrition and food security policy record library for CE-AFSN.

---

## Target Route

`/nutrition-policy-modeling/`

## Shortcode

`[ceafsn_policy_table]`

---

## Purpose

Provides an admin-managed, publicly searchable table of nutrition and food security policy documents. Each record requires a validated PDF before it can be published.

---

## Admin-Managed Entities

### Policy Records
| Field | Type | Required |
|-------|------|----------|
| Policy Title | Text | Yes |
| Description | Textarea | Yes |
| Publication Date | Date | Yes |
| Topic / Domain | Text | Yes |
| Authoring Institution | Text | Yes |
| PDF Attachment | File (Media Library) | Yes |
| Source / Citation URL | URL | No |
| Status | Enum: Draft, Published, Archived | Yes |

---

## Front-End Behavior

- Filterable and sortable accessible table
- "Read Policy" opens the PDF in a new tab with `target="_blank" rel="noopener noreferrer"`
- PDF must pass validation before a record can be published
- Clear empty state when no published policies exist: **"No policies have been published yet."**

---

## PDF Validation Rules

A PDF attachment is considered valid when:
- HTTP 200 response (if external URL)
- Valid PDF MIME type and `%PDF` file signature
- Non-zero file size
- Readable page count
- Not the known placeholder `ceafsn.pdf`

---

## Security

- Admin capability: `manage_options`
- Nonces required on all create/update/delete actions
- File upload restricted to PDF MIME type
- All inputs sanitized; all outputs escaped

---

## Accessibility

- WCAG 2.2 AA
- Sortable table with proper `<th scope>` and `aria-sort`
- Filter controls keyboard accessible
- Focus managed after filter/sort actions

---

## Folder Structure

```
ceafsn-nutrition-policy/
├── ceafsn-nutrition-policy.php   ← header, constants, autoload, hooks
├── readme.txt                    ← WordPress.org readme
├── README.md
├── uninstall.php
├── includes/
│   ├── class-ceafsn-np-db.php
│   ├── class-ceafsn-np-validator.php
│   └── class-ceafsn-np-activator.php
├── admin/
│   ├── class-ceafsn-np-admin.php
│   └── partials/
│       ├── policies.php
│       └── settings.php
├── public/
│   ├── class-ceafsn-np-public.php
│   └── partials/
│       └── policies.php
├── assets/
│   ├── css/
│   │   ├── ceafsn-np-public.css
│   │   └── ceafsn-np-admin.css
│   └── js/
│       ├── ceafsn-np-public.js
│       └── ceafsn-np-admin.js
├── languages/
│   └── ceafsn-np.pot
└── tests/
    ├── bootstrap.php         ← WordPress function stubs
    ├── run-tests.php         ← test suite entry point
    └── uninstall-cases.php   ← uninstall scenarios (subprocess)
```

---

## PDF Validation

`CEAFSN_NP_Validator` is deliberately pure where it can be: `inspect_bytes()`
takes a string and returns a result, so every rule is testable without
WordPress or a database. `validate_attachment()` resolves a Media Library
attachment and runs those same rules against the real file.

A document is publishable only when **all** of these hold:

| Check | Rejects |
|-------|---------|
| Attachment ID is non-zero | Record with no file |
| File resolves and is readable | Attachment deleted from the library |
| MIME type is `application/pdf` | Images or text renamed to `.pdf` |
| Filename is not a known placeholder | `ceafsn.pdf` and any configured names |
| File size is non-zero | Failed or interrupted upload |
| Bytes start with `%PDF-` | Any non-PDF content |
| Bytes contain `%%EOF` | Truncated upload |
| Page count is readable and ≥ 1 | Corrupt or empty file |

`count_pages()` counts `/Type /Page` objects (excluding the `/Type /Pages` tree
node) and falls back to the page tree's `/Count` value when objects are not
directly visible.

Publishing is blocked, not silently dropped: a record submitted as `Published`
with an invalid document is **saved as a draft** and the admin is told why.

---

## Tests

```bash
php plugins/ceafsn-nutrition-policy/tests/run-tests.php
```

235 assertions, no Composer, no PHPUnit, no WordPress install. Coverage
includes every PDF validation rule against real fixture files, field-level
sanitisation, SQL parameterisation and allow-listed sorting, the front-end
accessibility contract (labels, `aria-sort`, safe external links), output
escaping, upload restriction, activation behaviour, uninstall safety, and
content hygiene.

---

## Uninstall Behavior

Deactivation does not delete data. Full removal only on explicit admin confirmation in the Uninstall tab. Export records before uninstalling.

---

## Troubleshooting

| Symptom | Likely Cause | Resolution |
|---------|-------------|------------|
| Table shows empty state | No published records | Add and publish policy records in admin |
| Cannot publish record | PDF fails validation | Attach a valid, readable PDF |
| "Read Policy" broken | Attachment deleted from media library | Re-upload and reassign PDF |

---

## Credits

Built by [edkaydev](https://www.linkedin.com/in/edkaydev).
CE-AFSN plugin suite — GPLv2 or later.
