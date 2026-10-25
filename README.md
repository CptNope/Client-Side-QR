# Anderson QR Code Generator

A WordPress plugin that makes styled QR codes in the browser, with no outside QR service, and checks each one scans before you print it.

Version: `4.3.0` · on WordPress.org as `anderson-qr-code-generator` (pending review)

## What it does

- **Scan check.** Every code is decoded right after it's drawn (bundled [jsQR](https://github.com/cozmo/jsQR)), along with contrast, inverted-color and transparency checks. Problems come with one-click fixes. Editors see the result on published codes; visitors only see a short warning when a code may not scan.
- **Short links you can change later** (`QR Codes > Short links`). Codes point to `yoursite.com/qr/<slug>`, which redirects (302) to the destination you set. Scans are counted on your own site; link previews and bots aren't.
- **A QR code for every page.** Turn it on per post type in settings, or per page from the editor's document panel.
- **Print-ready downloads.** Choose a printed size (2–20 cm or inches) and get a 300 dpi PNG, an SVG sized in cm/in, or a one-page PDF, with an estimate of the scanning distance.
- **Logos from the Media Library.** Adding a logo sets error correction to the highest level and caps the logo size so the code still scans.
- **Saved styles** (`QR Codes > Saved styles`) for use on any block or shortcode.
- **Two blocks:**
  - `csqr/code` (**QR code**): a fixed code, with variations for this page, guest WiFi (network and password shown as text too), a contact card filled in from your profile, a review link and short links.
  - `csqr/generator` (**QR code generator**): a form where visitors make codes for links (with campaign tags), WiFi, contacts, email, text messages, crypto and PayPal.
- Theme-friendly by default: the interface uses your theme's font and colors, and the code always sits on white paper so it scans on dark themes.

## Plugin structure

- `client-side-qr.php` – bootstrap, settings, asset registration, the generator block and `[client_side_qr]`
- `includes/payloads.php` – QR content builders (WiFi and vCard escaping) and the shared code card
- `includes/code-block.php` – the QR code block and `[qr_code]`
- `includes/links.php` – short links (post type `csqr_link`, `/qr/<slug>` redirect and scan count)
- `includes/page-qr.php` – the per-page QR code
- `includes/styles.php` – saved styles
- `includes/admin.php` – the QR Codes menu, settings page and shortcode builder
- `assets/qr-core.js` – rendering, scan check, exports (PNG with dpi, SVG, PDF)
- `assets/qr-ui.js` – scan-check status, print-size picker and code cards
- `assets/qr-script.js` – the visitor generator
- `assets/qr-editor.js`, `qr-code-block.js`, `qr-block.js`, `qr-editor-panel.js` – block editor
- `assets/qr-admin.js`, `qr-admin.css` – admin screens
- `assets/vendor/` – qr-code-styling 1.5.0 (MIT) and jsQR 1.4.0 (Apache-2.0)
- `tests/e2e/` – browser tests (see below)

## Installation

1. Copy this project into `wp-content/plugins/`, or install the zip from `tools/build-wporg.sh`.
2. Activate the plugin.
3. Set your default design under `QR Codes > Settings`.

## Shortcodes

```shortcode
[qr_code]                                   QR code for the current page
[qr_code url="https://example.com" caption="Scan for the menu" downloads="true"]
[qr_code link="menu"]                       a short link, by its slug
[client_side_qr]                            the visitor generator
[client_side_qr style="brand" enableCrypto="false" showTitle="false"]
```

`QR Codes > Shortcode builder` writes these for you. `style="id"` uses a saved style. Attribute names are case-insensitive.

### Generator attributes

