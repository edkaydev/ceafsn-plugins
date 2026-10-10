# ceafsn-ai-assistant

Bilingual question-and-answer assistant for CE-AFSN content.

---

## Shortcode

`[ceafsn_ai_assistant]`

Attributes: `placeholder_en`, `placeholder_pt`.

---

## Purpose

Answers a visitor's question from the site's own published content only — never from outside knowledge — and returns the pages it used so the answer can be checked.

Text domain: `ceafsn-ai`. No public route of its own; it renders wherever the shortcode is placed, or in the site-wide floating button when that is switched on, and posts to `/wp-json/ceafsn-ai/v1/ask`.

---

## How an answer is built

1. The question is embedded with the configured embeddings provider.
2. Stored chunks carrying a vector are scored against it by cosine similarity; anything below `MIN_SIMILARITY` (0.30) is discarded and at most `TOP_K` (5) survive.
3. The survivors become the context of a fixed system prompt written in `CEAFSN_AI_Query::build_system_prompt()`.
4. The chat model answers inside that context. A question with no match returns the scripted refusal, in the language the question was asked in.

Result codes map to HTTP statuses in `CEAFSN_AI_Public::status_for()`; anything unrecognised is a 500, never a 200.

---

## What is indexed

| Source | Table or post type | Visible when |
|--------|--------------------|--------------|
| `wp_page` / `wp_post` | `wp_posts` | `post_status = 'publish'` and no password |
| `ceafsn_gf` | `ceafsn_gf_grants` | `status = 'published'` |
| `ceafsn_rf` | `ceafsn_rf_fellowships` | `status = 'published'` |
| `ceafsn_pp` | `ceafsn_pp_publications` | `status = 'published'` and `access_level = 'public'` |
| `ceafsn_od` | `ceafsn_od_datasets` | `status = 'published'` |
| `ceafsn_np` | `ceafsn_np_policies` | `status = 'published'` |
| `ceafsn_med` | `ceafsn_med_projects` | always — the registry has no draft state |

The rules live in `CEAFSN_AI_Indexer::TABLE_SOURCES`, so a source cannot be added without declaring its own visibility predicate.

### Keeping the index in step

* `save_post`, `trashed_post`, and `deleted_post` re-index or purge a page or post.
* The six plugins' `admin_post_ceafsn_{gf,rf,pp,od,np,med}_{save,delete}_*` actions are observed at priority 1 and applied at shutdown, after those handlers — which redirect and exit — have written the row.
* A single-record refresh that cannot complete (no embeddings provider, a document past `REFRESH_MAX_CHUNKS`, a failed embedding) purges the record's chunks and raises the stale flag in `CEAFSN_AI_Indexer::DIRTY_OPTION`, which the Overview reports. A complete, error-free run clears it.

---

## Admin screens

`ceafsn-ai` → Overview, Settings. Settings has three scopes: `display`, `providers`, `uninstall`. Each is its own form, so a tab only submits its own fields.

`display` carries the floating-launcher switch and corner (`CEAFSN_AI_Public::OPTION_FLOAT_ENABLED` / `OPTION_FLOAT_POSITION`). When on, `render_floater()` prints a launcher and panel in the footer of every front-end page; the panel reuses the shortcode's assistant markup, so both entry points behave the same.

Indexing runs inside the request that pressed the button and is capped at 90 seconds; a run that hits the cap reports itself partial rather than finished.

---

## Limits

* Question length: 500 characters (`CEAFSN_AI_Query::MAX_QUESTION_LENGTH`), enforced by the REST route and again in `ask()`.
* Rate limit: 20 questions per IP per fixed one-minute window. The window start is stored once and the counter's remaining life shrinks to match it, so a hit cannot slide the window forward.

---

## Tests

```
php plugins/ceafsn-ai-assistant/tests/run-tests.php
```

The harness stubs WordPress, a `$wpdb`, HTTP, and the shared library; nothing touches a database or the network. Release files (`README.md`, `readme.txt`, `.distignore`) and the POT header are asserted too.

---

## Files

```
ceafsn-ai-assistant.php   bootstrap, hooks
includes/                 db, providers, indexer, query, activator
public/                   shortcode, REST route, partials
admin/                    screens, settings, partials
assets/                   public css and js
languages/ceafsn-ai.pot   translation template
tests/                    test harness — excluded from release packages
```
