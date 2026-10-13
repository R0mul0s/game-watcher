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

/**
 * Maskovatelná ikona pro manifest (R37): podklad přes celou plochu bez zaoblení
 * a logo zmenšené do bezpečné zóny (kruh o průměru 80 % — systém ikonu ořízne
 * do kruhu, čtverce nebo kapky). Kresba loga se převezme ze zdroje bez jeho podkladu.
 */
const MASKABLE_SIZE = 512;
const MASKABLE_SCALE = 0.72;
const VIEWBOX = 48;
const offset = (VIEWBOX * (1 - MASKABLE_SCALE)) / 2;
const source = svg.toString('utf8');
const background = source.match(/<rect[^>]*fill="([^"]+)"/)[1];
const drawing = source.replace(/<!--[\s\S]*?-->/g, '').replace(/^[\s\S]*?<svg[^>]*>/, '').replace(/<\/svg>[\s\S]*$/, '').replace(/<rect[^>]*\/>/, '');
const maskable = `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ${VIEWBOX} ${VIEWBOX}" width="${MASKABLE_SIZE}" height="${MASKABLE_SIZE}">`
    + `<rect width="${VIEWBOX}" height="${VIEWBOX}" fill="${background}"/>`
    + `<g transform="translate(${offset} ${offset}) scale(${MASKABLE_SCALE})">${drawing}</g></svg>`;
fs.writeFileSync(
    path.join(TARGET_DIR, `icon-maskable-${MASKABLE_SIZE}.png`),
    new Resvg(maskable, { fitTo: { mode: 'width', value: MASKABLE_SIZE } }).render().asPng(),
);
