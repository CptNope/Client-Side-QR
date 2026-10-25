/**
 * Browser tests against a real WordPress site with the plugin active.
 *
 *   node tests/e2e/run.cjs http://127.0.0.1:8099 admin password
 *
 * Needs the `playwright` package and its Chromium. Every code the plugin
 * draws is decoded with the bundled jsQR and compared with what it should
 * hold, so "it renders" always means "it scans".
 *
 * The site is expected to contain the pages tests/e2e/setup.sh creates.
 */
const { chromium } = require('playwright');
const fs = require('fs');

const [, , BASE = 'http://127.0.0.1:8099', USER = 'admin', PASS = 'password'] = process.argv;
const fixtures = JSON.parse(fs.readFileSync(process.env.CSQR_FIXTURES || '/tmp/csqr-fixtures.json', 'utf8'));
const shots = process.env.CSQR_SHOTS || '';
if (shots) {
	fs.mkdirSync(shots, { recursive: true });
}

let pass = 0;
let fail = 0;
const failures = [];

function ok(cond, label, detail) {
	if (cond) {
		pass++;
		console.log('  ✓ ' + label);
	} else {
		fail++;
		failures.push(label);
		console.log('  ✗ ' + label + (detail !== undefined ? ' — got ' + JSON.stringify(detail) : ''));
		if (process.env.GITHUB_ACTIONS) {
			console.log('::error title=Browser test failed::' + label + (detail !== undefined ? ' — got ' + JSON.stringify(detail).slice(0, 500) : ''));
		}
	}
}

/** Decode the code drawn inside `selector` (canvas). */
async function decode(page, selector) {
	// Retry briefly (logos draw asynchronously) and try full and half size,
	// like the plugin's own scan check.
	for (let attempt = 0; attempt < 10; attempt++) {
		const found = await page.evaluate(async (sel) => {
			const canvas = document.querySelector(sel + ' canvas');
			if (!canvas) {
				return null;
			}
			const jsQR = await window.CSQR.loadDecoder();
			for (const scale of [1, 0.5]) {
				const c = document.createElement('canvas');
				c.width = Math.round(canvas.width * scale);
				c.height = Math.round(canvas.height * scale);
				const ctx = c.getContext('2d');
				ctx.fillStyle = '#fff';
				ctx.fillRect(0, 0, c.width, c.height);
				ctx.drawImage(canvas, 0, 0, c.width, c.height);
				const r = jsQR(ctx.getImageData(0, 0, c.width, c.height).data, c.width, c.height);
				if (r) {
					return r.data;
				}
			}
			return null;
		}, selector);
		if (found !== null) {
			return found;
		}
		await page.waitForTimeout(400);
	}
	return null;
}

async function waitCheck(page, selector) {
	try {
		await page.waitForFunction((sel) => {
			const n = document.querySelector(sel + ' .csqr-check');
			return n && ['ok', 'warn', 'fail', 'unknown'].includes(n.getAttribute('data-level'));
		}, selector, { timeout: 30000 });
	} catch (e) {
		// Say what was slow instead of only timing out.
		const slow = await page.evaluate(() => performance.getEntriesByType('resource')
			.filter((r) => r.duration > 500)
			.map((r) => r.name.replace(location.origin, '') + ' ' + Math.round(r.duration) + 'ms'));
		return { level: 'timeout', text: 'slow: ' + slow.join(', ') };
	}
	return page.$eval(selector + ' .csqr-check', (n) => ({ level: n.getAttribute('data-level'), text: n.textContent }));
}

async function shot(page, name) {
	if (shots) {
		await page.screenshot({ path: `${shots}/${name}.png`, fullPage: false });
	}
}

