import { test } from 'node:test';
import assert from 'node:assert/strict';
import { debrand } from '../lib/debrand.mjs';

test('replaces all case variants of Cartzilla', () => {
  assert.equal(debrand('Cartzilla | Shop'), 'Shopper | Shop');
  assert.equal(debrand('cartzilla-icons'), 'shopper-icons');
  assert.equal(debrand('CARTZILLA THEME'), 'SHOPPER THEME');
  assert.equal(debrand('by Cartzilla and cartzilla'), 'by Shopper and shopper');
});

test('leaves unrelated text untouched', () => {
  assert.equal(debrand('cart and zilla'), 'cart and zilla');
  assert.equal(debrand(''), '');
});

test('reports whether any replacement happened', () => {
  assert.equal(debrand.has('Cartzilla'), true);
  assert.equal(debrand.has('nothing here'), false);
});
