# Changelog

All notable changes to the Client-Side QR Code Generator will be documented in this file.

## [4.1.3] - Screenshot And Packaging Alignment
### Added
- Fourth WordPress.org screenshot for the shortcode builder workflow.

### Changed
- Updated plugin version metadata to `4.1.3`.
- Aligned release docs and screenshot references with the current admin feature set.

## [4.1.2] - Classic Workflow And Optional Release Notices
### Added
- Classic-editor shortcode builder screen with generated shortcode output and preview support.
- Optional opt-in GitHub release notices for self-managed installs.

### Changed
- Updated plugin version metadata to `4.1.2`.
- Frontend shell now inherits theme typography and text color more naturally by default.

## [4.1.1] - Release Packaging And Growth Planning
### Added
- `ROADMAP.md` with monetization and marketing guidance for future product expansion.
- WordPress.org starter banner, icon, and screenshot assets in `.wordpress-org/`.
- Source design files for the WordPress.org visual kit.

### Changed
- Updated plugin version metadata to `4.1.1`.
- Added reusable tooling for generating WordPress.org assets and translation templates.

## [4.1.0] - Public Release Readiness
### Added
- Lightweight settings page for site-wide defaults under `Settings > Client-Side QR`.
- WordPress.org style `readme.txt`.
- `uninstall.php` cleanup for stored plugin settings.
- `THIRD_PARTY_NOTICES.md` for bundled dependency attribution.
- `languages/csqr.pot` translation template and a local generator script.
- `.wordpress-org/` scaffold for banner, icon, and screenshot assets.

### Changed
- Updated plugin licensing metadata to `GPL-2.0-or-later`.
- Bundled `qr-code-styling` locally instead of loading it from jsDelivr.
- Improved frontend accessibility with keyboard tabs, visible labels, focus states, and live status messaging.
- Hardened shortcode and settings sanitization.
- Refined README positioning for privacy-friendly, client-side QR workflows.

## [4.0.0] - The Mastercraft Polish
### Added
- Independent Corner "Eye" Customization (Make edges dotted, rounded, or entirely different colors from the center dots).
- Translation / I18n Readiness: Fully wrapped all variables in WordPress native translation standards (`csqr` text domain).
- Native OS "Copy Image to Clipboard" button logic utilizing the modern `navigator.clipboard` Blob API.
- Re-engineered block structure to allow individual components and form tabs to be selectively toggled on/off within single blocks via the Gutenberg Side panel Inspector.

## [3.0.0] - Elite Premium Polish
### Added
- Real-time "As-You-Type" Frontend Preview! No more clicking a "Generate" button.
- SVG Vector Downloads for high-res print scaling.
- Transparent background support.
- New App-Like horizontal tab UI for switching data types smoothly.
- New Data Types: Crypto (Bitcoin, Ethereum, Litecoin) and PayPal.me support.
- Upgraded the vCard form with fields for Title, Address, and Website URL.

## [2.0.0] - The Core Engine Update
### Added
- Transitioned core rendering library from `qrcode.js` to `qr-code-styling`.
- Backend Editor live preview using React Hooks (`useRef`, `useEffect`).
- Native WordPress Media Library upload binding for center logos.
- Advanced layout options: Gradients, customizable Dot Styles.
- Added WiFi, Email, SMS, and basic vCard data formats.
- Built-in UTM query string builder for marketers.

## [1.0.0] - Initial Release
### Added
- Initial plugin scaffolding.
- Basic text/URL generating using `qrcode.js`.
- Gutenberg block integration and classic Shortcode (`[client_side_qr]`) support.
