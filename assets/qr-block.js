/**
 * Anderson QR Code Generator: the "QR code generator" block (csqr/generator),
 * where visitors make their own codes. The editor shows a static preview of
 * the form and the code style; the live form runs on the page.
 */
(function (window, wp) {
	'use strict';

	var CSQR = window.CSQR;
	if (!CSQR || !CSQR.editor || !wp || !wp.blocks) {
		return;
	}
	var el = wp.element.createElement;
	var __ = wp.i18n.__;
	var c = wp.components;
	var be = wp.blockEditor;
	var config = CSQR.editor.config;
	var settings = config.settings || {};

	var KINDS = [
		['enableUrl', __('Link', 'anderson-qr-code-generator')],
		['enableWifi', __('WiFi', 'anderson-qr-code-generator')],
		['enableVcard', __('Contact', 'anderson-qr-code-generator')],
		['enableEmail', __('Email', 'anderson-qr-code-generator')],
		['enableSms', __('Text message', 'anderson-qr-code-generator')],
		['enableCrypto', __('Crypto', 'anderson-qr-code-generator')],
		['enablePaypal', __('PayPal', 'anderson-qr-code-generator')]
	];

	function value(a, key) {
		return a[key] !== undefined ? a[key] : settings[key];
	}

	function toggle(a, set, key, label, help) {
		return el(c.ToggleControl, {
			key: key,
			label: label,
			help: help || null,
			checked: !!value(a, key),
			onChange: function (v) {
				var u = {};
				u[key] = v;
				set(u);
			},
			__nextHasNoMarginBottom: true
		});
	}

	function Edit(props) {
		var a = props.attributes;
		var set = props.setAttributes;
		var on = KINDS.filter(function (k) {
			return value(a, k[0]);
		});
		if (!on.length) {
			on = [KINDS[0]];
		}
		var themeColors = value(a, 'uiUseThemeColors') !== false;
		var themeFont = value(a, 'uiUseThemeFont') !== false;
		var style = {};
		if (!themeColors) {
			['uiSurfaceColor', 'uiTextColor', 'uiAccentColor'].forEach(function (k) {
				if (value(a, k)) {
					style['--csqr-ui-' + k.replace('ui', '').replace('Color', '').toLowerCase()] = value(a, k);
				}
			});
		}
		if (!themeFont && value(a, 'uiFontFamily')) {
			style['--csqr-ui-font'] = value(a, 'uiFontFamily');
		}
		var blockProps = be.useBlockProps({ className: 'csqr csqr-container csqr-generator csqr-generator--preview', style: style });
		var sample = (config.previewUrl || 'https://example.com/');

		return el('div', blockProps,
			el(be.InspectorControls, null,
				el(c.PanelBody, { title: __('Form', 'anderson-qr-code-generator'), initialOpen: true },
					el(c.__experimentalVStack || 'div', { spacing: 4 },
						el(c.TextControl, {
							label: __('Heading', 'anderson-qr-code-generator'),
							value: a.title || '',
							placeholder: __('Make a QR code', 'anderson-qr-code-generator'),
							onChange: function (v) {
								set({ title: v });
							},
							__nextHasNoMarginBottom: true
						}),
						el(c.ToggleControl, {
							label: __('Show the heading', 'anderson-qr-code-generator'),
							help: __('Turn off when the page title already says what the form is for. Screen readers still hear it.', 'anderson-qr-code-generator'),
							checked: a.showTitle !== false,
							onChange: function (v) {
								set({ showTitle: v });
							},
							__nextHasNoMarginBottom: true
						}),
						el('fieldset', null,
							el('legend', { className: 'components-base-control__label' }, __('Kinds of code', 'anderson-qr-code-generator')),
							KINDS.map(function (k) {
								return el(c.CheckboxControl, {
									key: k[0],
									label: k[1],
									checked: !!value(a, k[0]),
									onChange: function (v) {
										var u = {};
										u[k[0]] = v;
										var remaining = KINDS.filter(function (x) {
											return x[0] === k[0] ? v : value(a, x[0]);
										});
										if (!remaining.length) {
											u.enableUrl = true;
										}
										set(u);
									},
									__nextHasNoMarginBottom: true
								});
							})
						)
					)
				),
				el(c.PanelBody, { title: __('Visitors can change', 'anderson-qr-code-generator'), initialOpen: false },
					el(c.__experimentalVStack || 'div', { spacing: 4 },
						toggle(a, set, 'allowUserColor', __('Colors', 'anderson-qr-code-generator')),
						toggle(a, set, 'allowUserSize', __('Size on screen', 'anderson-qr-code-generator')),
						toggle(a, set, 'allowUserCorrectLevel', __('Error correction', 'anderson-qr-code-generator'))
					)
				),
				el(CSQR.editor.DesignPanel, { attributes: a, setAttributes: set }),
				el(c.PanelBody, { title: __('Look', 'anderson-qr-code-generator'), initialOpen: false },
					el(c.__experimentalVStack || 'div', { spacing: 4 },
						toggle(a, set, 'uiUseThemeColors', __('Use the theme’s colors', 'anderson-qr-code-generator')),
						themeColors ? null : el(c.TextControl, { label: __('Background', 'anderson-qr-code-generator'), value: value(a, 'uiSurfaceColor') || '', placeholder: '#ffffff', onChange: function (v) {
							set({ uiSurfaceColor: v });
						}, __nextHasNoMarginBottom: true }),
						themeColors ? null : el(c.TextControl, { label: __('Text', 'anderson-qr-code-generator'), value: value(a, 'uiTextColor') || '', placeholder: '#1e1e1e', onChange: function (v) {
							set({ uiTextColor: v });
						}, __nextHasNoMarginBottom: true }),
						themeColors ? null : el(c.TextControl, { label: __('Accent', 'anderson-qr-code-generator'), value: value(a, 'uiAccentColor') || '', placeholder: '#2271b1', onChange: function (v) {
							set({ uiAccentColor: v });
						}, __nextHasNoMarginBottom: true }),
						toggle(a, set, 'uiUseThemeFont', __('Use the theme’s font', 'anderson-qr-code-generator')),
						themeFont ? null : el(c.TextControl, { label: __('Font', 'anderson-qr-code-generator'), value: value(a, 'uiFontFamily') || '', placeholder: 'Georgia, serif', onChange: function (v) {
							set({ uiFontFamily: v });
						}, __nextHasNoMarginBottom: true })
					)
				)
			),
			el('div', { className: 'csqr-generator__form' },
				a.showTitle === false ? null : el('h2', { className: 'csqr-generator__title' }, a.title || __('Make a QR code', 'anderson-qr-code-generator')),
				on.length > 1 ? el('div', { className: 'csqr-kinds' }, on.map(function (k, i) {
					return el('span', { key: k[0], className: 'csqr-kind', 'aria-selected': i === 0 ? 'true' : 'false' }, k[1]);
				})) : null,
				el('div', { className: 'csqr-fields' },
					el('div', { className: 'csqr-field' },
						el('span', { className: 'csqr-field__label' }, on[0][1] === KINDS[0][1] ? __('Link or text', 'anderson-qr-code-generator') : on[0][1]),
						el('input', { className: 'csqr-input', type: 'text', value: sample, readOnly: true, tabIndex: -1 })
					)
				)
			),
			el(CSQR.editor.Preview, {
				className: 'csqr-generator__result',
				text: sample,
				design: CSQR.editor.effective(a),
				size: 220,
				onFix: a.styleId ? null : function (values) {
					set(values);
				}
			})
		);
	}

	var attributes = { title: { type: 'string' }, showTitle: { type: 'boolean', default: true }, styleId: { type: 'string' }, logoId: { type: 'number' }, logoSize: { type: 'number' }, transparent: { type: 'boolean' } };
	Object.keys(settings).forEach(function (key) {
		if (attributes[key]) {
			return;
		}
		var v = settings[key];
		attributes[key] = { type: typeof v === 'boolean' ? 'boolean' : typeof v === 'number' ? 'number' : Array.isArray(v) ? 'array' : 'string' };
	});

	wp.blocks.registerBlockType('csqr/generator', {
		apiVersion: 3,
		title: __('QR code generator', 'anderson-qr-code-generator'),
		description: __('A form where visitors make their own QR codes for links, WiFi, contacts and more. Codes are made in their browser.', 'anderson-qr-code-generator'),
		category: 'widgets',
		icon: 'smartphone',
		keywords: ['qr', 'qr code', 'generator', 'maker'],
		attributes: attributes,
		edit: Edit,
		save: function () {
			return null;
		}
	});
})(window, window.wp);
