# WEBP Support by This Is My URL

Current version: 0.6115

WEBP Support by This Is My URL is a WordPress plugin that converts supported media attachments to WebP in a non-destructive way.

## Features

- Tabbed admin UI (Optimize, Settings, Report)
- Live search and pagination in pending and managed media tables
- Batch AJAX conversion with visible spinner and continuous progress updates
- Automatic backup of originals before conversion with one-click restore
- Optional optimize-on-upload for supported file types
- Optional background auto-optimize via wp-admin traffic and/or WP-Cron
- Configurable auto-optimize interval and per-run batch size
- Output filter to serve .webp URLs at render time without DB writes
- Optional EXIF/GPS/device metadata stripping from converted WebP files
- Optional embedded XMP metadata including creator tag for This Is My URL
- ROI report with 30d/90d/12mo/all-time windows and business assumptions
- Privacy-safe optional outbound UTM parameters for thisismyurl links
- Activation checks for GD/Imagick image engine availability

## Requirements

- WordPress 6.0+
- PHP 7.4+
- WordPress image editor support (GD or Imagick)

## Installation

1. Copy this plugin into your plugins directory as thisismyurl-webp-support.
2. Activate WEBP Support by This Is My URL in the WordPress admin.
3. Open Tools > WebP Support.
4. Configure quality, automation, metadata, and reporting assumptions.
5. Start optimization.

## How Backup and Restore Works

- On conversion, the original image is moved to uploads/webp-backups/.
- The attachment is updated to use the generated .webp file.
- Restoring moves the original file back and restores attachment mime/file metadata.
- Attachment metadata is regenerated whenever a file is converted or restored.

## User Controls

- WebP quality and manual batch-size controls
- Source-format toggles for JPG/JPEG, PNG, GIF, BMP
- Optimize-on-upload and auto-optimize controls (admin and cron)
- Auto-optimize interval and run-size controls
- Metadata privacy controls (strip harmful metadata, embed site metadata)
- Output filter toggle for render-time URL swapping
- Reporting assumptions for monthly image hits and bandwidth cost
- Outbound UTM toggle for thisismyurl links
- Uninstall cleanup control for backup retention

## Security and Standards

- Direct access protection with ABSPATH checks
- Nonce checks for AJAX actions
- Capability checks for admin operations
- Escaping and sanitization aligned with WordPress coding standards
- Settings API usage with sanitization callbacks
- Admin scripts enqueued with WordPress script APIs
- WordPress.org-compatible plugin headers and packaging

## Versioning

This plugin uses the format x.Yddd:

- x = release class (`0` pre-release, `1` full release)
- Y = last digit of the year
- ddd = Julian day number for the release date

For April 25, 2026, the pre-release version is 0.6115.

## License

GPL-2.0-or-later
