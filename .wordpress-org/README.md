# WordPress.org Asset Structure

This folder is a staging location for WordPress.org directory assets when the plugin is mirrored from GitHub or prepared for SVN.

Recommended filenames:

- `banner-772x250.png`
- `banner-1544x500.png`
- `icon-128x128.png`
- `icon-256x256.png`
- `screenshot-1.png`
- `screenshot-2.png`
- `screenshot-3.png`
- `screenshot-4.png`

Suggested workflow:

1. Keep editable design files in `.wordpress-org/source/`.
2. Export the final PNG assets into `.wordpress-org/` using the exact filenames above.
3. Reuse the screenshot numbering from `readme.txt`.

Notes:

- WordPress.org banners and icons should be clean, legible, and consistent with the plugin name.
- Screenshots should reflect the current admin/editor/frontend UI.
- Do not package large source design files in the release ZIP if you want to keep distribution lean.
