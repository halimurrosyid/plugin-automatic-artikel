=== SourceWise AI Publisher ===
Contributors: halimurrosyid
Tags: ai, content, publisher, rss, editorial
Requires at least: 5.8
Tested up to: 6.5
Requires PHP: 5.6
Stable tag: 1.2.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Create draft posts from approved source URLs or RSS feeds with optional Anthropic-powered editorial assistance.

Website: https://ajidmujaddid.staff.telkomuniversity.ac.id/

This plugin is intended only for content that site owners own, license, or have explicit permission to use. Site owners are responsible for complying with source website terms, copyright law, and third-party API terms.

== Setup ==
1. Upload and activate the plugin.
2. Open WP Admin > SourceWise.
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
* Cleaner content before editorial drafting.
* Queue processing to reduce timeout risk.
* Per-source custom prompt.
* Yoast and RankMath meta description support.
* AI-generated tags.
* Editorial draft quality control.
* Source testing.
* Safety mode that keeps generated posts as draft.
* Optional source attribution link in generated post content.

== Third-party services ==

This plugin can connect to the Anthropic API when the site owner adds an Anthropic API key and uses API testing, preview, editorial drafting, or import features. Article text, prompts, titles, and related instructions are sent to Anthropic for processing.

Anthropic service:
https://www.anthropic.com/

Anthropic legal terms and privacy information:
https://www.anthropic.com/legal

The plugin also requests the source URLs or RSS feeds configured by the site owner in order to fetch article content and images.

== Privacy ==

The plugin does not add tracking scripts and does not send data to external services unless the site owner configures sources and an Anthropic API key, then runs testing, preview, import, or queue processing.

== Frequently Asked Questions ==

= Can this plugin republish any article from any website? =

No. Use it only with content that you own, license, or have permission to republish.

= Does it automatically publish generated posts? =

Safety mode is enabled by default and keeps generated posts as drafts. The site owner can change the post status settings.

= Can it add a source link? =

Yes. Source attribution links are optional and controlled from the plugin settings.

== Changelog ==

= 1.2.1 =
* Added GPL license header and license file.
* Added third-party service and privacy disclosures.
* Added optional source attribution setting.

= 1.2.0 =
* Added featured images, queue processing, source testing, SEO plugin metadata, auto tags, content cleaner, and quality controls.
