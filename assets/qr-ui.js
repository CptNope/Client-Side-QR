/**
 * Anderson QR Code Generator: small DOM helpers shared by every screen that
 * shows a code: the scan-check status line, the print/download panel, and
 * the read-only QR card (block, page QR, short-link previews).
 */
(function (window, document) {
	'use strict';

	var CSQR = window.CSQR;
	if (!CSQR) {
		return;
	}
	var t = CSQR.t;
	var ui = CSQR.ui = {};

	function el(tag, attrs, children) {
		var node = document.createElement(tag);
		Object.keys(attrs || {}).forEach(function (key) {
			if (key === 'text') {
				node.textContent = attrs[key];
			} else if (key === 'className') {
				node.className = attrs[key];
			} else if (attrs[key] !== null && attrs[key] !== undefined && attrs[key] !== false) {
				node.setAttribute(key, attrs[key] === true ? '' : attrs[key]);
			}
		});
		(children || []).forEach(function (child) {
			if (child) {
				node.appendChild(child);
			}
		});
		return node;
	}

	ui.el = el;

	/**
	 * Scan-check status. onFix(newDesign) is called when a fix is chosen; leave
	 * it out to show problems without fix buttons.
	 */
	ui.checkStatus = function (node) {
		node.classList.add('csqr-check');
		node.setAttribute('role', 'status');
		node.setAttribute('aria-live', 'polite');
		var mark = el('span', { className: 'csqr-check__mark', 'aria-hidden': 'true' });
		var title = el('span', { className: 'csqr-check__title' });
		var list = el('ul', { className: 'csqr-check__issues' });
		node.innerHTML = '';
		node.appendChild(el('p', { className: 'csqr-check__line' }, [mark, title]));
		node.appendChild(list);

		var token = 0;

		return {
			empty: function (message) {
				token++;
				node.setAttribute('data-level', 'empty');
				title.textContent = message || '';
				list.innerHTML = '';
			},
			run: function (qr, text, design, onFix) {
				var mine = ++token;
				node.setAttribute('data-level', 'pending');
				title.textContent = CSQR.checkHeadline(null);
				list.innerHTML = '';
				return CSQR.check(qr, text, design).then(function (result) {
					if (mine !== token) {
						return result; // A newer code was drawn meanwhile.
					}
					node.setAttribute('data-level', result.level);
					title.textContent = CSQR.checkHeadline(result);
					result.issues.forEach(function (item) {
						var li = el('li', { className: 'csqr-check__issue is-' + item.severity }, [el('span', { text: item.message })]);
						if (item.fix && onFix) {
							var btn = el('button', { type: 'button', className: 'csqr-fix', text: item.fix.label });
							btn.addEventListener('click', function () {
								onFix(item.fix.apply(Object.assign({}, design)));
							});
							li.appendChild(document.createTextNode(' '));
							li.appendChild(btn);
						}
						list.appendChild(li);
					});
					return result;
				});
			}
		};
	};

	/**
	 * Print size + download buttons. getCode() returns {design, text, name} or
	 * null when there is nothing to download yet.
	 */
	ui.exportPanel = function (node, getCode, opts) {
		opts = opts || {};
		var uid = 'csqr-x' + Math.random().toString(36).slice(2, 8);
		node.classList.add('csqr-export');
		node.innerHTML = '';

		var select = el('select', { id: uid + '-size', className: 'csqr-export__size' });
		CSQR.PRINT_SIZES.forEach(function (size) {
			select.appendChild(el('option', { value: String(size.cm), text: size.label }));
		});
		select.appendChild(el('option', { value: 'custom', text: t('sizeCustom', 'Custom size…') }));
		select.value = String(opts.cm || 5);

		var custom = el('input', { type: 'number', id: uid + '-custom', className: 'csqr-export__custom', min: '1', max: '200', step: '0.5', value: '5', 'aria-label': t('customWidth', 'Width in centimetres'), hidden: true });
		var unit = el('span', { className: 'csqr-export__unit', text: 'cm', hidden: true });
		var tip = el('p', { className: 'csqr-export__tip', id: uid + '-tip' });

		var label = el('label', { for: uid + '-size', className: 'csqr-export__label', text: t('printSize', 'Print size') });
		var sizeRow = el('div', { className: 'csqr-export__row' }, [label, select, custom, unit]);
		var buttons = el('div', { className: 'csqr-export__buttons' });

		function cm() {
			if (select.value === 'custom') {
				var v = parseFloat(custom.value);
				return v > 0 ? Math.min(v, 200) : 5;
			}
			return parseFloat(select.value) || 5;
		}

		function updateTip() {
			var size = cm();
			tip.textContent = CSQR.format(t('scanTip', 'Prints %1$s wide and scans from %2$s away.'), CSQR.sizeLabel(size), CSQR.scanDistance(size)) + (size < 2 ? ' ' + t('tooSmall', 'Below 2 cm, many phones struggle.') : '');
		}

		function button(label, fmt) {
			var b = el('button', { type: 'button', className: 'csqr-button csqr-export__' + fmt, text: label, 'aria-describedby': uid + '-tip' });
			b.addEventListener('click', function () {
				var code = getCode();
				if (!code) {
					return;
				}
				b.disabled = true;
				var job = fmt === 'copy' ? CSQR.copy(code.design, code.text) : CSQR.download(code.design, code.text, { format: fmt, cm: cm(), name: code.name });
				job.then(function () {
					if (fmt === 'copy' && opts.onMessage) {
						opts.onMessage(t('copied', 'Copied. Paste it into your document or message.'));
					}
				}).catch(function () {
					if (opts.onMessage) {
						opts.onMessage(fmt === 'copy' ? t('copyError', 'Your browser didn’t allow copying. Download it instead.') : t('downloadError', 'That download didn’t work in this browser.'));
					}
				}).then(function () {
					b.disabled = false;
				});
			});
			buttons.appendChild(b);
			return b;
		}

		button(t('downloadPng', 'Download PNG'), 'png');
		button(t('downloadSvg', 'SVG'), 'svg');
		button(t('downloadPdf', 'PDF'), 'pdf');
		if (CSQR.canCopy() && opts.copy !== false) {
			button(t('copyImage', 'Copy'), 'copy');
		}

		select.addEventListener('change', function () {
			var isCustom = select.value === 'custom';
			custom.hidden = !isCustom;
			unit.hidden = !isCustom;
			updateTip();
		});
		custom.addEventListener('input', updateTip);
		updateTip();

		var waiting = el('p', { className: 'csqr-export__waiting', text: opts.waiting || t('fillFirst', 'Fill in the form to download your code.'), hidden: true });

		node.appendChild(sizeRow);
		node.appendChild(tip);
		node.appendChild(buttons);
		node.appendChild(waiting);

		return {
			setEnabled: function (on) {
				Array.prototype.forEach.call(buttons.querySelectorAll('button'), function (b) {
					b.disabled = !on;
				});
				waiting.hidden = !!on;
			}
		};
	};

	/** Read-only QR card: <figure class="csqr-card" data-csqr-text data-csqr-design>. */
	ui.mountCard = function (card) {
		if (card.csqrMounted) {
			return card.csqrMounted;
		}
		var text = card.getAttribute('data-csqr-text') || '';
		var settings = {};
		try {
			settings = JSON.parse(card.getAttribute('data-csqr-design') || '{}');
		} catch (e) {
			settings = {};
		}
		var design = CSQR.design(settings);
		var size = parseInt(settings.qrSize, 10) || 200;
		var codeNode = card.querySelector('.csqr-sheet__code');
		var exportNode = card.querySelector('.csqr-export');
		var checkNode = card.querySelector('.csqr-check');
		var name = card.getAttribute('data-csqr-name') || 'qr-code';
		var qr = null;
		var status = checkNode ? ui.checkStatus(checkNode) : null;

		function draw() {
			if (!text || !codeNode) {
				return;
			}
			qr = CSQR.render(codeNode, design, text, size);
			if (status) {
				var mode = checkNode.getAttribute('data-csqr-check');
				status.run(qr, text, design, null).then(function (result) {
					if (!mode || !result) {
						return;
					}
					// Editors always see the result; visitors only when it may not scan.
					var show = mode === 'editor' || result.level === 'fail';
					checkNode.hidden = !show;
					if (show && mode === 'editor') {
						checkNode.appendChild(el('p', { className: 'csqr-check__note', text: t('editorOnly', 'Only people who can edit the site see this check.') }));
					}
					if (show && mode === 'quiet') {
						var list = checkNode.querySelector('.csqr-check__issues');
						if (list) {
							list.hidden = true;
						}
					}
				});
			}
		}

		if (exportNode) {
			ui.exportPanel(exportNode, function () {
				return text ? { design: design, text: text, name: name } : null;
			}, { copy: card.getAttribute('data-csqr-copy') !== '0' });
		}
		draw();
		card.csqrMounted = { redraw: draw };
		return card.csqrMounted;
	};

	ui.mountAll = function (root) {
		Array.prototype.forEach.call((root || document).querySelectorAll('.csqr-card[data-csqr-text]'), ui.mountCard);
	};

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', function () {
			ui.mountAll();
		});
	} else {
		ui.mountAll();
	}
})(window, document);
