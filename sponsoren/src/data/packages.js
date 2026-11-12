// Sponsoring-Pakete Struktur
// Bilder: CSS-Hintergründe (keine externen Abhängigkeiten)

// Abgabefristen (ISO-Datum) – bis wann Logo/Druckdaten für das jeweilige Event eingehen müssen
export const printDeadlines = {
  liederheft: '2026-12-01',
  zunftsitzung: '2027-01-01',
  karnevalszug: '2026-10-01',
  // summerjeck: '2026-05-01',
}

/** true, wenn das Datum vor dem heutigen Kalendertag liegt (Fristtag selbst zählt noch). */
export function isPrintDeadlinePassed(isoDate, now = new Date()) {
  const info = getPrintDeadlineInfo(isoDate, now)
  return info?.passed ?? false
}

export function getPrintDeadlineInfo(isoDate, now = new Date()) {
  if (!isoDate) return null
  const deadline = new Date(`${isoDate}T12:00:00`)
  if (Number.isNaN(deadline.getTime())) return null
  const today = new Date(now)
  today.setHours(0, 0, 0, 0)
  deadline.setHours(0, 0, 0, 0)
  const daysUntil = Math.round((deadline.getTime() - today.getTime()) / (1000 * 60 * 60 * 24))
  const formattedDate = deadline.toLocaleDateString('de-DE', {
    day: 'numeric',
    month: 'long',
    year: 'numeric',
  })
  return { daysUntil, formattedDate, passed: daysUntil < 0 }
}

export function isCategoryBookable(categoryId, now = new Date()) {
  return !isPrintDeadlinePassed(printDeadlines[categoryId], now)
}

export const packageCategories = [
  {
    id: 'liederheft',
    name: 'Liederheft',
    description: 'Das offizielle Liederheft geht in tausende Hände – bei jeder Sitzung und jedem Event. Deine Werbeanzeige bleibt den Jecken als wertvolle Begleitung der Session im Gedächtnis.',
    gradient: 'from-bnz-600 to-bnz-700',
    image: 'https://bedburger-narrenzunft.de/wp-content/uploads/2026/01/2026-01-25-20.35.55,402x-980x467.jpg.pagespeed.ce.PXkCRzHsZj.jpg',
    packages: [
      { id: 'lh-third', name: '1/3 Seite', price: 70, description: 'Kompakte Werbefläche zum attraktiven Preis' },
      { id: 'lh-half', name: '1/2 Seite', price: 90, description: 'Halbe Seite – idealer Einstieg in die Print-Präsenz' },
      { id: 'lh-full', name: '1/1 Seite', price: 120, description: 'Volle Seite – maximale Aufmerksamkeit beim Blättern' },
      
     
    ]
  },
  {
    id: 'zunftsitzung',
    name: 'Zunftsitzung',
    ticketPrice: 38,
    description: 'Sichtbar bei hunderten Gästen: Deine Werbung erscheint auf der LED-Leinwand im Festzelt und auf dem TV im Vorzelt. Perfekt für Unternehmen, die mit der BNZ-Community in Kontakt kommen wollen.',
    gradient: 'from-red-700 to-bnz-600',
    image: 'https://bedburger-narrenzunft.de/wp-content/uploads/2024/01/2024-01-27_Prunksitzung-Jenna-5.jpg',
    packages: [
      { id: 'zs-led', name: 'LED Leinwand + TV Vorzelt', price: 250, description: 'Doppelte Reichweite – innen wie außen wahrgenommen' },
    ]
  },
  {
    id: 'karnevalszug',
    name: 'Karnevalszug',
    description: 'Wenn der Zug durch Bedburg rollt, bist du mittendrin: Dein Branding auf Wurfmaterial erreicht die Menschen am Straßenrand direkt – emotional, unvergesslich und regional stark verankert.',
    gradient: 'from-bnz-500 to-bnz-700',
    image: 'https://bedburger-narrenzunft.de/wp-content/uploads/2024/02/2024-02-11_Zug-12.jpg',
    packages: [
      { id: 'kz-wurf', name: 'Wurfmaterial', price: 250, priceNote: 'ab', description: 'Dein Logo auf dem Weg durch die Straßen' },
    ]
  },
  // Summerjeck (vorübergehend deaktiviert – bei Bedarf wieder einkommentieren)
  // {
  //   id: 'summerjeck',
  //   name: 'Summerjeck',
  //   description: 'Das Open-Air-Festival auf dem Schlossparkplatz – dein Paket für maximales Networking und Sichtbarkeit bei Musik, Feierlaune und regionalem Publikum.',
  //   gradient: 'from-sky-600 to-bnz-500',
  //   image: 'https://bedburger-narrenzunft.de/wp-content/uploads/2024/12/summerjeck-stage.jpg',
  //   // In ALLEN Summerjeck-Paketen enthalten:
  //   benefitsIncluded: [
  //     'Zugang zum VIP-Zelt',
  //     'Getränkebons für deine Gäste',
  //     'Kleiner Snack',
  //     'Logo auf allen Festival-Werbematerialien',
  //     'Bauzaunbanner auf dem Festivalgelände',
  //     'Nennung als Sponsor auf dem Festival',
  //   ],
  //   packages: [
  //     {
  //       id: 'sj-partner',
  //       name: 'Partner',
  //       price: 500,
  //       tickets: 4,
  //       description: '4 Festival-Tickets inklusive – ideal für den Einstieg ins Summerjeck-Sponsoring.',
  //       extras: [],
  //     },
  //     {
  //       id: 'sj-premium',
  //       name: 'Premium Partner',
  //       price: 1000,
  //       tickets: 10,
  //       description: '10 Tickets + erweiterte Sichtbarkeit.',
  //       extras: [
  //         'Zusätzliches Banner auf dem Festivalgelände',
  //         'Interview-Möglichkeit auf der Bühne',
  //       ],
  //     },
  //     {
  //       id: 'sj-presenting',
  //       name: 'Presenting Partner',
  //       price: 2000,
  //       tickets: 20,
  //       description: '20 Tickets + maximale Präsenz – das Top-Paket.',
  //       extras: [
  //         'Banner im Bühnensichtfeld',
  //         'Zusätzliches Banner auf dem Festivalgelände',
  //         'Interview-Möglichkeit auf der Bühne',
  //       ],
  //     },
  //   ]
  // },
]

export const comboDiscounts = [
  { minPackages: 2, discountPercent: 5, zunftsitzungTicketPrice: 33 },
  { minPackages: 3, discountPercent: 10, zunftsitzungTicketPrice: 30 },
  // { minPackages: 4, discountPercent: 15, zunftsitzungTicketPrice: 25 }, // benötigt Summerjeck
]

export const autoRenewal = {
  minYears: 3,
  benefit: 'Garantie gleichbleibende Preise bei mind. 3 Jahre gleiche Pakete',
}
