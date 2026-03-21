=== Client-Side QR Code Generator ===
Contributors: jeremyanderson
Tags: qr code, qr generator, marketing, shortcode, block
Requires at least: 6.0
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 4.1.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Generate privacy-friendly QR codes directly in the browser with a Gutenberg block and shortcode for links, campaigns, contact sharing, WiFi, and payment workflows.

== Description ==

Client-Side QR Code Generator helps WordPress sites publish useful QR experiences without sending QR creation to a remote API. Rendering happens in the visitor's browser, which keeps the plugin lightweight and privacy-friendly while still supporting richer QR workflows.

= Key features =

- Client-side QR generation with a bundled local QR library
- Gutenberg block and classic shortcode support
- URL, WiFi, vCard, Email, SMS, Crypto, and PayPal payloads
- Built-in UTM builder for campaign QR links
- Styling controls for colors, gradients, size, dot shape, corner eyes, and logos
- Optional end-user controls for colors, size, and error correction
- PNG download, SVG download, and clipboard copy support where available
- Lightweight settings page for global defaults

= Good fit for =

- landing pages and printed campaigns
- event materials and posters
- contact sharing flows
- WiFi onboarding pages
- QR-driven marketing and site workflows

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/`, or upload the ZIP file in `Plugins > Add New Plugin`.
2. Activate the plugin through the `Plugins` screen.
3. Optionally set plugin defaults in `Settings > Client-Side QR`.
4. Add the `Client-Side QR Code` block or use the `[client_side_qr]` shortcode.

== Frequently Asked Questions ==

= Does this plugin call a remote QR API? =

No. QR rendering happens client-side in the browser.

= Does the plugin still use a CDN? =

No. Version 4.1.0 and later bundle the `qr-code-styling` library locally inside the plugin.

= Can I use the plugin without Gutenberg? =

Yes. The `[client_side_qr]` shortcode remains supported.

= Can visitors download the generated QR code? =

Yes. PNG and SVG downloads are supported, and clipboard image copy is available when the browser supports the required APIs.

== Screenshots ==

1. Frontend QR generator with accessible payload tabs and exports
2. Gutenberg block controls for design and payload defaults
3. Lightweight settings page for site-wide defaults

== Changelog ==

= 4.1.1 =

- Added a monetization and growth roadmap for future product planning.
- Added WordPress.org starter banner, icon, and screenshot assets.
- Added translation tooling documentation and generated language template support.

= 4.1.0 =

- Switched plugin licensing metadata to GPL-2.0-or-later for WordPress distribution.
- Bundled `qr-code-styling` locally instead of loading it from a CDN.
- Added a lightweight settings page for site-wide defaults.
- Improved frontend accessibility with labeled controls, keyboard tabs, and live status messaging.
- Hardened shortcode and settings sanitization.
- Added uninstall cleanup for stored options.
- Added WordPress.org style readme metadata and third-party notices.
- Added a translation template and repo structure for WordPress.org banner and icon assets.

= 4.0.0 =

- Added independent corner eye customization.
- Added translation-ready strings across the UI.
- Added clipboard image copy support.
- Added per-block payload type toggles.

== Upgrade Notice ==

= 4.1.1 =

This release adds WordPress.org media assets, translation tooling support, and a product roadmap for future monetization planning.

= 4.1.0 =

This release replaces the remote QR library with a bundled local copy, adds site-wide defaults, and improves accessibility and packaging for public distribution.
