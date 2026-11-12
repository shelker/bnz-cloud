export type Consent = { stats: boolean; media: boolean };

const KEY = 'zz-consent-v1';
let memory: Consent | null = null;

export function getConsent(): Consent | null {
  if (memory) return memory;
  try {
    const raw = localStorage.getItem(KEY);
    if (raw) {
      const parsed = JSON.parse(raw);
      memory = { stats: parsed.stats === true, media: parsed.media === true };
    }
  } catch {
    /* Speicher nicht verfügbar: Entscheidung gilt nur für diese Seite */
  }
  return memory;
}

export function setConsent(consent: Consent) {
  memory = consent;
  try {
    localStorage.setItem(KEY, JSON.stringify(consent));
  } catch {
    /* siehe oben */
  }
  window.dispatchEvent(new CustomEvent<Consent>('consent-change', { detail: consent }));
}

/** Ruft cb sofort (falls schon entschieden) und bei jeder Änderung auf. */
export function onConsent(cb: (consent: Consent) => void) {
  window.addEventListener('consent-change', (e) => cb((e as CustomEvent<Consent>).detail));
  const current = getConsent();
  if (current) cb(current);
}

export function openConsentSettings() {
  window.dispatchEvent(new Event('consent-open'));
}
