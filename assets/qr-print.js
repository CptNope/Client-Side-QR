/**
 * Anderson QR Code Generator: the print sheet. Lays out many codes on
 * Letter or A4 pages (a grid to cut out, table tents or name badges), draws
 * them as SVG so they print sharp, and downloads them all as a zip.
 */
(function (window, document) {
	'use strict';

	var CSQR = window.CSQR;
	var root = document.querySelector('[data-csqr-print]');
	if (!CSQR || !root) {
		return;
	}
	var t = CSQR.t;
	var itemsNode = root.querySelector('[data-csqr-print-items]');
	var pagesNode = root.querySelector('[data-csqr-print-pages]');
	var status = root.querySelector('[data-csqr-print-status]');
	var items = [];
	try {
		items = JSON.parse(itemsNode ? itemsNode.textContent : '[]');
	} catch (e) {
		items = [];
	}
	var design = {};
	try {
		design = CSQR.design(JSON.parse(root.getAttribute('data-csqr-design') || '{}'));
	} catch (e) {
		design = CSQR.design({});
	}

	var controls = {
		layout: root.querySelector('[data-csqr-print-layout]'),
		paper: root.querySelector('[data-csqr-print-paper]'),
		size: root.querySelector('[data-csqr-print-size]'),
		extra: root.querySelector('[data-csqr-print-extra]'),
		under: root.querySelector('[data-csqr-print-under]')
	};

	// Paper in millimetres, with a 10 mm margin most printers can reach.
	var PAPER = { letter: [215.9, 279.4], a4: [210, 297] };
	var MARGIN = 10;
	var svgCache = {};
	var uid = 0;

	function el(tag, cls, text) {
		var n = document.createElement(tag);
		if (cls) {
			n.className = cls;
		}
		if (text) {
			n.textContent = text;
		}
		return n;
	}

	/** SVG markup for a code (cached per text), drawn by the core. */
	function svgFor(text) {
		if (!svgCache[text]) {
			svgCache[text] = CSQR.download(design, text, { format: 'svg', save: false }).then(function (blob) {
				return blob.text();
			});
		}
		return svgCache[text];
	}

	/** A code at a physical size, filled in once its SVG is ready. */
	function code(item, mm) {
		var box = el('div', 'csqr-print__code');
		box.style.width = mm + 'mm';
		box.style.height = mm + 'mm';
		box.setAttribute('role', 'img');
		box.setAttribute('aria-label', CSQR.format(t('codeFor', 'QR code for %1$s'), item.text));
		svgFor(item.text).then(function (svg) {
			// The library reuses the same ids in every SVG; on one page they'd
			// clash and codes would borrow each other's shapes. Make them unique.
			var suffix = '-' + (++uid);
			box.innerHTML = svg.replace(/^<\?xml[^>]*>\s*/, '').replace(/id="([^"]+)"/g, 'id="$1' + suffix + '"').replace(/url\(['"]?#([^'")]+)['"]?\)/g, "url('#$1" + suffix + "')").replace(/href="#([^"]+)"/g, 'href="#$1' + suffix + '"');
			var node = box.querySelector('svg');
			if (node) {
				// Scale the drawing to the box: keep its coordinates, drop its fixed size.
				if (!node.getAttribute('viewBox')) {
					node.setAttribute('viewBox', '0 0 ' + (parseFloat(node.getAttribute('width')) || 1000) + ' ' + (parseFloat(node.getAttribute('height')) || 1000));
				}
				node.removeAttribute('width');
				node.removeAttribute('height');
				node.setAttribute('aria-hidden', 'true');
			}
		});
		return box;
	}

	function caption(item, cls) {
		var under = controls.under ? controls.under.value : 'address';
		var wrap = el('div', 'csqr-print__text ' + (cls || ''));
		if (under === 'none') {
			return wrap;
		}
		wrap.appendChild(el('strong', 'csqr-print__title', item.title));
		var lines = (item.lines || []).slice();
		if (under === 'address' && item.address) {
			lines.push(item.address);
		}
		if (under === 'spot' && item.spot) {
			lines.push(item.spot);
		}
		lines.forEach(function (line) {
			if (line) {
				wrap.appendChild(el('span', 'csqr-print__sub', line));
			}
		});
		var extra = controls.extra.value.trim();
		if (extra) {
			wrap.appendChild(el('span', 'csqr-print__extra', extra));
		}
		return wrap;
	}

	function page(paper) {
		var p = el('section', 'csqr-print__page csqr-print__page--' + paper);
		p.style.width = PAPER[paper][0] + 'mm';
		p.style.height = PAPER[paper][1] + 'mm';
		pagesNode.appendChild(p);
		return p;
	}

	function layoutGrid(paper) {
		var codeMm = parseFloat(controls.size.value) * 10;
		var cellW = codeMm + 10;
		var cellH = codeMm + 22; // Room for two lines of text.
		var usableW = PAPER[paper][0] - 2 * MARGIN;
		var usableH = PAPER[paper][1] - 2 * MARGIN;
		var cols = Math.max(1, Math.floor(usableW / cellW));
		var rows = Math.max(1, Math.floor(usableH / cellH));
		var per = cols * rows;
		for (var i = 0; i < items.length; i += per) {
			var p = page(paper);
			var grid = el('div', 'csqr-print__grid');
			grid.style.gridTemplateColumns = 'repeat(' + cols + ', ' + (usableW / cols) + 'mm)';
			grid.style.gridAutoRows = (usableH / rows) + 'mm';
			items.slice(i, i + per).forEach(function (item) {
				var cell = el('div', 'csqr-print__cell');
				cell.appendChild(code(item, codeMm));
				cell.appendChild(caption(item));
				grid.appendChild(cell);
			});
			p.appendChild(grid);
		}
	}

	function layoutTent(paper) {
		var half = (PAPER[paper][1] - 2 * MARGIN) / 2;
		var codeMm = Math.min(PAPER[paper][0] - 2 * MARGIN - 20, half - 45);
		items.forEach(function (item) {
			var p = page(paper);
			var tent = el('div', 'csqr-print__tent');
			// The top half is upside down, so both sides read the right way up once folded.
			[true, false].forEach(function (flipped) {
				var side = el('div', 'csqr-print__tent-side' + (flipped ? ' is-flipped' : ''));
				side.style.height = half + 'mm';
				side.appendChild(caption(item, 'csqr-print__text--big'));
				side.appendChild(code(item, codeMm));
				tent.appendChild(side);
			});
			p.appendChild(tent);
		});
	}

	function layoutBadge(paper) {
		var w = (PAPER[paper][0] - 2 * MARGIN) / 2;
		var h = (PAPER[paper][1] - 2 * MARGIN) / 2;
		var codeMm = Math.min(w - 20, h - 60);
		for (var i = 0; i < items.length; i += 4) {
			var p = page(paper);
			var grid = el('div', 'csqr-print__grid csqr-print__grid--badges');
			grid.style.gridTemplateColumns = 'repeat(2, ' + w + 'mm)';
			grid.style.gridAutoRows = h + 'mm';
			items.slice(i, i + 4).forEach(function (item) {
				var cell = el('div', 'csqr-print__cell csqr-print__cell--badge');
				cell.appendChild(caption(item, 'csqr-print__text--big'));
				cell.appendChild(code(item, codeMm));
				grid.appendChild(cell);
			});
			p.appendChild(grid);
		}
	}

	function render() {
		var layout = controls.layout.value;
		var paper = controls.paper.value in PAPER ? controls.paper.value : 'letter';
		pagesNode.innerHTML = '';
		pagesNode.setAttribute('data-layout', layout);
		root.querySelectorAll('[data-csqr-print-for]').forEach(function (n) {
			n.hidden = n.getAttribute('data-csqr-print-for') !== layout;
		});
		// Tell the printer the paper size and that there are no extra margins.
		var style = document.getElementById('csqr-print-page');
		if (!style) {
			style = el('style');
			style.id = 'csqr-print-page';
			document.head.appendChild(style);
		}
		style.textContent = '@page { size: ' + (paper === 'a4' ? 'A4' : 'letter') + '; margin: 0; }';
		if (layout === 'tent') {
			layoutTent(paper);
		} else if (layout === 'badge') {
			layoutBadge(paper);
		} else {
			layoutGrid(paper);
		}
		try {
			window.localStorage.setItem('csqrPrint', JSON.stringify({ layout: layout, paper: paper, size: controls.size.value }));
		} catch (e) {
			// Remembering the choice is a convenience only.
		}
	}

	/* Zip (stored, no compression: PNG and SVG barely shrink) ------------- */

	var CRC = (function () {
		var table = new Uint32Array(256);
		for (var n = 0; n < 256; n++) {
			var c = n;
			for (var k = 0; k < 8; k++) {
				c = c & 1 ? 0xedb88320 ^ (c >>> 1) : c >>> 1;
			}
			table[n] = c >>> 0;
		}
		return function (bytes) {
			var crc = 0xffffffff;
			for (var i = 0; i < bytes.length; i++) {
				crc = table[(crc ^ bytes[i]) & 0xff] ^ (crc >>> 8);
			}
			return (crc ^ 0xffffffff) >>> 0;
		};
	})();

	function zip(files) {
		var enc = new TextEncoder();
		var parts = [];
		var central = [];
		var offset = 0;
		files.forEach(function (f) {
			var name = enc.encode(f.name);
			var crc = CRC(f.data);
			var head = new DataView(new ArrayBuffer(30));
			head.setUint32(0, 0x04034b50, true);
			head.setUint16(4, 20, true);
			head.setUint16(6, 0x0800, true); // UTF-8 names.
			head.setUint32(14, crc, true);
			head.setUint32(18, f.data.length, true);
			head.setUint32(22, f.data.length, true);
			head.setUint16(26, name.length, true);
			parts.push(new Uint8Array(head.buffer), name, f.data);
			var dir = new DataView(new ArrayBuffer(46));
			dir.setUint32(0, 0x02014b50, true);
			dir.setUint16(4, 20, true);
			dir.setUint16(6, 20, true);
			dir.setUint16(8, 0x0800, true);
			dir.setUint32(16, crc, true);
			dir.setUint32(20, f.data.length, true);
			dir.setUint32(24, f.data.length, true);
			dir.setUint16(28, name.length, true);
			dir.setUint32(42, offset, true);
			central.push(new Uint8Array(dir.buffer), name);
			offset += 30 + name.length + f.data.length;
		});
		var size = central.reduce(function (a, b) {
			return a + b.length;
		}, 0);
		var end = new DataView(new ArrayBuffer(22));
		end.setUint32(0, 0x06054b50, true);
		end.setUint16(8, files.length, true);
		end.setUint16(10, files.length, true);
		end.setUint32(12, size, true);
		end.setUint32(16, offset, true);
		return new Blob(parts.concat(central, [new Uint8Array(end.buffer)]), { type: 'application/zip' });
	}
	CSQR.zip = zip;

	function downloadAll(button) {
		// Tents and badges size their codes to the page; the files use 5 cm.
		var cm = controls.layout.value === 'grid' ? parseFloat(controls.size.value) : 5;
		var used = {};
		var done = 0;
		button.disabled = true;
		status.textContent = t('zipWorking', 'Making the files…');
		var jobs = items.map(function (item) {
			var base = item.name || 'qr-code';
			var name = base;
			for (var n = 2; used[name]; n++) {
				name = base + '-' + n;
			}
			used[name] = true;
			var opts = { cm: cm, save: false };
			return Promise.all([
				CSQR.download(design, item.text, Object.assign({ format: 'png' }, opts)),
				CSQR.download(design, item.text, Object.assign({ format: 'svg' }, opts))
			]).then(function (blobs) {
				done++;
				status.textContent = CSQR.format(t('zipProgress', 'Made %1$s of %2$s…'), done, items.length);
				return Promise.all(blobs.map(function (b) {
					return b.arrayBuffer();
				})).then(function (buffers) {
					return [
						{ name: name + '.png', data: new Uint8Array(buffers[0]) },
						{ name: name + '.svg', data: new Uint8Array(buffers[1]) }
					];
				});
			});
		});
		Promise.all(jobs).then(function (pairs) {
			var files = [].concat.apply([], pairs);
			CSQR.save(zip(files), 'qr-codes.zip');
			status.textContent = CSQR.format(t('zipDone', 'Downloaded %1$s codes as PNG and SVG.'), items.length) + ' ' + CSQR.format(t('zipSize', 'Each is %1$s wide.'), CSQR.sizeLabel(cm));
		}).catch(function () {
			status.textContent = t('downloadError', 'That download didn’t work in this browser.');
		}).then(function () {
			button.disabled = false;
		});
	}

	try {
		var saved = JSON.parse(window.localStorage.getItem('csqrPrint') || '{}');
		['layout', 'paper', 'size'].forEach(function (k) {
			if (saved[k] && controls[k].querySelector('option[value="' + saved[k] + '"]')) {
				controls[k].value = saved[k];
			}
		});
	} catch (e) {
		// No saved choice.
	}
	var asked = new URLSearchParams(window.location.search).get('layout');
	if (asked && controls.layout.querySelector('option[value="' + asked + '"]')) {
		controls.layout.value = asked;
	}

	['layout', 'paper', 'size', 'under'].forEach(function (k) {
		if (controls[k]) {
			controls[k].addEventListener('change', render);
		}
	});
	var timer;
	controls.extra.addEventListener('input', function () {
		window.clearTimeout(timer);
		timer = window.setTimeout(render, 250);
	});
	root.querySelector('[data-csqr-print-go]').addEventListener('click', function () {
		window.print();
	});
	root.querySelector('[data-csqr-print-zip]').addEventListener('click', function (e) {
		downloadAll(e.currentTarget);
	});
	render();
})(window, document);
