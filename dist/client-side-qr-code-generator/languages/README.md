# Translation Files

This directory stores translation assets for the `csqr` text domain.

Included files:

- `csqr.pot` - translation template generated from the plugin's PHP and JavaScript source

Typical workflow:

1. Update translatable strings in plugin code.
2. Regenerate `csqr.pot`.
3. Create locale-specific `.po` and `.mo` files as needed.

The plugin loads translations from this directory using the `csqr` text domain.
