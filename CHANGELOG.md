# Changelog

All notable changes to **WEBP Support by thisismyurl.com** are documented here.

The version scheme is `x.Yddd` — `x` = release class (`0` = pre-release, `1` = full),
`Y` = last digit of the year, `ddd` = Julian day. So `0.6123` = 2026 Julian day 123.

## 1.6190.1540 — 2026-07-09

### Changed
- UX consistency pass: "Donate" links updated to "Sponsor" to match GitHub Sponsors; sentence-case applied to changelog date ranges.

## 1.6143 — 2026-05-23

### Changed
- Promoted to a full release (class 1). The `0.6xxx` line was pre-release on the `x.Yddd` scheme.
- Standardized the donation link to GitHub Sponsors (`https://github.com/sponsors/thisismyurl`).

## 0.6126 — 2026-05-06

### Added

- **Quality presets** (`#25`): replace the single quality integer with named profiles — Web (82), Print (95), Lossless (100), and Custom. The custom number input is shown/hidden dynamically in the admin UI.
- **Savings display** (`#23`): the Optimization Dashboard now shows pending file count and total size, plus cumulative bytes saved across already-converted images. Pending table gains a Size column; Managed Media table gains a Saved column.
- **AVIF output format** (`#14`, `#19`): new "Output Format" setting — WebP (default, backwards-compatible), AVIF (requires Imagick with libheif), or Both. "Both" produces an AVIF primary file plus a WebP companion and wraps `wp_get_attachment_image` output in a `<picture>` element for format negotiation. If AVIF is unavailable at the server level the option is disabled with an inline notice.
- **i18n** (`#2`): `load_plugin_textdomain()` wired on the `init` hook; `TIMU_WEBP_SUPPORT_DIR` and `TIMU_WEBP_SUPPORT_BASENAME` constants defined. French (Canada) `.po` translation committed to `languages/`. Run `msgfmt languages/thisismyurl-webp-support-fr_CA.po -o languages/thisismyurl-webp-support-fr_CA.mo` to compile the binary.

### Fixed

- `get_media_lists()` mime filter now includes `image/avif` alongside `image/webp`, so AVIF attachments appear in the Managed Media table after conversion.
- `$is_webp` variable renamed to `$is_converted` and updated to cover both `image/webp` and `image/avif`.
- `convert_to_webp()` retained as a backwards-compatible alias for external callers; internally delegates to `convert_image()`.

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
