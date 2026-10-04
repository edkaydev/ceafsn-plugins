# ceafsn-shared

Shared utility library for the CE-AFSN plugin suite.

---

## Purpose

Provides reusable, namespaced utility classes used across multiple CE-AFSN plugins. Activating this library is optional — individual plugins may duplicate minimal required code to avoid hidden coupling.

---

## Planned Shared Utilities

### Validators
- `CEAFSN_PDF_Validator` — validates PDF attachments (MIME, signature, size, page count, placeholder check, duplicate check)
- `CEAFSN_CSV_Validator` — validates CSV/ZIP files (MIME, size, header row)
- `CEAFSN_URL_Validator` — validates application URLs, document links, and external sources

### Security Helpers
- `CEAFSN_Nonce` — standardized nonce generation and verification
- `CEAFSN_Capabilities` — capability checks shared across plugins
- `CEAFSN_Sanitize` — input sanitization helpers (URLs, rich text, enums, file extensions)

### Admin Helpers
- `CEAFSN_Admin_Notice` — standardized admin notice rendering
- `CEAFSN_Empty_State` — reusable honest empty-state component
- `CEAFSN_Export` — JSON/CSV export helper for all plugin record types

---

## Usage Principle

Each plugin must work independently. The shared library may only contain utilities that have no side effects when absent. If a plugin requires a shared utility, it must gracefully degrade or include a local copy of the minimal required code.

---

## Folder Structure

```
ceafsn-shared/
├── ceafsn-shared.php       ← optional bootstrap (no-op if not active)
└── README.md
```

Full class structure to be defined during Phase 2 build.

---

## Credits

Built by [edkaydev](https://www.linkedin.com/in/edkaydev).
CE-AFSN plugin suite — GPLv2 or later.
