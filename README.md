# Client-Side QR Code Generator

Client-Side QR Code Generator is a WordPress plugin that renders customizable QR codes in the browser using [`qr-code-styling`](https://github.com/kozakdenys/qr-code-styling). It supports both a Gutenberg block and a classic shortcode, with live generation on the frontend and no server-side image rendering.

Version: `4.0.0`

## Overview

This plugin is designed for sites that want attractive QR codes without relying on a remote QR generation API or heavy server-side processing. The rendered QR code is created in the visitor's browser, and the plugin exposes controls for styling, data formatting, downloads, and optional end-user customization.

The plugin currently ships as a small codebase:

- `client-side-qr.php` - main plugin file, shortcode, block registration, enqueue logic
- `assets/qr-block.js` - Gutenberg block editor controls and preview
- `assets/qr-script.js` - frontend QR generation logic
- `assets/qr-style.css` - frontend styling
- `CHANGELOG.md` - release history

## Features

- Gutenberg block: `csqr/generator`
- Classic shortcode: `[client_side_qr]`
- Client-side QR rendering with `qr-code-styling`
- Live frontend generation as users type
- Live preview inside the block editor
- Support for multiple QR payload types:
  - URL / plain text
  - WiFi network
  - vCard contact
  - Email
  - SMS
  - Crypto wallet
  - PayPal.me
- Built-in UTM parameter builder for URL codes
- Customizable design options:
  - foreground color
  - optional foreground gradient
  - background color
  - dot style
  - corner eye style
  - corner eye color
  - logo image
  - output size
  - error correction level
- Optional end-user controls for:
  - colors
  - size
  - error correction level
  - transparent background
- Export actions:
  - download PNG
  - download SVG
  - copy image to clipboard
- Translation-ready strings using the `csqr` text domain

## Supported QR Content Types

The frontend UI can expose one or more of the following tabs:

- `URL`: plain URL or text input, with optional `utm_source`, `utm_medium`, and `utm_campaign`
- `WiFi`: SSID, password, encryption type, hidden network toggle
- `vCard`: first name, last name, phone, email, company, title, website, address
- `Email`: recipient, subject, message body
- `SMS`: phone number and message
- `Crypto`: Bitcoin, Ethereum, or Litecoin wallet address with optional amount
- `PayPal`: PayPal.me username with optional amount and currency

Each block instance can enable or disable these data types independently in the editor sidebar.

## Requirements

- WordPress `5.0+`
- A theme/page where JavaScript is allowed to run normally
- Internet access to load the `qr-code-styling` library from jsDelivr:
  - `https://cdn.jsdelivr.net/npm/qr-code-styling@1.5.0/lib/qr-code-styling.js`

## Installation

### Install from this repository

1. Copy this project into your WordPress plugins directory.
2. Make sure the folder contains:
   - `client-side-qr.php`
   - `assets/`
3. Rename the plugin folder if needed.
4. Activate the plugin in WordPress under `Plugins`.

Example plugin path:

```text
wp-content/plugins/client-side-qr-code-generator/
```

### Install as a ZIP

1. Zip the plugin folder.
2. In WordPress admin, go to `Plugins > Add New Plugin > Upload Plugin`.
3. Upload the ZIP file.
4. Activate the plugin.

## Usage

### Gutenberg block

1. Open a post or page in the block editor.
2. Add the block named `Client-Side QR Code`.
3. Configure design settings in the inspector panel.
4. Toggle which data type tabs should be available.
5. Optionally allow site visitors to change color, size, or correction level.
6. Publish or update the page.

### Shortcode

Use the shortcode anywhere shortcodes are supported:

```shortcode
[client_side_qr]
```

Minimal example with some custom styling:

```shortcode
[client_side_qr qrColorDark="#111111" qrColorLight="#ffffff" qrSize="320" qrDotStyle="rounded"]
```

Example with limited tabs and end-user controls enabled:

```shortcode
[client_side_qr enableUrl="true" enableWifi="false" enableEmail="true" enableSms="false" enableVcard="false" enableCrypto="false" enablePaypal="false" allowUserColor="true" allowUserSize="true" allowUserCorrectLevel="true"]
```

Example with gradient and logo:

```shortcode
[client_side_qr qrGradient="true" qrColorDark="#0f172a" qrColorDark2="#2563eb" logoUrl="https://example.com/logo.png"]
```

## Shortcode Attributes

The shortcode maps directly to the defaults defined in `client-side-qr.php`.

| Attribute | Type | Default | Description |
| --- | --- | --- | --- |
| `qrColorDark` | string | `#111111` | Primary foreground color |
| `qrColorDark2` | string | `#111111` | Secondary foreground color used when gradient is enabled |
| `qrColorLight` | string | `#ffffff` | Background color |
| `qrSize` | int | `256` | Default QR output size in pixels |
| `qrCorrectLevel` | string | `H` | Error correction level: `L`, `M`, `Q`, or `H` |
| `qrDotStyle` | string | `square` | Dot style for the QR modules |
| `qrEyeStyle` | string | `square` | Corner eye style |
| `qrEyeColor` | string | empty | Optional eye color override |
| `qrGradient` | bool | `false` | Enables a linear gradient using `qrColorDark` and `qrColorDark2` |
| `logoUrl` | string | empty | Center logo image URL |
| `allowUserColor` | bool | `false` | Lets visitors change colors and toggle transparent background |
| `allowUserSize` | bool | `false` | Lets visitors change output size |
| `allowUserCorrectLevel` | bool | `false` | Lets visitors change error correction level |
| `enableUrl` | bool | `true` | Show the URL / text tab |
| `enableWifi` | bool | `true` | Show the WiFi tab |
| `enableEmail` | bool | `true` | Show the Email tab |
| `enableSms` | bool | `true` | Show the SMS tab |
| `enableVcard` | bool | `true` | Show the vCard tab |
| `enableCrypto` | bool | `true` | Show the Crypto tab |
| `enablePaypal` | bool | `true` | Show the PayPal tab |

## Editor Settings

The block sidebar currently exposes three main groups:

### Design Settings

- dot style
- foreground gradient toggle
- foreground colors
- corner eye style
- corner eye color
- background color
- default output size
- error correction level
- center logo upload

### Available Data Types

You can independently enable or disable:

- URL / Text
- WiFi Network
- vCard
- Email
- SMS / Phone
- Crypto Wallet
- PayPal.me

### End-User Controls

You can allow visitors to change:

- colors
- size
- error correction level

## Frontend Behavior

- The QR code updates automatically as the user types or changes options.
- If the active form is incomplete, the output area stays hidden.
- Download buttons appear only after a valid QR code is generated.
- PNG and SVG downloads are handled by `qr-code-styling`.
- The copy button uses the browser clipboard API to write a PNG image.

## How It Works

### Rendering model

- WordPress outputs the form and container markup on the page.
- The browser loads `qr-code-styling` from a CDN.
- `assets/qr-script.js` reads the active form values and styling options.
- A QR code is rendered into the page client-side.

### Block model

- The Gutenberg block registers as `csqr/generator`.
- The editor preview uses a sample URL and re-renders when design settings change.
- The saved block output is handled through the PHP render callback, so the block behaves as a dynamic block.

## Accessibility and Compatibility Notes

- The UI is keyboard-usable for standard form controls, but there is no dedicated accessibility audit in this repo yet.
- Clipboard image copy depends on browser support for `navigator.clipboard` and `ClipboardItem`.
- The plugin uses external CDN assets, so locked-down environments may need to self-host the QR library.
- If a logo image is loaded from another origin, browser/CORS behavior may affect export or clipboard actions depending on the environment.

## Limitations

- There is no admin settings page; configuration is done per block or shortcode instance.
- The QR library is loaded from jsDelivr rather than bundled locally.
- Translation strings are wrapped in code, but no `.pot` file is included in this repository.
- No automated tests are included in this project.
- The shortcode interface is powerful but not especially user-friendly for non-technical site owners compared with a dedicated settings UI.

## Development Notes

This plugin is intentionally lightweight and uses plain PHP, plain JavaScript, and WordPress block APIs directly.

If you modify the plugin:

1. Update the plugin header version in `client-side-qr.php`.
2. Update asset version strings where needed.
3. Add release notes to `CHANGELOG.md`.
4. Re-test both:
   - Gutenberg block flow
   - shortcode flow

## File Reference

- Main plugin: `client-side-qr.php`
- Frontend script: `assets/qr-script.js`
- Block editor script: `assets/qr-block.js`
- Styles: `assets/qr-style.css`
- Changelog: `CHANGELOG.md`

## Changelog

See `CHANGELOG.md` for version history.

## License

No license file is currently included in this repository. If you plan to distribute this plugin publicly, adding an explicit license is recommended.
