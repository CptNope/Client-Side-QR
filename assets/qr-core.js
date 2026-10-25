/**
 * Anderson QR Code Generator: the shared QR engine.
 *
 * Everything that draws, checks or exports a QR code goes through here, so the
 * visitor generator, the QR code block, the page QR card and the admin
 * previews behave the same way:
 *
 *  - CSQR.payload.*   build the text a code holds (with the escaping the
 *                     WiFi and vCard formats need);
 *  - CSQR.render()    draws a code with a quiet zone and logo safety rules;
 *  - CSQR.check()     decodes the code it just drew (bundled jsQR, loaded on
 *                     first use) at full size and at a small print size, and
 *                     checks colour contrast, returning plain-language issues
 *                     with one-click fixes;
 *  - CSQR.download()  print-ready PNG (with DPI metadata), SVG (with physical
 *                     size) and PDF files at a chosen print size.
 */
(function (window, document) {
	'use strict';

	var CSQR = window.CSQR = window.CSQR || {};
	var config = window.csqrCoreConfig || {};
	var i18n = config.i18n || {};

	function t(key, fallback) {
		return i18n[key] || fallback;
	}

	function format(text) {
		var args = Array.prototype.slice.call(arguments, 1);
		return String(text).replace(/%(\d)\$s/g, function (match, n) {
			return args[n - 1] !== undefined ? args[n - 1] : match;
		});
	}

	CSQR.t = t;
	CSQR.format = format;

	/* ------------------------------------------------------------------ */
	/* Payloads                                                            */
	/* ------------------------------------------------------------------ */

	function trim(value) {
		return value === undefined || value === null ? '' : String(value).trim();
	}

	// WiFi QR format (ZXing): \ ; , : " are escaped with a backslash.
	function escWifi(value) {
		return String(value).replace(/([\\;,:"])/g, '\\$1');
	}

	// vCard 3.0 text values: backslash, newline, ; and , are escaped.
	function escVcard(value) {
		return String(value).replace(/\\/g, '\\\\').replace(/\r?\n/g, '\\n').replace(/([;,])/g, '\\$1');
	}

	function withScheme(url) {
		url = trim(url);
		if (!url) {
			return '';
		}
		return /^[a-z][a-z0-9+.-]*:/i.test(url) ? url : 'https://' + url;
	}

	CSQR.payload = {
		text: function (f) {
			return trim(f.text);
		},
		url: function (f) {
			var data = trim(f.url);
			if (!data) {
				return '';
			}
			if (trim(f.utmSource) || trim(f.utmMedium) || trim(f.utmCampaign)) {
				try {
					var url = new URL(withScheme(data));
					[['utm_source', f.utmSource], ['utm_medium', f.utmMedium], ['utm_campaign', f.utmCampaign]].forEach(function (pair) {
						if (trim(pair[1])) {
							url.searchParams.set(pair[0], trim(pair[1]));
						}
					});
					return url.toString();
				} catch (e) {
					return data;
				}
			}
			return data;
		},
		wifi: function (f) {
			var ssid = trim(f.ssid);
			if (!ssid) {
				return '';
			}
			var enc = f.encryption === 'WEP' || f.encryption === 'nopass' ? f.encryption : 'WPA';
			var out = 'WIFI:T:' + enc + ';S:' + escWifi(ssid) + ';';
			if (enc !== 'nopass') {
				out += 'P:' + escWifi(f.password || '') + ';';
			}
			if (f.hidden) {
				out += 'H:true;';
			}
			return out + ';';
		},
		vcard: function (f) {
			var first = trim(f.firstName);
			var last = trim(f.lastName);
			var org = trim(f.company);
			if (!first && !last && !org) {
				return '';
			}
			var lines = ['BEGIN:VCARD', 'VERSION:3.0', 'N:' + escVcard(last) + ';' + escVcard(first) + ';;;', 'FN:' + escVcard([first, last].join(' ').trim() || org)];
			if (org) {
				lines.push('ORG:' + escVcard(org));
			}
			if (trim(f.title)) {
				lines.push('TITLE:' + escVcard(trim(f.title)));
			}
			if (trim(f.phone)) {
				lines.push('TEL;TYPE=CELL:' + escVcard(trim(f.phone)));
			}
			if (trim(f.email)) {
				lines.push('EMAIL:' + escVcard(trim(f.email)));
			}
			if (trim(f.website)) {
				lines.push('URL:' + escVcard(withScheme(f.website)));
			}
			if (trim(f.address)) {
				lines.push('ADR;TYPE=WORK:;;' + escVcard(trim(f.address)) + ';;;;');
			}
			lines.push('END:VCARD');
			return lines.join('\r\n');
		},
		email: function (f) {
			var to = trim(f.email);
			if (!to) {
				return '';
			}
			var query = [];
			if (trim(f.subject)) {
				query.push('subject=' + encodeURIComponent(trim(f.subject)));
			}
			if (trim(f.body)) {
				query.push('body=' + encodeURIComponent(trim(f.body)));
			}
			return 'mailto:' + to + (query.length ? '?' + query.join('&') : '');
		},
		sms: function (f) {
			var phone = trim(f.phone).replace(/[^\d+]/g, '');
			if (!phone) {
				return '';
			}
			return 'SMSTO:' + phone + ':' + trim(f.message);
		},
		crypto: function (f) {
			var address = trim(f.address);
			if (!address) {
				return '';
			}
			var scheme = ['bitcoin', 'ethereum', 'litecoin'].indexOf(f.currency) !== -1 ? f.currency : 'bitcoin';
			var amount = trim(f.amount);
			return scheme + ':' + address + (amount ? '?amount=' + encodeURIComponent(amount) : '');
		},
		paypal: function (f) {
			var user = trim(f.username).replace(/^@/, '').replace(/[^A-Za-z0-9_.-]/g, '');
			if (!user) {
				return '';
			}
			var amount = trim(f.amount);
			return 'https://paypal.me/' + user + (amount ? '/' + encodeURIComponent(amount) + (f.currency || 'USD') : '');
		}
	};

	/* ------------------------------------------------------------------ */
	/* Design                                                              */
	/* ------------------------------------------------------------------ */

	var DOTS = ['square', 'dots', 'rounded', 'extra-rounded', 'classy', 'classy-rounded'];
	var EYES = ['square', 'dot', 'extra-rounded'];

	function hex(value, fallback) {
		value = trim(value);
		return /^#([0-9a-f]{3}|[0-9a-f]{6})$/i.test(value) ? value : fallback;
	}

	/**
	 * Normalise a settings object (block attributes, saved style, data attribute)
	 * into the design the engine draws with.
	 */
	CSQR.design = function (s) {
		s = s || {};
		var level = ['L', 'M', 'Q', 'H'].indexOf(s.qrCorrectLevel) !== -1 ? s.qrCorrectLevel : 'M';
		return {
			dark: hex(s.qrColorDark, '#111111'),
			dark2: hex(s.qrColorDark2, hex(s.qrColorDark, '#111111')),
			light: hex(s.qrColorLight, '#ffffff'),
			transparent: !!s.transparent,
			gradient: !!s.qrGradient,
			dotStyle: DOTS.indexOf(s.qrDotStyle) !== -1 ? s.qrDotStyle : 'square',
			eyeStyle: EYES.indexOf(s.qrEyeStyle) !== -1 ? s.qrEyeStyle : 'square',
			eyeColor: hex(s.qrEyeColor, ''),
			level: level,
			logo: trim(s.logoUrl),
			logoSize: Math.min(0.3, Math.max(0.1, parseFloat(s.logoSize) || 0.24))
		};
	};

	/** The settings keys a design maps back to (for one-click fixes). */
	CSQR.designToSettings = function (d) {
		return {
			qrColorDark: d.dark,
			qrColorDark2: d.dark2,
			qrColorLight: d.light,
			transparent: d.transparent,
			qrGradient: d.gradient,
			qrDotStyle: d.dotStyle,
			qrEyeStyle: d.eyeStyle,
			qrEyeColor: d.eyeColor,
			qrCorrectLevel: d.level,
			logoUrl: d.logo,
			logoSize: d.logoSize
		};
	};

	/** Error correction actually used: a logo covers modules, so it needs H. */
	function levelFor(d) {
		return d.logo ? 'H' : d.level;
	}

	/*
	 * The bundled library waits for a logo's onload and never gives up, so a
	 * broken logo address would leave the code undrawn. Probe each logo once;
	 * codes are drawn without a logo that doesn't load.
	 */
	var logoState = {};

	function probeLogo(url) {
		if (logoState[url] === 'ok' || logoState[url] === 'bad') {
			return Promise.resolve(logoState[url] === 'ok');
		}
		if (!logoState[url]) {
			logoState[url] = new Promise(function (resolve) {
				var img = new Image();
				var done = function (ok) {
					window.clearTimeout(timer);
					logoState[url] = ok ? 'ok' : 'bad';
					resolve(ok);
				};
				var timer = window.setTimeout(function () {
					done(false);
				}, 10000);
				img.crossOrigin = 'anonymous';
				img.onload = function () {
					done(img.naturalWidth > 0);
				};
				img.onerror = function () {
					done(false);
				};
				img.src = url;
			});
		}
		return logoState[url];
	}
	CSQR.probeLogo = probeLogo;

	function options(d, text, width, type) {
		var opts = {
			type: type || 'canvas',
			width: width,
			height: width,
			margin: 0,
			data: text,
			qrOptions: { errorCorrectionLevel: levelFor(d) },
			dotsOptions: { color: d.dark, type: d.dotStyle },
			cornersSquareOptions: { color: d.eyeColor || d.dark, type: d.eyeStyle },
			cornersDotOptions: { color: d.eyeColor || d.dark, type: d.eyeStyle === 'square' ? 'square' : 'dot' },
			backgroundOptions: { color: d.transparent ? 'rgba(0,0,0,0)' : d.light }
		};
		if (d.gradient && d.dark2 && d.dark2.toLowerCase() !== d.dark.toLowerCase()) {
			opts.dotsOptions.gradient = {
				type: 'linear',
				rotation: Math.PI / 4,
				colorStops: [{ offset: 0, color: d.dark }, { offset: 1, color: d.dark2 }]
			};
		}
		if (d.logo && logoState[d.logo] !== 'bad') {
			opts.image = d.logo;
			opts.imageOptions = { crossOrigin: 'anonymous', margin: Math.max(2, Math.round(width / 120)), imageSize: d.logoSize, hideBackgroundDots: true };
		}
		return opts;
	}

	/**
	 * Create a QRCodeStyling instance with a four-module quiet zone. Scanners
	 * need that blank border; the library's default is none.
	 */
	function make(d, text, width, type) {
		var qr = new window.QRCodeStyling(options(d, text, width, type));
		var count = qr._qr && qr._qr.getModuleCount ? qr._qr.getModuleCount() : 0;
		var margin = count ? Math.round((4 * width) / (count + 8)) : Math.round(width * 0.09);
		qr.update({ margin: margin });
		qr.csqrModules = count;
		return qr;
	}

	CSQR.make = make;

	/**
	 * Draw a code into an element. Returns the instance; when a logo is
	 * involved, instance.csqrReady resolves to the instance that ends up on
	 * screen (redrawn without the logo if it doesn't load).
	 */
	CSQR.render = function (el, d, text, width) {
		function draw(withLogo) {
			var shown = withLogo ? d : Object.assign({}, d, { logo: '' });
			el.innerHTML = '';
			var instance = make(shown, text, width || 256);
			instance.append(el);
			var node = el.querySelector('canvas, svg');
			if (node) {
				node.setAttribute('aria-hidden', 'true');
				node.style.maxWidth = '100%';
				node.style.height = 'auto';
			}
			return instance;
		}
		if (!d.logo || logoState[d.logo] === 'ok' || logoState[d.logo] === 'bad') {
			el.csqrDrawToken = (el.csqrDrawToken || 0) + 1;
			var qr = draw(d.logo && logoState[d.logo] === 'ok');
			qr.csqrLogoFailed = !!d.logo && logoState[d.logo] === 'bad';
			return qr;
		}
		// Unknown logo: show the code straight away, add the logo once it loads.
		var token = (el.csqrDrawToken || 0) + 1;
		el.csqrDrawToken = token;
		var first = draw(false);
		first.csqrReady = probeLogo(d.logo).then(function (ok) {
			if (el.csqrDrawToken !== token) {
				return first; // Something newer was drawn here meanwhile.
			}
			if (!ok) {
				first.csqrLogoFailed = true;
				return first;
			}
			return draw(true);
		});
		return first;
	};

	/* ------------------------------------------------------------------ */
	/* Scan check                                                          */
	/* ------------------------------------------------------------------ */

	var jsqrPromise = null;

	function loadJsQR() {
		if (window.jsQR) {
			return Promise.resolve(window.jsQR);
		}
		if (!config.jsqrUrl) {
			return Promise.reject(new Error('jsQR unavailable'));
		}
		if (!jsqrPromise) {
			jsqrPromise = new Promise(function (resolve, reject) {
				var s = document.createElement('script');
				s.src = config.jsqrUrl;
				s.async = true;
				s.onload = function () {
					window.jsQR ? resolve(window.jsQR) : reject(new Error('jsQR did not load'));
				};
				s.onerror = function () {
					jsqrPromise = null;
					reject(new Error('jsQR did not load'));
				};
				document.head.appendChild(s);
			});
		}
		return jsqrPromise;
	}

	CSQR.loadDecoder = loadJsQR;

	function rgb(h) {
		h = h.replace('#', '');
		if (h.length === 3) {
			h = h[0] + h[0] + h[1] + h[1] + h[2] + h[2];
		}
		return [parseInt(h.slice(0, 2), 16), parseInt(h.slice(2, 4), 16), parseInt(h.slice(4, 6), 16)];
	}

	function luminance(h) {
		var c = rgb(h).map(function (v) {
			v /= 255;
			return v <= 0.03928 ? v / 12.92 : Math.pow((v + 0.055) / 1.055, 2.4);
		});
		return 0.2126 * c[0] + 0.7152 * c[1] + 0.0722 * c[2];
	}

	function contrast(a, b) {
		var la = luminance(a);
		var lb = luminance(b);
		return (Math.max(la, lb) + 0.05) / (Math.min(la, lb) + 0.05);
	}

	CSQR.contrast = contrast;

	function blobToImage(blob) {
		if (window.createImageBitmap) {
			return window.createImageBitmap(blob);
		}
		return new Promise(function (resolve, reject) {
			var img = new Image();
			img.onload = function () {
				resolve(img);
			};
			img.onerror = reject;
			img.src = URL.createObjectURL(blob);
		});
	}

	function decodeAt(jsQR, img, width) {
		var canvas = document.createElement('canvas');
		canvas.width = width;
		canvas.height = width;
		var ctx = canvas.getContext('2d');
		// What a camera sees: the code on white paper.
		ctx.fillStyle = '#ffffff';
		ctx.fillRect(0, 0, width, width);
		ctx.imageSmoothingEnabled = true;
		ctx.drawImage(img, 0, 0, width, width);
		var data = ctx.getImageData(0, 0, width, width);
		// Most phone cameras don't read inverted codes, so neither do we.
		var result = jsQR(data.data, width, width, { inversionAttempts: 'dontInvert' });
		return result ? result.data : null;
	}

	function issue(code, severity, message, fix) {
		return { code: code, severity: severity, message: message, fix: fix || null };
	}

	/**
	 * Check that a code scans. Resolves to {level: 'ok'|'warn'|'fail'|'unknown',
	 * issues: [{code, severity, message, fix: {label, apply(design) -> design}}]}.
	 */
	CSQR.check = function (qr, text, d) {
		if (qr && qr.csqrReady) {
			var pending = qr.csqrReady;
			qr.csqrReady = null;
			return pending.then(function (final) {
				return CSQR.check(final, text, d);
			});
		}
		var issues = [];
		if (qr && qr.csqrLogoFailed) {
			issues.push(issue('logo', 'warn', t('checkLogoMissing', 'The logo couldn’t be loaded, so the code is shown without it. Check its address, or choose it from your Media Library.'), {
				label: t('fixLogoRemove', 'Remove the logo'),
				apply: function (x) {
					x.logo = '';
					return x;
				}
			}));
			d = Object.assign({}, d, { logo: '' });
		}
		var bg = d.transparent ? '#ffffff' : d.light;
		var darks = [d.dark];
		if (d.gradient) {
			darks.push(d.dark2);
		}
		if (d.eyeColor) {
			darks.push(d.eyeColor);
		}
		var worst = Math.min.apply(null, darks.map(function (c) {
			return contrast(c, bg);
		}));
		var inverted = darks.some(function (c) {
			return luminance(c) > luminance(bg);
		});

		if (inverted) {
			issues.push(issue('inverted', 'fail', t('checkInverted', 'The code is lighter than its background. Most phone cameras can’t read light-on-dark codes.'), {
				label: t('fixInverted', 'Swap the colors'),
				apply: function (x) {
					var dark = x.light;
					x.light = x.dark;
					x.dark = dark;
					x.dark2 = dark;
					x.eyeColor = '';
					return x;
				}
			}));
		} else if (worst < 3) {
			issues.push(issue('contrast', 'fail', format(t('checkContrastLow', 'The colors are too close together (contrast %1$s:1). Use a darker code or a lighter background.'), worst.toFixed(1)), {
				label: t('fixContrast', 'Use black on white'),
				apply: function (x) {
					x.dark = '#000000';
					x.dark2 = '#000000';
					x.light = '#ffffff';
					x.eyeColor = '';
					x.transparent = false;
					return x;
				}
			}));
		} else if (worst < 4.5) {
			issues.push(issue('contrast', 'warn', format(t('checkContrastMid', 'Contrast is on the low side (%1$s:1). It scans on screen, but print and dim light make it harder.'), worst.toFixed(1)), {
				label: t('fixContrastDarken', 'Darken the code'),
				apply: function (x) {
					x.dark = '#000000';
					x.dark2 = '#000000';
					x.eyeColor = '';
					return x;
				}
			}));
		}
		if (d.transparent) {
			issues.push(issue('transparent', 'info', t('checkTransparent', 'The background is transparent, so it only scans on a light surface.'), null));
		}

		if (!window.QRCodeStyling || !qr) {
			return Promise.resolve({ level: summarize(issues), issues: issues });
		}

		return loadJsQR().then(function (jsQR) {
			return qr.getRawData('png').then(blobToImage).then(function (img) {
				var full = Math.min(img.width, 600);
				var modules = (qr.csqrModules || 33) + 8;
				var small = Math.max(modules * 2, 64);
				var atFull = decodeAt(jsQR, img, full);
				if (atFull !== text) {
					if (!inverted && worst >= 3) {
						var fixes = unreadableFix(d);
						issues.unshift(issue('unreadable', 'fail', atFull === null ? t('checkUnreadable', 'A phone camera can’t read this code. The styling hides too much of it.') : t('checkMismatch', 'This code reads back as different text. Simplify the styling.'), fixes));
					}
				} else if (decodeAt(jsQR, img, small) !== text) {
					issues.push(issue('small', 'warn', t('checkSmall', 'It scans close up, but may fail when printed small or seen from a distance.'), simplifyFix(d)));
				}
				return { level: summarize(issues), issues: issues, modules: qr.csqrModules || 0 };
			});
		}).catch(function () {
			issues.push(issue('unchecked', 'info', d.logo ? t('checkLogoBlocked', 'Couldn’t test this code: its logo is on another site that doesn’t allow it. Choose the logo from your Media Library.') : t('checkFailed', 'Couldn’t test this code in this browser.'), null));
			return { level: issues.some(function (i) {
				return i.severity === 'fail';
			}) ? 'fail' : 'unknown', issues: issues };
		});
	};

	function summarize(issues) {
		if (issues.some(function (i) {
			return i.severity === 'fail';
		})) {
			return 'fail';
		}
		return issues.some(function (i) {
			return i.severity === 'warn';
		}) ? 'warn' : 'ok';
	}

	function simplifyFix(d) {
		if (d.logo && d.logoSize > 0.18) {
			return {
				label: t('fixLogoSmaller', 'Make the logo smaller'),
				apply: function (x) {
					x.logoSize = 0.16;
					return x;
				}
			};
		}
		if (d.dotStyle !== 'square' || d.gradient) {
			return {
				label: t('fixSimplify', 'Use plain square dots'),
				apply: function (x) {
					x.dotStyle = 'square';
					x.gradient = false;
					return x;
				}
			};
		}
		if (d.level === 'H' || d.level === 'Q') {
			return {
				label: t('fixLowerLevel', 'Use fewer, larger dots'),
				apply: function (x) {
					x.level = 'M';
					return x;
				}
			};
		}
		return null;
	}

	function unreadableFix(d) {
		if (d.logo) {
			return {
				label: d.logoSize > 0.18 ? t('fixLogoSmaller', 'Make the logo smaller') : t('fixLogoRemove', 'Remove the logo'),
				apply: function (x) {
					if (x.logoSize > 0.18) {
						x.logoSize = 0.16;
					} else {
						x.logo = '';
					}
					return x;
				}
			};
		}
		return simplifyFix(d) || {
			label: t('fixContrast', 'Use black on white'),
			apply: function (x) {
				x.dark = '#000000';
				x.light = '#ffffff';
				x.dotStyle = 'square';
				x.gradient = false;
				return x;
			}
		};
	}

	/** Messages for the status line. */
	CSQR.checkHeadline = function (result) {
		if (!result) {
			return t('checking', 'Checking it scans…');
		}
		return {
			ok: t('checkOk', 'Scans correctly'),
			warn: t('checkWarn', 'Scans, with a caveat'),
			fail: t('checkFail', 'Won’t scan reliably'),
			unknown: t('checkUnknown', 'Not checked')
		}[result.level] || '';
	};

	/* ------------------------------------------------------------------ */
	/* Print sizes and downloads                                           */
	/* ------------------------------------------------------------------ */

	CSQR.PRINT_SIZES = [
		{ id: 'card', cm: 2.5, label: t('sizeCard', 'Business card (2.5 cm)') },
		{ id: 'flyer', cm: 5, label: t('sizeFlyer', 'Flyer or table card (5 cm)') },
		{ id: 'poster', cm: 10, label: t('sizePoster', 'Poster (10 cm)') },
		{ id: 'sign', cm: 20, label: t('sizeSign', 'Sign or window (20 cm)') }
	];

	/** How far away a code of this width can be scanned (rule of thumb: 10x). */
	CSQR.scanDistance = function (cm) {
		var dist = cm * 10;
		if (config.units === 'in') {
			var inches = dist / 2.54;
			return inches >= 36 ? format(t('distFeet', 'about %1$s ft'), Math.round(inches / 12)) : format(t('distInches', 'about %1$s in'), Math.round(inches));
		}
		return dist >= 100 ? format(t('distMetres', 'about %1$s m'), (dist / 100).toFixed(dist % 100 ? 1 : 0)) : format(t('distCm', 'about %1$s cm'), Math.round(dist));
	};

	CSQR.sizeLabel = function (cm) {
		if (config.units === 'in') {
			return format(t('sizeInches', '%1$s in'), (cm / 2.54).toFixed(cm < 5 ? 1 : 0));
		}
		return format(t('sizeCm', '%1$s cm'), cm % 1 ? cm.toFixed(1) : cm);
	};

	var CRC_TABLE = null;

	function crc32(bytes) {
		if (!CRC_TABLE) {
			CRC_TABLE = [];
			for (var n = 0; n < 256; n++) {
				var c = n;
				for (var k = 0; k < 8; k++) {
					c = c & 1 ? 0xedb88320 ^ (c >>> 1) : c >>> 1;
				}
				CRC_TABLE[n] = c >>> 0;
			}
		}
		var crc = 0xffffffff;
		for (var i = 0; i < bytes.length; i++) {
			crc = CRC_TABLE[(crc ^ bytes[i]) & 0xff] ^ (crc >>> 8);
		}
		return (crc ^ 0xffffffff) >>> 0;
	}

	/** Add a pHYs chunk so print software knows the intended size. */
	function pngWithDpi(buffer, dpi) {
		var src = new Uint8Array(buffer);
		var ppm = Math.round(dpi / 0.0254);
		var chunk = new Uint8Array(21);
		var view = new DataView(chunk.buffer);
		view.setUint32(0, 9);
		chunk.set([0x70, 0x48, 0x59, 0x73], 4); // "pHYs"
		view.setUint32(8, ppm);
		view.setUint32(12, ppm);
		chunk[16] = 1; // metres
		view.setUint32(17, crc32(chunk.subarray(4, 17)));
		var at = 33; // signature (8) + IHDR chunk (25)
		var out = new Uint8Array(src.length + chunk.length);
		out.set(src.subarray(0, at), 0);
		out.set(chunk, at);
		out.set(src.subarray(at), at + chunk.length);
		return out;
	}

	function deflate(bytes) {
		if (!window.CompressionStream) {
			return Promise.resolve(null);
		}
		var stream = new Blob([bytes]).stream().pipeThrough(new window.CompressionStream('deflate'));
		return new Response(stream).arrayBuffer().then(function (b) {
			return new Uint8Array(b);
		});
	}

	/** A one-page PDF the size of the code, holding it as a lossless image. */
	function pdfFromCanvas(canvas, points) {
		var w = canvas.width;
		var h = canvas.height;
		var rgba = canvas.getContext('2d').getImageData(0, 0, w, h).data;
		var raw = new Uint8Array(w * h * 3);
		for (var i = 0, j = 0; i < rgba.length; i += 4, j += 3) {
			// Flatten onto white (transparent backgrounds print as paper).
			var a = rgba[i + 3] / 255;
			raw[j] = Math.round(rgba[i] * a + 255 * (1 - a));
			raw[j + 1] = Math.round(rgba[i + 1] * a + 255 * (1 - a));
			raw[j + 2] = Math.round(rgba[i + 2] * a + 255 * (1 - a));
		}
		return deflate(raw).then(function (packed) {
			var filter = '/Filter /FlateDecode ';
			if (!packed) {
				packed = raw;
				filter = '';
			}
			var size = points.toFixed(2);
			var enc = new TextEncoder();
			var parts = [];
			var offsets = [];
			var length = 0;
			function add(chunk) {
				var bytes = typeof chunk === 'string' ? enc.encode(chunk) : chunk;
				parts.push(bytes);
				length += bytes.length;
			}
			function obj(n, body, stream) {
				offsets[n] = length;
				add(n + ' 0 obj\n' + body + '\n');
				if (stream) {
					add('stream\n');
					add(stream);
					add('\nendstream\n');
				}
				add('endobj\n');
			}
			var content = 'q ' + size + ' 0 0 ' + size + ' 0 0 cm /Im0 Do Q';
			add('%PDF-1.4\n%\xE2\xE3\xCF\xD3\n');
			obj(1, '<< /Type /Catalog /Pages 2 0 R >>');
			obj(2, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
			obj(3, '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 ' + size + ' ' + size + '] /Resources << /XObject << /Im0 4 0 R >> >> /Contents 5 0 R >>');
			obj(4, '<< /Type /XObject /Subtype /Image /Width ' + w + ' /Height ' + h + ' /ColorSpace /DeviceRGB /BitsPerComponent 8 ' + filter + '/Length ' + packed.length + ' >>', packed);
			obj(5, '<< /Length ' + content.length + ' >>', enc.encode(content));
			var xref = length;
			var table = 'xref\n0 6\n0000000000 65535 f \n';
			for (var n = 1; n <= 5; n++) {
				table += String(offsets[n]).padStart(10, '0') + ' 00000 n \n';
			}
			add(table + 'trailer\n<< /Size 6 /Root 1 0 R >>\nstartxref\n' + xref + '\n%%EOF\n');
			return new Blob(parts, { type: 'application/pdf' });
		});
	}

	function save(blob, filename) {
		var url = URL.createObjectURL(blob);
		var a = document.createElement('a');
		a.href = url;
		a.download = filename;
		document.body.appendChild(a);
		a.click();
		a.remove();
		window.setTimeout(function () {
			URL.revokeObjectURL(url);
		}, 4000);
	}

	CSQR.save = save;

	/**
	 * Make a file. opts: {format: 'png'|'svg'|'pdf', cm: print width in cm
	 * (0 = screen size), name}. Resolves to the Blob (and saves it unless
	 * opts.save === false).
	 */
	var _download = function (d, text, opts) {
		opts = opts || {};
		var dpi = 300;
		var cm = parseFloat(opts.cm) || 0;
		var px = cm ? Math.round((cm / 2.54) * dpi) : parseInt(opts.px, 10) || 512;
		px = Math.max(128, Math.min(px, 6000));
		var name = (opts.name || 'qr-code').replace(/[^a-z0-9-]+/gi, '-').replace(/^-|-$/g, '').toLowerCase() || 'qr-code';
		var fmt = opts.format || 'png';
		var job;

		if (fmt === 'svg') {
			var vector = make(d, text, 1000, 'svg');
			job = vector.getRawData('svg').then(function (blob) {
				return blob.text();
			}).then(function (svg) {
				if (cm) {
					var unit = config.units === 'in' ? (cm / 2.54).toFixed(2) + 'in' : cm + 'cm';
					svg = svg.replace(/<svg([^>]*?)\swidth="[^"]*"\sheight="[^"]*"/, '<svg$1 width="' + unit + '" height="' + unit + '" viewBox="0 0 1000 1000"');
				}
				return new Blob([svg], { type: 'image/svg+xml' });
			});
		} else {
			var raster = make(d, text, px, 'canvas');
			if (fmt === 'pdf') {
				job = raster.getRawData('png').then(blobToImage).then(function (img) {
					var canvas = document.createElement('canvas');
					canvas.width = img.width;
					canvas.height = img.height;
					canvas.getContext('2d').drawImage(img, 0, 0);
					return pdfFromCanvas(canvas, ((cm || 5) / 2.54) * 72);
				});
			} else {
				job = raster.getRawData('png').then(function (blob) {
					return blob.arrayBuffer();
				}).then(function (buffer) {
					return new Blob([cm ? pngWithDpi(buffer, dpi) : new Uint8Array(buffer)], { type: 'image/png' });
				});
			}
		}

		return job.then(function (blob) {
			if (opts.save !== false) {
				save(blob, name + (cm ? '-' + String(cm).replace('.', '-') + 'cm' : '') + '.' + fmt);
			}
			return blob;
		});
	};
	// Wait until the logo is known to load (or not) so the library never hangs.
	CSQR.download = function (d, text, opts) {
		return (d.logo ? probeLogo(d.logo) : Promise.resolve()).then(function () {
			return _download(d, text, opts);
		});
	};

	var _copy = function (d, text) {
		if (!navigator.clipboard || typeof window.ClipboardItem === 'undefined') {
			return Promise.reject(new Error('unsupported'));
		}
		return make(d, text, 600, 'canvas').getRawData('png').then(function (blob) {
			return navigator.clipboard.write([new window.ClipboardItem({ 'image/png': blob })]);
		});
	};
	// Wait until the logo is known to load (or not) so the library never hangs.
	CSQR.copy = function (d, text) {
		return (d.logo ? probeLogo(d.logo) : Promise.resolve()).then(function () {
			return _copy(d, text);
		});
	};

	CSQR.canCopy = function () {
		return !!(navigator.clipboard && typeof window.ClipboardItem !== 'undefined' && window.isSecureContext);
	};
})(window, document);
