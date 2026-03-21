const fs = require('fs');
const path = require('path');

const repoRoot = process.cwd();
const targetFiles = [
    'client-side-qr.php',
    path.join('assets', 'qr-block.js')
];

const patterns = [
    /(?:__|esc_html__|esc_attr__)\(\s*'((?:\\'|[^'])*)'\s*,\s*'csqr'\s*\)/g,
    /(?:__|esc_html__|esc_attr__)\(\s*"((?:\\"|[^"])*)"\s*,\s*"csqr"\s*\)/g,
    /(?:_e|esc_html_e|esc_attr_e)\(\s*'((?:\\'|[^'])*)'\s*,\s*'csqr'\s*\)/g,
    /(?:_e|esc_html_e|esc_attr_e)\(\s*"((?:\\"|[^"])*)"\s*,\s*"csqr"\s*\)/g
];

function unescapePoString(value) {
    return value
        .replace(/\\'/g, "'")
        .replace(/\\"/g, '"')
        .replace(/\\\\/g, '\\');
}

function escapePoString(value) {
    return value
        .replace(/\\/g, '\\\\')
        .replace(/"/g, '\\"')
        .replace(/\n/g, '\\n');
}

function collectEntries(filePath) {
    const absolutePath = path.join(repoRoot, filePath);
    const content = fs.readFileSync(absolutePath, 'utf8');
    const entries = new Map();
    const lines = content.split(/\r?\n/);

    lines.forEach((line, index) => {
        patterns.forEach((pattern) => {
            pattern.lastIndex = 0;
            let match;

            while ((match = pattern.exec(line)) !== null) {
                const message = unescapePoString(match[1]);

                if (!entries.has(message)) {
                    entries.set(message, []);
                }

                entries.get(message).push(`${filePath}:${index + 1}`);
            }
        });
    });

    return entries;
}

const catalog = new Map();

targetFiles.forEach((filePath) => {
    const entries = collectEntries(filePath);

    entries.forEach((references, message) => {
        if (!catalog.has(message)) {
            catalog.set(message, new Set());
        }

        const catalogRefs = catalog.get(message);
        references.forEach((reference) => catalogRefs.add(reference));
    });
});

const sortedMessages = Array.from(catalog.keys()).sort((a, b) => a.localeCompare(b));
const year = new Date().getFullYear();

let pot = '';
pot += 'msgid ""\n';
pot += 'msgstr ""\n';
pot += '"Project-Id-Version: Client-Side QR Code Generator 4.1.1\\n"\n';
pot += '"Report-Msgid-Bugs-To: https://github.com/CptNope/Client-Side-QR/issues\\n"\n';
pot += `"POT-Creation-Date: ${new Date().toISOString().replace('T', ' ').replace(/\.\d+Z$/, '+0000')}\\n"\n`;
pot += `"PO-Revision-Date: ${year}-01-01 00:00+0000\\n"\n`;
pot += '"Last-Translator: FULL NAME <EMAIL@ADDRESS>\\n"\n';
pot += '"Language-Team: LANGUAGE <LL@li.org>\\n"\n';
pot += '"MIME-Version: 1.0\\n"\n';
pot += '"Content-Type: text/plain; charset=UTF-8\\n"\n';
pot += '"Content-Transfer-Encoding: 8bit\\n"\n';
pot += '"X-Domain: csqr\\n"\n';
pot += '"X-Generator: tools/generate-pot.js\\n"\n\n';

sortedMessages.forEach((message) => {
    const references = Array.from(catalog.get(message)).sort();
    references.forEach((reference) => {
        pot += `#: ${reference}\n`;
    });
    pot += `msgid "${escapePoString(message)}"\n`;
    pot += 'msgstr ""\n\n';
});

fs.mkdirSync(path.join(repoRoot, 'languages'), { recursive: true });
fs.writeFileSync(path.join(repoRoot, 'languages', 'csqr.pot'), pot, 'utf8');

console.log(`Generated languages/csqr.pot with ${sortedMessages.length} strings.`);
