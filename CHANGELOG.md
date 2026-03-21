# Changelog

All notable changes to the Client-Side QR Code Generator will be documented in this file.

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
