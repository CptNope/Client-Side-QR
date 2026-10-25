=== Anderson QR Code Generator ===
Contributors: cptnope
Tags: qr code, qr generator, short links, wifi, vcard
Requires at least: 6.0
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 4.3.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Styled QR codes made in the browser, with no outside service. Checks each code scans before you print it. Short links you can change later.

== Description ==

Anderson QR Code Generator makes QR codes inside WordPress without sending anything to a QR service. Codes are drawn in the browser by a bundled library, and the plugin checks each one before you use it.

= What makes it different =

- **Scan check.** Every code is decoded right after it's drawn. You see "Scans correctly", or what's wrong (low contrast, inverted colors, a logo that's too big) with a one-click fix.
- **Short links you can change later.** Make a link like `yoursite.com/qr/menu`, print its code, and point it somewhere new whenever you like. Scans are counted on your own site, with no cookies and no outside service.
- **A QR code for every page.** Turn it on for posts, pages or products and each one gets its own code, or switch it per page from the editor.
- **Print-ready downloads.** Pick a printed size (2 to 20 cm, or inches) and get a 300 dpi PNG, an SVG at that physical size, or a PDF. The plugin tells you how far away it will scan from.
- **Logos from the Media Library**, with error correction and logo size set automatically so the code still scans.
- **Saved styles.** Save colors, shapes and a logo once, then use them on any block or shortcode.
- **Ready-made blocks** for this page, guest WiFi (with the network and password shown for people who can't scan), a contact card filled in from your profile, a "leave a review" link and short links.
- **A visitor generator** for links (with campaign tracking), WiFi, contacts, email, text messages, crypto and PayPal.

= Free, open source, no account =

Everything in the plugin is free. There's no paid version, no sign-up and no tracking. Development happens on [GitHub](https://github.com/CptNope/Client-Side-QR).

= Need hands-on help? =

The plugin is made by [Jeremy Anderson](https://jeremyanderson.tech/), who builds and looks after WordPress sites. For a custom QR workflow, a new site or a tool built for your business, [book a call](https://jeremyanderson.tech/book-a-call/) or see the [services](https://jeremyanderson.tech/services/).

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/`, or upload the ZIP file in `Plugins > Add New Plugin`.
2. Activate the plugin through the `Plugins` screen.
3. Set your default design under `QR Codes > Settings`.
4. Add the `QR code` block (a fixed code) or the `QR code generator` block (a form for visitors). In the classic editor, use `[qr_code]` or `[client_side_qr]`; `QR Codes > Shortcode builder` writes them for you.

== Source code and third-party libraries ==

The plugin bundles two unmodified open-source libraries:

- `assets/vendor/qr-code-styling.js` is the minified build of [qr-code-styling](https://github.com/kozakdenys/qr-code-styling) 1.5.0 by Denys Kozak (MIT license, notice in `assets/vendor/qr-code-styling-LICENSE.txt`). Its human-readable source is at https://github.com/kozakdenys/qr-code-styling/tree/v1.5.0.
- `assets/vendor/jsQR.js` is [jsQR](https://github.com/cozmo/jsQR) 1.4.0 by Cosmo Wolfe (Apache License 2.0, notice in `assets/vendor/jsQR-LICENSE.txt`), used for the scan check. It loads only when a code is checked.

Everything else in the plugin is unminified.

== External services ==

The plugin doesn't contact any outside service. Short-link scans are counted in your own WordPress database (a number and the time of the last scan; nothing about the person scanning is stored). If you set a logo image by web address, browsers load that image from the address you entered.

== Frequently Asked Questions ==

= Does this plugin call a remote QR API? =

No. Codes are drawn and checked in the browser.

= What does the scan check do? =

After drawing a code, the plugin reads it back the way a phone camera would and compares the result with what it should say. It also checks color contrast and transparency. If something is off it explains the problem and offers a fix.

= Can I change where a printed code goes? =

Yes, if you made it from a short link (`QR Codes > Short links`). The code points to your site, so changing the destination later doesn't change the code.

= Do short links count scans? =

Yes: a total and the last scan time, stored on your site. Link previews and search bots aren't counted.

= What size should I print? =

Pick the size in the download panel; it shows roughly how far away a phone can scan it. As a rule, a code scans from about ten times its width.

= Can I use the plugin without the block editor? =

Yes. Use `[qr_code]` for a fixed code and `[client_side_qr]` for the visitor generator. Both accept `style="your-style-id"`.

= Will it match my theme? =

The generator and code cards use your theme's font and colors by default. You can override them under `QR Codes > Settings`.

== Screenshots ==

1. The visitor generator with the scan check and print-size downloads
2. The QR code block in the editor, with design controls and the scan check
3. A short link: change where it goes and see how often it's scanned
4. Settings with a live preview that checks the code as you change it

== Changelog ==

= 4.3.0 =

- New QR code block with ready-made versions for this page, guest WiFi, a contact card, a review link and short links. Also available as `[qr_code]`.
- Scan check: every code is read back after it's drawn, with one-click fixes for problems.
- Short links at `yoursite.com/qr/…` that you can redirect later, with a private scan count.
- Optional QR code at the end of posts, pages or other post types, switchable per page.
- Print-ready downloads: choose a printed size and get a 300 dpi PNG, a sized SVG or a PDF.
- Logos from the Media Library, with safe size and error correction set automatically.
- Saved styles for reuse across blocks and shortcodes.
- Redesigned generator and code cards, and a new QR Codes menu in wp-admin (the old Settings links redirect).
- Browser tests run in CI.

= 4.2.0 =

- Now on WordPress.org as Anderson QR Code Generator. Existing blocks, shortcodes and settings keep working.
- Removed the optional GitHub release notices: updates now come through WordPress.org.
- Translations load from WordPress.org (text domain `anderson-qr-code-generator`).
- Documentation, Support and Hire the author links on the Plugins screen, and a short about footer on the plugin's settings pages.
- The shortcode builder sanitizes every field as it's read.

= 4.1.4 =

- Fixed Gutenberg block selection in the editor by restoring a proper block wrapper.
- Updated the frontend shell to inherit theme context more reliably on dark and light themes.
- Added shell style inheritance and override controls to the block editor, shortcode builder, and global defaults.

= 4.1.3 =

- Added a fourth WordPress.org screenshot for the shortcode builder workflow.
- Refined release packaging to keep screenshots and docs aligned with the current admin feature set.

= 4.1.2 =

- Added a classic-editor shortcode builder screen with preview support.
- Added optional opt-in GitHub release notices for self-managed installs.
- Updated the frontend shell to inherit theme fonts and text color more naturally by default.

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

= 4.3.0 =

Adds a QR code block, scan check, editable short links, page QR codes and print-ready downloads. Plugin settings move to the new QR Codes menu.

= 4.2.0 =

First WordPress.org release. Removes the optional GitHub release notices; updates now come through WordPress.org.

= 4.1.4 =

This release fixes the editor selection regression and adds cleaner theme inheritance with optional shell overrides.

= 4.1.3 =

This release aligns the WordPress.org screenshot set and docs with the shortcode builder added in 4.1.2.

= 4.1.2 =

This release adds a classic-editor shortcode builder, opt-in GitHub release notices, and more theme-friendly frontend defaults.

= 4.1.1 =

This release adds WordPress.org media assets, translation tooling support, and a product roadmap for future monetization planning.

= 4.1.0 =

This release replaces the remote QR library with a bundled local copy, adds site-wide defaults, and improves accessibility and packaging for public distribution.
