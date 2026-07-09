=== - WEBP Support by Christopher Ross ===
Contributors: thisismyurl
Donate link: https://github.com/sponsors/thisismyurl
Tags: webp, avif, images, media, optimization
Requires at least: 6.0
Tested up to: 6.8
Requires PHP: 8.1
Stable tag: 1.6190.1650
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Non-destructive WebP/AVIF conversion for Media Library images with safe backups, one-click restore, automatic optimization, and an ROI report.

== Description ==

WEBP Support by thisismyurl.com converts supported Media Library attachments (JPEG, PNG, GIF) to WebP or AVIF using the WordPress image editor stack (GD or Imagick). Originals are preserved in a backup directory under `uploads/webp-backups/` and can be restored at any time, individually or in bulk.

The Tools > WebP Support screen is organised into three tabs:

* **Optimize** — the conversion dashboard, the pending-images list with file sizes, and the managed-media list with per-image savings.
* **Settings** — output format, quality preset, batch size, per-format toggles, optimize-on-upload, background auto-optimize, report assumptions, outbound UTM, and uninstall behaviour.
* **Report** — a business ROI table across Last 30 Days, Last 90 Days, Last 12 Months, and All Time windows.

What this plugin ships:

* Tabbed Optimize / Settings / Report admin screen under Tools > WebP Support.
* Output format: WebP (default), AVIF (Imagick + libheif required), or Both (AVIF primary + WebP companion served via a `<picture>` element).
* Quality preset: Web (82), Print (95), Lossless (100), or Custom.
* Per-format conversion toggles for JPG/JPEG, PNG, and GIF.
* Non-destructive batch optimization with a progress bar, savings display, cancel, and per-table search and pagination.
* Optimize-on-upload: convert new uploads automatically right after they land.
* Background auto-optimize: process pending images during wp-admin visits, in WP-Cron, or both, on a configurable interval and batch size.
* Business ROI report estimating bandwidth saved and the bandwidth cost avoided, from assumptions you set.
* Single Restore button per managed image and a Restore All bulk action.
* Status flags for missing files and items excluded by current settings.
* Per-attachment lock so two operators or two browser tabs cannot race the same file.
* Backup paths stored relative to the uploads directory so dev/prod database copies survive migration.
* Attachment metadata regenerated after each conversion or restoration.
* Optional backup-folder cleanup on uninstall.
* WP-CLI commands: `wp webp convert`, `wp webp restore`, `wp webp status`.
* WordPress 7.0 Abilities API support for the convert and restore operations.
* Localization support: French (Canada) translation included.

How it works:

1. Go to Tools > WebP Support.
2. On the Settings tab, choose output format, quality preset, batch size, enabled formats, and optimization behaviour.
3. On the Optimize tab, click "Optimize All" to process pending attachments in AJAX batches, or let auto-optimize work in the background.
4. Use Restore on individual rows, or "Restore All Originals" for a bulk rollback.
5. Open the Report tab to see bandwidth saved and estimated ROI.

Notes:

