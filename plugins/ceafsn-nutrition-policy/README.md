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
├── ceafsn-nutrition-policy.php
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
| Table shows empty state | No published records | Add and publish policy records in admin |
| Cannot publish record | PDF fails validation | Attach a valid, readable PDF |
| "Read Policy" broken | Attachment deleted from media library | Re-upload and reassign PDF |
