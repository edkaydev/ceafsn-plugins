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
| Field | Type | Notes |
|-------|------|-------|
| `pub_id` | INT AUTO_INCREMENT | Primary key |
| `title` | VARCHAR(255) | Required |
| `content_type` | ENUM('report','annual_report','policy_brief','working_paper','strategic_document','project') | Required |
| `executive_summary` | TEXT | Optional |
| `authors` | VARCHAR(255) | Required |
| `publication_date` | DATE | Required |
| `status` | ENUM('in_progress','completed','under_review','archived') | Required |
| `cover_image_id` | BIGINT | WP attachment ID — optional |
| `cover_image_alt` | VARCHAR(255) | Required if cover image set |
| `pdf_attachment_id` | BIGINT | WP attachment ID — required |
| `page_count` | SMALLINT | Optional |
| `doi_url` | TEXT | Optional |
| `access_level` | ENUM('public','members_only') | Required |
| `duplicate_flag` | BOOLEAN | True if PDF shared with another record |
| `duplicate_note` | TEXT | Required if duplicate_flag is true |

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
