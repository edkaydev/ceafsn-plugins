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
├── ceafsn-me-dashboard.php   ← main plugin file (header only, no logic yet)
├── README.md
├── uninstall.php
├── includes/                 ← core classes (to be built)
├── admin/                    ← admin screens (to be built)
├── public/                   ← front-end shortcode (to be built)
├── assets/
│   ├── css/
│   └── js/
└── tests/
```

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
