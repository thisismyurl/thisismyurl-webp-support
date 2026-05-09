# WEBP Support by This Is My URL

[![CI](https://github.com/thisismyurl/thisismyurl-webp-support/actions/workflows/ci.yml/badge.svg)](https://github.com/thisismyurl/thisismyurl-webp-support/actions/workflows/ci.yml) [![WordPress Tested](https://img.shields.io/badge/WordPress-6.9%2B-blue)](https://wordpress.org/) [![License](https://img.shields.io/badge/License-GPL--2.0-blue)](LICENSE)

Current version: **0.6126**

A WordPress plugin that converts Media Library attachments (JPEG, PNG, GIF, BMP) to WebP or AVIF non-destructively, with safe backups and one-click restore. Built for site owners who want modern image formats without giving up the ability to roll back.

## What It Does

- Tools > WebP Support page with Optimize, Settings, Pending, and Managed Media sections.
- **Output Format** setting: WebP (default), AVIF (requires Imagick with libheif), or Both (AVIF primary + WebP companion via `<picture>` for format negotiation).
- **Quality Preset** setting: Web (82), Print (95), Lossless (100), or Custom.
- Per-format conversion toggles for JPG/JPEG, PNG, GIF, and BMP.
- Non-destructive batch conversion with progress bar, savings stats, and a working Cancel.
- Pending table shows per-image file sizes; Managed Media table shows bytes saved.
- Single-image Restore plus a Restore All Originals bulk action.
- Per-attachment lock so two operators (or two browser tabs) cannot race the same file.
- Attachment metadata regenerated after each conversion or restoration.
- Optional backup-folder cleanup on uninstall.
- WP-CLI: `wp webp convert <id|--all>`, `wp webp restore <id|--all>`, `wp webp status`.
- French (Canada) translation included (`fr_CA`).

## What It Does Not Do

The 0.6115 README listed several features that were never in the code. Those claims have been retracted. This plugin does **not** ship:

- A tabbed admin UI or ROI report.
- Optimize-on-upload, background auto-optimize, or WP-Cron scheduling.
- EXIF / GPS / metadata stripping.
- Outbound UTM tagging.
- Theme-image conversion (removed in 0.6123 — incompatible with managed hosts).

If you need any of those, this is not the plugin for you. Open an issue if you'd like one of them added.

## Requirements

- WordPress 6.0 or later
- PHP 8.1 or later
- WordPress image editor support (GD or Imagick)

## Installation

1. Copy this plugin into your plugins directory as `thisismyurl-webp-support/`.
2. Activate **WEBP Support by This Is My URL** in `wp-admin > Plugins`.
3. Open `Tools > WebP Support`.
4. Configure quality, batch size, and enabled source formats.
5. Click **Optimize All** to start.

## How Backup and Restore Works

- On conversion the original is moved to `uploads/webp-backups/<original-subdir>/`.
- The attachment record is updated to point at the new `.webp` file.
- Backup paths are stored relative to `uploads/basedir/` so dev↔prod database copies survive migration. Legacy absolute paths from earlier versions are still understood on read.
- Restoring moves the original back, deletes the WebP, and regenerates metadata.

## Security and Standards

- Direct-access protection (`ABSPATH` check).
- Nonce verification on every AJAX action.
- `current_user_can( 'manage_options' )` capability checks on every admin / AJAX handler.
- Output escaping and input sanitization aligned with WordPress Coding Standards.
- Settings API used with a sanitization callback.
- No external services. No phone-home. No telemetry.

## Versioning

This plugin uses the format `x.Yddd`:

- `x` = release class (`0` pre-release, `1` full release)
- `Y` = last digit of the year
- `ddd` = Julian day of the release

`0.6123` = pre-release built on Julian day 123 of 2026 (May 3).

## Changelog

See [CHANGELOG.md](CHANGELOG.md).

## License

GPL-2.0-or-later. See [LICENSE](LICENSE).


---
*This project follows the [10 Core Pillars](PILLARS.md). Support quality work [here](https://github.com/sponsors/thisismyurl).*

