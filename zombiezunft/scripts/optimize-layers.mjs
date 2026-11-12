import { mkdir, readdir, stat } from 'node:fs/promises';
import { join, basename } from 'node:path';
import sharp from 'sharp';

const srcDir = new URL('../public/layers/', import.meta.url);
const outDir = new URL('../public/layers/opt/', import.meta.url);

await mkdir(outDir, { recursive: true });

const files = (await readdir(srcDir)).filter((name) => name.endsWith('.png'));

async function isFresh(src, dest) {
  try {
    const [sourceStat, destStat] = await Promise.all([stat(src), stat(dest)]);
    return destStat.mtimeMs >= sourceStat.mtimeMs;
  } catch {
    return false;
  }
}

for (const name of files) {
  const src = new URL(name, srcDir);
  const stem = basename(name, '.png');
  const full = new URL(`${stem}.webp`, outDir);
  const narrow = new URL(`${stem}-900.webp`, outDir);

  if (await isFresh(src, full)) continue;

  const detail = /^(02|03|04|05)-/.test(name);
  const fullQuality = detail ? 86 : 80;
  const narrowQuality = detail ? 84 : 78;

  let pipeline = sharp(src.pathname).ensureAlpha();
  if (detail) {
    pipeline = pipeline.sharpen({ sigma: 0.55, m1: 0.4, m2: 0.2 });
  }

  await pipeline.clone().webp({ quality: fullQuality, alphaQuality: 88, effort: 4 }).toFile(full.pathname);
  await pipeline
    .clone()
    .resize({ width: 900, withoutEnlargement: true })
    .webp({ quality: narrowQuality, alphaQuality: 86, effort: 4 })
    .toFile(narrow.pathname);

  const fullStat = await stat(full);
  console.log(`${stem}.webp ${Math.round(fullStat.size / 1024)} KB`);
}

/* ---------- Handy-Varianten ----------
 * Auf hohen Handy-Displays sind neun volle Bildebenen zu schwer für die GPU (Flackern auf Android).
 * Deshalb: Himmel + Schloss + Bäume + Nebel zu EINEM Bild verschmelzen und auf den sichtbaren
 * Hochformat-Ausschnitt zuschneiden; dazu eine zweite Ebene für den Vordergrund.
 */
const mobileDir = new URL('mobile/', outDir);
await mkdir(mobileDir, { recursive: true });

const CROP = { left: 800, top: 0, width: 700, height: 941 };
const layerPath = (stem) => new URL(`${stem}.png`, srcDir).pathname;

async function mobileFresh(dest, sources) {
  try {
    const destStat = await stat(dest);
    const times = await Promise.all(sources.map(async (s) => (await stat(s)).mtimeMs));
    return times.every((t) => destStat.mtimeMs >= t);
  } catch {
    return false;
  }
}

const bgSources = ['01-himmel-mond-wolken', '02-schloss', '03-baeume-vor-schloss', '04-nebel-wasser-zombies'].map(layerPath);
const bgDest = new URL('bg.webp', mobileDir).pathname;
if (!(await mobileFresh(bgDest, bgSources))) {
  const [sky, ...rest] = bgSources;
  const merged = await sharp(sky)
    .composite(await Promise.all(rest.map(async (input) => ({ input: await sharp(input).ensureAlpha().toBuffer() }))))
    .flatten({ background: '#070b14' })
    .png()
    .toBuffer();
  const flat = await sharp(merged).extract(CROP).webp({ quality: 80, effort: 5 }).toFile(bgDest);
  console.log(`mobile/bg.webp ${Math.round(flat.size / 1024)} KB`);
}

const fgDest = new URL('fg.webp', mobileDir).pathname;
if (!(await mobileFresh(fgDest, [layerPath('05-vordergrund')]))) {
  const out = await sharp(layerPath('05-vordergrund'))
    .ensureAlpha()
    .extract(CROP)
    .webp({ quality: 82, alphaQuality: 88, effort: 5 })
    .toFile(fgDest);
  console.log(`mobile/fg.webp ${Math.round(out.size / 1024)} KB`);
}

// 1×1 transparent: Platzhalter für Ebenen, die auf dem Handy entfallen (spart Daten, kostet keine GPU)
await sharp({ create: { width: 1, height: 1, channels: 4, background: { r: 0, g: 0, b: 0, alpha: 0 } } })
  .webp({ lossless: true })
  .toFile(new URL('empty.webp', mobileDir).pathname);
