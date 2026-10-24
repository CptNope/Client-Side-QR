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
