# ceafsn-grants-funding

Grants and scholarships opportunity listings for CE-AFSN.

---

## Target Route

`/scholarships-grants/`

## Shortcode

`[ceafsn_grants]`

---

## Purpose

Provides an admin-managed listing of grants and scholarships. No funding amounts, statuses, or deadlines are ever invented. The Official Call PDF must be unique, readable, and validated before publishing.

---

## Admin-Managed Entities

### Grant / Scholarship Records
| Field | Type | Required |
|-------|------|----------|
| Grant / Scholarship Title | Text | Yes |
| Award Range | Text or "Not disclosed" | Yes |
| Deadline | Date + Time | Yes |
| Deadline Timezone | Enum (WP site timezone) | Yes |
| Target Beneficiaries | Textarea | No |
| Eligibility | Textarea | Yes |
| Status | Enum: Open, Closed, Upcoming, Archived | Yes |
| Official Call PDF | File (Media Library) | Yes |
| Application URL | URL | No |
| Funding Institution | Text | Yes |
| Contact | Text | No (admin-approved for public display) |

---

## Front-End Behavior

- Accessible opportunity cards or table
- Deadline displayed with timezone and human-readable format
- Status displayed as badge (not color-only)
- Official Call PDF links to a validated, unique document in a new tab
- Apply button active only when a valid Application URL is set
- Clear empty state when no published opportunities exist: **"No funding opportunities are currently open."**

---

## PDF Validation Rules

An Official Call PDF is valid when:
- HTTP 200 (if external)
- Valid PDF MIME type and `%PDF` signature
- Non-zero file size
- Readable page count
- Unique — not shared silently with another grant record without an explicit duplicate flag

---

## Security

- Admin capability: `manage_options`
- Nonces required on all state-changing requests
- File upload restricted to PDF MIME type
- Deadline stored as UTC; displayed in site timezone
- All inputs sanitized; all outputs escaped
- Contact data only public if explicitly approved

---

## Accessibility

- WCAG 2.2 AA
- Cards/table keyboard accessible
- Deadline includes `<time datetime="">` element for machine readability
- Status badge uses text and icon, not color alone
- Apply button uses disabled state with `aria-disabled` when no URL

---

## Folder Structure

```
ceafsn-grants-funding/
├── ceafsn-grants-funding.php
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
| Listing empty | No published grants | Add and publish grant records in admin |
| Deadline shows wrong timezone | Site timezone not set | Set timezone in WP Settings → General |
| PDF fails validation | Invalid file or wrong MIME | Replace with a valid, readable PDF |
| Apply button inactive | No application URL | Add a valid URL in admin |
