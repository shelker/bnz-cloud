/** EVENTIM.Light: Shop-Einbindung (iframe + el-shop.js, siehe TicketShop.astro). */
export const eventimShopUrl =
  'https://www.eventim-light.com/de/a/5da59cee503ca200015e0c10/iframe/e/6ac8a970a47cb7abb7b1c3ba/';
/** Öffentlicher Shop-Link (für „in neuem Tab öffnen“). */
export const eventimPublicUrl =
  'https://www.eventim-light.com/de/a/5da59cee503ca200015e0c10/e/6ac8a970a47cb7abb7b1c3ba';
export const eventimScriptUrl = 'https://www.eventim-light.com/de/a/5da59cee503ca200015e0c10/public/iframe/el-shop.js';
export const eventimFrameId = 'eventim-light-iframe-v2-5da59cee503ca200015e0c10-event-6ac8a970a47cb7abb7b1c3ba';

export const links = {
  organizer: 'https://bedburger-narrenzunft.de/',
  impressum: 'https://bedburger-narrenzunft.de/impressum/',
  datenschutz: 'https://bedburger-narrenzunft.de/datenschutzerklaerung/',
} as const;

export const event = {
  name: 'Halloween Party',
  organizer: 'Bedburger Zombiezunft',
  host: 'Bedburger Narrenzunft',
  tagline: 'Die Zunft erwacht.',
  age: 'Ü30',
  dateLabel: '31.10.2027',
  dateTime: '2027-10-31T19:00:00+02:00',
  timeLabel: 'ab 19 Uhr',
  price: '18 €',
  priceNote: 'zzgl. Gebühren',
  /** Early-Bite-Aktion. Zum Beenden `active: false` setzen. */
  earlyBite: {
    active: true,
    label: 'Early Bite',
    price: '16 €',
  },
  place: 'Schloss Bedburg',
  mapsUrl: 'https://www.google.com/maps/search/?api=1&query=Schloss%20Bedburg%2C%20Bedburg',
  description:
    'Aus der Narrenzunft wird die Zombiezunft: Ü30 Halloween Party im Schloss Bedburg am 31.10.2027 mit Kostümwettbewerb und Preisen. Von den Machern der Discofever. Early Bite 16 €, danach 18 € zzgl. Gebühren, Tickets limitiert.',
} as const;

export function isEventimReady(url: string): boolean {
  try {
    const parsed = new URL(url);
    return parsed.protocol === 'https:' && parsed.hostname.length > 0;
  } catch {
    return false;
  }
}
