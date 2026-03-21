(function(blocks, element, components, editor) {
    var el = element.createElement;
    var blockEditor = window.wp.blockEditor || editor;
    var InspectorControls = blockEditor.InspectorControls;
    var MediaUpload = blockEditor.MediaUpload;
    var MediaUploadCheck = blockEditor.MediaUploadCheck;
    var PanelBody = components.PanelBody;
    var ToggleControl = components.ToggleControl;
    var ColorPicker = components.ColorPicker;
    var RangeControl = components.RangeControl;
    var SelectControl = components.SelectControl;
    var Button = components.Button;
    var Fragment = element.Fragment;
    var useRef = element.useRef;
    var useEffect = element.useEffect;
    
    blocks.registerBlockType('csqr/generator', {
        title: 'Client-Side QR Code',
        icon: 'smartphone',
        category: 'widgets',
        attributes: {
            qrColorDark: { type: 'string', default: '#111111' },
            qrColorDark2: { type: 'string', default: '#111111' },
            qrColorLight: { type: 'string', default: '#ffffff' },
            qrSize: { type: 'number', default: 256 },
            qrCorrectLevel: { type: 'string', default: 'H' },
            qrDotStyle: { type: 'string', default: 'square' },
            qrEyeStyle: { type: 'string', default: 'square' },
            qrEyeColor: { type: 'string', default: '' },
            qrGradient: { type: 'boolean', default: false },
            logoUrl: { type: 'string', default: '' },
            allowUserColor: { type: 'boolean', default: false },
            allowUserSize: { type: 'boolean', default: false },
            allowUserCorrectLevel: { type: 'boolean', default: false },
            enableUrl: { type: 'boolean', default: true },
            enableWifi: { type: 'boolean', default: true },
            enableEmail: { type: 'boolean', default: true },
            enableSms: { type: 'boolean', default: true },
            enableVcard: { type: 'boolean', default: true },
            enableCrypto: { type: 'boolean', default: true },
            enablePaypal: { type: 'boolean', default: true },
        },
        edit: function(props) {
            var attributes = props.attributes;
            var setAttributes = props.setAttributes;
            var qrRef = useRef(null);

            // Render live preview
            useEffect(function() {
                if (typeof QRCodeStyling === 'undefined') return;

                var options = {
                    width: 200, // Fixed preview size
                    height: 200,
                    data: "https://example.com (Preview)",
                    image: attributes.logoUrl || "",
                    dotsOptions: {
                        color: attributes.qrColorDark,
                        type: attributes.qrDotStyle
                    },
                    cornersSquareOptions: {
                        color: attributes.qrEyeColor || attributes.qrColorDark,
                        type: attributes.qrEyeStyle
                    },
                    cornersDotOptions: {
                        color: attributes.qrEyeColor || attributes.qrColorDark,
                        type: attributes.qrEyeStyle === 'extra-rounded' ? 'dot' : 'square'
                    },
                    backgroundOptions: {
                        color: attributes.qrColorLight,
                    },
                    imageOptions: {
                        crossOrigin: "anonymous",
                        margin: 5
                    }
                };

                if (attributes.qrGradient) {
                    options.dotsOptions.gradient = {
                        type: "linear",
                        rotation: 0,
                        colorStops: [
                            { offset: 0, color: attributes.qrColorDark },
                            { offset: 1, color: attributes.qrColorDark2 }
                        ]
                    };
                }

                var qrs = new QRCodeStyling(options);

                if (qrRef.current) {
                    qrRef.current.innerHTML = '';
                    qrs.append(qrRef.current);
                }
            }, [
                attributes.qrColorDark, 
                attributes.qrColorDark2, 
                attributes.qrColorLight,
                attributes.qrDotStyle,
                attributes.qrEyeStyle,
                attributes.qrEyeColor,
                attributes.qrGradient,
                attributes.logoUrl
            ]);

            return el(Fragment, {},
                el(InspectorControls, {},
                    el(PanelBody, { title: 'Design Settings', initialOpen: true },
                        el(SelectControl, {
                            label: 'Dot Style',
                            value: attributes.qrDotStyle,
                            options: [
                                { label: 'Square', value: 'square' },
                                { label: 'Dots', value: 'dots' },
                                { label: 'Rounded', value: 'rounded' },
                                { label: 'Extra Rounded', value: 'extra-rounded' },
                                { label: 'Classy', value: 'classy' },
                                { label: 'Classy Rounded', value: 'classy-rounded' }
                            ],
                            onChange: function(value) { setAttributes({ qrDotStyle: value }); }
                        }),
                        el(ToggleControl, {
                            label: 'Use Gradient for Foreground',
                            checked: attributes.qrGradient,
                            onChange: function(value) { setAttributes({ qrGradient: value }); }
                        }),
                        el('div', { style: { marginBottom: '10px' } }, 'Foreground Color 1'),
                        el(ColorPicker, {
                            color: attributes.qrColorDark,
                            onChangeComplete: function(value) { setAttributes({ qrColorDark: value.hex }); },
                            disableAlpha: true
                        }),
                        attributes.qrGradient && el(Fragment, {},
                            el('div', { style: { marginBottom: '10px', marginTop: '20px' } }, 'Foreground Color 2'),
                            el(ColorPicker, {
                                color: attributes.qrColorDark2,
                                onChangeComplete: function(value) { setAttributes({ qrColorDark2: value.hex }); },
                                disableAlpha: true
                            })
                        ),
                        el('div', { style: { marginTop: '20px', marginBottom: '10px' } }, 'Corner Eye Styling'),
                        el(SelectControl, {
                            label: 'Corner Edge Style',
                            value: attributes.qrEyeStyle,
                            options: [
                                { label: 'Square', value: 'square' },
                                { label: 'Dots', value: 'dot' },
                                { label: 'Extra Rounded', value: 'extra-rounded' }
                            ],
                            onChange: function(value) { setAttributes({ qrEyeStyle: value }); }
                        }),
                        el('div', { style: { marginBottom: '10px' } }, 'Corner Eye Color (Leave empty to match)'),
                        el(ColorPicker, {
                            color: attributes.qrEyeColor,
                            onChangeComplete: function(value) { setAttributes({ qrEyeColor: value.hex }); },
                            disableAlpha: true
                        }),
                        el('div', { style: { marginBottom: '10px', marginTop: '20px' } }, 'Background Color'),
                        el(ColorPicker, {
                            color: attributes.qrColorLight,
                            onChangeComplete: function(value) { setAttributes({ qrColorLight: value.hex }); },
                            disableAlpha: true
                        }),
                        el(RangeControl, {
                            label: 'Default Output Size (px)',
                            value: attributes.qrSize,
                            onChange: function(value) { setAttributes({ qrSize: value }); },
                            min: 100, max: 800, step: 10
                        }),
                        el(SelectControl, {
                            label: 'Error Correction Level',
                            value: attributes.qrCorrectLevel,
                            options: [
                                { label: 'L - Low (7%)', value: 'L' },
                                { label: 'M - Medium (15%)', value: 'M' },
                                { label: 'Q - Quartile (25%)', value: 'Q' },
                                { label: 'H - High (30%)', value: 'H' }
                            ],
                            onChange: function(value) { setAttributes({ qrCorrectLevel: value }); }
                        }),
                        el('div', { style: { marginTop: '20px', marginBottom: '10px' } }, 'Center Logo'),
                        el(MediaUploadCheck, {},
                            el(MediaUpload, {
                                onSelect: function(media) { setAttributes({ logoUrl: media.url }); },
                                allowedTypes: ['image'],
                                value: attributes.logoUrl,
                                render: function(obj) {
                                    return el(Button, {
                                        isSecondary: true,
                                        onClick: obj.open
                                    }, attributes.logoUrl ? 'Change Logo' : 'Upload Logo');
                                }
                            })
                        ),
                        attributes.logoUrl && el(Button, {
                            isDestructive: true,
                            isLink: true,
                            style: { marginTop: '10px' },
                            onClick: function() { setAttributes({ logoUrl: '' }); }
                        }, 'Remove Logo')
                    ),
                    el(PanelBody, { title: 'Available Data Types', initialOpen: false },
                        el(ToggleControl, {
                            label: 'URL / Text',
                            checked: attributes.enableUrl,
                            onChange: function(value) { setAttributes({ enableUrl: value }); }
                        }),
                        el(ToggleControl, {
                            label: 'WiFi Network',
                            checked: attributes.enableWifi,
                            onChange: function(value) { setAttributes({ enableWifi: value }); }
                        }),
                        el(ToggleControl, {
                            label: 'vCard (Contact)',
                            checked: attributes.enableVcard,
                            onChange: function(value) { setAttributes({ enableVcard: value }); }
                        }),
                        el(ToggleControl, {
                            label: 'Email',
                            checked: attributes.enableEmail,
                            onChange: function(value) { setAttributes({ enableEmail: value }); }
                        }),
                        el(ToggleControl, {
                            label: 'SMS / Phone',
                            checked: attributes.enableSms,
                            onChange: function(value) { setAttributes({ enableSms: value }); }
                        }),
                        el(ToggleControl, {
                            label: 'Crypto Wallet',
                            checked: attributes.enableCrypto,
                            onChange: function(value) { setAttributes({ enableCrypto: value }); }
                        }),
                        el(ToggleControl, {
                            label: 'PayPal.me',
                            checked: attributes.enablePaypal,
                            onChange: function(value) { setAttributes({ enablePaypal: value }); }
                        })
                    ),
                    el(PanelBody, { title: 'End-User Controls', initialOpen: false },
                        el(ToggleControl, {
                            label: 'Allow User to Change Colors',
                            checked: attributes.allowUserColor,
                            onChange: function(value) { setAttributes({ allowUserColor: value }); }
                        }),
                        el(ToggleControl, {
                            label: 'Allow User to Change Size',
                            checked: attributes.allowUserSize,
                            onChange: function(value) { setAttributes({ allowUserSize: value }); }
                        }),
                        el(ToggleControl, {
                            label: 'Allow User to Change Error Correction',
                            checked: attributes.allowUserCorrectLevel,
                            onChange: function(value) { setAttributes({ allowUserCorrectLevel: value }); }
                        })
                    )
                ),
                el(
                    'div',
                    { 
                        style: { 
                            padding: '24px', 
                            backgroundColor: '#f0f0f1', 
                            border: '1px dashed #8c8f94', 
                            textAlign: 'center',
                            borderRadius: '4px',
                            color: '#1d2327',
                            fontFamily: '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif'
                        } 
                    },
                    el('h4', { style: { margin: '0 0 15px 0' } }, '📱 Client-Side QR Code Generator Preview'),
                    el('div', { 
                        ref: qrRef, 
                        style: { display: 'flex', justifyContent: 'center', alignItems: 'center', minHeight: '200px' } 
                    }, 'Loading preview...')
                )
            );
        },
        save: function() {
            return null;
        }
    });
})(window.wp.blocks, window.wp.element, window.wp.components, window.wp.editor);
