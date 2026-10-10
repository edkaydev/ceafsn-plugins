# CE-AFSN AI Assistant — Read-Only Audit Report

Audit target: `plugins/ceafsn-ai-assistant` (v1.0.0). Read-only review; no files were modified in the plugin during the audit.

## 1. Executive summary

**What it is.** `plugins/ceafsn-ai-assistant` (v1.0.0) is a RAG-style Q&A assistant. A visitor types a question into a `[ceafsn_ai_assistant]` widget; the server embeds the question, runs cosine similarity in PHP over stored chunk vectors, and passes the top 5 chunks plus the question to a hosted LLM (OpenAI / Gemini / Grok / Claude). The answer is returned with a list of source links. There is no vector database, no cron, no PDF parsing, and no chat history — one question in, one answer out.

**Biggest concerns:**

1. **High — non-public content can enter the public index.** Publications have `access_level ENUM('public','members_only')`, and the listings plugin filters on it, but the AI indexer filters only `status = 'published'` (`class-ceafsn-ai-indexer.php:184`). Members-only publication summaries/DOIs are therefore indexed and answerable by anonymous visitors.
2. **High — the index never invalidates.** No `save_post`, `transition_post_status`, `deleted_post`, cron, or WP-CLI hook exists (`ceafsn-ai-assistant.php:66-91`). Edited or deleted content keeps being cited from the index until an admin presses "Run indexing".
3. **Medium/High — M&E projects are silently never indexed.** The indexer queries `status = 'published'` (`class-ceafsn-ai-indexer.php:184`) but `wp_ceafsn_med_projects.status` is `ENUM('active','completed','suspended')` (`class-ceafsn-med-db.php:154`). The query returns zero rows with no error.
4. **Medium — cost and abuse controls are thin.** No server-side question-length cap, no token/day budget, a rate limiter that resets its own TTL on every hit and trusts `REMOTE_ADDR`.
5. **Readiness: prototype.** 122 tests exist but 8 assertions fail, there is no README, no `readme.txt`, no `.distignore`, and no release ZIP.

---

## 2. Repository and files inspected

| Path | Purpose |
|---|---|
| `plugins/ceafsn-ai-assistant/ceafsn-ai-assistant.php` | Plugin header, constants, requires, hooks |
| `.../includes/class-ceafsn-ai-db.php` | Schema (`wp_ceafsn_ai_chunks`), CRUD, chunking |
| `.../includes/class-ceafsn-ai-query.php` | Embed → cosine search → prompt → model → answer |
| `.../includes/class-ceafsn-ai-indexer.php` | Content collection, per-item chunk+embed |
| `.../includes/class-ceafsn-ai-providers.php` | Provider/model/key registry |
| `.../includes/class-ceafsn-ai-provider{,-openai,-gemini,-grok,-claude}.php` | HTTP adapters |
| `.../includes/class-ceafsn-ai-activator.php` | Activation: tables, caps, audit table |
| `.../admin/class-ceafsn-ai-admin.php` | Menus, settings/index/clear form handlers |
| `.../admin/partials/{overview,settings}.php` | Admin screens |
| `.../public/class-ceafsn-ai-public.php` | Shortcode + REST `/ceafsn-ai/v1/ask` |
| `.../public/partials/assistant.php`, `assets/js/ceafsn-ai-public.js` | Widget markup and client |
| `.../uninstall.php` | Opt-in data deletion |
| `.../tests/{bootstrap,run-tests,uninstall-cases}.php` | 122-test zero-dependency CLI suite |
| `plugins/ceafsn-shared/includes/class-ceafsn-{caps,audit-log,search}.php` | Shared caps/audit/keyword search |
| `plugins/ceafsn-me-dashboard/**` | M&E plugin (integration + requirement check) |
| `plugins/ceafsn-{grants,research,projects,open,nutrition}-*/includes/*db*.php` | Source table schemas |
| `README.md`, `docs/*`, `qa/*`, `.gitignore` | Project context |

No build tooling: no `composer.json`, `package.json`, CI config, or `.distignore`.

---

## 3. Current architecture and query flow

