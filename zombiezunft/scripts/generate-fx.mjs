/**
 * Erzeugt die prozeduralen Effekt-Texturen (nahtlos in X-Richtung kachelbar).
 * Aufruf: node scripts/generate-fx.mjs   (Ergebnis liegt eingecheckt in public/fx/)
 */
import { mkdir } from 'node:fs/promises';
import sharp from 'sharp';

const outDir = new URL('../public/fx/', import.meta.url);
await mkdir(outDir, { recursive: true });

function mulberry32(seed) {
  return () => {
    seed |= 0;
    seed = (seed + 0x6d2b79f5) | 0;
    let t = Math.imul(seed ^ (seed >>> 15), 1 | seed);
    t = (t + Math.imul(t ^ (t >>> 7), 61 | t)) ^ t;
    return ((t ^ (t >>> 14)) >>> 0) / 4294967296;
  };
}

const smooth = (t) => t * t * (3 - 2 * t);
const smoothstep = (a, b, x) => smooth(Math.min(Math.max((x - a) / (b - a), 0), 1));

/** Value-Noise mit Gitter, das in X periodisch ist. */
function makeNoise(periodX, periodY, seed) {
  const rand = mulberry32(seed);
  const grid = Array.from({ length: periodX * periodY }, rand);
  return (x, y) => {
    const x0 = Math.floor(x);
    const y0 = Math.floor(y);
    const fx = smooth(x - x0);
    const fy = smooth(y - y0);
    const at = (gx, gy) => grid[(((gy % periodY) + periodY) % periodY) * periodX + (((gx % periodX) + periodX) % periodX)];
    const top = at(x0, y0) * (1 - fx) + at(x0 + 1, y0) * fx;
    const bottom = at(x0, y0 + 1) * (1 - fx) + at(x0 + 1, y0 + 1) * fx;
    return top * (1 - fy) + bottom * fy;
  };
}

async function fog({ name, width, height, seed, density }) {
  const octaves = [
    { noise: makeNoise(4, 3, seed), fx: 4, fy: 3, amp: 0.5 },
    { noise: makeNoise(8, 6, seed + 1), fx: 8, fy: 6, amp: 0.28 },
    { noise: makeNoise(16, 12, seed + 2), fx: 16, fy: 12, amp: 0.15 },
    { noise: makeNoise(32, 24, seed + 3), fx: 32, fy: 24, amp: 0.07 },
  ];
  const data = Buffer.alloc(width * height * 4);

  for (let y = 0; y < height; y++) {
    const v = y / height;
    // dichter im unteren Drittel, oben und unten weich auslaufend
    const profile = smoothstep(0, 0.45, v) * (1 - smoothstep(0.78, 1, v));
    for (let x = 0; x < width; x++) {
      const u = x / width;
      let n = 0;
      for (const o of octaves) n += o.noise(u * o.fx, v * o.fy) * o.amp;
      const shaped = smoothstep(0.34, 0.8, n) * profile * density;
      const i = (y * width + x) * 4;
      data[i] = 196;
      data[i + 1] = 206;
      data[i + 2] = 226;
      data[i + 3] = Math.round(Math.min(shaped, 1) * 255);
    }
  }

  // Dreifach nebeneinander kacheln, weichzeichnen, mittlere Kachel nehmen: sonst entsteht an den Rändern eine Naht
  const tile = await sharp(data, { raw: { width, height, channels: 4 } }).png().toBuffer();
  const wide = await sharp({ create: { width: width * 3, height, channels: 4, background: { r: 0, g: 0, b: 0, alpha: 0 } } })
    .composite([0, 1, 2].map((i) => ({ input: tile, left: i * width, top: 0 })))
    .png()
    .toBuffer();
  await sharp(wide)
    .blur(1.2)
    .extract({ left: width, top: 0, width, height })
    .webp({ quality: 74, alphaQuality: 80, effort: 5 })
    .toFile(new URL(`${name}.webp`, outDir).pathname);
  console.log(`${name}.webp`);
}

await fog({ name: 'fog-a', width: 1600, height: 360, seed: 11, density: 0.95 });
await fog({ name: 'fog-b', width: 1600, height: 360, seed: 73, density: 0.8 });
