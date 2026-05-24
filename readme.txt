=== This Is My URL - WEBP Support ===
Contributors: thisismyurl
Donate link: https://github.com/sponsors/thisismyurl
Tags: webp, images, media, optimization, compression
Requires at least: 6.0
Tested up to: 7.0
Requires PHP: 8.1
Stable tag: 1.6143
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Non-destructive WebP/AVIF conversion for Media Library images with safe backups and one-click restore.

== Description ==

WEBP Support by thisismyurl.com converts supported Media Library attachments (JPEG, PNG, GIF, BMP) to WebP using the WordPress image editor stack (GD or Imagick). Originals are preserved in a backup directory under `uploads/webp-backups/` and can be restored at any time, individually or in bulk.

What this plugin actually ships:

* Tools > WebP Support page with Optimize, Settings, Pending, and Managed Media sections.
* Output Format setting: WebP (default), AVIF (Imagick + libheif required), or Both (AVIF primary + WebP companion via &lt;picture&gt;).
* Quality Preset setting: Web (82), Print (95), Lossless (100), or Custom.
* Per-format conversion toggles for JPG/JPEG, PNG, GIF, and BMP.
* Non-destructive batch optimization with progress bar, savings display, and cancel.
* Pending table shows per-image file sizes; Managed table shows bytes saved per image.
* Single Restore button per managed image and a Restore All bulk action.
* Status flags for missing files and items excluded by current settings.
* Per-attachment lock so two operators or two browser tabs cannot race the same file.
* Attachment metadata regenerated after each conversion or restoration.
* Optional backup-folder cleanup on uninstall.
* WP-CLI commands: `wp webp convert`, `wp webp restore`, `wp webp status`.
* Localization support: French (Canada) translation included.

What this plugin does NOT do (and never has — earlier readme drafts overstated the scope):

* No tabbed UI, no ROI report, no analytics surface.
* No optimize-on-upload, no background auto-optimize, no WP-Cron scheduler.
* No EXIF / GPS / metadata stripping.
* No outbound UTM tagging.
* No theme-image conversion (removed in 0.6123 — incompatible with managed hosts).

How it works:

1. Go to Tools > WebP Support.
2. Set quality, batch size, enabled formats, and uninstall behaviour.
3. Click "Optimize All" to process pending attachments in AJAX batches.
4. Use Restore on individual rows, or "Restore All Originals" for a bulk rollback.

Notes:

* Uses the WordPress image editor stack (GD or Imagick). No external services or phone-home.
* Existing WebP attachments are flagged as externally managed and never overwritten.
* Backup paths are stored relative to the uploads directory so dev↔prod database copies survive migration.

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/`.
2. Activate the plugin through the Plugins screen.
3. Go to Tools > WebP Support.
4. Configure and run optimization.

== Frequently Asked Questions ==

= Does this delete original images? =
No. Originals are moved to `uploads/webp-backups/` and can be restored.

= What image types are supported? =
JPEG, PNG, GIF, and BMP as conversion sources.

= Can I control performance and quality? =
Yes. WebP quality and AJAX batch size are configurable in Tools > WebP Support.

= Can I choose which image types are converted? =
Yes. Per-format toggles for JPG/JPEG, PNG, GIF, and BMP.

= Does this require Imagick? =
No. It uses WordPress image editors and works with either GD or Imagick.

= Is there a WP-CLI interface? =
Yes. `wp webp convert <id|--all>`, `wp webp restore <id|--all>`, `wp webp status`.

== Languages ==

* French (Canada) — Christopher Ross

== Changelog ==

= 1.6143 =
* First full release (class 1). The 0.6xxx line was pre-release on the `x.Yddd` scheme.
* Standardized the donation link to GitHub Sponsors.

= 0.6126 =
* Added Output Format setting: WebP (default), AVIF (Imagick + libheif), or Both (AVIF + WebP via picture element).
* Added Quality Preset setting: Web (82), Print (95), Lossless (100), or Custom.
* Added savings display: dashboard stats, Size column in Pending table, Saved column in Managed Media table.
* Added i18n support and French (Canada) translation.
* Fixed get_media_lists() to include image/avif in the mime type filter.

= 0.6123 =
* Removed the theme-image conversion feature — wrote to `wp-content/themes/` at runtime, which fails on managed hosts (WP Engine, Pantheon, Kinsta).
* Removed the dead `wp_ajax_timu_wsconvert_theme_batch` handler that became a fatal on any click.
* Bounded the Tools-page query so libraries with thousands of attachments no longer risk hitting the PHP memory limit (paged `fields=ids` walk).
* Added per-attachment locking so concurrent operators cannot race the same file.
* Backup paths now stored relative to uploads basedir for migration safety; legacy absolute paths still readable.
* Filesystem API initialisation falls back to direct PHP file ops on hosts that prompt for FTP credentials (admin-`manage_options` gated).
* Added WP-CLI commands `wp webp convert`, `wp webp restore`, `wp webp status`.
* Bumped Tested up to 6.9, Requires PHP 8.1.
* README and readme.txt rewritten to describe only what ships.
* Added CHANGELOG.md and `.distignore`.

= 0.6115 =
* Earlier release. The 0.6115 changelog claimed several features (tabbed UI, ROI report, optimize-on-upload, background auto-optimize, EXIF stripping, UTM tagging, search/pagination) that were never present in the code. Those claims have been retracted.

== Upgrade Notice ==

= 0.6123 =
Audit-batch release. Removes the broken theme-image feature, fixes a memory ceiling on the Tools page, adds per-attachment locking and WP-CLI commands. README aligned with what actually ships.
