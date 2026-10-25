/**
 * Anderson QR Code Generator: the "QR code" block (csqr/code). A fixed code
 * set up in the editor, with ready-made templates as block variations:
 * this page, a short link, guest WiFi, a contact card, a review link.
 */
(function (window, wp) {
	'use strict';

	var CSQR = window.CSQR;
	if (!CSQR || !CSQR.editor || !wp || !wp.blocks) {
		return;
	}
	var el = wp.element.createElement;
	var useState = wp.element.useState;
	var __ = wp.i18n.__;
	var c = wp.components;
	var be = wp.blockEditor;
	var config = CSQR.editor.config;
	var useSelect = wp.data.useSelect;

	var CAPTIONS = {
		page: __('Scan to open this page on your phone', 'anderson-qr-code-generator'),
		wifi: __('Scan to join our WiFi', 'anderson-qr-code-generator'),
		vcard: __('Scan to save my contact details', 'anderson-qr-code-generator'),
		review: __('Enjoyed your visit? Scan to leave us a review', 'anderson-qr-code-generator'),
		email: __('Scan to email us', 'anderson-qr-code-generator'),
		sms: __('Scan to text us', 'anderson-qr-code-generator')
	};

	var TYPES = [
		{ value: 'page', label: __('This page', 'anderson-qr-code-generator') },
		{ value: 'link', label: __('Short link (change it after printing)', 'anderson-qr-code-generator') },
		{ value: 'url', label: __('A link', 'anderson-qr-code-generator') },
		{ value: 'wifi', label: __('Guest WiFi', 'anderson-qr-code-generator') },
		{ value: 'vcard', label: __('Contact card', 'anderson-qr-code-generator') },
		{ value: 'review', label: __('Review link', 'anderson-qr-code-generator') },
		{ value: 'email', label: __('Email', 'anderson-qr-code-generator') },
		{ value: 'sms', label: __('Text message', 'anderson-qr-code-generator') },
		{ value: 'text', label: __('Plain text', 'anderson-qr-code-generator') }
	];

	var icon = el('svg', { viewBox: '0 0 24 24', xmlns: 'http://www.w3.org/2000/svg' },
		el('path', { d: 'M3 3h8v8H3zm2 2v4h4V5zm8-2h8v8h-8zm2 2v4h4V5zM3 13h8v8H3zm2 2v4h4v-4zm8-2h3v3h-3zm3 3h2v2h-2zm2-3h3v3h-3zm-5 5h3v3h-3zm5 0h3v3h-3z' })
	);

	function linkById(id) {
		return (config.links || []).filter(function (l) {
			return l.id === id;
		})[0];
	}

	/** What the code holds, for the editor preview (the server builds the real one). */
	function payload(a, permalink) {
		switch (a.type) {
			case 'page':
				return permalink || config.previewUrl;
			case 'link':
				var link = linkById(a.linkId);
				return link ? link.url : '';
			case 'review':
				return CSQR.payload.url({ url: a.url });
			case 'text':
				return (a.text || '').trim();
			default:
				return CSQR.payload[a.type] ? CSQR.payload[a.type](a) : '';
		}
	}

	function field(a, set, key, label, extra) {
		return el(c.TextControl, Object.assign({
			key: key,
			label: label,
			value: a[key] || '',
			onChange: function (v) {
				var u = {};
				u[key] = v;
				set(u);
			},
			__nextHasNoMarginBottom: true
		}, extra || {}));
	}

	function ContentFields(props) {
		var a = props.attributes;
		var set = props.setAttributes;
		var _s = useState('');
		var profileNote = _s[0];
		var setProfileNote = _s[1];

		switch (a.type) {
			case 'page':
				return el('p', { className: 'components-base-control__help' }, __('Opens the page or post this block is on. Each page gets its own code.', 'anderson-qr-code-generator'));

			case 'link':
				var links = config.links || [];
				return el('div', null,
					links.length ? el(c.SelectControl, {
						label: __('Short link', 'anderson-qr-code-generator'),
						value: a.linkId || '',
						options: [{ label: __('Choose a short link', 'anderson-qr-code-generator'), value: '' }].concat(links.map(function (l) {
							return { label: l.title, value: l.id };
						})),
						onChange: function (v) {
							set({ linkId: v ? parseInt(v, 10) : undefined });
						},
						help: __('Change where it goes any time under QR Codes → Short links. Printed codes follow.', 'anderson-qr-code-generator'),
						__nextHasNoMarginBottom: true
					}) : el('p', null, __('You have no short links yet.', 'anderson-qr-code-generator')),
					config.canManage ? el('p', null, el('a', { href: config.newLinkUrl, target: '_blank', rel: 'noopener' }, __('Add a short link', 'anderson-qr-code-generator'))) : null
				);

			case 'url':
				return field(a, set, 'url', __('Link', 'anderson-qr-code-generator'), { type: 'url', placeholder: 'https://' });

			case 'review':
				return el('div', null,
					field(a, set, 'url', __('Your review link', 'anderson-qr-code-generator'), { type: 'url', placeholder: 'https://g.page/r/…/review' }),
					el('p', { className: 'components-base-control__help' }, __('In Google Business Profile, choose “Ask for reviews” and copy the link. Links from Yelp, Tripadvisor or Facebook work too.', 'anderson-qr-code-generator'))
				);

			case 'text':
				return el(c.TextareaControl, { label: __('Text', 'anderson-qr-code-generator'), value: a.text || '', onChange: function (v) {
					set({ text: v });
				}, __nextHasNoMarginBottom: true });

			case 'wifi':
				return el(c.__experimentalVStack || 'div', { spacing: 3 },
					field(a, set, 'ssid', __('Network name', 'anderson-qr-code-generator'), { autoComplete: 'off' }),
					a.encryption === 'nopass' ? null : field(a, set, 'password', __('Password', 'anderson-qr-code-generator'), { autoComplete: 'off', help: __('Anyone who can see this page can read the password, in the code and below it.', 'anderson-qr-code-generator') }),
					el(c.SelectControl, {
						label: __('Security', 'anderson-qr-code-generator'),
						value: a.encryption || 'WPA',
						options: [
							{ label: __('WPA, WPA2 or WPA3 (most networks)', 'anderson-qr-code-generator'), value: 'WPA' },
							{ label: __('WEP (older networks)', 'anderson-qr-code-generator'), value: 'WEP' },
							{ label: __('No password', 'anderson-qr-code-generator'), value: 'nopass' }
						],
						onChange: function (v) {
							set({ encryption: v });
						},
						__nextHasNoMarginBottom: true
					}),
					el(c.ToggleControl, { label: __('The network is hidden', 'anderson-qr-code-generator'), checked: !!a.hidden, onChange: function (v) {
						set({ hidden: v });
					}, __nextHasNoMarginBottom: true }),
					el(c.ToggleControl, { label: __('Show the name and password below the code', 'anderson-qr-code-generator'), help: __('For guests who’d rather type it.', 'anderson-qr-code-generator'), checked: a.showWifiDetails !== false, onChange: function (v) {
						set({ showWifiDetails: v });
					}, __nextHasNoMarginBottom: true })
				);

			case 'vcard':
				return el(c.__experimentalVStack || 'div', { spacing: 3 },
					el(c.Button, {
						variant: 'secondary',
						onClick: function () {
							wp.apiFetch({ path: '/wp/v2/users/me?context=edit' }).then(function (u) {
								set({ firstName: u.first_name || a.firstName, lastName: u.last_name || a.lastName, email: u.email || a.email, website: u.url || a.website });
								setProfileNote(__('Filled in from your profile. Check it before publishing.', 'anderson-qr-code-generator'));
							}).catch(function () {
								setProfileNote(__('Couldn’t read your profile.', 'anderson-qr-code-generator'));
							});
						}
					}, __('Fill in from my profile', 'anderson-qr-code-generator')),
					profileNote ? el('p', { className: 'components-base-control__help' }, profileNote) : null,
					field(a, set, 'firstName', __('First name', 'anderson-qr-code-generator')),
					field(a, set, 'lastName', __('Last name', 'anderson-qr-code-generator')),
					field(a, set, 'company', __('Company', 'anderson-qr-code-generator')),
					field(a, set, 'title', __('Job title', 'anderson-qr-code-generator')),
					field(a, set, 'phone', __('Phone', 'anderson-qr-code-generator'), { type: 'tel' }),
					field(a, set, 'email', __('Email', 'anderson-qr-code-generator'), { type: 'email' }),
					field(a, set, 'website', __('Website', 'anderson-qr-code-generator'), { type: 'url' }),
					field(a, set, 'address', __('Address', 'anderson-qr-code-generator'))
				);

			case 'email':
				return el(c.__experimentalVStack || 'div', { spacing: 3 },
					field(a, set, 'email', __('Send to', 'anderson-qr-code-generator'), { type: 'email' }),
					field(a, set, 'subject', __('Subject', 'anderson-qr-code-generator')),
					el(c.TextareaControl, { label: __('Message', 'anderson-qr-code-generator'), value: a.body || '', onChange: function (v) {
						set({ body: v });
					}, __nextHasNoMarginBottom: true })
				);

			case 'sms':
				return el(c.__experimentalVStack || 'div', { spacing: 3 },
					field(a, set, 'phone', __('Phone number', 'anderson-qr-code-generator'), { type: 'tel' }),
					el(c.TextareaControl, { label: __('Message', 'anderson-qr-code-generator'), value: a.message || '', onChange: function (v) {
						set({ message: v });
					}, __nextHasNoMarginBottom: true })
				);
		}
		return null;
	}

	function Edit(props) {
		var a = props.attributes;
		var set = props.setAttributes;
		var permalink = useSelect(function (select) {
			var editor = select('core/editor');
			return editor && editor.getPermalink ? editor.getPermalink() : '';
		}, []);
		var text = payload(a, permalink);
		var design = CSQR.editor.effective(a);
		var caption = a.caption || CAPTIONS[a.type] || '';
		var blockProps = be.useBlockProps({ className: 'csqr-code-block' });
		var wifiDetails = a.type === 'wifi' && a.showWifiDetails !== false && a.ssid ? el('span', null,
			el('span', { className: 'csqr-card__detail' }, __('Network', 'anderson-qr-code-generator'), ' ', el('code', { className: 'csqr-card__value' }, a.ssid)),
			a.password && a.encryption !== 'nopass' ? el('span', { className: 'csqr-card__detail' }, __('Password', 'anderson-qr-code-generator'), ' ', el('code', { className: 'csqr-card__value' }, a.password)) : null
		) : null;

		return el('div', blockProps,
			el(be.InspectorControls, null,
				el(c.PanelBody, { title: __('What the code opens', 'anderson-qr-code-generator'), initialOpen: true },
					el(c.__experimentalVStack || 'div', { spacing: 4 },
						el(c.SelectControl, {
							label: __('Kind', 'anderson-qr-code-generator'),
							value: a.type,
							options: TYPES,
							onChange: function (v) {
								set({ type: v });
							},
							__nextHasNoMarginBottom: true
						}),
						el(ContentFields, props)
					)
				),
				el(c.PanelBody, { title: __('Card', 'anderson-qr-code-generator'), initialOpen: true },
					el(c.__experimentalVStack || 'div', { spacing: 4 },
						el(c.TextControl, {
							label: __('Caption', 'anderson-qr-code-generator'),
							value: a.caption || '',
							placeholder: CAPTIONS[a.type] || '',
							onChange: function (v) {
								set({ caption: v });
							},
							__nextHasNoMarginBottom: true
						}),
						el(c.RangeControl, {
							label: __('Size on the page', 'anderson-qr-code-generator'),
							value: a.qrSize || 200,
							min: 96,
							max: 480,
							step: 8,
							onChange: function (v) {
								set({ qrSize: v || 200 });
							},
							help: __('Downloads are made at the print size visitors choose.', 'anderson-qr-code-generator'),
							__nextHasNoMarginBottom: true
						}),
						el(c.ToggleControl, {
							label: __('Let visitors download it to print', 'anderson-qr-code-generator'),
							checked: !!a.showDownloads,
							onChange: function (v) {
								set({ showDownloads: v });
							},
							__nextHasNoMarginBottom: true
						})
					)
				),
				el(CSQR.editor.DesignPanel, { attributes: a, setAttributes: set }),
				el(c.PanelBody, { title: __('Download for print', 'anderson-qr-code-generator'), initialOpen: false },
					el(ExportBox, { text: text, design: design, name: 'qr-' + a.type })
				)
			),
			el(CSQR.editor.Preview, {
				className: 'csqr-card csqr-card--' + (a.align === 'left' || a.align === 'right' ? a.align : 'center'),
				text: text,
				design: design,
				size: a.qrSize || 200,
				caption: caption || wifiDetails ? el('span', null, caption, wifiDetails && caption ? el('br') : null, wifiDetails) : null,
				emptyText: a.type === 'link' ? __('Choose a short link in the sidebar.', 'anderson-qr-code-generator') : null,
				onFix: a.styleId ? null : function (values) {
					set(values);
				}
			})
		);
	}

	/** Print/download panel inside the sidebar, for the person editing. */
	function ExportBox(props) {
		var ref = wp.element.useRef(null);
		var state = wp.element.useRef({});
		state.current = props;
		wp.element.useEffect(function () {
			if (ref.current && !ref.current.csqrPanel) {
				ref.current.csqrPanel = CSQR.ui.exportPanel(ref.current, function () {
					var p = state.current;
					return p.text ? { design: CSQR.design(p.design), text: p.text, name: p.name } : null;
				}, { copy: true });
			}
			if (ref.current && ref.current.csqrPanel) {
				ref.current.csqrPanel.setEnabled(!!props.text);
			}
		}, [props.text]);
		return el('div', { className: 'csqr', ref: ref });
	}

	var variations = [
		{ name: 'page', isDefault: true, title: __('QR code for this page', 'anderson-qr-code-generator'), description: __('A code that opens the page it’s on.', 'anderson-qr-code-generator'), attributes: { type: 'page' }, scope: ['inserter', 'transform'] },
		{ name: 'wifi', title: __('Guest WiFi QR code', 'anderson-qr-code-generator'), description: __('Guests scan to join your WiFi without typing the password.', 'anderson-qr-code-generator'), attributes: { type: 'wifi', showDownloads: true }, keywords: ['wifi', 'wireless', 'password', 'guest'], scope: ['inserter', 'transform'] },
		{ name: 'contact', title: __('Contact card QR code', 'anderson-qr-code-generator'), description: __('Scan to save your name, phone and email to contacts.', 'anderson-qr-code-generator'), attributes: { type: 'vcard' }, keywords: ['vcard', 'contact', 'business card'], scope: ['inserter', 'transform'] },
		{ name: 'review', title: __('Review QR code', 'anderson-qr-code-generator'), description: __('Ask customers for a Google, Yelp or Tripadvisor review.', 'anderson-qr-code-generator'), attributes: { type: 'review', showDownloads: true }, keywords: ['review', 'google', 'yelp', 'feedback'], scope: ['inserter', 'transform'] },
		{ name: 'short-link', title: __('Short link QR code', 'anderson-qr-code-generator'), description: __('A code you can point somewhere new after it’s printed.', 'anderson-qr-code-generator'), attributes: { type: 'link', showDownloads: true }, keywords: ['dynamic', 'redirect', 'menu', 'short link'], scope: ['inserter', 'transform'] }
	].map(function (v) {
		v.icon = icon;
		v.isActive = function (attrs) {
			return attrs.type === v.attributes.type;
		};
		return v;
	});

	wp.blocks.registerBlockType('csqr/code', {
		apiVersion: 3,
		title: __('QR code', 'anderson-qr-code-generator'),
		description: __('A ready-to-scan QR code for this page, a short link, guest WiFi, a contact card or a review link.', 'anderson-qr-code-generator'),
		category: 'widgets',
		icon: icon,
		keywords: ['qr', 'qr code', 'barcode', 'scan'],
		supports: { html: false, align: ['left', 'center', 'right'], anchor: true },
		variations: variations,
		example: { attributes: { type: 'url', url: 'https://wordpress.org/', caption: __('Scan to visit', 'anderson-qr-code-generator') } },
		edit: Edit,
		save: function () {
			return null;
		}
	});
})(window, window.wp);