```
[admin] Settings → options: provider, API keys, model overrides
[admin] "Run indexing" → admin_post_ceafsn_ai_run_index (cap + nonce)
        └─ CEAFSN_AI_Indexer::run($full, 90s)
             collect_items() = WP posts/pages (publish)
                              + 6 custom tables (status='published')
             per item: wipe own chunks → split 800 chars/10% overlap
                       → 1 embed API call per chunk (usleep 50ms)
                       → INSERT into wp_ceafsn_ai_chunks (JSON vector)

[visitor] shortcode widget → POST /wp-json/ceafsn-ai/v1/ask {question}
        1. rate limit: 20 per md5(REMOTE_ADDR) per 60s (transient)
        2. sanitize_textarea_field   ← no upper bound
        3. embed(question)
        4. SELECT up to 2000 chunks WHERE provider=? AND embedding!=''
        5. cosine similarity in PHP; keep score ≥ 0.30; top 5
        6. system prompt = rules + chunk texts (verbatim)
        7. chat completion (max_tokens 600, temperature 0.2)
        8. wp_kses(answer, p/br/strong/em/ul/ol/li) + deduped sources
        → HTTP status from result code (200/400/404/429/502/503/500)
```

**Retrieval type:** pure semantic/vector RAG over a single MySQL table. No keyword hybrid, no reranker, no chat memory, no tool use.

---

## 4. Content/data coverage

| Desired source | Status | Evidence | Gap |
|---|---|---|---|
| Pages, posts | Supported (published only) | `class-ceafsn-ai-indexer.php:121-150` | Password-protected posts included; no post-modified check; other post types excluded |
| Categories, authors | Unsupported | no taxonomy/user query anywhere | — |
| Projects (M&E) | **Broken** | `class-ceafsn-ai-indexer.php:184` vs `class-ceafsn-med-db.php:154` | ENUM mismatch → 0 rows, no error |
| Publications / policies | Partly supported | `indexer.php:108,110` | `access_level` ignored → members-only leak; `pdf_attachment_id` unread |
| Grants, fellowships | Partly supported | `indexer.php:106-107` | `description` column does not exist in either table → silently dropped (`indexer.php:285,297`) |
| Datasets | Partly supported | `indexer.php:109` | Only metadata text; no file/CSV parsing; `methodology_url` unread |
| Uploaded PDFs / documents | **Unsupported** | no PDF code path; `call_pdf_id`, `pdf_attachment_id`, `file_attachment_id` never read | — |
| Open-dataset files / CSV metadata | **Unsupported** | `class-ceafsn-od-validator.php` is admin-only | — |
| M&E dashboard metrics/definitions | **Unsupported** | `ceafsn_med_metrics` / `_demographics` never queried by the indexer | `visibility` column exists but is unused by AI |
| CPTs / REST-exposed custom fields | N/A | `grep register_post_type` → no matches in any plugin | Data lives in custom tables, not REST-exposed (good) |

**Other coverage facts**

- `lang` is always written as `'en'` (`indexer.php:144,207`) and is never used as a query filter (`class-ceafsn-ai-db.php:206-235`) — dead schema, PT content not separable.
- `source_url` for all six plugin types is the **listing page**, not a record permalink (`indexer.php:360-371`). Every grant/fellowship/publication citation points at the category URL.
- `verification_status`, `duplicate_ok`, `scanned`, `access_level`, `visibility` are all ignored by the indexer — demo/unverified/duplicate records are indexed if `status='published'`.
- No `ceafsn_ai_...` hook exists on publish/update/delete (`grep add_action` across the plugin).

---

## 5. Indexing and answer quality

