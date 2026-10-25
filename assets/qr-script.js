/**
 * Anderson QR Code Generator: the visitor generator (block csqr/generator and
 * the [client_side_qr] shortcode). Builds the code as people type, checks
 * it scans, and offers print-ready downloads.
 */
(function (window, document) {
	'use strict';

	var CSQR = window.CSQR;
	var config = window.csqrFrontendConfig || {};

	function init(root) {
		if (root.csqrReady || !CSQR || !window.QRCodeStyling) {
			return;
		}
		root.csqrReady = true;

		var settings = {};
		try {
			settings = JSON.parse(root.getAttribute('data-csqr-design') || '{}');
		} catch (e) {
			settings = {};
		}
		var tabs = Array.prototype.slice.call(root.querySelectorAll('.csqr-kind'));
		var tabRow = root.querySelector('.csqr-kinds');
		if (tabRow) {
			// Fade the edge only while some tabs are out of view.
			var edge = function () {
				var more = tabRow.scrollWidth - tabRow.clientWidth - tabRow.scrollLeft > 2;
				tabRow.classList.toggle('has-more', more);
			};
			tabRow.addEventListener('scroll', edge, { passive: true });
			window.addEventListener('resize', edge);
			edge();
		}
		var panels = Array.prototype.slice.call(root.querySelectorAll('.csqr-panel'));
		var sheet = root.querySelector('.csqr-sheet');
		var codeNode = root.querySelector('.csqr-sheet__code');
		var empty = root.querySelector('.csqr-sheet__empty');
		var status = CSQR.ui.checkStatus(root.querySelector('.csqr-check'));
		var overrides = {};
		var current = null;
		var timer = null;

		function activeType() {
			var panel = panels.filter(function (p) {
				return !p.hidden;
			})[0];
			return panel ? panel.getAttribute('data-type') : 'url';
		}

		function fields(type) {
			var panel = root.querySelector('.csqr-panel[data-type="' + type + '"]');
			var out = {};
			Array.prototype.forEach.call(panel ? panel.querySelectorAll('[data-field]') : [], function (input) {
				out[input.getAttribute('data-field')] = input.type === 'checkbox' ? input.checked : input.value;
			});
			return out;
		}

		function visitorDesign() {
			var out = {};
			Array.prototype.forEach.call(root.querySelectorAll('[data-design]'), function (input) {
				out[input.getAttribute('data-design')] = input.type === 'checkbox' ? input.checked : input.value;
			});
			return out;
		}

		function size() {
			var range = root.querySelector('[data-design="qrSize"]');
			var px = parseInt(range ? range.value : settings.qrSize, 10) || 240;
			return Math.max(120, Math.min(px, 480));
		}

		function draw() {
			var type = activeType();
			var builder = CSQR.payload[type];
			var text = builder ? builder(fields(type)) : '';
			if (!text) {
				current = null;
				codeNode.innerHTML = '';
				sheet.classList.add('is-empty');
				if (empty) {
					empty.hidden = false;
				}
				status.empty('');
				exportPanel.setEnabled(false);
				return;
			}
			var design = CSQR.design(Object.assign({}, settings, visitorDesign(), overrides));
			sheet.classList.remove('is-empty');
			if (empty) {
				empty.hidden = true;
			}
			var qr = CSQR.render(codeNode, design, text, size());
			current = { design: design, text: text, name: 'qr-' + type };
			codeNode.setAttribute('aria-label', /^https?:\/\//i.test(text) ? CSQR.format(CSQR.t('codeFor', 'QR code for %1$s'), text) : CSQR.t('codeReady', 'Your QR code'));
			exportPanel.setEnabled(true);
			status.run(qr, text, design, function (fixed) {
				overrides = CSQR.designToSettings(fixed);
				syncControls(overrides);
				draw();
			});
		}

		// Reflect a one-click fix in the visitor's own controls, if shown.
		function syncControls(values) {
			Array.prototype.forEach.call(root.querySelectorAll('[data-design]'), function (input) {
				var key = input.getAttribute('data-design');
				if (values[key] !== undefined) {
					if (input.type === 'checkbox') {
						input.checked = !!values[key];
					} else {
						input.value = values[key];
					}
				}
			});
		}

		function soon() {
			window.clearTimeout(timer);
			timer = window.setTimeout(draw, 180);
		}

		function select(tab, focus) {
			tabs.forEach(function (t) {
				var on = t === tab;
				t.setAttribute('aria-selected', on ? 'true' : 'false');
				t.tabIndex = on ? 0 : -1;
			});
			panels.forEach(function (p) {
				p.hidden = p.getAttribute('data-type') !== tab.getAttribute('data-type');
			});
			if (focus) {
				tab.focus();
			}
			draw();
		}

		tabs.forEach(function (tab, i) {
			tab.addEventListener('click', function () {
				select(tab, false);
			});
			tab.addEventListener('keydown', function (e) {
				var next = { ArrowRight: i + 1, ArrowDown: i + 1, ArrowLeft: i - 1, ArrowUp: i - 1, Home: 0, End: tabs.length - 1 }[e.key];
				if (next === undefined) {
					return;
				}
				e.preventDefault();
				select(tabs[(next + tabs.length) % tabs.length], true);
			});
		});

		root.addEventListener('input', function (e) {
			if (e.target.matches('[data-design]')) {
				overrides = {};
				var out = e.target.parentNode.querySelector('output');
				if (out && e.target.type === 'range') {
					out.textContent = e.target.value + ' px';
				}
			}
			if (e.target.closest('.csqr-generator__form')) {
				soon();
			}
		});
		root.addEventListener('change', function (e) {
			if (e.target.closest('.csqr-generator__form')) {
				soon();
			}
		});

		var exportPanel = CSQR.ui.exportPanel(root.querySelector('.csqr-export'), function () {
			return current;
		}, {
			onMessage: function (message) {
				status.empty(message);
				window.setTimeout(draw, 2600);
			}
		});

		status.empty(config.i18n && config.i18n.empty ? '' : '');
		draw();
	}

	function initAll() {
		Array.prototype.forEach.call(document.querySelectorAll('.csqr-generator'), init);
	}

	CSQR && (CSQR.initGenerators = initAll);

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', initAll);
	} else {
		initAll();
	}
})(window, document);
