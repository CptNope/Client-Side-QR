# Client-Side QR Code Generator

Client-Side QR Code Generator is a WordPress plugin for creating privacy-friendly QR experiences directly in the browser. It supports a Gutenberg block and shortcode, keeps QR rendering on the client side, and gives site owners flexible controls for links, campaigns, contact sharing, WiFi access, and payment flows.

Version: `4.1.4`

## Why this plugin

- Client-side QR generation with no external QR API required
- Useful for landing pages, printed campaigns, events, contact sharing, and on-site QR workflows
- Dynamic frontend interface with multiple payload types in one block or shortcode
- Built for WordPress sites that want styling controls without server-side image generation

## Features

- Gutenberg block: `csqr/generator`
- Shortcode: `[client_side_qr]`
- Bundled local copy of `qr-code-styling` for WordPress.org-friendlier packaging
- QR payload types:
  - URL / plain text
  - WiFi
  - vCard
  - Email
  - SMS
  - Crypto wallet
  - PayPal.me
- Built-in UTM builder for campaign QR links
- Styling controls for:
  - foreground color
  - optional foreground gradient
  - background color
  - dot style
  - corner eye style
  - corner eye color
  - logo image
  - size
  - error correction level
- Optional end-user controls for:
  - color changes
  - size changes
  - error correction changes
  - transparent background
- Export actions:
  - PNG download
  - SVG download
  - clipboard image copy where browser support is available
- Lightweight global settings page for defaults
- Classic-editor shortcode builder for admin-side shortcode configuration
- Theme-aware interface shell that inherits site colors and fonts by default
- Per-instance interface shell overrides in both the block editor and shortcode builder
- Optional opt-in GitHub release notices for self-managed installs
- Translation-ready strings with the `csqr` text domain

## Plugin structure

- `client-side-qr.php` - main plugin bootstrap, asset registration, settings page, block registration, shortcode rendering
- `assets/qr-script.js` - frontend QR logic and accessible tab behavior
- `assets/qr-block.js` - block editor controls and live preview
- `assets/qr-style.css` - frontend styles and accessibility-focused UI states
- `assets/vendor/qr-code-styling.js` - bundled QR rendering library
- `languages/csqr.pot` - translation template for the `csqr` text domain
- `readme.txt` - WordPress.org style plugin metadata
- `.wordpress-org/` - banner, icon, and screenshot scaffold for WordPress.org assets
- `THIRD_PARTY_NOTICES.md` - third-party attribution and compatibility notes
- `uninstall.php` - plugin cleanup for stored settings
- `CHANGELOG.md` - release history

## Installation

1. Copy this project into `wp-content/plugins/`.
2. Ensure the folder contains `client-side-qr.php` and the `assets/` directory.
3. Activate the plugin in `Plugins`.
4. Optionally set global defaults in `Settings > Client-Side QR`.
5. Use `Settings > QR Shortcode Builder` if you want to configure shortcode output visually for classic-editor workflows.

## Usage

### Gutenberg block

1. Open a post or page in the block editor.
2. Insert the `Client-Side QR Code` block.
3. Configure design defaults, payload types, and optional end-user controls.
4. Use the `Interface Shell` panel if you want the block to inherit the active theme or force custom shell colors and font-family values for this instance.
5. Publish the page.

### Shortcode

Basic example:

```shortcode
[client_side_qr]
```

Custom styling example:

```shortcode
[client_side_qr qrColorDark="#111111" qrColorLight="#ffffff" qrSize="320" qrDotStyle="rounded"]
```

Limited payload example:

```shortcode
[client_side_qr enableUrl="true" enableWifi="false" enableEmail="true" enableSms="false" enableVcard="false" enableCrypto="false" enablePaypal="false"]
```

Gradient and logo example:

```shortcode
[client_side_qr qrGradient="true" qrColorDark="#0f172a" qrColorDark2="#2563eb" logoUrl="https://example.com/logo.png"]
```

### Classic editor workflow

If you are using the classic editor or a classic theme workflow, you can use the shortcode builder at `Settings > QR Shortcode Builder` to generate and preview shortcode output without hand-writing attributes.

