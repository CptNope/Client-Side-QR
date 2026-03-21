document.addEventListener('DOMContentLoaded', function() {
    var containers = document.querySelectorAll('.csqr-container');
    
    containers.forEach(function(container) {
        var outputWrapper = container.querySelector('.csqr-output-wrapper');
        var downloadsDiv = container.querySelector('.csqr-downloads');
        var downloadPngBtn = container.querySelector('.csqr-download-png-btn');
        var downloadSvgBtn = container.querySelector('.csqr-download-svg-btn');
        var qrContainer = container.querySelector('.csqr-qrcode');
        var inputGroup = container.querySelector('.csqr-input-group');
        
        // Data Type Tabs
        var tabRadios = container.querySelectorAll('.csqr-data-type-radio');
        var tabLabels = container.querySelectorAll('.csqr-tab');
        var allFieldContainers = container.querySelectorAll('.csqr-fields-container');
        
        // Settings elements (if activated by admin)
        var colorDarkInput = container.querySelector('.csqr-color-dark');
        var colorDark2Input = container.querySelector('.csqr-color-dark2');
        var colorLightInput = container.querySelector('.csqr-color-light');
        var sizeInput = container.querySelector('.csqr-size-input');
        var sizeVal = container.querySelector('.csqr-size-val');
        var correctLevelInput = container.querySelector('.csqr-correct-level');
        var transparentBgInput = container.querySelector('.csqr-transparent-bg');
        var bgPickerLabel = container.querySelector('.csqr-bg-picker');

        if (!qrContainer) return;

        var qrcode = null;
        var debounceTimer;

        if (sizeInput && sizeVal) {
            sizeInput.addEventListener('input', function() {
                sizeVal.textContent = this.value;
            });
        }
        
        if (transparentBgInput && bgPickerLabel) {
            transparentBgInput.addEventListener('change', function() {
                if (this.checked) {
                    bgPickerLabel.style.opacity = '0.5';
                    bgPickerLabel.style.pointerEvents = 'none';
                } else {
                    bgPickerLabel.style.opacity = '1';
                    bgPickerLabel.style.pointerEvents = 'auto';
                }
            });
        }

        function getActiveType() {
            var activeRadio = container.querySelector('.csqr-data-type-radio:checked');
            return activeRadio ? activeRadio.value : 'url';
        }

        // Toggle visibility of data type fields
        tabRadios.forEach(function(radio) {
            radio.addEventListener('change', function() {
                // Update active tab styles
                tabLabels.forEach(function(lbl) { lbl.classList.remove('active'); });
                this.closest('.csqr-tab').classList.add('active');

                var selectedType = this.value;
                allFieldContainers.forEach(function(fc) {
                    fc.style.display = 'none';
                });
                var activeContainer = container.querySelector('.csqr-' + selectedType + '-fields');
                if (activeContainer) {
                    activeContainer.style.display = 'flex';
                }
                triggerGenerate();
            });
        });

        function buildDataString() {
            var type = getActiveType();
            var data = '';

            if (type === 'url') {
                data = container.querySelector('.csqr-url-input').value.trim();
                if (!data) return null;
                
                // Append UTMs if present
                var utmSource = container.querySelector('.csqr-utm-source') ? container.querySelector('.csqr-utm-source').value.trim() : '';
                var utmMedium = container.querySelector('.csqr-utm-medium') ? container.querySelector('.csqr-utm-medium').value.trim() : '';
                var utmCampaign = container.querySelector('.csqr-utm-campaign') ? container.querySelector('.csqr-utm-campaign').value.trim() : '';
                
                if (utmSource || utmMedium || utmCampaign) {
                    var url;
                    try {
                        url = new URL(data.indexOf('http') !== 0 ? 'https://' + data : data);
                        if (utmSource) url.searchParams.set('utm_source', utmSource);
                        if (utmMedium) url.searchParams.set('utm_medium', utmMedium);
                        if (utmCampaign) url.searchParams.set('utm_campaign', utmCampaign);
                        data = url.toString();
                    } catch(e) {
                         // invalid url, just bypass
                    }
                }
            } else if (type === 'wifi') {
                var ssid = container.querySelector('.csqr-wifi-ssid').value.trim();
                if (!ssid) return null;
                var pass = container.querySelector('.csqr-wifi-pass').value;
                var enc = container.querySelector('.csqr-wifi-enc').value;
                var hidden = container.querySelector('.csqr-wifi-hidden').checked ? 'true' : 'false';
                data = 'WIFI:S:' + ssid + ';T:' + enc + ';P:' + pass + ';H:' + hidden + ';;';
            } else if (type === 'email') {
                var email = container.querySelector('.csqr-email-address').value.trim();
                if (!email) return null;
                var sub = container.querySelector('.csqr-email-subject').value.trim();
                var body = container.querySelector('.csqr-email-body').value.trim();
                data = 'MATMSG:TO:' + email + ';SUB:' + sub + ';BODY:' + body + ';;';
            } else if (type === 'sms') {
                var phone = container.querySelector('.csqr-sms-phone').value.trim();
                if (!phone) return null;
                var msg = container.querySelector('.csqr-sms-message').value.trim();
                data = 'SMSTO:' + phone + ':' + msg;
            } else if (type === 'vcard') {
                var fname = container.querySelector('.csqr-vcard-fname').value.trim();
                var lname = container.querySelector('.csqr-vcard-lname').value.trim();
                if (!fname && !lname) return null;
                
                var vphone = container.querySelector('.csqr-vcard-phone').value.trim();
                var vemail = container.querySelector('.csqr-vcard-email').value.trim();
                var vcompany = container.querySelector('.csqr-vcard-company').value.trim();
                var vtitle = container.querySelector('.csqr-vcard-title') ? container.querySelector('.csqr-vcard-title').value.trim() : '';
                var vurl = container.querySelector('.csqr-vcard-url') ? container.querySelector('.csqr-vcard-url').value.trim() : '';
                var vaddress = container.querySelector('.csqr-vcard-address') ? container.querySelector('.csqr-vcard-address').value.trim() : '';
                
                data = 'BEGIN:VCARD\nVERSION:3.0\nN:' + lname + ';' + fname + '\n';
                data += 'FN:' + fname + ' ' + lname + '\n';
                if (vcompany) data += 'ORG:' + vcompany + '\n';
                if (vtitle) data += 'TITLE:' + vtitle + '\n';
                if (vphone) data += 'TEL:' + vphone + '\n';
                if (vemail) data += 'EMAIL:' + vemail + '\n';
                if (vurl) data += 'URL:' + vurl + '\n';
                if (vaddress) data += 'ADR:;;' + vaddress + '\n';
                data += 'END:VCARD';
            } else if (type === 'crypto') {
                var ctype = container.querySelector('.csqr-crypto-currency').value;
                var caddress = container.querySelector('.csqr-crypto-address').value.trim();
                if (!caddress) return null;
                var camount = container.querySelector('.csqr-crypto-amount').value.trim();
                data = ctype + ':' + caddress;
                if (camount) data += '?amount=' + camount;
            } else if (type === 'paypal') {
                var puser = container.querySelector('.csqr-paypal-username').value.trim();
                if (!puser) return null;
                var pamount = container.querySelector('.csqr-paypal-amount').value.trim();
                var pcurrency = container.querySelector('.csqr-paypal-currency').value;
                puser = puser.replace('@', ''); // strip @ if added
                data = 'https://paypal.me/' + puser;
                if (pamount) data += '/' + pamount + pcurrency;
            }

            return data;
        }

        function generateQR() {
            var text = buildDataString();
            if (!text) {
                // If incomplete, hide the wrapper
                outputWrapper.style.display = 'none';
                return;
            }

            // Determine effective settings
            var colorDark = colorDarkInput ? colorDarkInput.value : container.getAttribute('data-color-dark');
            var colorDark2 = colorDark2Input ? colorDark2Input.value : container.getAttribute('data-color-dark2');
            var colorLight = colorLightInput ? colorLightInput.value : container.getAttribute('data-color-light');
            var size = sizeInput ? parseInt(sizeInput.value, 10) : parseInt(container.getAttribute('data-size'), 10);
            var correctLevelStr = correctLevelInput ? correctLevelInput.value : container.getAttribute('data-correct-level');
            
            var dotStyle = container.getAttribute('data-dot-style');
            var eyeStyle = container.getAttribute('data-eye-style') || 'square';
            var eyeColor = container.getAttribute('data-eye-color') || '';
            var gradientMode = container.getAttribute('data-gradient') === 'true';
            var logoUrl = container.getAttribute('data-logo-url');
            
            var isTransparent = transparentBgInput ? transparentBgInput.checked : false;
            var finalBgColor = isTransparent ? "transparent" : colorLight;

            qrContainer.innerHTML = ''; // clear previous
            
            var options = {
                width: size,
                height: size,
                data: text,
                image: logoUrl || "",
                dotsOptions: {
                    color: colorDark,
                    type: dotStyle
                },
                cornersSquareOptions: {
                    color: eyeColor || colorDark,
                    type: eyeStyle
                },
                cornersDotOptions: {
                    color: eyeColor || colorDark,
                    type: eyeStyle === 'extra-rounded' ? 'dot' : 'square'
                },
                backgroundOptions: {
                    color: finalBgColor,
                },
                qrOptions: {
                    errorCorrectionLevel: correctLevelStr
                },
                imageOptions: {
                    crossOrigin: "anonymous",
                    margin: 5
                }
            };

            if (gradientMode) {
                options.dotsOptions.gradient = {
                    type: "linear",
                    rotation: 0,
                    colorStops: [
                        { offset: 0, color: colorDark },
                        { offset: 1, color: colorDark2 }
                    ]
                };
            }

            qrcode = new QRCodeStyling(options);
            qrcode.append(qrContainer);

            outputWrapper.style.display = 'flex';
            downloadsDiv.style.display = 'flex';
            
            downloadPngBtn.onclick = function() {
                qrcode.download({ name: "qrcode", extension: "png" });
            };
            
            downloadSvgBtn.onclick = function() {
                qrcode.download({ name: "qrcode", extension: "svg" });
            };
            
            var copyBtn = container.querySelector('.csqr-copy-btn');
            if (copyBtn) {
                copyBtn.onclick = function() {
                    qrcode.getRawData("png").then(function(buffer) {
                        var blob = new Blob([buffer], { type: "image/png" });
                        try {
                            navigator.clipboard.write([
                                new ClipboardItem({ 'image/png': blob })
                            ]).then(function() {
                                alert('QR Code Image copied to clipboard!');
                            });
                        } catch (err) {
                            alert('Clipboard copy not supported in this browser context.');
                        }
                    }).catch(function(err) {
                        console.error('Failed to copy QR code: ', err);
                    });
                };
            }
        }

        function triggerGenerate() {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(generateQR, 200);
        }

        // Live preview trigger on any input or change inside the input group
        if (inputGroup) {
            inputGroup.addEventListener('input', triggerGenerate);
            inputGroup.addEventListener('change', triggerGenerate);
            
            // Generate immediately if there's text
            triggerGenerate();
        }
    });
});
