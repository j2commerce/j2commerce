const RULES = [
  [/Cartzilla/g, 'Shopper'],
  [/CARTZILLA/g, 'SHOPPER'],
  [/cartzilla/g, 'shopper'],
];

export function debrand(str) {
  let out = str;
  for (const [re, to] of RULES) out = out.replace(re, to);
  return out;
}

debrand.has = (str) => /cartzilla/i.test(str);
