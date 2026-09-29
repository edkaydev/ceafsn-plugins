# Data Model

Entities, fields, statuses, and relationships across the CE-AFSN plugin suite.

---

## ceafsn-me-dashboard

### Core Metrics
| Field | Type | Notes |
|-------|------|-------|
| `metric_id` | INT AUTO_INCREMENT | Primary key |
| `label` | VARCHAR(255) | Required |
| `value` | VARCHAR(255) | Required — real data only |
| `unit` | VARCHAR(100) | Optional |
| `definition` | TEXT | Optional |
| `source` | VARCHAR(255) | Required |
| `reporting_period` | VARCHAR(100) | Required |
| `visibility` | ENUM('public','private') | Default: private |
| `created_at` | DATETIME | Auto |
| `updated_at` | DATETIME | Auto |
| `updated_by` | BIGINT | WP user ID |

### Demographic Groups
| Field | Type | Notes |
|-------|------|-------|
| `group_id` | INT AUTO_INCREMENT | Primary key |
| `label` | VARCHAR(255) | Required |
| `value` | DECIMAL(10,2) | Required |
| `value_type` | ENUM('count','percentage') | Required |
| `reporting_period` | VARCHAR(100) | Required |
| `source` | VARCHAR(255) | Required |
| `visibility` | ENUM('public','private') | Default: private |

### Projects
| Field | Type | Notes |
|-------|------|-------|
| `project_id` | INT AUTO_INCREMENT | Primary key |
| `title` | VARCHAR(255) | Required |
| `principal_investigator` | VARCHAR(255) | Required |
| `target_region` | VARCHAR(255) | Optional |
| `status` | ENUM('active','completed','suspended') | Required |
| `verification_status` | ENUM('verified','pending','unverified') | Required |
| `last_updated` | DATE | Required |
| `source_url` | TEXT | Optional |

---

## ceafsn-nutrition-policy

### Policy Records
| Field | Type | Notes |
|-------|------|-------|
| `policy_id` | INT AUTO_INCREMENT | Primary key |
| `title` | VARCHAR(255) | Required |
| `description` | TEXT | Required |
| `publication_date` | DATE | Required |
| `topic` | VARCHAR(255) | Required |
| `authoring_institution` | VARCHAR(255) | Required |
| `pdf_attachment_id` | BIGINT | WP attachment ID — required |
| `source_url` | TEXT | Optional |
| `status` | ENUM('draft','published','archived') | Default: draft |
| `created_at` | DATETIME | Auto |
| `updated_at` | DATETIME | Auto |
| `updated_by` | BIGINT | WP user ID |

---

## ceafsn-open-datasets

### Dataset Records
| Field | Type | Notes |
|-------|------|-------|
| `dataset_id` | INT AUTO_INCREMENT | Primary key |
| `name` | VARCHAR(255) | Required |
| `description` | TEXT | Required |
| `category` | VARCHAR(255) | Required |
| `coverage_area` | VARCHAR(255) | Required |
| `last_updated` | DATE | Required |
| `file_attachment_id` | BIGINT | WP attachment ID (nullable if using URL) |
| `download_url` | TEXT | External URL (nullable if using attachment) |
| `file_type` | ENUM('csv','zip','xlsx','other') | Required |
| `file_size` | VARCHAR(50) | Server-derived only: bytes on disk, or the reported `Content-Length` for external URLs. Never accepted from form input. |
| `data_license` | VARCHAR(255) | Required |
| `methodology_url` | TEXT | Optional |
| `contact_owner` | VARCHAR(255) | Optional |
| `status` | ENUM('draft','published','archived') | Default: draft |

Constraint: at publish time `file_attachment_id` or `download_url` must be present **and**
pass validation — the file's structure for attachments, or HTTP 200 plus a reported size
plus a matching content type for external URLs. A record that fails is downgraded to
`draft` with a reason rather than published with a broken link.

---

## ceafsn-projects-publications

### Publication / Project Records
Table: `{$wpdb->prefix}ceafsn_pp_publications`