| Concern | State | Evidence |
|---|---|---|
| Ingestion trigger | Manual admin button only; 90 s deadline, "partial" reported honestly | `admin/class-ceafsn-ai-admin.php:344-360` |
| Re-index on publish/update/delete | **Absent** | no `save_post`/`transition_post_status`/`deleted_post` hooks |
| Cron / WP-CLI / bulk job | **Absent** | no `wp_schedule_*` |
| Dedup / stale cleanup | Per-source wipe on incremental run; full wipe on rebuild only | `indexer.php:238-240, 62-64` |
| Retries | None — a failed embed increments `errors` and moves on | `indexer.php:249-252` |
| Batching | None — 1 HTTP call per 800-char chunk | `indexer.php:245-247` |
| Abstention | Yes, twice: cosine ≥ 0.30 gate (`query.php:40,138`) and prompt rule 3 (`query.php:220`) | `no_match` → HTTP 404 |
| Source titles/dates/versions | Title + URL only. No date, no version, no verification badge in prompt or response | `query.php:162-173` |
| Conflicting/outdated sources | Not handled — the model cannot tell which chunk is newer (`indexed_at` is stored but never selected into the prompt) | `db.php:215-234` |
| Provider/index/network outage | Distinct machine codes and HTTP statuses (`no_index`, `index_provider_mismatch`, `embed_failed`, `model_failed`) surfaced to the operator and to JS | `query.php:76-121,181-186`; `public:143-158`; `js:169-184` |
| Cost/quota controls | Rate limit only. No daily token budget, no per-question length cap, no embedding-call ceiling | `public:109-125` |
| Caching | None for questions or answers | — |
| Logging | `error_log()` of provider error strings only. Questions are **not** stored anywhere (good for privacy, no abuse forensics) | e.g. `provider-openai.php:77,102` |
| Audit trail | `CEAFSN_Audit_Log` table is created but **never called** by this plugin — key changes, index runs, clears are unlogged | `grep CEAFSN_Audit_Log` in `ceafsn-ai-assistant` → only `create_table`/`maybe_upgrade` |
| User feedback | Error text only; no thumbs up/down, no "report bad answer" | `js:220-224` |

---

## 6. Security and privacy findings

| # | Sev | Finding | Evidence | Impact | Fix direction (not implemented) |
|---|---|---|---|---|---|
| S1 | **High** | Members-only publications are indexed and publicly retrievable | `indexer.php:183-185` ignores `access_level`; `class-ceafsn-pp-db.php:143-145,472-477` shows the listings filter it | Anonymous visitors can extract restricted publication summaries/DOIs via the AI | Filter `access_level = 'public'` at collection time; add a per-type allowlist of columns |
| S2 | **High** | No index invalidation on edit/delete/trash | `ceafsn-ai-assistant.php:66-91` (only textdomain/init/admin_init hooks) | Deleted or revised content stays answerable with dead links until a manual rebuild | Hook content mutations to mark a source dirty; purge on delete |
| S3 | **Medium** | Password-protected published posts are indexed in full | `indexer.php:124-130` selects `post_content` with no `post_password` check | Protected content disclosed | Exclude `post_password != ''` |
| S4 | **Medium** | No server-side maximum question length | `public/class-ceafsn-ai-public.php:75-85` validates only `>= 3`; `query.php:62` never truncates | Unbounded embedding+completion spend per request | Enforce max length in `args.validate_callback` |
| S5 | **Medium** | Rate limiter resets its own window and trusts `REMOTE_ADDR` | `public:110-125` — `set_transient(...,60)` on every hit; non-atomic read/increment | 20 slow questions spread over hours still trip 429; behind a reverse proxy every visitor shares one bucket (self-DoS) or, if spoofable, unlimited buckets | Fixed-TTL atomic increment; derive client IP from a configured header chain |
| S6 | **Medium** | Gemini API key is sent in the URL query string | `class-ceafsn-ai-provider-gemini.php:64,89` | Key lands in any HTTP/proxy/access log that records URLs | Use `x-goog-api-key` header |
| S7 | **Medium** | Document poisoning / prompt injection: chunk text is concatenated into the system prompt verbatim | `query.php:162-176,211-228` | Any indexed record (or a compromised plugin table) can carry instructions that override the grounding rules | Treat context as untrusted data with explicit delimiters/escaping; keep the answer constrained server-side |
| S8 | Low | JS builds `href="${url}"` where `esc()` (`js:71-75`) does not escape double quotes | `assets/js/ceafsn-ai-public.js:194-202`; URLs pass through `esc_url_raw` (`db.php:179`) which permits `"` | Latent attribute breakout. Not reachable today — `source_url` is only `get_permalink()` or a hardcoded `home_url()` | Escape quotes in the attribute context |
| S9 | Low | `wp_rest` nonce is emitted into the widget but never verified | `public:55` vs `permission_callback => '__return_true'` (`:74`) | Decorative; page caching shares one nonce across visitors. Harmless now, will break if the route is later made authenticated | Remove it or make the route authenticated and documented |
| S10 | Low | Masked API key displays the last 4 characters in the DOM | `admin/class-ceafsn-ai-admin.php:219-224`; `settings.php:249-253` (`type=password`, `autocomplete=new-password`) | Minor exposure on shared screens | Show only "set / not set" |
| S11 | Low | Admin actions not written to the shared audit log | `CEAFSN_Audit_Log::record()` never called by this plugin | No forensic trail for key rotation or index wipes | Log settings/index/clear events |
| S12 | **Info** | Household-level survey data is committed to the repository | `CEAFSN_Mozambique_Household_Food_Security_Crop_Yield_Survey_2025.csv` (tracked; added in `c4d5d29`), columns `Household_ID`, `HDDS_Score`, `Food_Insecure_Status` | Personal/attribute-level records in version control. **No plugin code references this file** — not an AI-search exposure | Remove from history or move to an access-controlled store |

