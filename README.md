# WEBP Support by This Is My URL

Current version: 1.6365

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

This plugin uses the format 1.Yddd:

- Y = last digit of the year
- ddd = Julian day number for the final day of that year

For 2026, this is 1.6365.

## License

GPL-2.0-or-later

---

## Support and Contribute

### Ways to Support

I'm building these tools because WordPress developers and site owners deserve straightforward, practical solutions. There's no tracking, no ads, and you don't need to pay to use these plugins.

If you find them helpful, here are some genuine ways to support the work:

- **Sponsor if it fits your budget:** You can sponsor the project through [GitHub Sponsors](https://github.com/sponsors/thisismyurl). Sponsorship helps, but it's always optional.
- **Contribute code or ideas:** Opening a pull request, reporting an issue, or testing edge cases is just as valuable as sponsorship. Helping me improve these plugins is a great way to contribute.
- **Share your experience:** A review on [my Google My Business profile]([Add your Google Business Profile URL here]) or a follow on [WordPress.org](https://profiles.wordpress.org/thisismyurl/), [GitHub](https://github.com/thisismyurl), or [LinkedIn](https://linkedin.com/in/thisismyurl) helps others find this work.

### Report Issues and Questions

Found a bug? Want to suggest a feature? Just curious how something works?

- **File an issue:** Use the [Issues](../../issues) tab. Include your WordPress and PHP version, and steps to reproduce.
- **Start a discussion:** Use the [Discussions](../../discussions) tab for questions, ideas, or general conversation about the plugin.

### Contributing Code

Code contributions are welcome and genuinely valuable. Here's the workflow:

1. **Fork this repository** and clone it locally.
2. **Create a feature branch** with a clear name (e.g., `feature/improve-safety-check`).
3. **Make your changes** and test thoroughly on edge cases.
4. **Follow WordPress coding standards** — run `composer run lint:phpcs` before opening a PR.
5. **Open a pull request** with a clear description of what changed and why.

I review PRs thoughtfully and appreciate well-tested contributions. Contributing is never required, but it's genuinely helpful.

---


## About This Is My URL

This plugin supports the work I do at [This Is My URL](https://thisismyurl.com/wordpress-website-development/), where I help WordPress teams build secure, performant, and maintainable sites.

This plugin is built and maintained by [This Is My URL](https://thisismyurl.com/), a WordPress development and technical SEO practice. I'm Christopher Ross, a WordPress developer and technical SEO specialist with 25+ years of experience in software development, training, and digital learning.

### My Background

- **25+ years** in software development, technical training, and digital systems design
- **WordPress contributor since 2007** with a strong track record helping organizations build practical, maintainable web systems
- **Technical SEO practitioner** helping sites improve performance, security, and search visibility
- **Training specialist** focused on practical outcomes and helping teams adopt technology with confidence

I believe in straightforward solutions that work. No hype. No unnecessary complexity.

### Ways to Connect

- **WordPress.org profile:** [profiles.wordpress.org/thisismyurl](https://profiles.wordpress.org/thisismyurl/)
- **GitHub:** [github.com/thisismyurl](https://github.com/thisismyurl)
- **Website:** [thisismyurl.com](https://thisismyurl.com/)
- **LinkedIn:** [linkedin.com/in/thisismyurl](https://linkedin.com/in/thisismyurl)


## License

GPL-2.0-or-later — see [LICENSE](LICENSE) or [gnu.org/licenses/gpl-2.0.html](https://www.gnu.org/licenses/gpl-2.0.html).
