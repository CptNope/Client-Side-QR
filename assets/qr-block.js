(function (blocks, element, components, blockEditor, i18n) {
    var el = element.createElement;
    var Fragment = element.Fragment;
    var useEffect = element.useEffect;
    var useRef = element.useRef;

    var __ = i18n.__;
    var InspectorControls = blockEditor.InspectorControls;
    var MediaUpload = blockEditor.MediaUpload;
    var MediaUploadCheck = blockEditor.MediaUploadCheck;
    var PanelBody = components.PanelBody;
    var ToggleControl = components.ToggleControl;
    var ColorPicker = components.ColorPicker;
    var RangeControl = components.RangeControl;
    var SelectControl = components.SelectControl;
    var Button = components.Button;
    var Notice = components.Notice;

    var config = window.csqrBlockConfig || {};
    var defaults = Object.assign({
        qrColorDark: '#111111',
        qrColorDark2: '#111111',
        qrColorLight: '#ffffff',
        qrSize: 256,
        qrCorrectLevel: 'H',
        qrDotStyle: 'square',
        qrEyeStyle: 'square',
        qrEyeColor: '',
        qrGradient: false,
        logoUrl: '',
        allowUserColor: false,
        allowUserSize: false,
        allowUserCorrectLevel: false,
        enableUrl: true,
        enableWifi: true,
        enableEmail: true,
        enableSms: true,
        enableVcard: true,
        enableCrypto: true,
        enablePaypal: true
    }, config.defaults || {});

    var payloadKeys = ['enableUrl', 'enableWifi', 'enableEmail', 'enableSms', 'enableVcard', 'enableCrypto', 'enablePaypal'];

    function getEnabledPayloadCount(attributes) {
        return payloadKeys.reduce(function (count, key) {
            return count + (attributes[key] ? 1 : 0);
        }, 0);
    }

    blocks.registerBlockType('csqr/generator', {
        apiVersion: 2,
        title: __('Client-Side QR Code', 'csqr'),
        icon: 'smartphone',
        category: 'widgets',
        description: __('Render privacy-friendly QR forms that generate in the browser for links, campaigns, contact details, WiFi, and payment flows.', 'csqr'),
        attributes: {
            qrColorDark: { type: 'string', default: defaults.qrColorDark },
            qrColorDark2: { type: 'string', default: defaults.qrColorDark2 },
            qrColorLight: { type: 'string', default: defaults.qrColorLight },
            qrSize: { type: 'number', default: defaults.qrSize },
            qrCorrectLevel: { type: 'string', default: defaults.qrCorrectLevel },
            qrDotStyle: { type: 'string', default: defaults.qrDotStyle },
            qrEyeStyle: { type: 'string', default: defaults.qrEyeStyle },
            qrEyeColor: { type: 'string', default: defaults.qrEyeColor },
            qrGradient: { type: 'boolean', default: defaults.qrGradient },
            logoUrl: { type: 'string', default: defaults.logoUrl },
            allowUserColor: { type: 'boolean', default: defaults.allowUserColor },
            allowUserSize: { type: 'boolean', default: defaults.allowUserSize },
            allowUserCorrectLevel: { type: 'boolean', default: defaults.allowUserCorrectLevel },
            enableUrl: { type: 'boolean', default: defaults.enableUrl },
            enableWifi: { type: 'boolean', default: defaults.enableWifi },
            enableEmail: { type: 'boolean', default: defaults.enableEmail },
            enableSms: { type: 'boolean', default: defaults.enableSms },
            enableVcard: { type: 'boolean', default: defaults.enableVcard },
            enableCrypto: { type: 'boolean', default: defaults.enableCrypto },
            enablePaypal: { type: 'boolean', default: defaults.enablePaypal }
        },
        edit: function (props) {
            var attributes = props.attributes;
            var setAttributes = props.setAttributes;
            var qrRef = useRef(null);

            function setPayloadToggle(key, value) {
                var update = {};

                if (value) {
                    update[key] = true;
                    setAttributes(update);
                    return;
                }

                if (getEnabledPayloadCount(attributes) <= 1) {
                    if (key === 'enableUrl') {
                        setAttributes({ enableUrl: true });
                    } else {
                        setAttributes({
                            enableUrl: true,
                            [key]: false
                        });
                    }

                    return;
                }

                update[key] = false;
                setAttributes(update);
            }

            useEffect(function () {
                if (typeof QRCodeStyling === 'undefined' || !qrRef.current) {
                    return;
                }

                var previewOptions = {
                    width: 200,
                    height: 200,
                    data: (config.i18n && config.i18n.previewUrl ? config.i18n.previewUrl : 'https://example.com/') + '?utm_source=qr-preview',
                    image: attributes.logoUrl || '',
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
                        color: attributes.qrColorLight
                    },
                    qrOptions: {
                        errorCorrectionLevel: attributes.qrCorrectLevel
                    },
                    imageOptions: {
                        crossOrigin: 'anonymous',
                        margin: 5
                    }
                };

                if (attributes.qrGradient) {
                    previewOptions.dotsOptions.gradient = {
                        type: 'linear',
                        rotation: 0,
                        colorStops: [
                            { offset: 0, color: attributes.qrColorDark },
                            { offset: 1, color: attributes.qrColorDark2 }
                        ]
                    };
                }

                var preview = new QRCodeStyling(previewOptions);

                qrRef.current.innerHTML = '';
                preview.append(qrRef.current);
            }, [
                attributes.logoUrl,
                attributes.qrColorDark,
                attributes.qrColorDark2,
                attributes.qrColorLight,
                attributes.qrCorrectLevel,
                attributes.qrDotStyle,
                attributes.qrEyeColor,
                attributes.qrEyeStyle,
                attributes.qrGradient
            ]);

            return el(Fragment, {},
                el(InspectorControls, {},
                    el(PanelBody, { title: __('Design Defaults', 'csqr'), initialOpen: true },
                        el(SelectControl, {
                            label: __('Dot style', 'csqr'),
                            value: attributes.qrDotStyle,
                            options: [
                                { label: __('Square', 'csqr'), value: 'square' },
                                { label: __('Dots', 'csqr'), value: 'dots' },
                                { label: __('Rounded', 'csqr'), value: 'rounded' },
                                { label: __('Extra Rounded', 'csqr'), value: 'extra-rounded' },
                                { label: __('Classy', 'csqr'), value: 'classy' },
                                { label: __('Classy Rounded', 'csqr'), value: 'classy-rounded' }
                            ],
                            onChange: function (value) {
                                setAttributes({ qrDotStyle: value });
                            }
                        }),
                        el(ToggleControl, {
                            label: __('Use a foreground gradient', 'csqr'),
                            checked: attributes.qrGradient,
                            onChange: function (value) {
                                setAttributes({ qrGradient: value });
                            }
                        }),
                        el('p', { className: 'components-base-control__label' }, __('Foreground color 1', 'csqr')),
                        el(ColorPicker, {
                            color: attributes.qrColorDark,
                            onChangeComplete: function (value) {
                                setAttributes({ qrColorDark: value.hex });
                            },
                            disableAlpha: true
                        }),
                        attributes.qrGradient && el(Fragment, {},
                            el('p', { className: 'components-base-control__label' }, __('Foreground color 2', 'csqr')),
                            el(ColorPicker, {
                                color: attributes.qrColorDark2,
                                onChangeComplete: function (value) {
                                    setAttributes({ qrColorDark2: value.hex });
                                },
                                disableAlpha: true
                            })
                        ),
                        el(SelectControl, {
                            label: __('Corner eye style', 'csqr'),
                            value: attributes.qrEyeStyle,
                            options: [
                                { label: __('Square', 'csqr'), value: 'square' },
                                { label: __('Dot', 'csqr'), value: 'dot' },
                                { label: __('Extra Rounded', 'csqr'), value: 'extra-rounded' }
                            ],
                            onChange: function (value) {
                                setAttributes({ qrEyeStyle: value });
                            }
                        }),
                        el('p', { className: 'components-base-control__label' }, __('Corner eye color', 'csqr')),
                        el(ColorPicker, {
                            color: attributes.qrEyeColor || '#111111',
                            onChangeComplete: function (value) {
                                setAttributes({ qrEyeColor: value.hex });
                            },
                            disableAlpha: true
                        }),
                        el('p', { className: 'components-base-control__label' }, __('Background color', 'csqr')),
                        el(ColorPicker, {
                            color: attributes.qrColorLight,
                            onChangeComplete: function (value) {
                                setAttributes({ qrColorLight: value.hex });
                            },
                            disableAlpha: true
                        }),
                        el(RangeControl, {
                            label: __('Default output size (px)', 'csqr'),
                            value: attributes.qrSize,
                            min: 100,
                            max: 800,
                            step: 10,
                            onChange: function (value) {
                                setAttributes({ qrSize: value });
                            }
                        }),
                        el(SelectControl, {
                            label: __('Error correction level', 'csqr'),
                            value: attributes.qrCorrectLevel,
                            options: [
                                { label: __('Low (7%)', 'csqr'), value: 'L' },
                                { label: __('Medium (15%)', 'csqr'), value: 'M' },
                                { label: __('Quartile (25%)', 'csqr'), value: 'Q' },
                                { label: __('High (30%)', 'csqr'), value: 'H' }
                            ],
                            onChange: function (value) {
                                setAttributes({ qrCorrectLevel: value });
                            }
                        }),
                        el('p', { className: 'components-base-control__label' }, __('Center logo', 'csqr')),
                        el(MediaUploadCheck, {},
                            el(MediaUpload, {
                                onSelect: function (media) {
                                    setAttributes({ logoUrl: media.url });
                                },
                                allowedTypes: ['image'],
                                value: attributes.logoUrl,
                                render: function (obj) {
                                    return el(Button, {
                                        isSecondary: true,
                                        onClick: obj.open
                                    }, attributes.logoUrl ? __('Change logo', 'csqr') : __('Upload logo', 'csqr'));
                                }
                            })
                        ),
                        attributes.logoUrl && el(Button, {
                            isDestructive: true,
                            isLink: true,
                            onClick: function () {
                                setAttributes({ logoUrl: '' });
                            }
                        }, __('Remove logo', 'csqr'))
                    ),
                    el(PanelBody, { title: __('Available Payload Types', 'csqr'), initialOpen: false },
                        el(ToggleControl, {
                            label: __('URL / Text', 'csqr'),
                            checked: attributes.enableUrl,
                            onChange: function (value) {
                                setPayloadToggle('enableUrl', value);
                            }
                        }),
                        el(ToggleControl, {
                            label: __('WiFi network', 'csqr'),
                            checked: attributes.enableWifi,
                            onChange: function (value) {
                                setPayloadToggle('enableWifi', value);
                            }
                        }),
                        el(ToggleControl, {
                            label: __('vCard contact', 'csqr'),
                            checked: attributes.enableVcard,
                            onChange: function (value) {
                                setPayloadToggle('enableVcard', value);
                            }
                        }),
                        el(ToggleControl, {
                            label: __('Email', 'csqr'),
                            checked: attributes.enableEmail,
                            onChange: function (value) {
                                setPayloadToggle('enableEmail', value);
                            }
                        }),
                        el(ToggleControl, {
                            label: __('SMS', 'csqr'),
                            checked: attributes.enableSms,
                            onChange: function (value) {
                                setPayloadToggle('enableSms', value);
                            }
                        }),
                        el(ToggleControl, {
                            label: __('Crypto wallet', 'csqr'),
                            checked: attributes.enableCrypto,
                            onChange: function (value) {
                                setPayloadToggle('enableCrypto', value);
                            }
                        }),
                        el(ToggleControl, {
                            label: __('PayPal.me', 'csqr'),
                            checked: attributes.enablePaypal,
                            onChange: function (value) {
                                setPayloadToggle('enablePaypal', value);
                            }
                        }),
                        getEnabledPayloadCount(attributes) <= 1 && el(Notice, { status: 'info', isDismissible: false }, __('At least one payload type stays enabled so the block remains usable.', 'csqr'))
                    ),
                    el(PanelBody, { title: __('End-User Controls', 'csqr'), initialOpen: false },
                        el(ToggleControl, {
                            label: __('Allow visitors to change colors', 'csqr'),
                            checked: attributes.allowUserColor,
                            onChange: function (value) {
                                setAttributes({ allowUserColor: value });
                            }
                        }),
                        el(ToggleControl, {
                            label: __('Allow visitors to change size', 'csqr'),
                            checked: attributes.allowUserSize,
                            onChange: function (value) {
                                setAttributes({ allowUserSize: value });
                            }
                        }),
                        el(ToggleControl, {
                            label: __('Allow visitors to change error correction', 'csqr'),
                            checked: attributes.allowUserCorrectLevel,
                            onChange: function (value) {
                                setAttributes({ allowUserCorrectLevel: value });
                            }
                        })
                    )
                ),
                el('div', {
                    style: {
                        padding: '24px',
                        backgroundColor: '#f6f7f7',
                        border: '1px solid #dcdcde',
                        borderRadius: '8px',
                        textAlign: 'center'
                    }
                },
                    el('h4', { style: { margin: '0 0 8px' } }, __('Client-Side QR Code Preview', 'csqr')),
                    el('p', { style: { margin: '0 0 16px', color: '#50575e' } }, __('The public block renders a fully interactive client-side QR form. This preview reflects your current design defaults.', 'csqr')),
                    el('div', {
                        ref: qrRef,
                        style: {
                            display: 'flex',
                            justifyContent: 'center',
                            alignItems: 'center',
                            minHeight: '200px'
                        }
                    }, __('Loading preview…', 'csqr'))
                )
            );
        },
        save: function () {
            return null;
        }
    });
}(window.wp.blocks, window.wp.element, window.wp.components, window.wp.blockEditor || window.wp.editor, window.wp.i18n));
