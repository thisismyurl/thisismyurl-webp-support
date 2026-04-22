=== WEBP Support by thisismyurl.com ===
Contributors: thisismyurl
Donate link: https://thisismyurl.com/donate/
Tags: webp, images, media, optimization, compression
Requires at least: 6.0
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 1.26112
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Non-destructive WebP conversion for Media Library images with safe backups and one-click restore.

== Description ==

WEBP Support by thisismyurl.com converts supported image attachments (JPEG, PNG, GIF, BMP) into WebP while preserving originals in a backup directory under uploads.

Key features:

* Bulk optimization from a single admin screen.
* Configurable batch size to control server load.
* Configurable WebP quality setting.
* Per-format conversion toggles (JPG/JPEG, PNG, GIF, BMP).
* Non-destructive conversion with automatic backups.
* One-click single restore and bulk restore.
* Status visibility for missing files and managed media.
* Regenerates attachment metadata after conversion and restore.
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

= 1.26112 =
* Added full settings controls for quality, batch size, mime-type toggles, and uninstall behavior.
* Replaced inline JavaScript with WordPress-enqueued admin assets.
* Added batched AJAX processing for more stable large-library optimization.
* Switched conversion flow to WordPress image editor stack and regenerate metadata on file changes.
* Improved managed media statuses for excluded formats.

== Upgrade Notice ==

= 1.26112 =
Submission-ready release with standards and security improvements.
