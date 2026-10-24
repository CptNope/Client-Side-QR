/**
 * Anderson QR Code Generator: "QR code" panel in the editor sidebar of
 * posts, pages and products. Switches the page's QR card on or off and
 * downloads the page's code for print.
 */
(function (window, wp) {
	'use strict';

	var CSQR = window.CSQR;
	var cfg = window.csqrPagePanel || {};
	if (!CSQR || !wp || !wp.plugins) {
		return;
	}
	var el = wp.element.createElement;
	var useRef = wp.element.useRef;
	var useEffect = wp.element.useEffect;
	var __ = wp.i18n.__;
	var c = wp.components;
	var useSelect = wp.data.useSelect;
	var useDispatch = wp.data.useDispatch;
	var Panel = (wp.editor && wp.editor.PluginDocumentSettingPanel) || (wp.editPost && wp.editPost.PluginDocumentSettingPanel);
	var META = '_csqr_page_qr';

	if (!Panel) {
		return;
	}

	function Download(props) {
		var ref = useRef(null);
		var latest = useRef(props.url);
		latest.current = props.url;
		useEffect(function () {
			if (ref.current && !ref.current.csqrPanel) {
				ref.current.csqrPanel = CSQR.ui.exportPanel(ref.current, function () {
					return latest.current ? { design: CSQR.design(cfg.design || {}), text: latest.current, name: props.name } : null;
				}, { copy: true });
			}
		}, []);
		return el('div', { className: 'csqr csqr-panel-export', ref: ref });
	}

	function QRPanel() {
		var data = useSelect(function (select) {
			var editor = select('core/editor');
			return {
				meta: editor.getEditedPostAttribute('meta') || {},
				link: editor.getPermalink ? editor.getPermalink() : '',
				published: editor.isCurrentPostPublished ? editor.isCurrentPostPublished() : false,
				slug: editor.getEditedPostAttribute('slug') || 'page'
			};
		}, []);
		var editPost = useDispatch('core/editor').editPost;
		var choice = data.meta[META] || '';
		var shown = choice === 'on' || (choice === '' && cfg.siteDefault);

		return el(Panel, { name: 'csqr-page-qr', title: __('QR code', 'anderson-qr-code-generator'), className: 'csqr-page-panel' },
			el(c.ToggleControl, {
				label: __('Show a QR code for this page', 'anderson-qr-code-generator'),
				help: choice === '' ? (cfg.siteDefault ? __('On for every page of this type in QR Codes → Settings.', 'anderson-qr-code-generator') : __('Adds a “scan to open this page” code below the content.', 'anderson-qr-code-generator')) : null,
				checked: shown,
				onChange: function (v) {
					var meta = {};
					meta[META] = v === !!cfg.siteDefault ? '' : (v ? 'on' : 'off');
					editPost({ meta: meta });
				},
				__nextHasNoMarginBottom: true
			}),
			data.published && data.link ? el('div', { style: { marginTop: '16px' } },
				el('p', { style: { margin: '0 0 8px', fontWeight: 600 } }, __('Download for print', 'anderson-qr-code-generator')),
				el(Download, { url: data.link, name: 'qr-' + data.slug })
			) : el('p', { className: 'components-base-control__help' }, __('Publish to download this page’s code for print.', 'anderson-qr-code-generator'))
		);
	}

	wp.plugins.registerPlugin('csqr-page-qr', { render: QRPanel, icon: null });
})(window, window.wp);
