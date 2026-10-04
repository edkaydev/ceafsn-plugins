# ceafsn-research-fellowships

Research fellowship and opportunity listings for CE-AFSN.

---

## Target Route

`/research-fellowships/`

## Shortcode

`[ceafsn_fellowships]`

---

## Purpose

Provides an admin-managed listing of research fellowship opportunities. Status is only shown as Open when an approved closing date exists or the admin has explicitly overridden it with a documented audit note. No opportunity is ever listed as Open without verification.

---

## Admin-Managed Entities

### Fellowship Opportunities
| Field | Type | Required |
|-------|------|----------|
| Fellowship Title | Text | Yes |
| Track / Domain | Text | No |
| Duration | Text | No |
| Eligibility Criteria | Textarea | Yes |
| Host / Supervisor | Text | No |
| Stipend / Funding Info | Text | No (only if approved) |
| Opening Date | Date | No |
| Closing Date | Date | Yes (required for auto-Open status) |
| Status | Enum: Open, Closed, Upcoming, Archived | Yes |
| Status Override | Boolean | No (requires audit note if true) |
| Status Override Note | Textarea | Required if override is true |
| Application URL | URL | No |
| Call PDF | File (Media Library) | No |
| Contact Email | Email | No (admin-approved for public display) |

---

## Front-End Behavior

- Cards or table layout with all required fields
- Apply button:
  - Links to a valid Application URL, or
  - Clearly shown as disabled/inactive when no URL is set
- Status derived from current date vs. closing date when dates are configured
- Timezone behavior documented: uses WordPress site timezone setting
- Status never shown as Open without an approved closing date or explicit admin override with audit note
- Clear empty state when no active opportunities exist

---

## Date-Driven Status Logic

| Condition | Displayed Status |
|-----------|-----------------|
| Opening date in future | Upcoming |
| Between opening and closing date | Open |
| Past closing date | Closed |
| Admin sets status manually (no dates) | As set by admin |
| Admin override = true | Open (with override note visible in admin) |

---

## Security

- Admin capability: `manage_options`
- Nonces required on all state-changing requests
- Contact email only displayed if admin has explicitly approved public display
- Application URL validated before saving
- All inputs sanitized; all outputs escaped

---

## Accessibility

- WCAG 2.2 AA
- Cards/table keyboard accessible
- Apply button properly disabled state with `aria-disabled` when inactive
- Closing date displayed with human-readable format and timezone
- No color-only status indicators

---

## Folder Structure

```
ceafsn-research-fellowships/
├── ceafsn-research-fellowships.php
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
| Listing shows empty state | No published opportunities | Add and publish fellowship records in admin |
| Status shows Closed incorrectly | Closing date passed | Update closing date or manually set status |
| Apply button disabled | No application URL set | Add a valid application URL in admin |
| Status shown as Open without dates | Manual override active | Review override note in admin |

---

## Credits

Built by [edkaydev](https://www.linkedin.com/in/edkaydev).
CE-AFSN plugin suite — GPLv2 or later.
