=== Smart Content Rewriter ===
Contributors: Ajid Digital Tools
Tags: ai, article, rewrite, anthropic, importer
Requires at least: 5.8
Tested up to: 6.5
Requires PHP: 5.6
Stable tag: 1.2.0
License: GPLv2 or later

Import approved article URLs or RSS feeds, rewrite them with Anthropic Claude, and create WordPress posts.

Website: https://ajidmujaddid.staff.telkomuniversity.ac.id/

== Setup ==
1. Upload and activate the plugin.
2. Open WP Admin > AI Rewriter.
3. Add your Anthropic API key.
4. Keep the model as claude-haiku-4-5-20251001 or replace it with another Anthropic model.
5. Add one source URL or RSS feed per line.
6. Optional: use Managed Sources with this format:
   URL | Label | Category ID | Status | keywords | custom prompt
7. Optional: use Keyword Category Mapping with this format:
   keyword | category_id
8. Choose Draft, Pending Review, or Publish.
9. Use Test API Connection, Preview Next Item, then Save Preview as Post.
10. Click Run Import Now for automatic processing.

== Features ==
* Detailed import logs.
* Preview before saving posts.
* Smarter article extraction.
* Prompt controls for language, writing style, length, SEO title, meta description, headings, and FAQ.
* Source rules with per-source label, category, status, and keywords.
* Keyword to category mapping.
* Import history.
* Duplicate protection by source URL, content hash, and similar title.
* Featured image from OG image, first article image, or RSS media.
* Cleaner content before rewrite.
* Queue processing to reduce timeout risk.
* Per-source custom prompt.
* Yoast and RankMath meta description support.
* AI-generated tags.
* Rewrite quality control.
* Source testing.
* Safety mode that keeps generated posts as draft.
