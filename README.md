# Client-Side QR Code Generator

Client-Side QR Code Generator is a WordPress plugin for creating privacy-friendly QR experiences directly in the browser. It supports a Gutenberg block and shortcode, keeps QR rendering on the client side, and gives site owners flexible controls for links, campaigns, contact sharing, WiFi access, and payment flows.

Version: `4.1.0`

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
- Translation-ready strings with the `csqr` text domain

## Plugin structure

- `client-side-qr.php` - main plugin bootstrap, asset registration, settings page, block registration, shortcode rendering
- `assets/qr-script.js` - frontend QR logic and accessible tab behavior
- `assets/qr-block.js` - block editor controls and live preview
- `assets/qr-style.css` - frontend styles and accessibility-focused UI states
- `assets/vendor/qr-code-styling.js` - bundled QR rendering library
- `readme.txt` - WordPress.org style plugin metadata
- `THIRD_PARTY_NOTICES.md` - third-party attribution and compatibility notes
- `uninstall.php` - plugin cleanup for stored settings
- `CHANGELOG.md` - release history

## Installation

1. Copy this project into `wp-content/plugins/`.
2. Ensure the folder contains `client-side-qr.php` and the `assets/` directory.
3. Activate the plugin in `Plugins`.
4. Optionally set global defaults in `Settings > Client-Side QR`.

## Usage

### Gutenberg block

1. Open a post or page in the block editor.
2. Insert the `Client-Side QR Code` block.
3. Configure design defaults, payload types, and optional end-user controls.
4. Publish the page.

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

## Shortcode attributes

The shortcode remains backward compatible with the existing attribute names.

| Attribute | Type | Default | Description |
| --- | --- | --- | --- |
| `qrColorDark` | string | `#111111` | Primary foreground color |
| `qrColorDark2` | string | `#111111` | Secondary foreground color when gradients are enabled |
| `qrColorLight` | string | `#ffffff` | Background color |
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
- default foreground color
- default background color
- default error correction
- payload types enabled by default

These defaults apply to new instances and can still be overridden per block or shortcode.

## Architecture notes

- The plugin bundles `qr-code-styling` locally instead of loading it from a CDN.
- The block is dynamic and rendered through PHP for consistent frontend output.
- The code includes filters for defaults and instance settings so future add-ons can extend behavior without rewriting core free-plugin logic.

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
