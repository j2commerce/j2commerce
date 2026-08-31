# Order Write Integrity — `orderinfos` Table class + transactional order write

**Issue:** [#1991](https://github.com/j2commerce/j2commerce/issues/1991)
**Status:** Planning — not started
**Author:** J2Commerce
**Date:** 2026-08-29
**Complexity:** 5 / 5 — touches the order-write path end to end
**Risk:** HIGH — every order on the site goes through this code

---

## 1. Problem

`CartOrder::saveOrder()` writes seven tables in sequence with no transaction and, for two of
them, no `Table` class. A failure part-way through leaves a partial order behind, and the one
table with no `Table` class has nowhere for a row-level invariant to live.

### 1.1 Verified current state

`administrator/components/com_j2commerce/src/Helper/CartOrder.php`:

| Step | Method | Write mechanism | Table class used |
|---|---|---|---|
| 1 | `saveOrder()` | `OrderTable::bind/check/store()` **twice** (PK, then `order_id`+`token`) | ✅ `OrderTable` |
| 2 | `saveOrderItems()` | `$db->insertObject('#__j2commerce_orderitems', …)` at `:2392` | ❌ not used — `OrderitemTable` exists |
| 3 | `saveOrderInfo()` | hand-rolled `insert()->columns()->values()` at `:2544` | ❌ **no class exists** |
| 4 | `saveOrderTaxes()` | raw query, `:2685` | ❌ |
| 5 | `saveOrderShipping()` | raw query, `:2725` | ❌ |
| 6 | `saveOrderDiscounts()` | raw query, `:2823` | ❌ |
| 7 | `saveOrderFees()` | raw query, `:2861` | ❌ |

Then: `OrderUploadHelper::attachUploadsToOrder()`, `DownloadHelper::createOrderDownloads()`,
the `AfterSaveOrder` plugin event, and `OrderHistoryHelper::add()`.

`grep -c 'transactionStart\|transactionCommit\|transactionRollback' CartOrder.php` → **0**.

`administrator/components/com_j2commerce/src/Table/` holds 43 classes including `OrderTable`,
`OrderitemTable`, `OrdershippingTable`, `OrderhistoryTable`, `OrderdownloadTable`,
`OrderstatusTable` and `OrderTransactionTable` — but **no `OrderinfoTable`**.

### 1.2 The orphan window

`saveOrder()` stores the order row, stores it a second time to attach `order_id` and `token`,
then writes items, then info. A throw anywhere after step 1 leaves:

- an `#__j2commerce_orders` row in state `5` (Incomplete) with a live `order_id` and `token`,
- however many `#__j2commerce_orderitems` rows had already been inserted,
- and **no** `orderinfos` row.

Nothing cleans that up. The confirm-step idempotency guard added later
(`CheckoutController::confirm()`) will *find* that row by `cart_id`, and reuses it only when
`orderMatchesCart()` agrees — so a partial order is not silently charged, but it does persist
as a permanent orphan and it does occupy the `j2commerce.order_id` user-state slot.

### 1.3 The invariant has nowhere to live

Because no `Table` is involved in step 3, no `check()` runs. Every column is written through a
null-coalesce default:

```php
$db->quote($shipping['address_1'] ?? ''),
(int) ($shipping['zone_id'] ?? 0),
```

and `loadAddressData()` **returns `[]` outright** when a member's saved address fails the
ownership check:

```php
if ($userId > 0 && (int) ($addressTable->user_id ?? 0) !== $userId) {
    return [];
}
```

So an address that never resolved is persisted as a row of empty strings and zeros rather than
failing the order.

### 1.4 The schema provides no floor either

Verified against `j6_j2commerce_orderinfos`:

| Column | Type | Null | Default |
|---|---|---|---|
| `shipping_address_1` | varchar(255) | NO | `''` |
| `shipping_city` | varchar(255) | NO | `''` |
| `shipping_zone_name` | varchar(255) | NO | `''` |
| `shipping_country_name` | varchar(255) | NO | `''` |
| `shipping_zip` | varchar(255) | NO | *(none)* |
| `shipping_zone_id` | int | NO | `0` |
| `shipping_country_id` | int | NO | `0` |
| `all_billing` / `all_shipping` / `all_payment` | longtext | NO | *(none)* |

An all-blank row is legal.

`OrderTable::check()` is the only check on this path and its money guard stops at sign:

```php
foreach (['order_shipping', 'order_shipping_tax', 'order_total'] as $moneyField) {
    if (round((float) $this->$moneyField, 5) < 0) { … }   // :129-136
}
```

It rejects negatives. It does not assert that a shippable order carries a destination.

### 1.5 Two columns are never written at all

The insert names **34** columns. The table has **38**. Not written:

- `j2commerce_orderinfo_id` — auto-increment, correct to omit.
- `billing_middle_name`, `shipping_middle_name` — nullable, silently always `NULL`.
- `shipping_id` — `varchar(255) NOT NULL DEFAULT ''`, relies on the DB default.

`billing_middle_name` / `shipping_middle_name` are a real gap: `CustomFieldHelper` can collect a
middle name and it is dropped on the floor here. Worth confirming against the address custom-field
set before deciding whether to add them or drop the columns.

### 1.6 The `all_shipping` empty-marker disagrees between writers

`saveOrderInfo()` emits whatever `json_encode($shipping)` produces — `[]` for an empty array —
while `OrderModel` writes an explicit `'{}'`. Consumers `json_decode(..., true)` so both degrade
to the same value, but the column cannot be queried reliably (`WHERE all_shipping = '{}'` misses
half the rows). Note the third value on the same line already normalises correctly:

```php
$db->quote($paymentCustom !== [] ? json_encode($paymentCustom) : '{}'),
```

so the fix is to apply that same shape to the other two.

---

## 2. Goals

1. Every write in the order path goes through a `Table` class with a real `check()`.
2. A shippable order cannot persist without a destination.
3. A failure anywhere in the order write leaves **no** rows behind.
4. `all_billing` / `all_shipping` / `all_payment` use one empty-marker.

### Non-goals

- Rewriting `loadAddressData()`'s resolution order.
- Changing the two-pass `order_id` / `token` generation.
- Touching the confirm-step idempotency guard.
- Backfilling or repairing existing orphan rows — a separate migration decision.

---

## 3. Proposed change

### 3.1 `OrderinfoTable`

New file `administrator/components/com_j2commerce/src/Table/OrderinfoTable.php`, modelled on
`OrderitemTable`:

```php
class OrderinfoTable extends Table
{
    public function __construct(DatabaseDriver $db)
    {
        parent::__construct('#__j2commerce_orderinfos', 'j2commerce_orderinfo_id', $db);
    }

    public function check(): bool { … }
}
```

No `setColumnAlias('published', 'enabled')` — this table has no state column.

`check()` responsibilities:

1. **Default every `NOT NULL` column that has no DB default** — `shipping_zip`, `all_billing`,
   `all_shipping`, `all_payment` — per the standing Table `check()` rule.
2. **Normalise the JSON columns** to `'{}'` when empty, closing §1.6.
3. **Assert a destination when the order needs one.** `check()` has no access to the parent
   order, so the caller must set a flag on the table before `store()`:

   ```php
   $infoTable->requires_shipping = $isShippable || $this->order_shipping > 0;
   ```

   `check()` then rejects when that flag is set and `shipping_address_1`, `shipping_city` or
   `shipping_country_id` is empty. Using a transient public property rather than a second query
   keeps `check()` free of I/O; it must be excluded from the store via `Table::$_jsonEncode`-style
   handling or simply named so it does not collide with a column (it does not).
4. **Reject an `all_shipping` that decodes to an empty array while `requires_shipping` is set.**

Route `saveOrderInfo()` through `bind()` / `check()` / `store()` with the same array it currently
builds, dropping the 34 `$db->quote()` calls.

### 3.2 `saveOrderItems()` through `OrderitemTable`

`:2392` calls `insertObject()` even though `OrderitemTable` exists and has a `check()`. Convert
to `bind`/`check`/`store` per row. This is where the `product_type` guard already lives as a
`RuntimeException`; moving it into `OrderitemTable::check()` is the natural follow-on but is
**optional** for this issue — the throw is currently correct and load-bearing.

### 3.3 The order-level twin in `OrderTable::check()`

Add, alongside the existing money guard:

```php
// A shippable order must carry a shipping charge decision, not an absent one.
if ((int) $this->is_shippable === 1 && !isset($this->order_shipping)) { … }
```

The exact predicate needs a decision — see §6 Open questions. The destination assertion itself
belongs in `OrderinfoTable::check()`, not here, because `OrderTable` never sees the address.

### 3.4 Wrap the whole write in a transaction

```php
$db->transactionStart();

try {
    // steps 1-7 …
    $db->transactionCommit();
} catch (\Throwable $e) {
    $db->transactionRollback();
    throw $e;
}
```

**Boundary decision — this is the crux of the change.** The transaction must close *before* the
side effects that are not database writes:

- `OrderUploadHelper::attachUploadsToOrder()` — moves files on disk. A rollback cannot un-move
  them.
- `DownloadHelper::createOrderDownloads()` — DB only; safe **inside**.
- `J2CommerceHelper::plugin()->event('AfterSaveOrder')` — arbitrary third-party code, which may
  call an external API, send mail, or open its own transaction. MySQL does not nest
  transactions; a plugin issuing DDL causes an **implicit commit** and silently ends ours.
- `OrderHistoryHelper::add()` — DB only; safe inside.

Recommended boundary: **commit after step 7 + `syncOrderDiscountTotal()`**, then run uploads,
downloads, the plugin event and history outside. That gives atomicity over the seven-table row
set — which is what §1.2 is actually about — without handing transaction control to plugins.

---

## 4. Files to change

| File | Change |
|---|---|
| `administrator/components/com_j2commerce/src/Table/OrderinfoTable.php` | **NEW** |
| `administrator/components/com_j2commerce/src/Helper/CartOrder.php` | `saveOrderInfo()` via Table; `saveOrderItems()` via Table; transaction wrapper in `saveOrder()` |
| `administrator/components/com_j2commerce/src/Table/OrderTable.php` | order-level `check()` addition |
| `administrator/components/com_j2commerce/language/en-US/com_j2commerce.ini` | error strings, if new ones are needed |
| `administrator/components/com_j2commerce/language/en-GB/com_j2commerce.ini` | en-GB twin |
| `administrator/language/en-US/com_j2commerce.ini` + `en-GB` | install mirrors |

Prefer the generic `COM_J2COMMERCE_ERR_FIELD_INVALID` / `COM_J2COMMERCE_ERR_FIELD_REQUIRED`
already used by `OrderTable::check()` over new view-specific keys.

---

## 5. Testing

1. Normal guest order, shippable → one `orderinfos` row, all address columns populated.
2. Normal member order using a saved address → same.
3. Member order where `shipping_address_id` points at **another** user's address → the
   ownership check returns `[]`; the order must now be **refused**, not persisted blank.
4. Non-shippable (downloads only) order → succeeds with blank shipping columns and
   `requires_shipping` unset.
5. Force a throw inside `saveOrderTaxes()` → **zero** rows in orders, orderitems, orderinfos.
6. Force a throw inside the `AfterSaveOrder` plugin event → order **is** committed (outside the
   boundary), which is the intended behaviour; confirm no half-rolled-back state.
7. Zero-total order (100% coupon) → still writes a complete `orderinfos` row.
8. Confirm-step double-submit → idempotency guard still reuses the prior row; the transaction
   must not have changed `cart_id` matching.
9. `php cli/joomla.php database:check` clean; no schema delta is introduced by this work.

---

## 6. Open questions — decide before implementing

1. **What is the destination predicate?** `is_shippable = 1` is the obvious trigger, but a
   store with a free-shipping-only geozone legitimately produces `order_shipping = 0` on a
   shippable order. Recommend keying on `is_shippable` alone and *not* on `order_shipping > 0`,
   since the issue's suggested `OR` would reject that legitimate case.
2. **Does rejecting a blank ship-to break any existing flow?** There is a reachable state where
   a member unticks "same as billing", does not complete the shipping step, and confirms in a
   store that quotes no rates for the destination with `shipping_mandatory = 0`. The order then
   persists with `is_shippable = 1` and blank `shipping_*` columns. This PRD's `check()` would
   start **refusing** that order. That is the correct outcome, but it is a behaviour change for
   any store currently relying on it, and it should be called out in release notes.
3. **Middle name** — add the two columns to the insert, or drop them from the schema?
4. **Existing orphan rows** — leave, or ship a cleanup task? A `#__j2commerce_orders` row in
   state 5 with no `orderinfos` row is the signature. Recommend leaving them and adding a
   diagnostics check rather than auto-deleting order rows.
5. **Does any payment plugin call `saveOrder()` re-entrantly?** If a plugin already opened a
   transaction, ours becomes a no-op nest. Audit `plugins/j2commerce/payment_*` for
   `transactionStart` before implementing.

---

## 7. Sequencing

The four parts are independently shippable and should be separate PRs:

1. `OrderinfoTable` + route `saveOrderInfo()` through it — no behaviour change, pure refactor.
2. `check()` invariants — behaviour change, needs the §6.1 and §6.2 decisions.
3. `saveOrderItems()` through `OrderitemTable` — no behaviour change.
4. The transaction wrapper — highest risk, wants the boundary decision settled and should land
   last so a regression is easy to attribute.
