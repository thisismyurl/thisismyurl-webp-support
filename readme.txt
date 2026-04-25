=== WEBP Support by thisismyurl.com ===
Contributors: thisismyurl
Donate link: https://thisismyurl.com/donate/
Tags: webp, images, media, optimization, compression
Requires at least: 6.0
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 0.6112
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

== Support, Contributing & Sponsorship ==

= I want to support you =

I'm building these tools because WordPress developers and site owners deserve straightforward, practical solutions. There's no tracking, no ads, and you don't need to pay to use these plugins.

If they're helpful, here are genuine ways to support the work:

* **Sponsor this project:** Visit https://github.com/sponsors/thisismyurl if sponsorship fits your budget. Sponsorship helps, but it's always optional.
* **Contribute code or ideas:** Opening a pull request, reporting an issue, or testing edge cases is just as valuable as sponsorship. Helping me improve these plugins is a great way to contribute.
* **Share your experience:** A review on my [Google My Business profile](https://business.google.com/refer) or a follow on [WordPress.org](https://profiles.wordpress.org/thisismyurl/), [GitHub](https://github.com/thisismyurl), or [LinkedIn](https://linkedin.com/in/thisismyurl) helps others find this work.

= I found a bug or have a feature idea =

* **File an issue on GitHub:** Visit https://github.com/thisismyurl/[plugin-name]/issues and include your WordPress and PHP version.
* **Start a discussion:** Use the Discussions tab on GitHub for questions or ideas.

= I want to contribute code =

Code contributions are welcome and genuinely valuable:

1. Fork the repository on GitHub.
2. Create a feature branch (e.g., `feature/improve-safety`).
3. Make your changes and test thoroughly.
4. Follow WordPress coding standards.
5. Open a pull request with a clear description of what changed and why.

I review PRs thoughtfully and appreciate well-tested contributions. Contributing is never required, but it's genuinely helpful.


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
