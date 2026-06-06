import { fileURLToPath } from 'node:url';
import { dirname, resolve } from 'node:path';

const __dirname = dirname(fileURLToPath(import.meta.url));

export const SKILL_ROOT = resolve(__dirname, '..');
export const REFERENCE_DIR = resolve(SKILL_ROOT, 'reference');
export const CATALOG_DIR = resolve(SKILL_ROOT, 'catalog');

export const CARTZILLA_SRC = 'P:/J2COMMERCE/NEW SITE/Cartzilla';
export const FA_KIT_ZIP = 'P:/J2COMMERCE/NEW SITE/kit-f1c6f99600-web.zip';

export const BRAND = { from: 'Cartzilla', to: 'Shopper' };