**Positive controls (verified):** all SQL uses `$wpdb->prepare()`/`$wpdb->insert()`/`$wpdb->delete()` or fixed table names (`db.php`, `indexer.php:175-185`); no visitor-supplied SQL anywhere; admin-post handlers call `require_access()` + `check_admin_referer()` (`admin:238-246,344-346,403-405`); capability is `ceafsn_manage` with `manage_options` fallback (`admin:483-496`); answers pass `wp_kses` (`query.php:189`); API keys are stored as options, masked in the UI, never read on the front end (`providers.php:157-178`); REST `args` sanitize + validate callbacks are registered; **the full DB is never exposed to the LLM — only the top-5 retrieved chunks**.

---

## 7. M&E integration

| Item | Finding |
|---|---|
| Existing data source | `wp_ceafsn_med_metrics`, `wp_ceafsn_med_demographics` (both `visibility ENUM('public','private') DEFAULT 'private'`, `class-ceafsn-med-db.php:122,139`), `wp_ceafsn_med_projects` (`status` = active/completed/suspended, `verification_status` = verified/pending/unverified, `:154-155`) |
| What the AI plugin reads today | Only `ceafsn_med_projects`, and with a status value that matches no rows (`indexer.php:111,184`) |
| Permission model | M&E admin menus are gated `manage_options` (`admin/class-ceafsn-med-admin.php:55,61`) with a `CEAFSN_Caps::can()` helper used elsewhere (`:748-756`). The shared library creates `ceafsn_research_editor` with `ceafsn_edit`+`ceafsn_approve` (`class-ceafsn-caps.php:49,78-80,99-108`) |
| "M&E editor" role | **Not present.** No role of that name; no role-specific gate on the M&E screens |
| Second shortcode | **Not present.** Only `[ceafsn_me_dashboard]` exists (`public/class-ceafsn-med-public.php:21`) |
| Safe query approach for metrics | No read-only metrics API exists in this workspace. The AI plugin has **no** path to metrics, so no arbitrary-SQL risk exists today — and no capability either |
| Unknowns | Whether metric definitions should be answerable at all; where the "approved aggregate" boundary lives; who owns `visibility` promotion |

**Risk if naively connected later:** exposing `visibility='private'` metrics or `verification_status!='verified'` projects to the assistant would leak non-public M&E data to anonymous visitors — the exact failure mode S1 already shows for publications.

---

## 8. Test coverage and acceptance tests

**Exists.** `plugins/ceafsn-ai-assistant/tests/run-tests.php` — zero-dependency CLI harness, 122 named tests.

**Verified result:** `934 assertions, 926 passed, 8 failed` (exit code 1).

| # | Failing assertion | Cause |
|---|---|---|
| 1-2 | `README.md exists`, `readme.txt exists` | Files absent from `plugins/ceafsn-ai-assistant/` |
| 3 | `the catalogue declares the domain` | Test asserts `Text Domain: ceafsn-ai` but WP-CLI emits `X-Domain: ceafsn-ai` — **bad assertion** (the publications suite asserts `X-Domain` correctly, `ceafsn-projects-publications/tests/run-tests.php:1919`) |
| 4-7 | readme content ×4 | Files absent |
| 8 | `.distignore` / `tests` excluded | `.distignore` absent; no release ZIP exists (`dist/` empty, no `plugins/ceafsn-ai-assistant.zip`) |

**Good coverage:** schema/dbDelta, chunking maths, provider routing and fallbacks, provider request shapes, result-code→HTTP mapping, rate limiting, settings tab scoping, nonce/cap on admin forms, uninstall opt-in, i18n discipline, output escaping in partials.

**Missing cases (none of these exist today):**

