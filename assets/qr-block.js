(function (blocks, element, components, blockEditor, i18n) {
    var el = element.createElement;
    var Fragment = element.Fragment;
    var useEffect = element.useEffect;
    var useRef = element.useRef;

    var __ = i18n.__;
    var InspectorControls = blockEditor.InspectorControls;
    var MediaUpload = blockEditor.MediaUpload;
    var MediaUploadCheck = blockEditor.MediaUploadCheck;
    var useBlockProps = blockEditor.useBlockProps;
    var PanelBody = components.PanelBody;
    var ToggleControl = components.ToggleControl;
    var ColorPicker = components.ColorPicker;
    var RangeControl = components.RangeControl;
    var SelectControl = components.SelectControl;
    var Button = components.Button;
    var Notice = components.Notice;
    var TextControl = components.TextControl;

    var config = window.csqrBlockConfig || {};
    var defaults = Object.assign({
        qrColorDark: '#111111',
        qrColorDark2: '#111111',
        qrColorLight: '#ffffff',
        uiUseThemeColors: true,
        uiUseThemeFont: true,
        uiSurfaceColor: '',
        uiTextColor: '',
        uiAccentColor: '',
        uiFontFamily: '',
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

    function getShellPreviewStyle(attributes) {
        var style = {
            fontFamily: attributes.uiUseThemeFont ? undefined : (attributes.uiFontFamily || undefined),
            color: attributes.uiUseThemeColors ? undefined : (attributes.uiTextColor || undefined),
            '--csqr-ui-font': attributes.uiUseThemeFont ? undefined : (attributes.uiFontFamily || undefined),
            '--csqr-ui-surface': attributes.uiUseThemeColors ? undefined : (attributes.uiSurfaceColor || undefined),
            '--csqr-ui-text': attributes.uiUseThemeColors ? undefined : (attributes.uiTextColor || undefined),
            '--csqr-ui-accent': attributes.uiUseThemeColors ? undefined : (attributes.uiAccentColor || undefined)
        };

        return style;
    }

    blocks.registerBlockType('csqr/generator', {
        apiVersion: 2,
        title: __('QR Code Generator', 'anderson-qr-code-generator'),
        icon: 'smartphone',
        category: 'widgets',
        description: __('Render privacy-friendly QR forms that generate in the browser for links, campaigns, contact details, WiFi, and payment flows.', 'anderson-qr-code-generator'),
        attributes: {
            qrColorDark: { type: 'string', default: defaults.qrColorDark },
            qrColorDark2: { type: 'string', default: defaults.qrColorDark2 },
            qrColorLight: { type: 'string', default: defaults.qrColorLight },
            uiUseThemeColors: { type: 'boolean', default: defaults.uiUseThemeColors },
            uiUseThemeFont: { type: 'boolean', default: defaults.uiUseThemeFont },
            uiSurfaceColor: { type: 'string', default: defaults.uiSurfaceColor },
            uiTextColor: { type: 'string', default: defaults.uiTextColor },
            uiAccentColor: { type: 'string', default: defaults.uiAccentColor },
            uiFontFamily: { type: 'string', default: defaults.uiFontFamily },
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
            var blockProps = useBlockProps({
                className: 'csqr-container csqr-block-preview',
                style: getShellPreviewStyle(attributes)
            });

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
                    el(PanelBody, { title: __('Design Defaults', 'anderson-qr-code-generator'), initialOpen: true },
                        el(SelectControl, {
                            label: __('Dot style', 'anderson-qr-code-generator'),
                            value: attributes.qrDotStyle,
                            options: [
                                { label: __('Square', 'anderson-qr-code-generator'), value: 'square' },
                                { label: __('Dots', 'anderson-qr-code-generator'), value: 'dots' },
                                { label: __('Rounded', 'anderson-qr-code-generator'), value: 'rounded' },
                                { label: __('Extra Rounded', 'anderson-qr-code-generator'), value: 'extra-rounded' },
                                { label: __('Classy', 'anderson-qr-code-generator'), value: 'classy' },
                                { label: __('Classy Rounded', 'anderson-qr-code-generator'), value: 'classy-rounded' }
                            ],
                            onChange: function (value) {
                                setAttributes({ qrDotStyle: value });
                            }
                        }),
                        el(ToggleControl, {
                            label: __('Use a foreground gradient', 'anderson-qr-code-generator'),
                            checked: attributes.qrGradient,
                            onChange: function (value) {
                                setAttributes({ qrGradient: value });
                            }
                        }),
                        el('p', { className: 'components-base-control__label' }, __('Foreground color 1', 'anderson-qr-code-generator')),
                        el(ColorPicker, {
                            color: attributes.qrColorDark,
                            onChangeComplete: function (value) {
                                setAttributes({ qrColorDark: value.hex });
                            },
                            disableAlpha: true
                        }),
                        attributes.qrGradient && el(Fragment, {},
                            el('p', { className: 'components-base-control__label' }, __('Foreground color 2', 'anderson-qr-code-generator')),
                            el(ColorPicker, {
                                color: attributes.qrColorDark2,
                                onChangeComplete: function (value) {
                                    setAttributes({ qrColorDark2: value.hex });
                                },
                                disableAlpha: true
                            })
                        ),
                        el(SelectControl, {
                            label: __('Corner eye style', 'anderson-qr-code-generator'),
                            value: attributes.qrEyeStyle,
                            options: [
                                { label: __('Square', 'anderson-qr-code-generator'), value: 'square' },
                                { label: __('Dot', 'anderson-qr-code-generator'), value: 'dot' },
                                { label: __('Extra Rounded', 'anderson-qr-code-generator'), value: 'extra-rounded' }
                            ],
                            onChange: function (value) {
                                setAttributes({ qrEyeStyle: value });
                            }
                        }),
                        el('p', { className: 'components-base-control__label' }, __('Corner eye color', 'anderson-qr-code-generator')),
                        el(ColorPicker, {
                            color: attributes.qrEyeColor || '#111111',
                            onChangeComplete: function (value) {
                                setAttributes({ qrEyeColor: value.hex });
                            },
                            disableAlpha: true
                        }),
                        el('p', { className: 'components-base-control__label' }, __('Background color', 'anderson-qr-code-generator')),
                        el(ColorPicker, {
                            color: attributes.qrColorLight,
                            onChangeComplete: function (value) {
                                setAttributes({ qrColorLight: value.hex });
                            },
                            disableAlpha: true
                        }),
                        el(RangeControl, {
                            label: __('Default output size (px)', 'anderson-qr-code-generator'),
                            value: attributes.qrSize,
                            min: 100,
                            max: 800,
                            step: 10,
                            onChange: function (value) {
                                setAttributes({ qrSize: value });
                            }
                        }),
                        el(SelectControl, {
                            label: __('Error correction level', 'anderson-qr-code-generator'),
                            value: attributes.qrCorrectLevel,
                            options: [
                                { label: __('Low (7%)', 'anderson-qr-code-generator'), value: 'L' },
                                { label: __('Medium (15%)', 'anderson-qr-code-generator'), value: 'M' },
                                { label: __('Quartile (25%)', 'anderson-qr-code-generator'), value: 'Q' },
                                { label: __('High (30%)', 'anderson-qr-code-generator'), value: 'H' }
                            ],
                            onChange: function (value) {
                                setAttributes({ qrCorrectLevel: value });
                            }
                        }),
                        el('p', { className: 'components-base-control__label' }, __('Center logo', 'anderson-qr-code-generator')),
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
                                    }, attributes.logoUrl ? __('Change logo', 'anderson-qr-code-generator') : __('Upload logo', 'anderson-qr-code-generator'));
                                }
                            })
                        ),
                        attributes.logoUrl && el(Button, {
                            isDestructive: true,
                            isLink: true,
                            onClick: function () {
                                setAttributes({ logoUrl: '' });
                            }
                        }, __('Remove logo', 'anderson-qr-code-generator'))
                ),
                    el(PanelBody, { title: __('Interface Shell', 'anderson-qr-code-generator'), initialOpen: false },
                        el(ToggleControl, {
                            label: __('Inherit theme colors', 'anderson-qr-code-generator'),
                            checked: attributes.uiUseThemeColors,
                            onChange: function (value) {
                                setAttributes({ uiUseThemeColors: value });
                            }
                        }),
                        !attributes.uiUseThemeColors && el(Fragment, {},
                            el('p', { className: 'components-base-control__label' }, __('Shell background color', 'anderson-qr-code-generator')),
                            el(ColorPicker, {
                                color: attributes.uiSurfaceColor || '#ffffff',
                                onChangeComplete: function (value) {
                                    setAttributes({ uiSurfaceColor: value.hex });
                                },
                                disableAlpha: true
                            }),
                            el('p', { className: 'components-base-control__label' }, __('Shell text color', 'anderson-qr-code-generator')),
                            el(ColorPicker, {
                                color: attributes.uiTextColor || '#111111',
                                onChangeComplete: function (value) {
                                    setAttributes({ uiTextColor: value.hex });
                                },
                                disableAlpha: true
                            }),
                            el('p', { className: 'components-base-control__label' }, __('Shell accent color', 'anderson-qr-code-generator')),
                            el(ColorPicker, {
                                color: attributes.uiAccentColor || '#007cba',
                                onChangeComplete: function (value) {
                                    setAttributes({ uiAccentColor: value.hex });
                                },
                                disableAlpha: true
                            })
                        ),
                        el(ToggleControl, {
                            label: __('Inherit theme font', 'anderson-qr-code-generator'),
                            checked: attributes.uiUseThemeFont,
                            onChange: function (value) {
                                setAttributes({ uiUseThemeFont: value });
                            }
                        }),
                        !attributes.uiUseThemeFont && el(TextControl, {
                            label: __('Shell font-family override', 'anderson-qr-code-generator'),
                            value: attributes.uiFontFamily || '',
                            onChange: function (value) {
                                setAttributes({ uiFontFamily: value });
                            },
                            help: __('Example: "Source Serif 4", Georgia, serif', 'anderson-qr-code-generator')
                        })
                    ),
                    el(PanelBody, { title: __('Available Payload Types', 'anderson-qr-code-generator'), initialOpen: false },
                        el(ToggleControl, {
                            label: __('URL / Text', 'anderson-qr-code-generator'),
                            checked: attributes.enableUrl,
                            onChange: function (value) {
                                setPayloadToggle('enableUrl', value);
                            }
                        }),
                        el(ToggleControl, {
                            label: __('WiFi network', 'anderson-qr-code-generator'),
                            checked: attributes.enableWifi,
                            onChange: function (value) {
                                setPayloadToggle('enableWifi', value);
                            }
                        }),
                        el(ToggleControl, {
                            label: __('vCard contact', 'anderson-qr-code-generator'),
                            checked: attributes.enableVcard,
                            onChange: function (value) {
                                setPayloadToggle('enableVcard', value);
                            }
                        }),
                        el(ToggleControl, {
                            label: __('Email', 'anderson-qr-code-generator'),
                            checked: attributes.enableEmail,
                            onChange: function (value) {
                                setPayloadToggle('enableEmail', value);
                            }
                        }),
                        el(ToggleControl, {
                            label: __('SMS', 'anderson-qr-code-generator'),
                            checked: attributes.enableSms,
                            onChange: function (value) {
                                setPayloadToggle('enableSms', value);
                            }
                        }),
                        el(ToggleControl, {
                            label: __('Crypto wallet', 'anderson-qr-code-generator'),
                            checked: attributes.enableCrypto,
                            onChange: function (value) {
                                setPayloadToggle('enableCrypto', value);
                            }
                        }),
                        el(ToggleControl, {
                            label: __('PayPal.me', 'anderson-qr-code-generator'),
                            checked: attributes.enablePaypal,
                            onChange: function (value) {
                                setPayloadToggle('enablePaypal', value);
                            }
                        }),
                        getEnabledPayloadCount(attributes) <= 1 && el(Notice, { status: 'info', isDismissible: false }, __('At least one payload type stays enabled so the block remains usable.', 'anderson-qr-code-generator'))
                    ),
                    el(PanelBody, { title: __('End-User Controls', 'anderson-qr-code-generator'), initialOpen: false },
                        el(ToggleControl, {
                            label: __('Allow visitors to change colors', 'anderson-qr-code-generator'),
                            checked: attributes.allowUserColor,
                            onChange: function (value) {
                                setAttributes({ allowUserColor: value });
                            }
                        }),
                        el(ToggleControl, {
                            label: __('Allow visitors to change size', 'anderson-qr-code-generator'),
                            checked: attributes.allowUserSize,
                            onChange: function (value) {
                                setAttributes({ allowUserSize: value });
                            }
                        }),
                        el(ToggleControl, {
                            label: __('Allow visitors to change error correction', 'anderson-qr-code-generator'),
                            checked: attributes.allowUserCorrectLevel,
                            onChange: function (value) {
                                setAttributes({ allowUserCorrectLevel: value });
                            }
                        })
                    )
                ),
                el('div', blockProps,
                    el('h4', { style: { margin: '0 0 8px' } }, __('QR code preview', 'anderson-qr-code-generator')),
                    el('p', { style: { margin: '0 0 16px', color: 'inherit', opacity: '0.8' } }, __('The public block renders a fully interactive client-side QR form. This preview reflects your current design defaults and shell styling.', 'anderson-qr-code-generator')),
                    el('div', {
                        ref: qrRef,
                        style: {
                            display: 'flex',
                            justifyContent: 'center',
                            alignItems: 'center',
                            minHeight: '200px'
                        }
                    }, __('Loading preview…', 'anderson-qr-code-generator'))
                )
            );
        },
        save: function () {
            return null;
        }
    });
}(window.wp.blocks, window.wp.element, window.wp.components, window.wp.blockEditor || window.wp.editor, window.wp.i18n));
