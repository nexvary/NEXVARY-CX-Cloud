#!/usr/bin/env node
/**
 * Verifies that every key present in the reference locale (en) also exists
 * in every other locale directory under lang/.
 * Exits with code 1 when missing keys are found.
 */

import { readFileSync, readdirSync, statSync, existsSync } from 'fs';
import { join, dirname } from 'path';
import { fileURLToPath } from 'url';

const __dirname = dirname(fileURLToPath(import.meta.url));
const langDir = join(__dirname, '..', 'lang');

function flattenKeys(obj, prefix = '') {
  return Object.entries(obj).flatMap(([k, v]) => {
    const full = prefix ? `${prefix}.${k}` : k;
    return typeof v === 'object' && v !== null ? flattenKeys(v, full) : [full];
  });
}

function loadJson(file) {
  try {
    return JSON.parse(readFileSync(file, 'utf8'));
  } catch (e) {
    console.error(`Failed to parse ${file}: ${e.message}`);
    process.exit(1);
  }
}

const locales = readdirSync(langDir).filter(d =>
  statSync(join(langDir, d)).isDirectory()
);

if (!locales.includes('en')) {
  console.error('Reference locale "en" not found in lang/');
  process.exit(1);
}

const refFile = join(langDir, 'en', 'messages.json');
if (!existsSync(refFile)) {
  console.error('lang/en/messages.json not found');
  process.exit(1);
}

const refKeys = new Set(flattenKeys(loadJson(refFile)));
let totalMissing = 0;

for (const locale of locales) {
  if (locale === 'en') continue;

  const file = join(langDir, locale, 'messages.json');
  if (!existsSync(file)) {
    console.error(`Missing translation file: lang/${locale}/messages.json`);
    totalMissing++;
    continue;
  }

  const keys = new Set(flattenKeys(loadJson(file)));
  const missing = [...refKeys].filter(k => !keys.has(k));

  if (missing.length > 0) {
    console.error(`\n[${locale}] ${missing.length} missing key(s):`);
    missing.forEach(k => console.error(`  - ${k}`));
    totalMissing += missing.length;
  } else {
    console.log(`[${locale}] ✓ all ${refKeys.size} keys present`);
  }
}

if (totalMissing > 0) {
  console.error(`\nFailed: ${totalMissing} missing translation key(s) found.`);
  process.exit(1);
}

console.log('\nAll locales are complete.');
