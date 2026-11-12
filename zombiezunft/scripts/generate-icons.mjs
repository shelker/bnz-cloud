/**
 * Erzeugt Favicon & App-Icons aus scripts/assets/kuerbis.webp (Kürbis auf einfarbigem Hintergrund).
 * Aufruf: node scripts/generate-icons.mjs   (Ergebnis liegt eingecheckt in public/)
 */
import { writeFile } from 'node:fs/promises';
import sharp from 'sharp';

const src = new URL('./assets/kuerbis.webp', import.meta.url).pathname;
const out = (name) => new URL(`../public/${name}`, import.meta.url).pathname;
const NIGHT = { r: 7, g: 11, b: 20, alpha: 1 };

// 1) Hintergrund per Flood-Fill von den Rändern freistellen
const { data, info } = await sharp(src).ensureAlpha().raw().toBuffer({ resolveWithObject: true });
const { width: w, height: h } = info;
const bg = [data[0], data[1], data[2]];
const dist = (i) => Math.hypot(data[i] - bg[0], data[i + 1] - bg[1], data[i + 2] - bg[2]);
const seen = new Uint8Array(w * h);
const stack = [];
for (let x = 0; x < w; x++) stack.push(x, (h - 1) * w + x);
for (let y = 0; y < h; y++) stack.push(y * w, y * w + w - 1);
while (stack.length) {
  const p = stack.pop();
  if (seen[p] || dist(p * 4) > 34) continue;
  seen[p] = 1;
  const x = p % w;
  const y = (p / w) | 0;
  if (x > 0) stack.push(p - 1);
  if (x < w - 1) stack.push(p + 1);
  if (y > 0) stack.push(p - w);
  if (y < h - 1) stack.push(p + w);
}
for (let p = 0; p < w * h; p++) data[p * 4 + 3] = seen[p] ? 0 : 255;
const raw = { raw: { width: w, height: h, channels: 4 } };
const softAlpha = await sharp(data, raw).blur(0.8).extractChannel(3).toBuffer();
const rgb = await sharp(data, raw).removeAlpha().png().toBuffer();
const cutout = await sharp(await sharp(rgb).joinChannel(softAlpha, { raw: { width: w, height: h, channels: 1 } }).png().toBuffer())
  .trim({ threshold: 1 })
  .toBuffer();

// 2) quadratische Icons
async function icon(size, { padding, background } = {}) {
  const inner = Math.round(size * (1 - padding * 2));
  const pumpkin = await sharp(cutout).resize(inner, inner, { fit: 'contain', background: { r: 0, g: 0, b: 0, alpha: 0 } }).toBuffer();
  return sharp({ create: { width: size, height: size, channels: 4, background: background ?? { r: 0, g: 0, b: 0, alpha: 0 } } })
    .composite([{ input: pumpkin, gravity: 'center' }])
    .png({ compressionLevel: 9 })
    .toBuffer();
}

const png16 = await icon(16, { padding: 0.02 });
const png32 = await icon(32, { padding: 0.02 });
const png48 = await icon(48, { padding: 0.02 });

await writeFile(out('favicon-32.png'), png32);
await writeFile(out('icon-192.png'), await icon(192, { padding: 0.12, background: NIGHT }));
await writeFile(out('apple-touch-icon.png'), await icon(180, { padding: 0.12, background: NIGHT }));

// 3) favicon.ico mit eingebetteten PNGs (16, 32, 48)
const entries = [
  [16, png16],
  [32, png32],
  [48, png48],
];
const header = Buffer.alloc(6);
header.writeUInt16LE(0, 0);
header.writeUInt16LE(1, 2);
header.writeUInt16LE(entries.length, 4);
let offset = 6 + entries.length * 16;
const dir = entries.map(([size, buf]) => {
  const e = Buffer.alloc(16);
  e.writeUInt8(size, 0);
  e.writeUInt8(size, 1);
  e.writeUInt16LE(1, 4);
  e.writeUInt16LE(32, 6);
  e.writeUInt32LE(buf.length, 8);
  e.writeUInt32LE(offset, 12);
  offset += buf.length;
  return e;
});
await writeFile(out('favicon.ico'), Buffer.concat([header, ...dir, ...entries.map(([, b]) => b)]));
console.log('Icons erzeugt');
