/**
 * Vyrenderuje PNG ikony z resources/images/logo.svg do storage/icons/.
 *
 * Vnitřní vykreslovač SVG v ImageMagicku nekreslí obrysy polygonů, proto PNG
 * dělá resvg (bez závislostí na systému) a ImageMagick z nich jen složí
 * favicon.ico. Celý postup je v docs/CODING_GUIDELINES.md, sekce 6.
 * CommonJS kvůli NODE_PATH — balíček se instaluje dočasně do /tmp mimo projekt.
 *
 * @author Roman Hlaváček
 * @created 2026-10-11
 */
const { Resvg } = require('@resvg/resvg-js');
const fs = require('node:fs');
const path = require('node:path');

const ROOT = path.resolve(__dirname, '../..');
const SOURCE = path.join(ROOT, 'resources/images/logo.svg');
const TARGET_DIR = path.join(ROOT, 'storage/icons');
/** Velikosti v px: 180 = apple-touch-icon, 192 a 512 = manifest aplikace (R27), ostatní do favicon.ico. */
const SIZES = [512, 192, 180, 48, 32, 16];

fs.mkdirSync(TARGET_DIR, { recursive: true });
const svg = fs.readFileSync(SOURCE);

for (const size of SIZES) {
    const png = new Resvg(svg, { fitTo: { mode: 'width', value: size } }).render().asPng();
    fs.writeFileSync(path.join(TARGET_DIR, `icon-${size}.png`), png);
}