| Attribute | Type | Default | Description |
| --- | --- | --- | --- |
| `title` | string | `Make a QR code` | Form heading |
| `showTitle` | bool | `true` | Show the heading (screen readers hear it either way) |
| `style` | string | empty | Saved style id |
| `qrColorDark` / `qrColorDark2` / `qrColorLight` | string | `#111111` / `#111111` / `#ffffff` | Code colors (second dark color for gradients) |
| `qrGradient` | bool | `false` | Gradient foreground |
| `qrDotStyle`, `qrEyeStyle`, `qrEyeColor` | string | `square`, `square`, empty | Shapes and corner color |
| `qrCorrectLevel` | string | `H` | `L`, `M`, `Q` or `H` |
| `qrSize` | int | `256` | On-screen size in pixels |
| `logoUrl` | string | empty | Logo image |
| `allowUserColor`, `allowUserSize`, `allowUserCorrectLevel` | bool | `false` | What visitors may change |
| `enableUrl`, `enableWifi`, `enableVcard`, `enableEmail`, `enableSms`, `enableCrypto`, `enablePaypal` | bool | `true` | Which kinds of code are offered |
| `uiUseThemeColors`, `uiUseThemeFont` | bool | `true` | Inherit theme colors and font |
| `uiSurfaceColor`, `uiTextColor`, `uiAccentColor`, `uiFontFamily` | string | empty | Overrides when not inheriting |

## Accessibility

- Kinds of code are a keyboard tablist (arrow keys, Home, End) that scrolls sideways on phones.
- Every field has a visible label; the scan check is a polite live region; codes are labelled with what they open.
- Touch targets are at least 44 px; focus is always visible; motion respects reduced-motion settings.

## Tests

```bash
WP="wp --path=/path/to/site" bash tests/e2e/setup.sh /tmp/fixtures.json
CSQR_FIXTURES=/tmp/fixtures.json node tests/e2e/run.cjs http://localhost:8080 admin password
```

The setup script creates test content; the runner (Playwright) decodes the rendered codes, checks downloads, short-link redirects and scan counts, the admin screens and the block editor. CI runs it on every pull request.

## Updates

Version 4.2.0 removed the optional GitHub release notices. Once the plugin is on WordPress.org, updates arrive through **Dashboard → Updates** like any other plugin. Sites that ran an earlier GitHub build have the old scheduled check and its cache cleaned up automatically.

## Building the WordPress.org zip

```bash
bash tools/build-wporg.sh            # → build/anderson-qr-code-generator.zip
```

It packages the current commit, leaving out design sources, tools and the roadmap (see `.gitattributes`), and checks that the three version numbers agree. CI runs WordPress's Plugin Check on the same build for every pull request.

## Architecture notes

- The plugin bundles `qr-code-styling` locally instead of loading it from a CDN.
- The block is dynamic and rendered through PHP for consistent frontend output.
- Translations come from translate.wordpress.org once the plugin is listed; `languages/anderson-qr-code-generator.pot` is the template.
- The frontend shell now inherits theme typography and text color more naturally by default for both block and shortcode output.
- The code includes filters for defaults and instance settings so future add-ons can extend behavior without rewriting core free-plugin logic.

## WordPress.org asset notes

The repository includes a `.wordpress-org/` scaffold for directory assets that are separate from runtime plugin files.

Recommended filenames:

- `banner-772x250.png`
- `banner-1544x500.png`
- `icon-128x128.png`
- `icon-256x256.png`
- `screenshot-1.png`
- `screenshot-2.png`
- `screenshot-3.png`
- `screenshot-4.png`

## Third-party library attribution

This plugin bundles `qr-code-styling` by Denys Kozak.

- Package: `qr-code-styling`
- Version bundled: `1.5.0`
- License: MIT
- Source: <https://github.com/kozakdenys/qr-code-styling>

See `THIRD_PARTY_NOTICES.md` for attribution details. The plugin zip carries the license as `assets/vendor/qr-code-styling-LICENSE.txt`. The bundled file is byte-for-byte the published 1.5.0 build (SHA-256 `9ffc5c7b…79254`, same as `https://unpkg.com/qr-code-styling@1.5.0/lib/qr-code-styling.js`).

## License

The plugin code in this repository is licensed under `GPL-2.0-or-later`.

Bundled third-party dependencies may use their own compatible licenses. Attribution for those dependencies is preserved in `THIRD_PARTY_NOTICES.md`.

## Changelog

See `CHANGELOG.md`.
