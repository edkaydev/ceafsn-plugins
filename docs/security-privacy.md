# Security and Privacy

Capabilities, data retention, form handling, and public/private data rules for the CE-AFSN plugin suite.

---

## Capabilities

All admin CRUD operations across all six plugins require the `manage_options` capability. This is equivalent to WordPress Administrator role.

| Action | Required Capability |
|--------|-------------------|
| Create / edit / delete any record | `manage_options` |
| Publish / unpublish a record | `manage_options` |
| Export data | `manage_options` |
| Uninstall plugin data | `manage_options` |
| Configure plugin settings | `manage_options` |
| View private/draft records | `manage_options` |

Unauthenticated users and any role below Administrator cannot perform any write operation.

---

## Nonces

Every state-changing HTTP request (create, update, delete, export, import, uninstall) must include a valid WordPress nonce. Requests without a valid nonce are rejected with a `403 Forbidden` response. Nonces are per-action and per-user.

---

## Input Sanitization

| Input Type | Sanitization Function |
|-----------|----------------------|
| Plain text fields | `sanitize_text_field()` |
| Textarea / rich text | `wp_kses_post()` |
| URLs | `esc_url_raw()` + URL format validation |
| Email addresses | `sanitize_email()` |
| Integers / IDs | `absint()` |
| Dates | Validated against `Y-m-d` or `Y-m-d H:i:s` format |
| Enum fields | Validated against an allowlist of accepted values |
| File uploads | MIME type check + `%PDF` or CSV signature check |

---

## Output Escaping

| Output Context | Escaping Function |
|---------------|-----------------|
| HTML attribute | `esc_attr()` |
| HTML content | `esc_html()` |
| URLs in href / src | `esc_url()` |
| JavaScript | `esc_js()` |
| Raw HTML (admin only) | `wp_kses_post()` |

---

## File Upload Security

- PDF uploads: only `application/pdf` MIME type accepted; `%PDF` file signature verified
- CSV uploads: must have a readable header row, so a binary file renamed to `.csv` is rejected
- ZIP uploads: must have a readable central directory and at least one entry
- XLSX uploads: must contain `[Content_Types].xml`
- Upload restrictions are **scoped to each plugin's own admin screen**, so they never
  remove an upload type the rest of the site depends on. The file picker is a usability
  guard only; the authoritative check is the server-side validator run on save.
- Direct PHP file access blocked by `defined('ABSPATH') or exit;` at the top of every PHP file
- No arbitrary file path input accepted from users
- External download URLs are restricted to `http`/`https` and are only ever requested by
  the server during an admin save, so the front end makes no request on a visitor's behalf.
  Non-HTTP schemes are rejected before any request is sent, and the response must be
  HTTP 200, report a size, and serve a content type matching the declared file type.
  Saving a record is privileged (capability plus nonce), which keeps this off the
  unauthenticated request path.

---

## SQL Security

All custom database queries use WordPress `$wpdb->prepare()` with parameterized placeholders. No raw SQL string interpolation of user input is permitted.

---

## Public vs. Private Data

| Data Type | Public Visibility Rule |
|-----------|----------------------|
| Metrics with `visibility = private` | Never shown on front end |
| Draft records (any plugin) | Never shown on front end |
| Archived records | Not shown by default; admin can enable |
| Contact email on fellowships | Only shown if admin has explicitly approved `show_public = true` |
| Contact on grants | Only shown if admin has explicitly approved `show_public = true` |
| Internal admin notes (e.g., duplicate flags, override notes) | Never shown on front end |
| Application data / form submissions | Never shown publicly; accessible only to admins |
| Members-only publication records | Excluded from the public query unless the visitor is signed in and passes the membership check; the document link is replaced with a sign-in message instead of a download |
| Publication duplicate and placeholder notes | Admin-only; visitors see only a "shared document" badge, never the reason |

### Members-Only Publications Are Link-Gating, Not Access Control

`ceafsn-projects-publications` hides members-only records and their document
links from visitors who do not pass the membership check. It does not make the
file secret: a document in the Media Library can still be fetched directly by
anyone who already knows its URL, and WordPress does not authenticate file
downloads. Restricted documents must therefore be treated as *unlisted*, not
*confidential*. A site that needs real confidentiality must store those files
outside the web root or behind a server that enforces access.

The membership check defaults to the `manage_options` capability and is
filterable:

```php
add_filter( 'ceafsn_pp_can_view_members_only', fn( $allowed ) => my_membership_check() );
```

---

## Data Retention

- Deactivating a plugin does **not** delete any data.
- Data is only deleted when the administrator:
  1. Navigates to the plugin's **Settings → Uninstall** tab
  2. Reads and acknowledges the warning
  3. Checks the confirmation checkbox
  4. Clicks the **Delete All Data** button
- There is no time-based automatic deletion.
- The admin is advised to export records before uninstalling.

---

## Form Handling

Contact forms and application forms (if implemented):
- Form recipient must be configured by the admin (no hardcoded email addresses)
- Sender policy (SPF/DKIM) depends on the hosting environment; the admin must configure accordingly
- Spam protection: WordPress nonces required; CAPTCHA integration documented but not bundled
- Privacy notice: a link to the site's privacy policy must be visible on any form that collects personal data
- Success/error messages: clear, non-technical messages shown to the user
- Data retention: form submissions stored in the database; admin can delete individual submissions; bulk delete available
- No submission data is sent to third-party services by these plugins

---

## Author and Comment Exposure

- The `edward_admin` author archive is noindexed by this suite unless a real public author profile is approved
- Default WordPress comments (`A WordPress Commenter`) are deleted as part of migration
- Comments on institutional publication pages are disabled unless moderation is explicitly configured by the admin

---

## SEO and Indexing

- Demo, Latin-slug, and placeholder routes are noindexed during migration
- Each canonical route uses a single, consistent URL (trailing slash convention follows WP permalink settings)
- Author archives for non-public authors are noindexed
- The `robots` meta tag for noindexed content is set via WordPress `wp_robots` filter
