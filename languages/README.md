# Translation Files

This directory stores the translation template for the `anderson-qr-code-generator` text domain.

- `anderson-qr-code-generator.pot` — generated from the plugin's PHP and JavaScript source.

Regenerate it after changing translatable strings, with WP-CLI (`wp i18n make-pot . languages/anderson-qr-code-generator.pot --exclude=dist,tools,.wordpress-org`) or `node tools/generate-pot.js`.

Once the plugin is on WordPress.org, translations are made at translate.wordpress.org and WordPress loads them automatically.
