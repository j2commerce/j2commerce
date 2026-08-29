# Checkout Step Sequencing — server-side completed-step state

**Issue:** [#1992](https://github.com/j2commerce/j2commerce/issues/1992)
**Status:** Planning — not started
**Author:** J2Commerce
**Date:** 2026-08-29
**Complexity:** 5 / 5 — touches every checkout task
**Risk:** HIGH — the checkout is the revenue path
**Blocks:** the second half of [#1993](https://github.com/j2commerce/j2commerce/issues/1993)

---

## 1. Problem

The checkout's step order exists only in the template JavaScript. The server has no record of
which steps a shopper has completed, so each `*Validate` task enforces only its own rules, and a
step that never runs is a step whose rules never run.

### 1.1 Verified current state

`components/com_j2commerce/src/Controller/CheckoutController.php` — every one of these is an
independently dispatchable public task gated only by `validateAjaxToken()`:

| Line | Task | Kind |
|---|---|---|
| 280 | `login()` | render |
| 292 | `loginValidate()` | validate |
| 413 | `register()` | render |
| 460 | `registerValidate()` | validate |
| 677 | `guest()` | render |
| 698 | `guestValidate()` | validate |
| 753 | `billingAddress()` | render |
| 785 | `billingAddressValidate()` | validate |
| 902 | `shippingAddress()` | render |
| 947 | `shippingAddressValidate()` | validate |
| 1041 | `guestShippingValidate()` | validate |
| 1081 | `shippingPaymentMethod()` | render |
| 1408 | `shippingPaymentMethodValidate()` | validate |
| 1522 | `getCustomSteps()` | render |
| 1548 | `saveCustomSteps()` | validate |
| 1592 | `confirm()` | render + **persists the order** |
| 1893 | `confirmPayment()` | finalise |

A repo-wide search for a progress key — `checkout_step`, `steps_completed`, `billing_complete`,
`shipping_complete`, `completed_steps` — returns **zero hits** in both component trees.

The sequence lives in `components/com_j2commerce/tmpl/checkout/bootstrap5/default.php` and its
uikit twin, in functions like `advanceFromBilling()` → `proceedAfterBilling()` →
`goToShippingPayment()` → `advanceToConfirm()`, which decide what to show next from DOM state.

### 1.2 What `confirm()` does and does not re-check

`confirm()` **does** independently re-validate:

- stock, via `$order->validate_order_stock()` — "confirm is the last stock gate on this path"
- the shipping rate, via `repriceShippingSelection()`
- the payment method, via `isPaymentMethodAllowed()` and the not-selected check

It **does not** assert that the billing step ever ran, that the shipping step ever ran on a
shippable cart, or that a custom step required by a plugin was completed. It takes the rest of
the session state as given.

### 1.3 The file already documents the gap

The docblock on `repriceShippingSelection()` says:

> The shipping step is where a selection is normally re-made, but nothing sequences the checkout
> tasks, so confirm cannot assume it was the last step to run.

That reasoning produced a correct, defensive re-check for the shipping *rate*. It was not
extended to anything else the earlier steps are responsible for.

### 1.4 The existing "complete" events are notifications, not gates

`onJ2CommerceCheckoutBillingComplete` and `onJ2CommerceCheckoutShippingComplete` are dispatched
from the validate tasks, but nothing reads them back. They feed the actionlog. They are not
state.

### 1.5 This is not a CSRF issue

`validateAjaxToken()` is doing its job. A token proves the request is genuine; it does not prove
the request arrived in a valid order. Framing this as an anti-forgery gap would be wrong — the
gap is sequencing.

---

## 2. Goals

1. The server knows which steps are complete for the current cart.
2. Going back to an earlier step invalidates the later ones.
3. `confirm()` asserts the prerequisites the **current cart shape** requires — billing always,
   shipping when the cart is shippable, custom steps when a plugin registered a required one.
4. The shopper gets an actionable "you still need to finish step N" message instead of a late,
   generic failure.
5. Later work — plugin-contributed steps, multi-address orders — has something to hang off.

### Non-goals

- Removing the JS sequencing. The DOM flow stays; this adds a server-side assertion underneath it.
- Turning the checkout into a server-rendered wizard.
- Changing what any individual `*Validate` task validates today.

---

## 3. Proposed design

### 3.1 State shape

A completed-step set in the existing `j2commerce` session namespace, alongside the keys the
controller already uses (`guest`, `guest_shipping`, `billing_address_id`, `shipping_address_id`,
`payment_method`, `customer_note`):

```php
$session->set('completed_steps', ['billing' => true, 'shipping' => true], 'j2commerce');
```

Recommend a **flat associative array of step-key → bool**, not an ordered list: the step order is
not linear once plugin steps at four positions (`after_billing`, `after_shipping`,
`before_payment`, `before_confirm`) are in play.

### 3.2 Step keys

Derive from what already exists rather than inventing a parallel vocabulary. The JS section ids
are the natural source: `checkout`, `billing-address`, `shipping-address`,
`shipping-payment-method`, `custom-steps-after-billing`, `custom-steps-after-shipping`,
`custom-steps-before-payment`, `custom-steps-before-confirm`, `confirm`.

### 3.3 Write points

| Task | Sets | Clears downstream |
|---|---|---|
| `guestValidate()` | `billing` | shipping, payment, all custom |
| `registerValidate()` | `billing` | shipping, payment, all custom |
| `billingAddressValidate()` | `billing` | shipping, payment, all custom |
| `shippingAddressValidate()` | `shipping` | payment, custom after `after_shipping` |
| `guestShippingValidate()` | `shipping` | payment, custom after `after_shipping` |
| `shippingPaymentMethodValidate()` | `payment` | custom `before_confirm` |
| `saveCustomSteps()` | the position it handled | every position after it |

**Clearing downstream is the load-bearing half.** Setting a flag is easy; the correctness comes
from invalidating everything after it, so that editing billing forces the shipping step to be
re-done rather than inheriting a stale answer.

This is exactly the invalidation `setShippingSession()` already performs for the shipping *rate*:

> Rates are quoted against these, so the offer list and the selection made from it are dropped
> here rather than at each caller — a destination writer added later inherits the invalidation
> instead of having to know.

Follow that precedent: put the clear in one helper (`markStepComplete($key)`) that derives the
downstream set from a single ordered map, so a step added later inherits the behaviour.

### 3.4 Read point

`confirm()`, before it builds the order:

```php
$required = ['billing'];

if ($orderIsShippable) {
    $required[] = 'shipping';
}

// plus any custom-step position a plugin flagged as required
```

Missing prerequisite → append a specific error to `$errors` and skip `saveOrder()`, which the
existing `if (!$errors && $order)` guard already does.

### 3.5 Shippability is decided server-side

`determineShowShipping()` / `determineShowShippingMethods()` already resolve shippability from
the order's DB-loaded line items, not from request input. Use those — do **not** trust a
client-supplied "this cart doesn't ship" claim.

---

## 4. The hard parts

1. **Cart mutation mid-checkout.** The confirm step has Modify links. Adding a shippable item to
   a previously download-only cart makes `shipping` newly required *after* the shipping step was
   legitimately skipped. The required-set must be computed from the **current** cart at
   `confirm()` time, never cached at step time.

2. **The context / admin-pay path.** `CheckoutContextHelper::isOwningRequest()` gives `confirm()`
   an entirely separate branch that loads an existing order and never runs the cart steps at all.
   That branch must be **exempt** from the step assertions — an admin-created order legitimately
   has no session step history. Getting this wrong breaks admin-pay.

3. **Session loss.** `$this->app->login()` regenerates the session on AJAX login/registration.
   Whatever holds `completed_steps` must survive, or be re-established, across that regeneration
   — the same hazard already documented for the CSRF token in the project gotchas.

4. **Cart identity.** Step state belongs to a cart, not a browser. If a shopper's cart is
   emptied and rebuilt, stale step flags must not carry over. Recommend stamping the state with
   `cart_id` and discarding it on mismatch — the same instinct behind `orderMatchesCart()`.

5. **Plugin steps.** `getCustomSteps()` returns `hasSteps` per position. There is currently no
   way for a plugin to declare a step **required**. Adding the gate without that flag means
   either treating every custom step as required (breaks optional steps) or none (leaves the
   #1995 class of hole open). This needs an event-contract decision.

6. **Backward compatibility.** A shopper mid-checkout when the update lands has no
   `completed_steps` key. Absent state must not hard-fail them. Recommend: treat a wholly absent
   key as "unknown" and fall through to today's behaviour for one release, logging when it
   happens, then enforce.

---

## 5. Files to change

| File | Change |
|---|---|
| `components/com_j2commerce/src/Controller/CheckoutController.php` | `markStepComplete()` / `assertStepsComplete()` helpers; calls in 6 validate tasks; assertion in `confirm()` |
| `components/com_j2commerce/tmpl/checkout/bootstrap5/default.php` | surface the new error; possibly reflect step state on load |
| `components/com_j2commerce/tmpl/checkout/uikit/default.php` | twin |
| `components/com_j2commerce/language/en-US/com_j2commerce.ini` | "complete step N first" strings |
| `components/com_j2commerce/language/en-GB/com_j2commerce.ini` | en-GB twin |
| `language/en-US/com_j2commerce.ini` + `en-GB` | site mirrors |

A new helper class (`CheckoutStepState`) is worth considering over private controller methods,
since `confirm()` is already ~300 lines.

---

## 6. Testing

1. Happy path, guest, shippable → all steps set, order confirms.
2. Happy path, member, download-only → `shipping` never required, order confirms.
3. POST `checkout.confirm` directly after only `guestValidate()` → refused with a specific
   message naming the missing step.
4. Complete every step, then go back and re-submit billing → `shipping` and `payment` cleared;
   confirm is refused until they are re-done.
5. Download-only cart, complete checkout to confirm, then add a shippable item via Modify →
   `shipping` becomes required; confirm refused.
6. Admin-pay / context path → assertions skipped entirely, order confirms.
7. AJAX register mid-checkout → step state survives session regeneration.
8. Empty the cart mid-checkout and rebuild it → stale flags discarded.
9. Custom-steps plugin at each of the four positions → required ones gate, optional ones do not.
10. Shopper with no `completed_steps` key (simulating mid-checkout upgrade) → behaves per the
    §4.6 decision.
11. Both template families.

---

## 7. Sequencing

1. **State + write points only, no gate.** Land `markStepComplete()` and the downstream clearing,
   log what `confirm()` *would* have refused. Zero behaviour change; produces real data on
   whether the assertions would misfire.
2. **The `confirm()` gate for billing and shipping**, once the logs are clean.
3. **The plugin required-step contract** (§4.5) as its own design + PR.
4. **#1993's remaining half** — the unticked "same as billing" arm marking the shipping step
   incomplete — depends on step 1 and should follow it directly.

---

## 8. Related

- [#1993](https://github.com/j2commerce/j2commerce/issues/1993) — ship-to symmetry. The
  checkbox and validator-arm halves shipped in PR #2002; the "mark incomplete" half waits on
  this.
- [#1995](https://github.com/j2commerce/j2commerce/issues/1995) — custom-steps loader advancing
  on failure. Fixed client-side in PR #2002; a server-side required-step gate (§4.5) would close
  the same hole from the other end.
- [#1991](https://github.com/j2commerce/j2commerce/issues/1991) /
  `docs/plans/order_write_integrity_prd.md` — the order-write half. Independent of this, but both
  are about the confirm step trusting state it did not verify.