1. Members-only / non-public records excluded from collection.
2. M&E project status values actually match the source ENUM.
3. Deleted/trashed post or record removes its chunks.
4. Password-protected post excluded.
5. Citation URL resolves to the record, not the listing page.
6. Abstention when all candidates are below threshold (partly covered as `no_match`).
7. Question length upper bound / oversized payload.
8. Rate-limit window does not extend on each hit; proxy IP handling.
9. Provider outage mid-index leaves a consistent index.
10. PT content produces PT citations/labels.
11. Stale/unverified (`verification_status`, `duplicate_ok`) demo records excluded.
12. Prompt-injection payload in a chunk cannot override the system rules.

**Acceptance tests to add before pilot:** exact-link correctness (every source URL → 200 on the live site); drafts/private/members-only never retrievable; no-answer phrasing in EN and PT; updated content reflected within one re-index; M&E metric definition + reporting period + visibility; malformed/oversized input; 429 behaviour; provider outage → clear 502/503, no partial answer; keyboard-only + screen-reader flow of the widget; viewport ≤ 380 px.

---

## 9. Prioritized next steps (recommendations only)

**P0 — before any public exposure**
1. Filter `access_level = 'public'` (and any equivalent per-type flag) in `collect_plugin_table()`; add a per-source column allowlist so unknown columns can't leak in.
2. Exclude password-protected posts; add an explicit per-source "is publicly visible" predicate rather than a raw `status = 'published'` string.
3. Fix the M&E status mismatch (or drop M&E from the indexer until a visibility contract is agreed).
4. Add content-mutation hooks so edits/deletes invalidate that source's chunks.

**P1 — before pilot**
5. Server-side max question length; per-day embedding/chat budget; fixed-TTL atomic rate limiter.
6. Move the Gemini key out of the URL; wire `CEAFSN_Audit_Log::record()` into settings/index/clear.
7. Emit real record permalinks as `source_url`; carry `publication_date`/`last_updated`/`verification_status` into the chunk row and the prompt.
8. Add `README.md`, `readme.txt`, `.distignore`; fix the POT assertion; build a release ZIP; get the suite green.

**P2 — quality/scale**
9. Batch embedding calls; add retries with backoff; consider a real vector store if the corpus grows past the 2 000-chunk in-memory scan (`db.php:206`).
10. Hybrid (keyword + vector) retrieval; per-language filtering using the existing `lang` column; answer caching; a feedback control.
11. Define the M&E read-only metrics API (allowlisted metrics, aggregated, `visibility='public'` only) before connecting it to the assistant.
12. PDF/document text extraction for the desired document sources.

---

## 10. Questions for the owner

1. Is `members_only` publication content in scope for the assistant at all, or should it be excluded permanently?
2. Should M&E metrics be answerable by anonymous visitors? If yes, which metrics and which reporting periods are "approved"?
3. What is the intended record permalink for grants/fellowships/publications/datasets — is there a single-record view, or must citations point at the listing page?
4. Is there a second M&E shortcode and an "M&E editor" role planned? Neither exists in this workspace.
5. Which AI provider is chosen for production, and what monthly spend ceiling should the plugin enforce?
6. Is the site behind a reverse proxy (for correct client-IP rate limiting)?
7. Should Portuguese content be indexed as a separate language slice, or is the current EN-labelled index acceptable?
8. Does the household survey CSV belong in this repository, or should it be moved out of version control?

---

## What I need from the owner before implementation

- **Approved public content types** — the exact list of post types, tables, and per-record flags (e.g. `access_level`, `visibility`, `verification_status`) the assistant may read.
- **Confirmation on protected content** — whether `members_only`, password-protected, or draft material is ever in scope (recommendation: never).
- **Where dashboard metrics live** — current location, who sets `visibility='public'`, and whether an aggregation layer already exists outside this repo.
- **Hosting/deployment constraints** — PHP `max_execution_time`, object-cache availability (the rate limiter and notices use transients), reverse proxy / `X-Forwarded-For` setup, and outbound-HTTP policy to the AI vendors.
- **Chosen AI provider and model set**, plus the monthly budget the plugin should enforce.
- **Deployment state** — only `ceafsn-me-dashboard` is installed live (`README.md:73-74`); confirm which of the other six plugins will be activated, since the AI index depends on five of their tables existing.

*Credentials: API keys should be configured on the plugin's Providers screen (stored as WordPress options, masked in the UI) or injected as environment variables if your host supports it. Do not paste keys into chat, tickets, or the repository — `.env*`, `*.pem`, and `*.key` are already gitignored (`.gitignore:44-46`).*