* Uses the WordPress image editor stack (GD or Imagick). No external services or phone-home.
* AVIF output requires Imagick built with libheif; without it, AVIF and Both are unavailable and the plugin falls back to WebP.
* Existing WebP and AVIF attachments are flagged as externally managed and never overwritten.
* Outbound UTM parameters are static and privacy-safe: they identify only this plugin as the traffic source and carry no site, account, user, visitor, or domain data.

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/`.
2. Activate the plugin through the Plugins screen.
3. Go to Tools > WebP Support.
4. Configure and run optimization.

== Frequently Asked Questions ==

= Does this delete original images? =
No. Originals are moved to `uploads/webp-backups/` and can be restored.

= What image types are supported? =
JPEG, PNG, and GIF as conversion sources.

= Can images be converted automatically? =
Yes. Enable optimize-on-upload to convert new uploads as they arrive, and enable background auto-optimize (admin traffic, WP-Cron, or both) to work through the existing library.

= Can I control performance and quality? =
Yes. Quality preset, custom quality, and AJAX batch size are configurable in Tools > WebP Support.

= Can I choose which image types are converted? =
Yes. Per-format toggles for JPG/JPEG, PNG, and GIF.

= Does this require Imagick? =
No for WebP — it uses WordPress image editors and works with either GD or Imagick. AVIF output does require Imagick built with libheif.

= What is the Report tab? =
A business ROI estimate. It counts images optimized in the selected window, totals the bytes saved, and projects monthly and annual bandwidth-cost savings from the monthly-requests and cost-per-GB assumptions you set on the Settings tab.

= Is there a WP-CLI interface? =
Yes. `wp webp convert <id|--all>`, `wp webp restore <id|--all>`, `wp webp status`.

== Languages ==

* French (Canada) — Christopher Ross

== Changelog ==

= 1.6190.1610 =
* **New:** Vortops cloud conversion section in Settings — when AVIF output is selected but the server lacks Imagick with libheif, connecting a Vortops account enables cloud AVIF conversion. Local conversion is always preferred. Zero-pressure offering.
* **New:** Test connection button for Vortops API key.
* **Fix:** Version constant `TIMU_WEBP_VERSION` was out of sync with the plugin header (stuck at 1.6165.0822); corrected to match the current version.

= 1.6190.1540 =
* **Hygiene:** UX consistency pass — "Donate" links updated to "Sponsor" to match GitHub Sponsors; sentence-case applied to changelog date ranges.

= 1.6165.0822 =
* New tabbed Optimize / Settings / Report admin screen, matching the rest of the thisismyurl.com image-plugin family.
* Added optimize-on-upload and background auto-optimize (admin-tick and WP-Cron) with a configurable interval and per-run batch size.
* Added the Business ROI report: bandwidth saved and estimated monthly/annual savings across 30-day, 90-day, 12-month, and all-time windows.
* Added Items-Per-Page, report-assumption, and outbound-UTM settings; conversion writes a converted-at timestamp so the report can window by date.
* Wired the shared backup adapter into the batch, auto-optimize, and per-file convert/restore paths as an extra safety snapshot.
* Dropped BMP from the source-format set; JPEG, PNG, and GIF are the supported sources.
* Uninstall now also removes the converted-at meta and environment-status option and clears the auto-optimize cron event.

= 1.6151 =
* Uninstall: the companion-path meta (`_webp_companion_path`) written for "Both" (AVIF + WebP) attachments is now removed on uninstall, alongside the original-path and savings meta, so no orphan post meta survives.

= 1.6150 =
* Accessibility (WCAG 2.2 AA): the optimization progress bar now exposes `role="progressbar"` with `aria-valuenow/min/max` and an accessible label, and the admin script keeps `aria-valuenow` in sync with the visual width.
* Accessibility: added a polite `role="status"` live region so screen readers announce batch progress, results, and completion.
* Accessibility: the "File Missing" status now carries a non-colour cue (warning icon and bold text) instead of signalling state by red colour alone.
* Accessibility: the Output Format and Quality Preset radio groups are wrapped in `<fieldset>` with a screen-reader `<legend>` for a programmatic group name.
* Accessibility: the custom-quality field is hidden from assistive technology and disabled when the Custom preset is not selected, and restored when it is.

= 1.6149 =
* Added WordPress 7.0 Abilities API support: `thisismyurl-webp-support/convert` (batch conversion) and `thisismyurl-webp-support/restore` (restore originals from backups), both guarded by the `manage_options` capability.

= 1.6147 =
* Unified plugin versioning to the x.Yddd calendar-version scheme.
* Confirmed compatibility with WordPress 7.0.


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
* Bumped Tested up to 6.8, Requires PHP 8.1.
* README and readme.txt rewritten to describe only what ships.
* Added CHANGELOG.md and `.distignore`.

== Upgrade Notice ==

= 1.6160 =
Adds the tabbed Optimize/Settings/Report admin screen, automatic optimization (on-upload, admin-tick, and WP-Cron), and a business ROI report. Drops BMP from the source-format set.