| Field | Type | Notes |
|-------|------|-------|
| `publication_id` | BIGINT UNSIGNED AUTO_INCREMENT | Primary key |
| `title` | VARCHAR(255) | Required |
| `content_type` | ENUM('report','annual_report','policy_brief','working_paper','strategic_document','project') | Required, default `report` |
| `executive_summary` | TEXT | Optional |
| `author_institution` | VARCHAR(255) | Required |
| `publication_date` | DATE | Required |
| `project_status` | ENUM('in_progress','completed','under_review','archived') | Required, default `in_progress` |
| `cover_image_id` | BIGINT UNSIGNED | WP attachment ID, 0 when none |
| `cover_image_alt` | VARCHAR(255) | Required if `cover_image_id` is set |
| `pdf_attachment_id` | BIGINT UNSIGNED | WP attachment ID — required to publish |
| `page_count` | SMALLINT UNSIGNED | 0 means unknown; measured from the document on save when left at 0 |
| `doi_citation` | TEXT | Optional |
| `access_level` | ENUM('public','members_only') | Required, default `public` |
| `duplicate_note` | TEXT | Required if `duplicate_ok` is 1 |
| `scanned` | TINYINT(1) | 1 when the PDF has no extractable text by design |
| `duplicate_ok` | TINYINT(1) | 1 when sharing one PDF across records is intentional |
| `status` | ENUM('draft','published','archived') | Publication state, default `draft` |
| `created_at` / `updated_at` | DATETIME | Auto |
| `updated_by` | BIGINT UNSIGNED | WP user ID |

Indexes: `status`, `content_type`, `project_status`, `access_level`,
`publication_date`, `pdf_attachment_id`. The last one exists because the
duplicate-document check counts other records by attachment.

**Two status fields, not one.** `status` is the publication state and decides
whether a record appears on the public page. `project_status` describes the work
itself and is what the public filter offers. A finished project (`completed`) is
still a `draft` until its document passes validation.

Documents are attachment-only by design: `pdf_attachment_id` is the single source
of the file, so validation, the duplicate check, and the front-end link all read
the same attachment. An external URL field was removed rather than left as an
unchecked second path.

At publish time all of the following must hold, or the record is downgraded to
`draft` with a reason:

- `pdf_attachment_id` names an attachment whose MIME type is `application/pdf`,
  whose bytes start with `%PDF-` and end with `%%EOF`, and which is not empty.
- A page count can be read from the file. If the admin entered one, it matches;
  if the field was 0, the measured value is stored.
- The PDF contains extractable text, unless `scanned` is 1.
- The filename is not on the placeholder list, unless confirmed with a note.
- No other record uses the same attachment, unless `duplicate_ok` is 1 with a
  note. A record is never counted as a duplicate of itself.

---

## ceafsn-research-fellowships

### Fellowship Opportunities
| Field | Type | Notes |
|-------|------|-------|
| `fellowship_id` | INT AUTO_INCREMENT | Primary key |
| `title` | VARCHAR(255) | Required |
| `track_domain` | VARCHAR(255) | Optional |
| `duration` | VARCHAR(100) | Optional |
| `eligibility` | TEXT | Required |
| `host_supervisor` | VARCHAR(255) | Optional |
| `stipend_info` | TEXT | Optional — only if approved |
| `opening_date` | DATE | Optional |
| `closing_date` | DATE | Required for auto-Open |
| `status` | ENUM('open','closed','upcoming','archived') | Required |
| `status_override` | BOOLEAN | Default: false |
| `status_override_note` | TEXT | Required if override is true |
| `application_url` | TEXT | Optional |
| `call_pdf_id` | BIGINT | WP attachment ID — optional |
| `contact_email` | VARCHAR(255) | Optional — admin-approved for display |

---

## ceafsn-grants-funding

### Grant / Scholarship Records
| Field | Type | Notes |
|-------|------|-------|
| `grant_id` | INT AUTO_INCREMENT | Primary key |
| `title` | VARCHAR(255) | Required |
| `award_range` | VARCHAR(255) | Required — use "Not disclosed" if unknown |
| `deadline` | DATETIME | Required |
| `deadline_timezone` | VARCHAR(100) | Site timezone from WP settings |
| `target_beneficiaries` | TEXT | Optional |
| `eligibility` | TEXT | Required |
| `status` | ENUM('open','closed','upcoming','archived') | Required |
| `call_pdf_id` | BIGINT | WP attachment ID — required |
| `application_url` | TEXT | Optional |
| `funding_institution` | VARCHAR(255) | Required |
| `contact` | VARCHAR(255) | Optional — admin-approved for display |

---

## Status Enums Reference

| Plugin | Status Values |
|--------|--------------|
| Metrics | public, private |
| Projects (M&E) | active, completed, suspended |
| Verification | verified, pending, unverified |
| Policies | draft, published, archived |
| Datasets | draft, published, archived |
| Publications | in_progress, completed, under_review, archived |
| Access | public, members_only |
| Fellowships | open, closed, upcoming, archived |
| Grants | open, closed, upcoming, archived |
