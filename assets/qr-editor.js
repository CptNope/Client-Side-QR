/**
 * Anderson QR Code Generator: block editor pieces shared by the QR code and
 * QR generator blocks: the design panel (saved style, colors, dots, logo
 * from the Media Library) and a live preview that runs the scan check and
 * offers one-click fixes.
 */
(function (window, wp) {
	'use strict';

	var CSQR = window.CSQR;
	if (!CSQR || !wp || !wp.element) {
		return;
	}
	var el = wp.element.createElement;
	var useEffect = wp.element.useEffect;
	var useRef = wp.element.useRef;
	var __ = wp.i18n.__;
	var c = wp.components;
	var be = wp.blockEditor;
	var config = window.csqrEditorConfig || {};

	var DESIGN_KEYS = ['qrColorDark', 'qrColorDark2', 'qrColorLight', 'qrGradient', 'qrDotStyle', 'qrEyeStyle', 'qrEyeColor', 'qrCorrectLevel', 'logoUrl', 'logoSize', 'transparent'];

	function styleById(id) {
		return (config.styles || []).filter(function (s) {
			return s.id === id;
		})[0];
	}

	/** The design a block is drawn with: site default < block settings, or its saved style. */
	function effective(attributes) {
		var base = Object.assign({}, config.defaults || {});
		var style = attributes.styleId ? styleById(attributes.styleId) : null;
		if (style) {
			return Object.assign(base, style.design);
		}
		DESIGN_KEYS.forEach(function (key) {
			if (attributes[key] !== undefined && attributes[key] !== null && attributes[key] !== '') {
				base[key] = attributes[key];
			}
		});
		return base;
	}

	function pick(obj) {
		var out = {};
		DESIGN_KEYS.forEach(function (key) {
			if (obj[key] !== undefined) {
				out[key] = obj[key];
			}
		});
		return out;
	}

	/** Live preview: the code on its sheet, plus scan check with fixes. */
	function Preview(props) {
		var codeRef = useRef(null);
		var checkRef = useRef(null);
		var statusRef = useRef(null);
		var design = props.design;
		var key = JSON.stringify([props.text, design, props.size]);

		useEffect(function () {
			if (!codeRef.current || !checkRef.current || !window.QRCodeStyling) {
				return;
			}
			if (!statusRef.current) {
				statusRef.current = CSQR.ui.checkStatus(checkRef.current);
			}
			if (!props.text) {
				codeRef.current.innerHTML = '';
				statusRef.current.empty('');
				return;
			}
			var d = CSQR.design(design);
			var qr = CSQR.render(codeRef.current, d, props.text, props.size || 200);
			statusRef.current.run(qr, props.text, d, props.onFix ? function (fixed) {
				props.onFix(pick(CSQR.designToSettings(fixed)));
			} : null);
		}, [key]);

		return el('div', { className: 'csqr csqr-editor-preview' + (props.className ? ' ' + props.className : '') },
			el('div', { className: 'csqr-sheet' + (props.text ? '' : ' is-empty') },
				el('div', { className: 'csqr-sheet__code', ref: codeRef, role: 'img', 'aria-label': __('QR code preview', 'anderson-qr-code-generator') }),
				props.text ? null : el('p', { className: 'csqr-sheet__empty' }, props.emptyText || __('Fill in the details to see the code.', 'anderson-qr-code-generator'))
			),
			props.caption ? el('p', { className: 'csqr-card__caption' }, props.caption) : null,
			props.children || null,
			el('div', { className: 'csqr-check', ref: checkRef })
		);
	}

	function ColorRow(props) {
		return el(c.BaseControl, { label: props.label, id: props.id, __nextHasNoMarginBottom: true },
			el('div', { style: { display: 'flex', gap: '8px', alignItems: 'center' } },
				el('input', {
					type: 'color',
					id: props.id,
					value: props.value || '#000000',
					onChange: function (e) {
						props.onChange(e.target.value);
					},
					style: { width: '44px', height: '32px', padding: '2px', border: '1px solid #949494', borderRadius: '2px', background: '#fff' }
				}),
				el('code', null, props.value || '')
			)
		);
	}

	/** Design settings panel. setDesign(values) writes block attributes. */
	function DesignPanel(props) {
		var attributes = props.attributes;
		var setAttributes = props.setAttributes;
		var d = effective(attributes);
		var styles = config.styles || [];
		var usingStyle = attributes.styleId && styleById(attributes.styleId);
		var custom = !usingStyle && DESIGN_KEYS.some(function (k) {
			return attributes[k] !== undefined && attributes[k] !== '';
		});

		function set(key) {
			return function (value) {
				var update = {};
				update[key] = value;
				setAttributes(update);
			};
		}

		var styleOptions = [{ label: __('Site default design', 'anderson-qr-code-generator'), value: '' }].concat(styles.map(function (s) {
			return { label: s.name, value: s.id };
		}));

		var children = [
			el(c.SelectControl, {
				key: 'style',
				label: __('Style', 'anderson-qr-code-generator'),
				value: usingStyle ? attributes.styleId : '',
				options: styleOptions,
				onChange: function (v) {
					setAttributes({ styleId: v || undefined });
				},
				help: styles.length ? (usingStyle ? __('Editing the style changes every code that uses it.', 'anderson-qr-code-generator') : null) : el('span', null, __('Save your brand colors and logo as a style to reuse them. ', 'anderson-qr-code-generator'), config.canManage ? el('a', { href: config.stylesUrl, target: '_blank', rel: 'noopener' }, __('Add a style', 'anderson-qr-code-generator')) : null),
				__nextHasNoMarginBottom: true
			})
		];

		if (usingStyle) {
			children.push(config.canManage ? el('p', { key: 'edit' }, el('a', { href: config.stylesUrl + '&edit=' + encodeURIComponent(attributes.styleId), target: '_blank', rel: 'noopener' }, __('Edit this style', 'anderson-qr-code-generator'))) : null);
		} else {
			children.push(
				el(ColorRow, { key: 'dark', id: 'csqr-dark', label: __('Code color', 'anderson-qr-code-generator'), value: d.qrColorDark, onChange: set('qrColorDark') }),
				el(c.ToggleControl, { key: 'grad', label: __('Blend into a second color', 'anderson-qr-code-generator'), checked: !!d.qrGradient, onChange: set('qrGradient'), __nextHasNoMarginBottom: true }),
				d.qrGradient ? el(ColorRow, { key: 'dark2', id: 'csqr-dark2', label: __('Second color', 'anderson-qr-code-generator'), value: d.qrColorDark2, onChange: set('qrColorDark2') }) : null,
				el(ColorRow, { key: 'light', id: 'csqr-light', label: __('Background', 'anderson-qr-code-generator'), value: d.qrColorLight, onChange: set('qrColorLight') }),
				el(c.ToggleControl, { key: 'transparent', label: __('Transparent background', 'anderson-qr-code-generator'), checked: !!d.transparent, onChange: set('transparent'), __nextHasNoMarginBottom: true }),
				el(c.SelectControl, {
					key: 'dots',
					label: __('Dots', 'anderson-qr-code-generator'),
					value: d.qrDotStyle,
					options: [
						{ label: __('Square', 'anderson-qr-code-generator'), value: 'square' },
						{ label: __('Rounded', 'anderson-qr-code-generator'), value: 'rounded' },
						{ label: __('Extra rounded', 'anderson-qr-code-generator'), value: 'extra-rounded' },
						{ label: __('Dots', 'anderson-qr-code-generator'), value: 'dots' },
						{ label: __('Classy', 'anderson-qr-code-generator'), value: 'classy' },
						{ label: __('Classy rounded', 'anderson-qr-code-generator'), value: 'classy-rounded' }
					],
					onChange: set('qrDotStyle'),
					__nextHasNoMarginBottom: true
				}),
				el(c.SelectControl, {
					key: 'eyes',
					label: __('Corners', 'anderson-qr-code-generator'),
					value: d.qrEyeStyle,
					options: [
						{ label: __('Square', 'anderson-qr-code-generator'), value: 'square' },
						{ label: __('Rounded', 'anderson-qr-code-generator'), value: 'extra-rounded' },
						{ label: __('Circle', 'anderson-qr-code-generator'), value: 'dot' }
					],
					onChange: set('qrEyeStyle'),
					__nextHasNoMarginBottom: true
				}),
				el(ColorRow, { key: 'eye', id: 'csqr-eye', label: __('Corner color', 'anderson-qr-code-generator'), value: d.qrEyeColor || d.qrColorDark, onChange: set('qrEyeColor') }),
				el(LogoControl, { key: 'logo', design: d, setAttributes: setAttributes, attributes: attributes }),
				el(c.SelectControl, {
					key: 'level',
					label: __('Error correction', 'anderson-qr-code-generator'),
					value: d.qrCorrectLevel,
					options: [
						{ label: __('Low: fewest dots', 'anderson-qr-code-generator'), value: 'L' },
						{ label: __('Medium (recommended)', 'anderson-qr-code-generator'), value: 'M' },
						{ label: __('High', 'anderson-qr-code-generator'), value: 'Q' },
						{ label: __('Highest: survives damage', 'anderson-qr-code-generator'), value: 'H' }
					],
					help: d.logoUrl ? __('A logo always uses the highest level, so the code still scans.', 'anderson-qr-code-generator') : null,
					onChange: set('qrCorrectLevel'),
					__nextHasNoMarginBottom: true
				}),
				custom ? el(c.Button, {
					key: 'reset',
					variant: 'link',
					onClick: function () {
						var clear = {};
						DESIGN_KEYS.forEach(function (k) {
							clear[k] = undefined;
						});
						clear.logoId = undefined;
						setAttributes(clear);
					}
				}, __('Use the site default design', 'anderson-qr-code-generator')) : null
			);
		}

		return el(c.PanelBody, { title: __('Design', 'anderson-qr-code-generator'), initialOpen: !!props.initialOpen }, el(c.__experimentalVStack || 'div', { spacing: 4 }, children));
	}

	function LogoControl(props) {
		var d = props.design;
		var setAttributes = props.setAttributes;
		var picker = function (open) {
			return el(c.Button, { variant: 'secondary', onClick: open.open }, d.logoUrl ? __('Replace logo', 'anderson-qr-code-generator') : __('Choose logo', 'anderson-qr-code-generator'));
		};
		return el(c.BaseControl, { label: __('Logo', 'anderson-qr-code-generator'), __nextHasNoMarginBottom: true },
			d.logoUrl ? el('img', { src: d.logoUrl, alt: '', style: { display: 'block', maxWidth: '64px', maxHeight: '64px', margin: '4px 0 8px' } }) : null,
			el('div', { style: { display: 'flex', gap: '8px', flexWrap: 'wrap' } },
				el(be.MediaUploadCheck, { fallback: el(c.TextControl, { value: d.logoUrl || '', onChange: function (v) {
					setAttributes({ logoUrl: v });
				}, placeholder: 'https://', __nextHasNoMarginBottom: true }) },
					el(be.MediaUpload, {
						allowedTypes: ['image'],
						value: props.attributes.logoId,
						onSelect: function (media) {
							var sized = media.sizes && (media.sizes.medium || media.sizes.thumbnail);
							setAttributes({ logoUrl: sized ? sized.url : media.url, logoId: media.id });
						},
						render: picker
					})
				),
				d.logoUrl ? el(c.Button, { variant: 'tertiary', isDestructive: true, onClick: function () {
					setAttributes({ logoUrl: '', logoId: undefined });
				} }, __('Remove', 'anderson-qr-code-generator')) : null
			),
			d.logoUrl ? el(c.RangeControl, {
				label: __('Logo size', 'anderson-qr-code-generator'),
				value: Math.round((parseFloat(d.logoSize) || 0.24) * 100),
				min: 10,
				max: 30,
				onChange: function (v) {
					setAttributes({ logoSize: (v || 24) / 100 });
				},
				help: __('Percent of the code’s width. Larger logos can stop it scanning.', 'anderson-qr-code-generator'),
				__nextHasNoMarginBottom: true
			}) : null
		);
	}

	CSQR.editor = {
		Preview: Preview,
		DesignPanel: DesignPanel,
		effective: effective,
		pickDesign: pick,
		DESIGN_KEYS: DESIGN_KEYS,
		config: config
	};
})(window, window.wp);
