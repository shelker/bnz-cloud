let loaded = false;

export function loadMatomo() {
  if (loaded) return;
  loaded = true;
  const w = window as unknown as { _paq?: unknown[][] };
  const paq = (w._paq = w._paq || []);
  const base = '//matomo.bedburger-narrenzunft.de/';
  paq.push(['trackPageView']);
  paq.push(['enableLinkTracking']);
  paq.push(['setTrackerUrl', base + 'matomo.php']);
  paq.push(['setSiteId', '5']);
  const script = document.createElement('script');
  script.async = true;
  script.src = base + 'matomo.js';
  document.head.appendChild(script);
}
