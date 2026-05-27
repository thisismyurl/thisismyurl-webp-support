# This Is My URL - WEBP Support

[![CI](https://github.com/thisismyurl/thisismyurl-webp-support/actions/workflows/ci.yml/badge.svg)](https://github.com/thisismyurl/thisismyurl-webp-support/actions/workflows/ci.yml) [![WordPress](https://img.shields.io/badge/WordPress-6.0%2B-blue)](https://wordpress.org/) [![License](https://img.shields.io/badge/License-GPL--2.0-blue)](LICENSE)

A WordPress plugin that converts Media Library attachments (JPEG, PNG, GIF, BMP) to WebP or AVIF non-destructively, with safe backups and one-click restore. Built for site owners who want modern image formats without giving up the ability to roll back.

> **Part of the This Is My URL image toolkit:** [Image Support](https://github.com/thisismyurl/thisismyurl-image-support) for library-wide filename cleanup, content-reference syncing, photo credits, and alt text; [WebP Support](https://github.com/thisismyurl/thisismyurl-webp-support) and [HEIC Support](https://github.com/thisismyurl/thisismyurl-heic-support) for focused format conversion; and [SVG Support](https://github.com/thisismyurl/thisismyurl-svg-support) for safe SVG uploads. Reach for a focused plugin if you only need that format; use Image Support for library-wide work.

## What it does

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

## What it doesn't do

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

## How backup and restore works

- On conversion the original is moved to `uploads/webp-backups/<original-subdir>/`.
- The attachment record is updated to point at the new `.webp` file.
- Backup paths are stored relative to `uploads/basedir/` so dev↔prod database copies survive migration. Legacy absolute paths from earlier versions are still understood on read.
- Restoring moves the original back, deletes the WebP, and regenerates metadata.

## Security and standards

- Direct-access protection (`ABSPATH` check).
- Nonce verification on every AJAX action.
- `current_user_can( 'manage_options' )` capability checks on every admin / AJAX handler.
- Output escaping and input sanitization aligned with WordPress Coding Standards.
- Settings API used with a sanitization callback.
- No external services. No phone-home. No telemetry.

## Versioning

This plugin uses the format `x.Yddd`:

- `x` = release class (`0` = pre-release, `1` = full release)
- `Y` = last digit of the year
- `ddd` = Julian day of the release

`0.6123` = pre-release built on Julian day 123 of 2026 (May 3).

## Changelog

See [releases](../../releases) or [readme.txt](readme.txt).

---

## Support and donations

I build these tools because WordPress sites in the wild keep hitting the same problems, and a small, focused plugin is usually the right fix. They're free to use, with no tracking and no ads.

If one of them saves you time, here are the genuine ways to help:

- **Sponsor the work.** [GitHub Sponsors](https://github.com/sponsors/thisismyurl) is the simplest way, and the Sponsor button at the top of this repo lists it alongside Bitcoin, Dogecoin, PayPal, and Interac e-transfer. Any amount helps, and none of it is expected.
- **Contribute code or ideas.** A pull request, a bug report, or a tested edge case is worth as much as a donation. See [CONTRIBUTING.md](CONTRIBUTING.md) to get started.
- **Share it.** A note on [WordPress.org](https://profiles.wordpress.org/thisismyurl/), [GitHub](https://github.com/thisismyurl), or [LinkedIn](https://linkedin.com/in/thisismyurl) helps other people find work that might save them the same afternoon.

### Report issues and questions

- **Found a bug or want a feature?** Open an issue on the [Issues](../../issues) tab. Include your WordPress and PHP versions and the steps to reproduce it.
- **Have a question?** Start a thread on the [Discussions](../../discussions) tab.

### Contributing code

Code contributions are welcome. The short version:

1. Fork the repository and clone your fork.
2. Create a branch with a clear name, like `feature/short-descriptive-name`.
3. Make your change and test it against the edge cases.
4. Run the coding-standards check before you open the pull request.
5. Open a pull request that explains what changed and why.

The full workflow and standards live in [CONTRIBUTING.md](CONTRIBUTING.md). Contributing is never required, but it is always appreciated.

## About This Is My URL

This plugin is built and maintained by [This Is My URL](https://thisismyurl.com/), the WordPress development and technical SEO practice of Christopher Ross. I help teams build WordPress sites that stay secure, fast, and maintainable, and I write small, focused plugins like this one for the problems those sites keep running into.

### My background

- On the web since 1996, and in WordPress since 2007
- WordPress.org plugin developer with 19 plugins published since 2009
- Technical SEO practitioner focused on performance, security, and search visibility
- Lead instructor and curriculum architect at the M.L. Campbell Training Center, the Sherwin-Williams® international training facility for its industrial wood division

### Ways to connect

- **Website:** [thisismyurl.com](https://thisismyurl.com/)
- **WordPress.org:** [profiles.wordpress.org/thisismyurl](https://profiles.wordpress.org/thisismyurl/)
- **GitHub:** [github.com/thisismyurl](https://github.com/thisismyurl)
- **LinkedIn:** [linkedin.com/in/thisismyurl](https://linkedin.com/in/thisismyurl)

## Contributors

- **Christopher Ross** ([@thisismyurl](https://github.com/thisismyurl)) — author and maintainer
- Thanks to everyone who has reported issues, tested edge cases, and contributed code

## License

GPL-2.0-or-later — see [LICENSE](LICENSE) or [gnu.org/licenses/gpl-2.0.html](https://www.gnu.org/licenses/gpl-2.0.html).

---
*This project follows the [10 Core Pillars](PILLARS.md). Support quality work [here](https://github.com/sponsors/thisismyurl).*
