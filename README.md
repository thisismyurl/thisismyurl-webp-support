# WEBP Support by thisismyurl.com

Current version: 1.26112

WEBP Support by thisismyurl.com is a WordPress plugin that converts supported media attachments to WebP in a non-destructive way.

## Features

- Bulk conversion from a single admin screen
- Batch-based AJAX processing for better control on shared hosting
- User-configurable WebP quality (0-100)
- User-configurable batch size (1-100)
- User-configurable format toggles (JPG/JPEG, PNG, GIF, BMP)
- Automatic backup of original files before conversion
- One-click per-image restore
- Bulk restore for all plugin-managed images
- Regeneration of WordPress attachment metadata after conversion/restore
- Optional backup deletion on uninstall
- Managed and pending image lists with excluded-format visibility

## Requirements

- WordPress 6.0+
- PHP 7.4+
- WordPress image editor support (GD or Imagick)

## Installation

1. Copy this plugin into your plugins directory as thisismyurl-webp-support.
2. Activate WEBP Support by thisismyurl.com in the WordPress admin.
3. Open Tools > WebP Support.
4. Configure quality, batch size, enabled source formats, and uninstall behavior.
5. Start optimization.

## How Backup and Restore Works

- On conversion, the original image is moved to uploads/webp-backups/.
- The attachment is updated to use the generated .webp file.
- Restoring moves the original file back and restores attachment mime/file metadata.
- Attachment metadata is regenerated whenever a file is converted or restored.

## User Controls

- WebP quality control for output quality/size balancing
- Batch size control for server resource pacing
- Per-format conversion controls for JPG/JPEG, PNG, GIF, BMP
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

This plugin uses the format 1.Yddd:

- Y = last two digits of the year
- ddd = Julian day of year

For April 22, 2026 this is 1.26112.

## License

GPL-2.0-or-later
