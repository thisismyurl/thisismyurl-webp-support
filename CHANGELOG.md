# Changelog

All notable changes to **WEBP Support by thisismyurl.com** are documented here.

The version scheme is `x.Yddd` — `x` = release class (`0` = pre-release, `1` = full),
`Y` = last digit of the year, `ddd` = Julian day. So `0.6123` = 2026 Julian day 123.

## 0.6123 — 2026-05-03

Audit-batch release closing every open `audit-finding` issue from the 2026-05-03 cross-plugin audit.

### Removed

- **Theme-image conversion feature.** The `Convert All Theme Images` action wrote
  to `wp-content/themes/` at runtime, which fails on managed hosts (WP Engine,
  Pantheon, Kinsta) where the themes directory is read-only outside deploy. The
  AJAX handler `wp_ajax_timu_wsconvert_theme_batch` was also registered without a
  method body, so any click became a fatal `Call to undefined method`. Both the
  feature and the dead handler are gone. The Media Library WebP path — the
  actual value — is unchanged.

### Fixed

- `posts_per_page => -1` in `get_media_lists()` replaced with a paged
  `fields => 'ids'` walk so libraries with thousands of attachments no longer
  risk hitting the PHP memory limit on the Tools page.
- Race-condition lock implemented on bulk-batch entry points using the
  `LOCK_PREFIX` constant the codebase had already declared. Two operators (or
  two browser tabs) can no longer double-process the same attachment.
- `_webp_original_path` now stores an uploads-relative path so dev↔prod database
  copies and host migrations do not orphan backups. A backwards-compatible
  reader honours legacy absolute-path values.
- `init_fs()` now falls back to direct PHP file operations when the WP
  Filesystem API needs FTP credentials. The plugin is admin-`manage_options`
  gated, so the direct path is acceptable in this context.

### Added

- WP-CLI commands:
  - `wp webp convert <id|--all>` — convert one attachment or every pending one.
  - `wp webp restore <id|--all>` — restore originals from backup.
  - `wp webp status` — report pending / managed / restorable counts.
- `CHANGELOG.md` (this file — `SECURITY.md` already linked to it).
- `.distignore` so the .org zip ships only the runtime code.

### Documentation

- `README.md` and `readme.txt` rewritten to describe only what ships. Tabbed
  UI, ROI report, optimize-on-upload, background auto-optimize, EXIF strip,
  UTM tagging, render-time URL swap, search/pagination — never existed in the
  code, no longer claimed in the marketing.
- `Tested up to: 6.9`, `Requires PHP: 8.1`.

### CI

- Removed `|| true` from PHPCS and the syntax-check pipelines so future
  regressions actually fail the build.
