/**
 * Hostnames that must never show the Atlasoft brand or the public
 * marketing/registration pages — a dedicated, unbranded entry point set up
 * for a specific client who isn't meant to know this is a resold SaaS
 * platform shared with other syndics. Everything past login (dashboard,
 * sidebar, etc.) is unaffected — this only strips the public-facing pages.
 */
const WHITE_LABEL_HOSTNAMES = ['sahelouad.online', 'www.sahelouad.online'];

export function isWhiteLabelHost(): boolean {
  return WHITE_LABEL_HOSTNAMES.includes(window.location.hostname);
}