You can also control the surrounding interface shell separately from the QR itself by inheriting the active theme colors and font, or by supplying instance-level shell overrides when a page needs a specific surface, text, accent, or font treatment.

## Shortcode attributes

The shortcode remains backward compatible with the existing attribute names.

| Attribute | Type | Default | Description |
| --- | --- | --- | --- |
| `qrColorDark` | string | `#111111` | Primary foreground color |
| `qrColorDark2` | string | `#111111` | Secondary foreground color when gradients are enabled |
| `qrColorLight` | string | `#ffffff` | Background color |
| `uiUseThemeColors` | bool | `true` | Inherit the surrounding theme colors for the interface shell |
| `uiUseThemeFont` | bool | `true` | Inherit the surrounding theme font for the interface shell |
| `uiSurfaceColor` | string | empty | Optional shell background override |
| `uiTextColor` | string | empty | Optional shell text override |
| `uiAccentColor` | string | empty | Optional shell accent override |
| `uiFontFamily` | string | empty | Optional shell font-family override |
| `qrSize` | int | `256` | Default output size in pixels |
| `qrCorrectLevel` | string | `H` | Error correction level: `L`, `M`, `Q`, or `H` |
| `qrDotStyle` | string | `square` | Dot style |
| `qrEyeStyle` | string | `square` | Corner eye style |
| `qrEyeColor` | string | empty | Optional eye color override |
| `qrGradient` | bool | `false` | Enable a linear foreground gradient |
| `logoUrl` | string | empty | Logo image URL |
| `allowUserColor` | bool | `false` | Allow visitors to change colors |
| `allowUserSize` | bool | `false` | Allow visitors to change size |
| `allowUserCorrectLevel` | bool | `false` | Allow visitors to change error correction |
| `enableUrl` | bool | `true` | Enable the URL / text payload |
| `enableWifi` | bool | `true` | Enable the WiFi payload |
| `enableEmail` | bool | `true` | Enable the Email payload |
| `enableSms` | bool | `true` | Enable the SMS payload |
| `enableVcard` | bool | `true` | Enable the vCard payload |
| `enableCrypto` | bool | `true` | Enable the Crypto payload |
| `enablePaypal` | bool | `true` | Enable the PayPal payload |

## Accessibility notes

- Payload switching now uses keyboard-accessible tabs with proper `tablist`, `tab`, and `tabpanel` semantics.
- Form controls use visible labels instead of placeholder-only labeling.
- Status updates for QR generation and clipboard actions are announced through a live region.
- Focus states are styled for keyboard users.

## Settings page

The plugin includes a lightweight settings page at `Settings > Client-Side QR` for global defaults:

- default QR size
- default interface shell inheritance behavior
- default shell background, text, accent, and font overrides
- default foreground color
- default background color
- default error correction
- payload types enabled by default

These defaults apply to new instances and can still be overridden per block or shortcode.

## Shortcode builder

The plugin includes a shortcode builder screen at `Settings > QR Shortcode Builder`.

It can be used to:

- configure shortcode attributes visually
- generate copy/paste-ready shortcode output
- preview frontend rendering from wp-admin
- support classic-editor and shortcode-heavy workflows more comfortably

## Release notices

The plugin can optionally check GitHub for newer releases and show an admin notice when one is available.

- this feature is off by default
- it must be enabled by an administrator
- it is intended mainly for self-managed installs that track the GitHub repository directly

## Architecture notes

- The plugin bundles `qr-code-styling` locally instead of loading it from a CDN.
- The block is dynamic and rendered through PHP for consistent frontend output.
- The plugin loads translations from `languages/` and includes a generated `csqr.pot` file for translators.
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

See `THIRD_PARTY_NOTICES.md` for attribution details.

## License

The plugin code in this repository is licensed under `GPL-2.0-or-later`.

Bundled third-party dependencies may use their own compatible licenses. Attribution for those dependencies is preserved in `THIRD_PARTY_NOTICES.md`.

## Changelog

See `CHANGELOG.md`.
