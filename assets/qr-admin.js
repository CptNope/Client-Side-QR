/**
 * Anderson QR Code Generator: admin screens. Live design preview with scan
 * check (settings, saved styles), Media Library logo picker, and the
 * shortcode builder.
 */
(function (window, document) {
	'use strict';

	var CSQR = window.CSQR;
	var config = window.csqrAdminConfig || {};
	var i18n = config.i18n || {};
	if (!CSQR) {
		return;
	}

	/* Live preview ------------------------------------------------------ */

	function designInputs(scope) {
		return Array.prototype.slice.call(scope.querySelectorAll('[data-csqr-design]'));
	}

	function readDesign(scope) {
		var out = {};
		designInputs(scope).forEach(function (input) {
			var key = input.getAttribute('data-csqr-design');
			out[key] = input.type === 'checkbox' ? input.checked : input.value;
		});
		return out;
	}

	function writeDesign(scope, values) {
		designInputs(scope).forEach(function (input) {
			var key = input.getAttribute('data-csqr-design');
			if (values[key] === undefined) {
				return;
			}
			if (input.type === 'checkbox') {
				input.checked = !!values[key];
			} else {
				input.value = values[key] === false ? '' : values[key];
			}
		});
	}

	function mountPreview(preview) {
		var split = preview.closest('.csqr-admin__split') || document;
		var scope = split.querySelector('[data-csqr-design-form]') || split;
		var code = preview.querySelector('.csqr-sheet__code');
		var status = CSQR.ui.checkStatus(preview.querySelector('.csqr-check'));
		var sample = preview.getAttribute('data-csqr-sample') || window.location.origin;
		var timer;

		function draw() {
			var design = CSQR.design(readDesign(scope));
			var qr = CSQR.render(code, design, sample, 220);
			status.run(qr, sample, design, function (fixed) {
				writeDesign(scope, CSQR.designToSettings(fixed));
				draw();
			});
		}

		scope.addEventListener('input', function () {
			window.clearTimeout(timer);
			timer = window.setTimeout(draw, 150);
		});
		scope.addEventListener('change', draw);
		scope.addEventListener('csqr:changed', draw);
		draw();
	}

	/* Media Library logo picker ---------------------------------------- */

	function mountMedia(wrap) {
		var input = wrap.querySelector('input[type="url"]');
		var pick = wrap.querySelector('[data-csqr-media-pick]');
		var clear = wrap.querySelector('[data-csqr-media-clear]');
		var frame;

		function changed() {
			input.dispatchEvent(new Event('change', { bubbles: true }));
		}

		if (pick) {
			if (!window.wp || !window.wp.media) {
				pick.hidden = true;
			}
			pick.addEventListener('click', function () {
				if (!frame) {
					frame = window.wp.media({
						title: i18n.chooseLogo || 'Choose a logo',
						button: { text: i18n.useLogo || 'Use this image' },
						library: { type: 'image' },
						multiple: false
					});
					frame.on('select', function () {
						var file = frame.state().get('selection').first().toJSON();
						var sized = file.sizes && (file.sizes.medium || file.sizes.thumbnail);
						input.value = sized ? sized.url : file.url;
						changed();
					});
				}
				frame.open();
			});
		}
		function syncClear() {
			if (clear) {
				clear.hidden = !input.value;
			}
		}
		if (clear) {
			clear.addEventListener('click', function () {
				input.value = '';
				changed();
			});
		}
		input.addEventListener('change', syncClear);
		input.addEventListener('input', syncClear);
		syncClear();
	}

	/* Shortcode builder -------------------------------------------------- */

	function mountBuilder(root) {
		var get = function (name) {
			return root.querySelector('[data-b="' + name + '"]');
		};
		var output = root.querySelector('[data-b-output]');
		var copy = root.querySelector('[data-b-copy]');
		var copied = root.querySelector('[data-b-copied]');

		function row(name) {
			var el = get(name);
			return el ? el.closest('tr') : null;
		}

		function quote(v) {
			return String(v).replace(/"/g, '&quot;').replace(/[\[\]]/g, '');
		}

		function build() {
			var kind = get('kind').value;
			['url', 'link', 'caption', 'downloads'].forEach(function (name) {
				var r = row(name);
				if (r) {
					var show = name === 'url' ? kind === 'url' : name === 'link' ? kind === 'link' : kind !== 'generator';
					r.hidden = !show;
				}
			});
			var parts = [];
			var style = get('style').value;
			if (kind === 'generator') {
				parts.push('client_side_qr');
			} else {
				parts.push('qr_code');
				if (kind === 'url' && get('url').value.trim()) {
					parts.push('url="' + quote(get('url').value.trim()) + '"');
				} else if (kind === 'link' && get('link')) {
					parts.push('link="' + quote(get('link').value) + '"');
				}
				if (get('caption').value.trim()) {
					parts.push('caption="' + quote(get('caption').value.trim()) + '"');
				}
				if (get('downloads').checked) {
					parts.push('downloads="true"');
				}
			}
			if (style) {
				parts.push('style="' + quote(style) + '"');
			}
			output.value = '[' + parts.join(' ') + ']';
		}

		root.addEventListener('input', build);
		root.addEventListener('change', build);
		if (copy) {
			copy.addEventListener('click', function () {
				output.select();
				var done = function () {
					if (copied) {
						copied.hidden = false;
						window.setTimeout(function () {
							copied.hidden = true;
						}, 2000);
					}
				};
				if (navigator.clipboard && window.isSecureContext) {
					navigator.clipboard.writeText(output.value).then(done);
				} else {
					document.execCommand('copy');
					done();
				}
			});
		}
		build();
	}

	document.addEventListener('DOMContentLoaded', function () {
		Array.prototype.forEach.call(document.querySelectorAll('[data-csqr-media]'), mountMedia);
		Array.prototype.forEach.call(document.querySelectorAll('[data-csqr-preview]'), mountPreview);
		Array.prototype.forEach.call(document.querySelectorAll('[data-csqr-builder]'), mountBuilder);
	});
})(window, document);
