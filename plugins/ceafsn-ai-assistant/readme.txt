=== CE-AFSN AI Assistant ===
Contributors: ceafsn
Author: edkaydev
Author URI: https://www.linkedin.com/in/edkaydev
Tags: ai, assistant, search, knowledge base, ceafsn
Requires at least: 6.0
Tested up to: 6.7
Requires PHP: 8.1
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A bilingual question-and-answer assistant that answers only from the CE-AFSN content published on this site.

== Description ==

The CE-AFSN AI Assistant puts a search box on any page or post. A visitor asks a question in English or Portuguese and gets an answer built strictly from the site's own published content — grants, fellowships, publications, open datasets, nutrition policy records, M&E projects, plus WordPress pages and posts.

Every answer comes back with the pages it was built from, so a reader can check the source instead of trusting the model.

= How it works =

* The index stores chunks of published text with a vector for each one, built by your chosen embeddings provider.
* A question is embedded the same way, matched against the index by cosine similarity, and the closest few chunks are handed to a chat model inside a fixed system prompt.
* The prompt confines the model to that context. When the answer is not in the index, it says so rather than guessing.
* An answer is refused outright when the site is not configured yet, rather than answered from a half-built index.

= What is indexed =

* Published WordPress pages and posts, excluding password-protected ones.
* Published, publicly accessible records from the six CE-AFSN plugins.
* Members-only publications are never indexed, and the M&E project registry is indexed in full because it has no draft state.

The index follows edits: saving or trashing a page updates it immediately, and saving a record from any CE-AFSN admin screen does the same. Changes that arrive with no embeddings provider configured are counted and shown on the Overview until indexing runs again.

= Providers =

Bring your own key for one of:

* OpenAI
* Google Gemini
* xAI Grok
* Anthropic Claude

The key is stored in the WordPress options table, never printed on the page, and only its last four characters are ever displayed back to you.

= Limits =

Questions are capped at 500 characters and 20 questions per IP per fixed one-minute window, both enforced server-side.

= Usage =

1. Activate the plugin.
2. Add your API key under **CE-AFSN → AI Assistant → Providers**.
3. Press **Run indexing** to build the knowledge base.
4. Place the `[ceafsn_ai_assistant]` shortcode on a page, or turn on the floating chat button under **Settings → Display** to show it on every page instead.

Shortcode attributes:

* `placeholder_en` — English placeholder text.
* `placeholder_pt` — Portuguese placeholder text.

The floating button opens the same assistant in a panel: bottom right or bottom left, full screen on phones, closed with Escape.

= Data =

Content is sent to the configured AI provider to generate an answer. Nothing is stored by the plugin beyond the index in your own database. Data is removed on uninstall only when the administrator explicitly opts in on the Uninstall screen.

== Frequently Asked Questions ==

= Does it answer from the internet? =

No. The system prompt confines the model to the retrieved context, and a question with no match in the index is answered with a scripted refusal rather than outside knowledge.

= What happens when a visitor asks something the site does not cover? =

The assistant replies that the information is not in the CE-AFSN knowledge base and points them to the CE-AFSN team, in the language they asked in.

= Can I change the model? =

Yes. Each provider has a model override on the Providers screen, so you can move to a newer model without a plugin update.

== Screenshots ==

1. The assistant rendered on the front end.
2. The Overview screen with its index status.
3. Provider settings with masked keys.

== Changelog ==

= 1.0.0 =
* Initial release.
