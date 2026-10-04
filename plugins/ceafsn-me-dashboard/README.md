# ceafsn-me-dashboard

Institutional monitoring and evaluation hub for CE-AFSN.

---

## Target Route

`/me-dashboard/`

## Shortcode

`[ceafsn_me_dashboard]`

---

## Purpose

Provides a public-facing M&E dashboard showing real, admin-entered institutional metrics, demographics, and project registry. No placeholder data, scanning animations, or fake live-sync indicators are ever shown.

---

## Admin-Managed Entities

### Core Metrics
| Field | Type | Required |
|-------|------|----------|
| Label | Text | Yes |
| Value | Number/Text | Yes |
| Unit | Text | No |
| Definition | Textarea | No |
| Source | Text | Yes |
| Reporting Period | Text | Yes |
| Visibility | Enum: Public, Private | Yes |

### Demographic Groups
| Field | Type | Required |
|-------|------|----------|
| Label | Text | Yes |
| Count or Percentage | Number | Yes |
| Reporting Period | Text | Yes |
| Source | Text | Yes |

### Projects
| Field | Type | Required |
|-------|------|----------|
| Title | Text | Yes |
| Principal Investigator | Text | Yes |
| Target Region | Text | No |
| Status | Enum: Active, Completed, Suspended | Yes |
| Verification Status | Enum: Verified, Pending, Unverified | Yes |
| Last Updated | Date | Yes |
| Source URL | URL | No |

---

## Front-End Behavior

- Accessible tab navigation: **Overview**, **Demographics**, **Project Registry**
- Stat cards render only when a real, approved value exists
- SVG/HTML charts always accompanied by an accessible data table
- Filterable, paginated project table
- "Last updated" and "Data source" visible on all records
- If no approved data: **"No public metrics have been published yet."**
- If deliberately a preview: clear **Preview** badge with explanation

---

## Empty States

- No fake zeroes
- No ellipses (`…`)
- No scanning or initializing animations
- No fabricated live-sync claims

---

## Security

- Admin capability: `manage_options`
- All state-changing requests require a nonce
- All inputs sanitized; all outputs escaped
- Private metrics never exposed through public endpoints

---

## Accessibility

- WCAG 2.2 AA
- Keyboard-navigable tabs and tables
- Every chart has a text/table alternative
- Color is never the sole status indicator

---

## Folder Structure

```
ceafsn-me-dashboard/
├── ceafsn-me-dashboard.php   ← header, constants, autoload, hooks
├── readme.txt                ← WordPress.org readme
├── README.md
├── uninstall.php
├── includes/
│   ├── class-ceafsn-med-db.php
│   └── class-ceafsn-med-activator.php
├── admin/
│   ├── class-ceafsn-med-admin.php
│   └── partials/
│       ├── metrics.php
│       ├── demographics.php
│       ├── projects.php
│       └── settings.php
├── public/
│   ├── class-ceafsn-med-public.php
│   └── partials/
│       └── dashboard.php
├── assets/
│   ├── css/
│   │   ├── ceafsn-med-public.css
│   │   └── ceafsn-med-admin.css
│   └── js/
│       ├── ceafsn-med-public.js
│       └── ceafsn-med-admin.js
├── languages/
│   └── ceafsn-med.pot
└── tests/
    ├── bootstrap.php         ← WordPress function stubs
    ├── run-tests.php         ← test suite entry point
    └── uninstall-cases.php   ← uninstall scenarios (subprocess)
```

---

## Tests

The suite is dependency free — no Composer, no PHPUnit, no WordPress install:

```bash
php plugins/ceafsn-me-dashboard/tests/run-tests.php
```

Exit code `0` means every assertion passed. Coverage includes input validation, output escaping,
SQL parameterisation, the ARIA tab contract, empty states, uninstall safety, and content hygiene
(no Lorem ipsum or template copy anywhere in the shipped files).

---

## Uninstall Behavior

Deactivation does **not** delete data. Full data removal only occurs when the administrator confirms deletion in the **Uninstall** settings tab. Export records as JSON/CSV before uninstalling.

---

## Troubleshooting

| Symptom | Likely Cause | Resolution |
|---------|-------------|------------|
| Dashboard shows empty state | No published metrics | Add and publish records in admin |
| Shortcode outputs nothing | Plugin not active | Activate plugin and flush permalinks |
| Chart missing accessible table | Bug | File an issue with the shortcode output |

---

## Credits

Built by [edkaydev](https://www.linkedin.com/in/edkaydev).
CE-AFSN plugin suite — GPLv2 or later.