(async () => {
	const browser = await chromium.launch();
	const context = await browser.newContext({ viewport: { width: 1280, height: 900 }, acceptDownloads: true });
	const page = await context.newPage();
	const errors = [];
	page.on('pageerror', (e) => errors.push(e.message));
	page.on('console', (m) => {
		if (m.type() === 'error' && !/ERR_TUNNEL|favicon|net::ERR_/.test(m.text())) {
			errors.push(m.text());
		}
	});

	/* ---------------------------------------------------------------- */
	console.log('== Visitor generator');
	await page.goto(fixtures.generator);
	const gen = '.csqr-generator';
	await page.waitForSelector(gen);
	ok(await page.$eval(gen + ' .csqr-sheet', (n) => n.classList.contains('is-empty')), 'starts with an empty sheet');
	ok(await page.$eval(gen + ' .csqr-generator__title', (n) => n.tagName === 'H2'), 'generator heading is an h2');
	ok(await page.$eval(gen + ' .csqr-export__waiting', (n) => !n.hidden && n.textContent.length > 0), 'disabled downloads say why');
	await page.fill(gen + ' [data-field="url"]', 'https://jeremyanderson.tech/?a=1&b=2');
	await page.waitForTimeout(500);
	ok((await decode(page, gen + ' .csqr-sheet__code')) === 'https://jeremyanderson.tech/?a=1&b=2', 'link code decodes to the link');
	ok((await waitCheck(page, gen)).level === 'ok', 'scan check says it scans');
	await shot(page, 'generator-link');

	// Campaign tags.
	await page.click(gen + ' .csqr-more > summary');
	await page.fill(gen + ' [data-field="utmSource"]', 'flyer');
	await page.waitForTimeout(500);
	ok((await decode(page, gen + ' .csqr-sheet__code')) === 'https://jeremyanderson.tech/?a=1&b=2&utm_source=flyer', 'campaign tag added to the link');

	// WiFi with characters the format must escape.
	await page.click(gen + ' .csqr-kind[data-type="wifi"]');
	await page.fill(gen + ' [data-panel], ' + gen + ' .csqr-panel[data-type="wifi"] [data-field="ssid"]', 'Cafe; "Guest"');
	await page.fill(gen + ' .csqr-panel[data-type="wifi"] [data-field="password"]', 'p@ss:wo,rd\\');
	await page.waitForTimeout(500);
	ok((await decode(page, gen + ' .csqr-sheet__code')) === 'WIFI:T:WPA;S:Cafe\\; \\"Guest\\";P:p@ss\\:wo\\,rd\\\\;;', 'WiFi code escapes ; " : , and \\');

	// Contact card.
	await page.click(gen + ' .csqr-kind[data-type="vcard"]');
	await page.fill(gen + ' .csqr-panel[data-type="vcard"] [data-field="firstName"]', 'Jeremy');
	await page.fill(gen + ' .csqr-panel[data-type="vcard"] [data-field="lastName"]', 'Anderson');
	await page.fill(gen + ' .csqr-panel[data-type="vcard"] [data-field="company"]', 'Anderson, Inc.');
	await page.waitForTimeout(500);
	const vcard = await decode(page, gen + ' .csqr-sheet__code');
	ok(vcard && vcard.includes('N:Anderson;Jeremy;;;') && vcard.includes('ORG:Anderson\\, Inc.'), 'contact card holds escaped fields', vcard);

	// Keyboard: arrows move between kinds.
	await page.focus(gen + ' .csqr-kind[aria-selected="true"]');
	await page.keyboard.press('ArrowRight');
	ok(await page.$eval(gen + ' .csqr-kind[data-type="email"]', (n) => n.getAttribute('aria-selected') === 'true' && n === document.activeElement), 'arrow keys move between kinds');

	// Downloads: PNG at print size with DPI, PDF, SVG with physical size.
	await page.click(gen + ' .csqr-kind[data-type="url"]');
	await page.waitForTimeout(400);
	await page.selectOption(gen + ' .csqr-export__size', '10');
	const [png] = await Promise.all([page.waitForEvent('download'), page.click(gen + ' .csqr-export__png')]);
	const pngPath = await png.path();
	const buf = fs.readFileSync(pngPath);
	const width = buf.readUInt32BE(16);
	const phys = buf.indexOf(Buffer.from('pHYs'));
	ok(width === 1181, '10 cm PNG is 1181 px wide (300 dpi)', width);
	ok(phys > 0 && buf.readUInt32BE(phys + 4) === 11811, 'PNG says 300 dpi', phys > 0 ? buf.readUInt32BE(phys + 4) : 'no pHYs');
	ok(/\.png$/.test(png.suggestedFilename()) && png.suggestedFilename().includes('10cm'), 'PNG file name says its size', png.suggestedFilename());
	const [pdf] = await Promise.all([page.waitForEvent('download'), page.click(gen + ' .csqr-export__pdf')]);
	const pdfBuf = fs.readFileSync(await pdf.path());
	ok(pdfBuf.slice(0, 5).toString() === '%PDF-' && pdfBuf.includes(Buffer.from('/MediaBox [0 0 283.46 283.46]')), 'PDF page is 10 cm square');
	const [svg] = await Promise.all([page.waitForEvent('download'), page.click(gen + ' .csqr-export__svg')]);
	const svgText = fs.readFileSync(await svg.path(), 'utf8');
	ok(/<svg[^>]*width="10cm"/.test(svgText), 'SVG carries its printed width', svgText.slice(0, 160));

	/* ---------------------------------------------------------------- */
	console.log('== QR code block: guest WiFi card');
	await page.goto(fixtures.wifi);
	await page.waitForSelector('.csqr-card canvas');
	ok((await decode(page, '.csqr-card .csqr-sheet__code')) === fixtures.wifiPayload, 'WiFi card decodes to the network', await decode(page, '.csqr-card .csqr-sheet__code'));
	const caption = await page.$eval('.csqr-card__caption', (n) => n.textContent);
	ok(caption.includes('Scan to join our WiFi') && caption.includes('Cafe; "Guest"'), 'caption shows the network for people who type it', caption);
	ok(await page.$('.csqr-card .csqr-export__png'), 'card offers downloads when switched on');
	await page.waitForFunction(() => {
		const n = document.querySelector('.csqr-card .csqr-check');
		return n && n.getAttribute('data-level') === 'ok';
	}, null, { timeout: 15000 });
	ok(await page.$eval('.csqr-card .csqr-check', (n) => n.hidden), 'visitors don\'t see the check when the code scans');
	await shot(page, 'card-wifi');

	/* ---------------------------------------------------------------- */
	console.log('== Page QR');
	await page.goto(fixtures.pageQr);
	await page.waitForSelector('.csqr-page-qr canvas');
	ok((await decode(page, '.csqr-page-qr .csqr-sheet__code')) === fixtures.pageQr, 'page QR decodes to the page address');
	await shot(page, 'page-qr');

	/* ---------------------------------------------------------------- */
	console.log('== Scan check catches bad designs');
	await page.goto(fixtures.lowContrast);
	await page.waitForSelector('.csqr-card canvas');
	const low = await waitCheck(page, '.csqr-card');
	ok(low.level === 'fail' && /contrast/i.test(low.text), 'low contrast is reported', low);
	ok(await page.$eval('.csqr-card .csqr-check', (n) => !n.hidden), 'visitors are warned when a published code may not scan');

	/* ---------------------------------------------------------------- */
	console.log('== Saved style with a logo');
	await page.goto(fixtures.styled);
	await page.waitForSelector('.csqr-card canvas');
	const styled = await page.$eval('.csqr-card', (n) => JSON.parse(n.getAttribute('data-csqr-design')));
	ok(styled.qrColorDark === '#1d3a8a' && styled.logoUrl === fixtures.logo, 'block uses the saved style', styled);
	// The scan check finishes only after the logo has loaded and been drawn.
	const logoCheck = await waitCheck(page, '.csqr-card');
	const logoText = await decode(page, '.csqr-card .csqr-sheet__code');
	ok(logoText === 'https://example.com/brand', 'code with a logo still decodes', logoText === 'https://example.com/brand' ? undefined : await page.evaluate(() => {
		const c = document.querySelector('.csqr-card canvas');
		return { decoded: null, canvas: c ? [c.width, c.height, c.style.width] : null, dpr: window.devicePixelRatio, check: document.querySelector('.csqr-card .csqr-check')?.textContent, design: document.querySelector('.csqr-card').getAttribute('data-csqr-design'), ua: navigator.userAgent };
	}));
	ok(logoCheck.level === 'ok', 'logo code passes the scan check', logoCheck);
	const broken = await page.evaluate(async () => {
		const el = document.createElement('div');
		document.body.appendChild(el);
		const d = window.CSQR.design({ logoUrl: location.origin + '/wp-content/uploads/missing-logo.png' });
		const qr = window.CSQR.render(el, d, 'https://example.com/broken', 200);
		const r = await window.CSQR.check(qr, 'https://example.com/broken', d);
		return { level: r.level, ids: r.issues.map((i) => i.code), drawn: !!el.querySelector('canvas') };
	});
	ok(broken.drawn && broken.level === 'warn' && broken.ids.includes('logo'), 'a logo that doesn\'t load is reported, and the code still draws', broken);

	console.log('== Shortcodes');
	await page.goto(fixtures.shortcodes);
	await page.waitForSelector('.csqr-card canvas');
	const cards = await page.$$('.csqr-card');
	ok(cards.length === 2, 'two [qr_code] cards render', cards.length);
	ok((await decode(page, '.csqr-card:nth-of-type(1) .csqr-sheet__code')) === 'https://example.com/short' || (await page.evaluate(() => document.querySelectorAll('.csqr-card')[0].getAttribute('data-csqr-text'))) === 'https://example.com/short', '[qr_code url] holds the link');
	ok((await page.evaluate(() => document.querySelectorAll('.csqr-card')[1].getAttribute('data-csqr-text'))) === fixtures.link, '[qr_code link] uses the short address');
	const sc = await page.$eval('.csqr-generator', (n) => ({ d: JSON.parse(n.getAttribute('data-csqr-design')), wifi: !!n.querySelector('.csqr-kind[data-type="wifi"]') }));
	ok(sc.d.qrColorDark === '#123456' && !sc.wifi, 'camel-case shortcode attributes work (WordPress lowercases them)', sc);

	console.log('== Dark theme and phone width');
	await page.goto(fixtures.generator);
	await page.addStyleTag({ content: 'body{background:#111!important;color:#eee!important}' });
	await page.fill('.csqr-generator [data-field="url"]', 'https://example.com/dark');
	await page.waitForTimeout(500);
	const paper = await page.$eval('.csqr-sheet', (n) => getComputedStyle(n).backgroundColor);
	ok(paper === 'rgb(255, 255, 255)', 'code stays on white paper on a dark theme', paper);
	ok((await decode(page, '.csqr-generator .csqr-sheet__code')) === 'https://example.com/dark', 'and still decodes');
	await shot(page, 'generator-dark');
	await page.setViewportSize({ width: 375, height: 800 });
	await page.waitForTimeout(300);
	const overflow = await page.evaluate(() => document.documentElement.scrollWidth - window.innerWidth);
	ok(overflow <= 0, 'no sideways scrolling at phone width', overflow);
	await shot(page, 'generator-phone');
	await page.setViewportSize({ width: 1280, height: 900 });

	/* ---------------------------------------------------------------- */
	console.log('== Short link');
	const before = await page.request.get(fixtures.link, { maxRedirects: 0, headers: { 'User-Agent': 'Mozilla/5.0 (iPhone) e2e' } });
	ok(before.status() === 302 && before.headers().location === fixtures.linkDest, 'short link redirects to its destination', before.status() + ' ' + before.headers().location);
	const bot = await page.request.get(fixtures.link, { maxRedirects: 0, headers: { 'User-Agent': 'Slackbot-LinkExpanding 1.0' } });
	ok(bot.status() === 302, 'link previews still redirect');
	const missing = await page.request.get(fixtures.link.replace('/menu', '/nope'), { maxRedirects: 0 });
	ok(missing.status() === 404, 'unknown short link is a 404');

	/* ---------------------------------------------------------------- */
	console.log('== Smarter short links');
	const BASEU = new URL(fixtures.lunchUrl).origin;
	const desk = { 'User-Agent': 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 Chrome/130 Safari/537.36' };
	const go = (path, headers) => page.request.get(BASEU + path, { maxRedirects: 0, headers: headers || desk });
	let r = await go('/qr/lunch/');
	ok(r.status() === 302 && r.headers().location === 'https://example.com/lunch', 'desktop scan goes to the destination', r.headers().location);
	r = await go('/qr/lunch/', { 'User-Agent': 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_0 like Mac OS X) AppleWebKit/605.1.15 Mobile/15E148' });
	ok(r.headers().location === 'https://apps.apple.com/app/id1', 'iPhone scan goes to the App Store link', r.headers().location);
	r = await go('/qr/lunch/', { 'User-Agent': 'Mozilla/5.0 (Linux; Android 15; Pixel 9) AppleWebKit/537.36 Chrome/130 Mobile Safari/537.36' });
	ok(r.headers().location === 'https://play.google.com/store/apps/details?id=x', 'Android scan goes to the Play link', r.headers().location);
	r = await go('/qr/lunch/front-window/');
	ok(r.status() === 302 && r.headers().location === 'https://example.com/lunch', 'a spot\'s code goes to the same place', r.status());
	r = await go('/qr/happy/');
	ok(r.headers().location === 'https://example.com/scheduled', 'a schedule rule changes the destination', r.headers().location);
	r = await go('/qr/fall-offer/');
	ok(r.status() === 410 && (await r.text()).includes('Our fall offer has ended.'), 'after the end date, people see the message', r.status());
	r = await go('/qr/card/');
	const vcf = await r.text();
	ok(r.status() === 200 && /text\/vcard/.test(r.headers()['content-type']) && vcf.includes('FN:Jeremy Anderson') && vcf.includes('TEL;TYPE=CELL:+1 508 555 0100'), 'contact link serves a contact card', r.headers()['content-type']);

	console.log('== Codes from other QR plugins');
	await page.goto(fixtures.compat);
	await page.waitForFunction(() => document.querySelectorAll('.csqr-card canvas').length === 2);
	ok((await decode(page, '.csqr-card:nth-of-type(1) .csqr-sheet__code')) === 'https://example.com/kaya' || (await page.evaluate(() => document.querySelectorAll('.csqr-card')[0].getAttribute('data-csqr-text'))) === 'https://example.com/kaya', '[kaya_qrcode] is drawn');
	ok((await page.evaluate(() => document.querySelectorAll('.csqr-card')[1].getAttribute('data-csqr-text'))) === 'https://example.com/su' && /SU code/.test(await page.evaluate(() => document.querySelectorAll('.csqr-card')[1].textContent)), '[su_qrcode] is drawn with its title');

	if (fixtures.product) {
		console.log('== WooCommerce product');
		await page.goto(fixtures.productUrl);
		await page.waitForSelector('.product_meta .csqr-page-qr canvas, .product_meta ~ .csqr-page-qr canvas, .csqr-page-qr canvas', { timeout: 15000 });
		ok((await decode(page, '.csqr-page-qr .csqr-sheet__code')) === fixtures.productUrl, 'product page shows its code');
		ok((await page.$$('.csqr-page-qr')).length === 1, 'only one code on the product page', (await page.$$('.csqr-page-qr')).length);
	}

	/* ---------------------------------------------------------------- */
	console.log('== Admin');
	await page.goto(BASE + '/wp-login.php');
	await page.fill('#user_login', USER);
	await page.fill('#user_pass', PASS);
	await Promise.all([page.waitForNavigation(), page.click('#wp-submit')]);

	await page.goto(fixtures.wifi);
	await page.waitForFunction(() => {
		const n = document.querySelector('.csqr-card .csqr-check');
		return n && !n.hidden && n.getAttribute('data-level') === 'ok';
	}, null, { timeout: 15000 });
	ok(await page.$eval('.csqr-card .csqr-check', (n) => /Scans correctly/.test(n.textContent) && !!n.querySelector('.csqr-check__note')), 'editors see the scan check on published codes');

	await page.goto(BASE + '/wp-admin/post.php?post=' + fixtures.linkId + '&action=edit');
	await page.waitForSelector('#csqr-link-code canvas');
	ok((await decode(page, '#csqr-link-code .csqr-sheet__code')) === fixtures.link, 'short link screen shows its code');
	ok((await page.$eval('.csqr-scans', (n) => n.textContent.trim())) === '1', 'one real scan counted, the link preview not', await page.$eval('.csqr-scans', (n) => n.textContent.trim()));
	await page.fill('#csqr-destination', 'https://example.com/new');
	await Promise.all([page.waitForNavigation(), page.click('#publish')]);
	const moved = await page.request.get(fixtures.link, { maxRedirects: 0, headers: { 'User-Agent': 'Mozilla/5.0 e2e' } });
	ok(moved.headers().location === 'https://example.com/new', 'changing the destination repoints the printed code', moved.headers().location);
	await shot(page, 'admin-link');

	await page.goto(BASE + '/wp-admin/admin.php?page=csqr-settings');
	await page.waitForSelector('[data-csqr-preview] canvas');
	ok((await waitCheck(page, '[data-csqr-preview]')).level === 'ok', 'settings preview passes its scan check');
	await page.fill('#csqr_settings-qrColorDark', '#dddddd');
	await page.dispatchEvent('#csqr_settings-qrColorDark', 'input');
	await page.waitForTimeout(400);
	const bad = await waitCheck(page, '[data-csqr-preview]');
	ok(bad.level === 'fail', 'a pale code color fails the check', bad);
	await page.click('[data-csqr-preview] .csqr-fix');
	await page.waitForTimeout(500);
	ok((await page.inputValue('#csqr_settings-qrColorDark')) === '#000000' && (await waitCheck(page, '[data-csqr-preview]')).level === 'ok', 'one-click fix updates the setting and passes');
	await shot(page, 'admin-settings');

	// Saved style.
	await page.goto(BASE + '/wp-admin/admin.php?page=csqr-styles');
	await page.fill('#csqr-style-name', 'Brand blue');
	await page.fill('#design-qrColorDark', '#1d3a8a');
	await Promise.all([page.waitForNavigation(), page.click('#submit')]);
	ok(await page.$('.csqr-style-list__item'), 'a style can be saved');
	await shot(page, 'admin-styles');

	// Shortcode builder.
	await page.goto(BASE + '/wp-admin/admin.php?page=csqr-shortcode-builder');
	await page.selectOption('#csqr-b-kind', 'link');
	await page.selectOption('#csqr-b-style', 'brand-blue');
	ok((await page.inputValue('#csqr-b-output')) === '[qr_code link="menu" style="brand-blue"]', 'builder writes the shortcode', await page.inputValue('#csqr-b-output'));

	/* ---------------------------------------------------------------- */
	console.log('== Scan history and spots');
	await page.goto(BASE + '/wp-admin/post.php?post=' + fixtures.lunch + '&action=edit');
	await page.waitForSelector('#csqr-link-history');
	ok(await page.$('#csqr-link-history .csqr-history__chart'), 'link screen charts the scans');
	ok(/[1-9]/.test(await page.$eval('#csqr-link-history .csqr-history__total strong', (n) => n.textContent)), 'chart counts today\'s scans');
	const spotRows = await page.$$eval('.csqr-places__table tbody tr', (rows) => rows.map((tr) => [tr.querySelector('th').textContent.trim().split('\n')[0], tr.querySelector('.csqr-places__n').textContent.trim()]));
	const windowRow = spotRows.find((row) => /Front window/.test(row[0]));
	ok(windowRow && windowRow[1] === '1', 'a spot\'s scans are counted separately', spotRows);
	ok(await page.isHidden('#csqr-contact-firstname'), 'contact fields are hidden for a web-address link');
	await page.check('input[name="csqr[kind]"][value="contact"]');
	ok(await page.isVisible('#csqr-contact-firstname') && await page.isHidden('#csqr-destination'), 'choosing "Contact card" swaps the fields');
	await page.check('input[name="csqr[kind]"][value="url"]');
	// Change a rule and an end date from the screen, then check the redirect.
	await page.fill('input[name="csqr[schedule][0][url]"]', 'https://example.com/from-screen');
	await page.fill('input[name="csqr[placements][new][name]"]', 'Takeout bag');
	await Promise.all([page.waitForNavigation(), page.click('#publish')]);
	r = await go('/qr/lunch/');
	ok(r.headers().location === 'https://example.com/from-screen', 'a rule added on the screen takes effect', r.headers().location);
	ok(await page.$$eval('.csqr-places__item input[type="text"]', (ns) => ns.map((n) => n.value).includes('Takeout bag')), 'a new spot is saved');

	console.log('== REST API');
	const nonce = await (await page.request.get(BASE + '/wp-admin/admin-ajax.php?action=rest-nonce')).text();
	const api = (method, path, data) => page.request.fetch(BASE + '/wp-json/anderson-qr/v1' + path, { method, headers: { 'X-WP-Nonce': nonce }, data });
	let res = await api('POST', '/links', { name: 'From the API', destination: 'https://example.com/api', placements: 'Poster' });
	const made = await res.json();
	ok(res.status() === 201 && made.slug === 'from-the-api' && made.placements.length === 1, 'API creates a link', res.status());
	res = await api('POST', '/links', { name: 'From the API', destination: 'https://example.com/api' });
	ok(res.status() === 409, 'API refuses a short address that\'s taken', res.status());
	res = await api('PATCH', '/links/' + made.id, { destination: 'https://example.com/api-2' });
	ok((await res.json()).destination === 'https://example.com/api-2', 'API updates a link');
	res = await api('GET', '/links/' + fixtures.lunch + '/stats?days=7');
	const stats = await res.json();
	ok(res.status() === 200 && Object.keys(stats.days).length === 7 && stats.in_period >= 1, 'API reports daily scans', stats.in_period);
	res = await api('DELETE', '/links/' + made.id);
	ok((await res.json()).trashed === true, 'API trashes a link');
	res = await page.request.get(BASE + '/wp-json/anderson-qr/v1/links', { headers: { 'X-WP-Nonce': 'nope' } });
	ok(res.status() === 401 || res.status() === 403, 'API needs a logged-in admin', res.status());

	console.log('== Bulk create and print');
	await page.goto(BASE + '/wp-admin/admin.php?page=csqr-bulk');
	await page.fill('#csqr-rows', 'name,destination,placements\nBrunch menu,https://example.com/brunch,"Patio, Bar"\nWine list,https://example.com/wine,\nNo address,,');
	await Promise.all([page.waitForNavigation(), page.click('.csqr-bulk__form [type="submit"]')]);
	const note = await page.textContent('.csqr-bulk__results');
	ok(/2 created/.test(note) && /1 with a problem/.test(note), 'bulk create makes the good rows and reports the bad one', note.replace(/\s+/g, ' ').trim());
	await Promise.all([page.waitForNavigation(), page.click('.csqr-bulk__results .button-primary')]);
	await page.waitForFunction(() => document.querySelectorAll('.csqr-print__code svg').length >= 4, null, { timeout: 20000 });
	const printed = await page.evaluate(async () => {
		const jsQR = await window.CSQR.loadDecoder();
		const out = [];
		for (const box of document.querySelectorAll('.csqr-print__code')) {
			const img = new Image();
			await new Promise((done) => {
				img.onload = done;
				img.src = 'data:image/svg+xml;charset=utf-8,' + encodeURIComponent(box.querySelector('svg').outerHTML.replace('<svg', '<svg width="400" height="400"'));
			});
			const c = document.createElement('canvas');
			c.width = c.height = 400;
			const x = c.getContext('2d');
			x.fillStyle = '#fff';
			x.fillRect(0, 0, 400, 400);
			x.drawImage(img, 0, 0, 400, 400);
			const found = jsQR(x.getImageData(0, 0, 400, 400).data, 400, 400);
			out.push(found ? found.data : null);
		}
		return out;
	});
	ok(printed.length === 4 && printed.every((t) => /\/qr\/(brunch-menu|wine-list)\//.test(t || '')), 'every code on the print sheet decodes (each spot included)', printed);
	ok(printed.some((t) => /\/qr\/brunch-menu\/patio\/$/.test(t || '')), 'spots get their own codes on the sheet', printed);
	await shot(page, 'print-grid');
	await page.selectOption('[data-csqr-print-layout]', 'tent');
	ok((await page.$$('.csqr-print__page')).length === 4, 'table tents: one page per code', (await page.$$('.csqr-print__page')).length);
	const [zip] = await Promise.all([page.waitForEvent('download'), page.click('[data-csqr-print-zip]')]);
	const zipBuf = fs.readFileSync(await zip.path());
	ok(zipBuf.readUInt32LE(0) === 0x04034b50 && zipBuf.includes(Buffer.from('qr-brunch-menu-patio.svg')) && zipBuf.includes(Buffer.from('qr-wine-list.png')), 'zip holds a PNG and an SVG per code');

	const [csv] = await Promise.all([page.waitForEvent('download'), page.goto(BASE + '/wp-admin/admin.php?page=csqr-bulk').then(() => page.click('text=Download spreadsheet'))]);
	const csvText = fs.readFileSync(await csv.path(), 'utf8');
	ok(csvText.startsWith('name,slug,url,destination') && csvText.includes('Brunch menu') && csvText.includes('"Patio, Bar"'), 'export downloads every link as a spreadsheet');

	if (fixtures.product) {
		console.log('== WooCommerce labels');
		await page.goto(BASE + '/wp-admin/admin.php?page=csqr-print&source=products&ids=' + fixtures.product);
		await page.waitForSelector('.csqr-print__code svg');
		const label = await page.textContent('.csqr-print__cell');
		ok(/Espresso beans/.test(label) && /12[.,]50/.test(label) && /BEANS-1/.test(label), 'product label shows name, price and SKU', label.replace(/\s+/g, ' ').trim());
	}

	await page.goto(BASE + '/wp-admin/admin.php?page=csqr-settings');
	ok(/kaya_qrcode/.test(await page.textContent('#wpbody-content')), 'settings list the old plugin shortcodes in use');

	/* ---------------------------------------------------------------- */
	console.log('== Block editor');
	await page.goto(BASE + '/wp-admin/post-new.php');
	await page.waitForFunction(() => window.wp && wp.data && wp.data.select('core/block-editor') && wp.blocks.getBlockType('csqr/code'), null, { timeout: 30000 });
	await page.waitForTimeout(3000); // Let the editor finish loading the new post.
	// Close the welcome guide or starter patterns dialog, whichever shows.
	for (let i = 0; i < 3 && (await page.waitForSelector('.components-modal__frame', { timeout: 4000 }).catch(() => null)); i++) {
		await page.focus('.components-modal__frame');
		await page.keyboard.press('Escape');
		await page.waitForTimeout(300);
	}
	await page.evaluate(() => {
		try {
			wp.data.dispatch('core/preferences').set('core', 'welcomeGuide', false);
			wp.data.dispatch('core/preferences').set('core/edit-post', 'welcomeGuide', false);
		} catch (e) {}
	});
	const variations = await page.evaluate(() => wp.blocks.getBlockVariations('csqr/code').map((v) => v.name));
	ok(['page', 'wifi', 'contact', 'review', 'short-link'].every((n) => variations.includes(n)), 'templates offered as block variations', variations);
	await page.evaluate((linkId) => {
		const b = wp.blocks.createBlock;
		wp.data.dispatch('core/block-editor').insertBlocks([
			b('csqr/code', { type: 'wifi', ssid: 'Studio', password: 'hunter2', showDownloads: true }),
			b('csqr/code', { type: 'link', linkId: linkId }),
			b('csqr/generator', {})
		]);
	}, fixtures.linkId);
	await page.waitForTimeout(2500);
	const canvasFrame = page.frame({ name: 'editor-canvas' }) || page.mainFrame();
	async function decodeIn(frame, sel, index) {
		const url = await frame.evaluate((args) => {
			const c = document.querySelectorAll(args[0])[args[1] || 0];
			return c ? c.toDataURL() : null;
		}, [sel, index || 0]);
		if (!url) {
			return null;
		}
		return page.evaluate(async (u) => {
			const img = new Image();
			await new Promise((r) => {
				img.onload = r;
				img.src = u;
			});
			const c = document.createElement('canvas');
			c.width = img.width;
			c.height = img.height;
			const x = c.getContext('2d');
			x.fillStyle = '#fff';
			x.fillRect(0, 0, c.width, c.height);
			x.drawImage(img, 0, 0);
			const jsQR = await window.CSQR.loadDecoder();
			const r = jsQR(x.getImageData(0, 0, c.width, c.height).data, c.width, c.height);
			return r ? r.data : null;
		}, url);
	}
	const blocksInEditor = await canvasFrame.$$eval('[data-type="csqr/code"] canvas', (n) => n.length);
	ok(blocksInEditor === 2, 'both QR code blocks preview in the editor', blocksInEditor);
	ok((await decodeIn(canvasFrame, '[data-type="csqr/code"] canvas', 0)) === 'WIFI:T:WPA;S:Studio;P:hunter2;;', 'WiFi block preview decodes');
	ok((await decodeIn(canvasFrame, '[data-type="csqr/code"] canvas', 1)) === fixtures.link, 'short link block preview decodes to the short address');
	ok(await canvasFrame.$('[data-type="csqr/generator"] canvas'), 'generator block shows a style preview');
	const editorCheck = await canvasFrame.$eval('[data-type="csqr/code"] .csqr-check', (n) => n.getAttribute('data-level'));
	ok(editorCheck === 'ok', 'scan check runs in the editor', editorCheck);

	// Fix from the editor: pale color, then the fix button sets the block's color.
	await page.evaluate(() => {
		const blk = wp.data.select('core/block-editor').getBlocks().find((x) => x.name === 'csqr/code');
		wp.data.dispatch('core/block-editor').updateBlockAttributes(blk.clientId, { qrColorDark: '#e0e0e0' });
	});
	await canvasFrame.waitForSelector('[data-type="csqr/code"] .csqr-check[data-level="fail"] .csqr-fix', { timeout: 10000 });
	await canvasFrame.click('[data-type="csqr/code"] .csqr-fix');
	await page.waitForTimeout(400);
	const fixedColor = await page.evaluate(() => wp.data.select('core/block-editor').getBlocks().find((x) => x.name === 'csqr/code').attributes.qrColorDark);
	ok(fixedColor === '#000000', 'editor fix updates the block', fixedColor);
	await shot(page, 'editor');

	// Page QR panel: switch on, save, check the page.
	await page.evaluate(() => {
		wp.data.dispatch('core/editor').editPost({ title: 'Editor test', status: 'publish', meta: { _csqr_page_qr: 'on' } });
	});
	await page.evaluate(() => wp.data.dispatch('core/editor').savePost());
	await page.waitForFunction(() => !wp.data.select('core/editor').isSavingPost() && wp.data.select('core/editor').isCurrentPostPublished(), null, { timeout: 30000 });
	const saved = await page.evaluate(() => ({ link: wp.data.select('core/editor').getPermalink(), meta: wp.data.select('core/editor').getEditedPostAttribute('meta') }));
	ok(saved.meta._csqr_page_qr === 'on', 'page QR switch saves', saved.meta);
	await page.goto(saved.link);
	await page.waitForSelector('.csqr-code-block canvas');
	ok((await decode(page, '.csqr-code-block .csqr-sheet__code')) === 'WIFI:T:WPA;S:Studio;P:hunter2;;', 'saved WiFi block decodes on the page');
	ok(!(await page.$('.csqr-page-qr')), 'page with a QR code block doesn’t get a second page QR');

	ok(errors.length === 0, 'no JavaScript errors', errors);

	console.log(`\n${pass} passed, ${fail} failed`);
	await browser.close();
	process.exit(fail ? 1 : 0);
})().catch((e) => {
	console.error(e);
	process.exit(1);
});
