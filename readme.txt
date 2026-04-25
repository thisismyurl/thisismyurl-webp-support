=== WEBP Support by thisismyurl.com ===
Contributors: thisismyurl
Donate link: https://thisismyurl.com/donate/
Tags: webp, images, media, optimization, compression
Requires at least: 6.0
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 0.6115
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Non-destructive WebP conversion for Media Library images with safe backups and one-click restore.

== Description ==

WEBP Support by thisismyurl.com converts supported image attachments (JPEG, PNG, GIF, BMP) into WebP while preserving originals in a backup directory under uploads.

Key features:

* Tabbed admin experience with Optimize, Settings, and Report tabs.
* Configurable batch size to control server load.
* Configurable WebP quality setting.
* Per-format conversion toggles (JPG/JPEG, PNG, GIF, BMP).
* Non-destructive conversion with automatic backups.
* One-click single restore and bulk restore.
* Status visibility for missing files and managed media.
* Regenerates attachment metadata after conversion and restore.
* Search and pagination for long media lists.
* Optional optimize-on-upload and background auto optimization.
* Optional metadata strip/embed controls for privacy and attribution.
* Business-friendly ROI report metrics with date ranges.
* Optional privacy-safe UTM tags on thisismyurl outbound links.
* Optional backup deletion on uninstall.

How it works:

1. Go to Tools > WebP Support.
2. Configure quality, batch size, enabled source formats, and uninstall behavior.
3. Click Optimize All to process pending attachments in batches.
4. Use Restore on individual items, or Restore All Originals for bulk rollback.

Notes:

* This plugin uses the WordPress image editor stack (GD or Imagick).
* Existing WebP images are marked as externally managed and are not overwritten.

== Installation ==

1. Upload the plugin folder to /wp-content/plugins/.
2. Activate the plugin through the Plugins screen in WordPress.
3. Go to Tools > WebP Support.
4. Run optimization.

== Frequently Asked Questions ==

= Does this delete original images? =
No. Originals are moved to uploads/webp-backups/ and can be restored.

= What image types are supported? =
JPEG, PNG, GIF, and BMP are supported as conversion sources.

= Can I control performance and quality? =
Yes. You can set conversion quality and batch size in Tools > WebP Support.

= Can I choose which image types are converted? =
Yes. You can enable or disable conversion for JPG/JPEG, PNG, GIF, and BMP.

= Does this require Imagick? =
No. It uses WordPress image editors and supports environments with either GD or Imagick.

== Changelog ==

= 0.6115 =
* Added upload-time optimization and background auto optimization controls (admin access and WP-Cron).
* Added installation-time GD/Imagick capability checks and admin environment notices.
* Added report tab with business ROI metrics for 30d/90d/12mo/all-time windows.
* Added metadata controls for stripping sensitive metadata and embedding site XMP metadata.
* Added search and pagination controls to optimize and managed media tables.
* Added optional privacy-safe UTM tagging for links to thisismyurl.com.
* Improved optimize UX with active spinner and more frequent progress updates.

== Upgrade Notice ==

= 0.6115 =
Major feature release with automation, reporting, and improved admin UX.
