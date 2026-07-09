# WebP Support

[![CI](https://github.com/thisismyurl/thisismyurl-webp-support/actions/workflows/ci.yml/badge.svg)](https://github.com/thisismyurl/thisismyurl-webp-support/actions) [![WordPress](https://img.shields.io/badge/WordPress-6.0%2B-blue.svg)](https://wordpress.org/) [![License](https://img.shields.io/badge/License-GPL--2.0--or--later-green.svg)](LICENSE)

WebP Support lets WordPress accept WebP uploads and converts your existing JPEG and PNG images to WebP, keeping the originals so you can restore any image later.

It is part of the same image-plugin family as BMP, HEIC, and SVG Support, and shares the same admin shell, so the settings screens work the same way across all of them.

## What it does

- Accepts `.webp` uploads, with the file type checked against its real MIME signature rather than the extension
- Converts your existing JPEG and PNG library to WebP in bulk
- Keeps every original file and lets you restore images one at a time
- Converts incoming images at upload time, so new uploads land as WebP without a separate step
- Runs large libraries in the background, using either WP-Cron or wp-admin traffic to work through the queue
- Reports the file size savings across time windows so you can see what the conversion recovered
- Takes a safety snapshot before any destructive operation when a compatible backup plugin (Vault or Shadow) is active

## Requirements

- WordPress 6.0+
- PHP 7.4+
- GD or Imagick. GD handles most conversions; Imagick is needed for the metadata features.

## Installation

1. Upload the plugin to `/wp-content/plugins/thisismyurl-webp-support/`.
2. Activate it through the Plugins screen.
3. Go to **Tools > WebP Support** to configure your conversion settings.

## Versioning

Versions follow `X.Yjjj.hhmm` — year, Julian day, 24-hour time of the build.

## About

WebP Support is built and maintained by [Christopher Ross](https://thisismyurl.com/). I build focused WordPress tools for problems that keep showing up across real sites. No tracking, no ads, no upsells.

**WordPress.org:** [profiles.wordpress.org/thisismyurl](https://profiles.wordpress.org/thisismyurl/) · **GitHub:** [github.com/thisismyurl](https://github.com/thisismyurl) · **LinkedIn:** [linkedin.com/in/thisismyurl](https://linkedin.com/in/thisismyurl)

## License

GPL-2.0-or-later. See [LICENSE](LICENSE).
