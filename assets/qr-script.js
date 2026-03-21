document.addEventListener('DOMContentLoaded', function () {
    var frontendConfig = window.csqrFrontendConfig || {};
    var i18n = frontendConfig.i18n || {};
    var containers = document.querySelectorAll('.csqr-container');

    containers.forEach(function (container) {
        var outputWrapper = container.querySelector('.csqr-output-wrapper');
        var downloadsDiv = container.querySelector('.csqr-downloads');
        var downloadPngBtn = container.querySelector('.csqr-download-png-btn');
        var downloadSvgBtn = container.querySelector('.csqr-download-svg-btn');
        var copyBtn = container.querySelector('.csqr-copy-btn');
        var qrContainer = container.querySelector('.csqr-qrcode');
        var inputGroup = container.querySelector('.csqr-input-group');
        var statusNode = container.querySelector('.csqr-status');

        var tabButtons = Array.prototype.slice.call(container.querySelectorAll('.csqr-tab[role="tab"]'));
        var panels = Array.prototype.slice.call(container.querySelectorAll('.csqr-fields-container[role="tabpanel"]'));

        var colorDarkInput = container.querySelector('.csqr-color-dark');
        var colorDark2Input = container.querySelector('.csqr-color-dark2');
        var colorLightInput = container.querySelector('.csqr-color-light');
        var sizeInput = container.querySelector('.csqr-size-input');
        var sizeVal = container.querySelector('.csqr-size-val');
        var correctLevelInput = container.querySelector('.csqr-correct-level');
        var transparentBgInput = container.querySelector('.csqr-transparent-bg');

        var qrcode = null;
        var debounceTimer;

        if (!qrContainer || typeof QRCodeStyling === 'undefined') {
            return;
        }

        function setStatus(message) {
            if (statusNode) {
                statusNode.textContent = message;
            }
        }

        function setOutputVisible(isVisible) {
            if (outputWrapper) {
                outputWrapper.hidden = !isVisible;
            }

            if (downloadsDiv) {
                downloadsDiv.hidden = !isVisible;
            }
        }

        function getActiveTab() {
            return container.querySelector('.csqr-tab[aria-selected="true"]');
        }

        function getActiveType() {
            var activeTab = getActiveTab();
            return activeTab ? activeTab.getAttribute('data-type') : 'url';
        }

        function getPanelForType(type) {
            return container.querySelector('.csqr-' + type + '-fields');
        }

        function activateTab(nextTab, shouldFocus) {
            if (!nextTab) {
                return;
            }

            var selectedType = nextTab.getAttribute('data-type');

            tabButtons.forEach(function (tabButton) {
                var isActive = tabButton === nextTab;
                tabButton.classList.toggle('active', isActive);
                tabButton.setAttribute('aria-selected', isActive ? 'true' : 'false');
                tabButton.setAttribute('tabindex', isActive ? '0' : '-1');
            });

            panels.forEach(function (panel) {
                var isTarget = panel === getPanelForType(selectedType);
                panel.hidden = !isTarget;
            });

            if (shouldFocus) {
                nextTab.focus();
            }

            triggerGenerate();
        }

        function getTabIndex(currentTab) {
            return tabButtons.indexOf(currentTab);
        }

        function handleTabKeydown(event) {
            var currentTab = event.currentTarget;
            var currentIndex = getTabIndex(currentTab);
            var targetIndex = currentIndex;

            if (event.key === 'ArrowRight' || event.key === 'ArrowDown') {
                targetIndex = (currentIndex + 1) % tabButtons.length;
            } else if (event.key === 'ArrowLeft' || event.key === 'ArrowUp') {
                targetIndex = (currentIndex - 1 + tabButtons.length) % tabButtons.length;
            } else if (event.key === 'Home') {
                targetIndex = 0;
            } else if (event.key === 'End') {
                targetIndex = tabButtons.length - 1;
            } else {
                return;
            }

            event.preventDefault();
            activateTab(tabButtons[targetIndex], true);
        }

        tabButtons.forEach(function (tabButton) {
            tabButton.addEventListener('click', function () {
                activateTab(tabButton, false);
            });

            tabButton.addEventListener('keydown', handleTabKeydown);
        });

        if (sizeInput && sizeVal) {
            sizeInput.addEventListener('input', function () {
                sizeVal.textContent = this.value;
            });
        }

        if (transparentBgInput && colorLightInput) {
            var syncBackgroundState = function () {
                var isTransparent = transparentBgInput.checked;
                colorLightInput.disabled = isTransparent;
                colorLightInput.closest('.csqr-field').classList.toggle('is-disabled', isTransparent);
            };

            transparentBgInput.addEventListener('change', syncBackgroundState);
            syncBackgroundState();
        }

        function buildDataString() {
            var type = getActiveType();
            var data = '';

            if (type === 'url') {
                var urlInput = container.querySelector('.csqr-url-input');
                data = urlInput ? urlInput.value.trim() : '';

                if (!data) {
                    return null;
                }

                var utmSourceInput = container.querySelector('.csqr-utm-source');
                var utmMediumInput = container.querySelector('.csqr-utm-medium');
                var utmCampaignInput = container.querySelector('.csqr-utm-campaign');
                var utmSource = utmSourceInput ? utmSourceInput.value.trim() : '';
                var utmMedium = utmMediumInput ? utmMediumInput.value.trim() : '';
                var utmCampaign = utmCampaignInput ? utmCampaignInput.value.trim() : '';

                if (utmSource || utmMedium || utmCampaign) {
                    try {
                        var normalizedUrl = data.indexOf('http') === 0 ? data : 'https://' + data;
                        var url = new URL(normalizedUrl);

                        if (utmSource) {
                            url.searchParams.set('utm_source', utmSource);
                        }
                        if (utmMedium) {
                            url.searchParams.set('utm_medium', utmMedium);
                        }
                        if (utmCampaign) {
                            url.searchParams.set('utm_campaign', utmCampaign);
                        }

                        data = url.toString();
                    } catch (error) {
                        data = data;
                    }
                }
            } else if (type === 'wifi') {
                var ssidInput = container.querySelector('.csqr-wifi-ssid');
                var ssid = ssidInput ? ssidInput.value.trim() : '';

                if (!ssid) {
                    return null;
                }

                var passInput = container.querySelector('.csqr-wifi-pass');
                var encInput = container.querySelector('.csqr-wifi-enc');
                var hiddenInput = container.querySelector('.csqr-wifi-hidden');

                data = 'WIFI:S:' + ssid + ';T:' + (encInput ? encInput.value : 'WPA') + ';P:' + (passInput ? passInput.value : '') + ';H:' + (hiddenInput && hiddenInput.checked ? 'true' : 'false') + ';;';
            } else if (type === 'email') {
                var emailAddressInput = container.querySelector('.csqr-email-address');
                var emailAddress = emailAddressInput ? emailAddressInput.value.trim() : '';

                if (!emailAddress) {
                    return null;
                }

                var emailSubjectInput = container.querySelector('.csqr-email-subject');
                var emailBodyInput = container.querySelector('.csqr-email-body');

                data = 'MATMSG:TO:' + emailAddress + ';SUB:' + (emailSubjectInput ? emailSubjectInput.value.trim() : '') + ';BODY:' + (emailBodyInput ? emailBodyInput.value.trim() : '') + ';;';
            } else if (type === 'sms') {
                var smsPhoneInput = container.querySelector('.csqr-sms-phone');
                var smsPhone = smsPhoneInput ? smsPhoneInput.value.trim() : '';

                if (!smsPhone) {
                    return null;
                }

                var smsMessageInput = container.querySelector('.csqr-sms-message');
                data = 'SMSTO:' + smsPhone + ':' + (smsMessageInput ? smsMessageInput.value.trim() : '');
            } else if (type === 'vcard') {
                var firstNameInput = container.querySelector('.csqr-vcard-fname');
                var lastNameInput = container.querySelector('.csqr-vcard-lname');
                var firstName = firstNameInput ? firstNameInput.value.trim() : '';
                var lastName = lastNameInput ? lastNameInput.value.trim() : '';

                if (!firstName && !lastName) {
                    return null;
                }

                var vcardPhone = container.querySelector('.csqr-vcard-phone');
                var vcardEmail = container.querySelector('.csqr-vcard-email');
                var vcardCompany = container.querySelector('.csqr-vcard-company');
                var vcardTitle = container.querySelector('.csqr-vcard-title');
                var vcardUrl = container.querySelector('.csqr-vcard-url');
                var vcardAddress = container.querySelector('.csqr-vcard-address');

                data = 'BEGIN:VCARD\nVERSION:3.0\nN:' + lastName + ';' + firstName + '\n';
                data += 'FN:' + [firstName, lastName].join(' ').trim() + '\n';

                if (vcardCompany && vcardCompany.value.trim()) {
                    data += 'ORG:' + vcardCompany.value.trim() + '\n';
                }
                if (vcardTitle && vcardTitle.value.trim()) {
                    data += 'TITLE:' + vcardTitle.value.trim() + '\n';
                }
                if (vcardPhone && vcardPhone.value.trim()) {
                    data += 'TEL:' + vcardPhone.value.trim() + '\n';
                }
                if (vcardEmail && vcardEmail.value.trim()) {
                    data += 'EMAIL:' + vcardEmail.value.trim() + '\n';
                }
                if (vcardUrl && vcardUrl.value.trim()) {
                    data += 'URL:' + vcardUrl.value.trim() + '\n';
                }
                if (vcardAddress && vcardAddress.value.trim()) {
                    data += 'ADR:;;' + vcardAddress.value.trim() + '\n';
                }

                data += 'END:VCARD';
            } else if (type === 'crypto') {
                var cryptoAddressInput = container.querySelector('.csqr-crypto-address');
                var cryptoAddress = cryptoAddressInput ? cryptoAddressInput.value.trim() : '';

                if (!cryptoAddress) {
                    return null;
                }

                var cryptoCurrency = container.querySelector('.csqr-crypto-currency');
                var cryptoAmount = container.querySelector('.csqr-crypto-amount');

                data = (cryptoCurrency ? cryptoCurrency.value : 'bitcoin') + ':' + cryptoAddress;

                if (cryptoAmount && cryptoAmount.value.trim()) {
                    data += '?amount=' + cryptoAmount.value.trim();
                }
            } else if (type === 'paypal') {
                var paypalUsernameInput = container.querySelector('.csqr-paypal-username');
                var paypalUsername = paypalUsernameInput ? paypalUsernameInput.value.trim() : '';

                if (!paypalUsername) {
                    return null;
                }

                var paypalAmount = container.querySelector('.csqr-paypal-amount');
                var paypalCurrency = container.querySelector('.csqr-paypal-currency');

                data = 'https://paypal.me/' + paypalUsername.replace('@', '');

                if (paypalAmount && paypalAmount.value.trim()) {
                    data += '/' + paypalAmount.value.trim() + (paypalCurrency ? paypalCurrency.value : 'USD');
                }
            }

            return data;
        }

        function generateQR() {
            var text = buildDataString();

            if (!text) {
                qrContainer.innerHTML = '';
                setOutputVisible(false);
                setStatus(i18n.incompleteFields || 'Complete the active fields to generate a QR code.');
                return;
            }

            var colorDark = colorDarkInput ? colorDarkInput.value : container.getAttribute('data-color-dark');
            var colorDark2 = colorDark2Input ? colorDark2Input.value : container.getAttribute('data-color-dark2');
            var colorLight = colorLightInput ? colorLightInput.value : container.getAttribute('data-color-light');
            var size = sizeInput ? parseInt(sizeInput.value, 10) : parseInt(container.getAttribute('data-size'), 10);
            var correctLevel = correctLevelInput ? correctLevelInput.value : container.getAttribute('data-correct-level');
            var dotStyle = container.getAttribute('data-dot-style');
            var eyeStyle = container.getAttribute('data-eye-style') || 'square';
            var eyeColor = container.getAttribute('data-eye-color') || '';
            var gradientMode = container.getAttribute('data-gradient') === 'true';
            var logoUrl = container.getAttribute('data-logo-url') || '';
            var finalBackgroundColor = transparentBgInput && transparentBgInput.checked ? 'transparent' : colorLight;

            qrContainer.innerHTML = '';

            var options = {
                width: size,
                height: size,
                data: text,
                image: logoUrl,
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
                    color: finalBackgroundColor
                },
                qrOptions: {
                    errorCorrectionLevel: correctLevel
                },
                imageOptions: {
                    crossOrigin: 'anonymous',
                    margin: 5
                }
            };

            if (gradientMode) {
                options.dotsOptions.gradient = {
                    type: 'linear',
                    rotation: 0,
                    colorStops: [
                        { offset: 0, color: colorDark },
                        { offset: 1, color: colorDark2 }
                    ]
                };
            }

            qrcode = new QRCodeStyling(options);
            qrcode.append(qrContainer);

            setOutputVisible(true);
            setStatus(i18n.downloadReady || 'QR code ready for download.');
        }

        function triggerGenerate() {
            clearTimeout(debounceTimer);
            debounceTimer = window.setTimeout(generateQR, 200);
        }

        if (downloadPngBtn) {
            downloadPngBtn.addEventListener('click', function () {
                if (qrcode) {
                    qrcode.download({ name: 'qrcode', extension: 'png' });
                }
            });
        }

        if (downloadSvgBtn) {
            downloadSvgBtn.addEventListener('click', function () {
                if (qrcode) {
                    qrcode.download({ name: 'qrcode', extension: 'svg' });
                }
            });
        }

        if (copyBtn) {
            if (!navigator.clipboard || typeof ClipboardItem === 'undefined') {
                copyBtn.hidden = true;
            } else {
                copyBtn.addEventListener('click', function () {
                    if (!qrcode) {
                        return;
                    }

                    qrcode.getRawData('png').then(function (buffer) {
                        var blob = new Blob([buffer], { type: 'image/png' });

                        return navigator.clipboard.write([
                            new ClipboardItem({ 'image/png': blob })
                        ]);
                    }).then(function () {
                        setStatus(i18n.copySuccess || 'QR code image copied to your clipboard.');
                    }).catch(function () {
                        setStatus(i18n.copyError || 'The QR code could not be copied right now.');
                    });
                });
            }
        }

        if (inputGroup) {
            inputGroup.addEventListener('input', triggerGenerate);
            inputGroup.addEventListener('change', triggerGenerate);
        }

        var initialTab = getActiveTab();
        if (initialTab) {
            activateTab(initialTab, false);
        } else {
            triggerGenerate();
        }
    });
});
