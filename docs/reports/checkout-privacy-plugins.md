# Checkout vs Core Joomla User/Privacy Plugins — Audit & Remediation

**Issue:** [j2commerce/j2commerce#958](https://github.com/j2commerce/j2commerce/issues/958) — "require accept terms"
**Date:** 2026-05-11
**Scope:** Core Joomla 6 plugins that hook the registration / profile lifecycle and how each one interacts (or fails to interact) with the J2Commerce checkout-register flow.
**Status:** §4a implemented in this PR (branch `fix/issue-958`). §4b and §4c remain as separate follow-up issues.

---

## 1. Symptom

When the Joomla site is configured to require privacy consent (System − Privacy Consent plugin enabled, with "Force Consent" on) and a guest clicks **Create account** during the J2Commerce checkout flow, the next AJAX step renders the **entire Joomla user-profile-edit page inside the checkout container** instead of advancing to the shipping/payment step. The user is silently bounced off the checkout.

Reporter (@brianteeman): *"if you have your joomla configuration set to require accepting privacy terms with the system − privacy consent plugin enabled and then try to create a user on checkout then you get this weird redirect with the entire site loaded."*

Reporter hypothesised the same would happen with **User − Terms and Conditions**. As shown in §3 below, that plugin does **not** cause the same redirect — but a *different*, related compliance gap exists: terms/privacy/profile fields configured for Joomla user registration are silently bypassed by the J2C checkout register step, so no consent is ever captured.

---

## 2. Root cause

`CheckoutController::registerValidate()` (`components/com_j2commerce/src/Controller/CheckoutController.php:357-504`) does the following on successful registration:

1. `$user = new \Joomla\CMS\User\User(); $user->bind($userData); $user->save();` — creates the account directly via the `User` object, bypassing the `com_users` registration controller entirely (no `task=registration.register`, no `jform[...]` post).
2. `$this->app->login($credentials);` — auto-logs the new user in.
3. Returns a JSON response so the checkout JS can advance to the next AJAX step.

The next AJAX request now arrives as an authenticated user **without** a `#__privacy_consents` row. `plg_system_privacyconsent::onAfterRoute()` (`plugins/system/privacyconsent/src/Extension/PrivacyConsent.php:266-322`) sees:

```php
if ($userId > 0) {
    if ($this->isUserConsented($userId)) {
        return;
    }
    // ... allowlist of permitted views/tasks ...
    if (... permitted ...) {
        return;
    }

    $app->enqueueMessage($this->getRedirectMessage(), 'notice');
    $link = 'index.php?option=com_users&view=profile&layout=edit';
    $app->redirect(Route::_($link, false));
}
```

`option=com_j2commerce` is **not** in the allowlist, so the plugin calls `$app->redirect()` for every subsequent checkout AJAX request. The checkout JS does `fetch(...)`, receives a 30x → follows it → ends up with the full user-profile-edit HTML, which it inserts into its result container. That is the "weird redirect with the entire site loaded" screenshot.

**Why `plg_user_terms` does not cause the same symptom:** it only subscribes to `onContentPrepareForm`, `onUserBeforeSave`, `onUserAfterSave` (`plugins/user/terms/src/Extension/Terms.php:40-46`) — no `onAfterRoute`. Its `onUserBeforeSave` enforcement is gated by `option == 'com_users' && task in ['registration.register', 'profile.save']`, so during J2C's `task=checkout.registerValidate` it never throws. The user is created **without** acknowledging the terms — silent compliance gap, no visible breakage.

---

## 3. Core Joomla plugin audit

All core J6 plugins that subscribe to `onContentPrepareForm` against `com_users.registration` or `com_users.profile`, or that redirect mid-flow, were reviewed. The table shows whether each one is currently *breaking* the J2C checkout, *silently bypassed* by it (consent never captured), or unaffected.

| Plugin | Subscribed events | Form contexts | Redirects? | Effect on J2C checkout |
|---|---|---|---|---|
| **system − privacyconsent** | `onContentPrepareForm`, `onUserBeforeSave`, `onUserAfterSave`, `onUserAfterDelete`, **`onAfterRoute`**, `onPrivacyCheckPrivacyPolicyPublished` | `com_users.profile`, `com_users.registration` | **YES** — redirects logged-in unconsented users to `com_users&view=profile&layout=edit` on every route hit | **Breaks checkout** post-register. Confirmed root cause of #958. |
| **user − terms** | `onContentPrepareForm`, `onUserBeforeSave`, `onUserAfterSave` | `com_users.registration` only (Terms.php:144) | No | **Silently bypassed.** Terms field never rendered in checkout; `onUserBeforeSave` enforcement keyed to `option=com_users && task=registration.register`, so the J2C register path skips it. Compliance gap. |
| **user − profile** | `onContentPrepareData`, `onContentPrepareForm`, `onUserBeforeSave`, `onUserAfterSave` | `com_users.profile`, `com_users.registration` | No | **Silently bypassed.** Profile fields (DOB, address, TOS-on-registration option) never collected during J2C checkout register. |
| **user − token** | `onContentPrepareForm` (+ token mgmt) | `com_users.profile` only | No | No checkout impact (profile-only). |
| **system − webauthn** (via `UserProfileFields` trait) | `onContentPrepareForm`, `onContentPrepareData` | `com_admin.profile`, `com_users.user`, `com_users.profile`, `com_users.registration` (allowlisted but skipped) | No | No checkout impact. UserProfileFields.php:144 explicitly returns when form name is `com_users.registration`; only adds passkey UI to profile. |
| **system − actionlogs** | `onContentPrepareForm`, `onContentPrepareData` | `com_users.profile`, `com_users.user` | No | No checkout impact (profile-only). |
| **multifactorauth − \*** | `onUserAfterLogin` (captive redirect) | — | Yes (captive) | No checkout impact in practice — captive views/tasks (`captive.*`, `method.*`, `methods.*`, `callback.*`) are part of privacyconsent's allowlist and MFA captive views run only after the user attempts to access an MFA-gated area. The J2C checkout AJAX endpoints are not MFA-gated. **Worth re-testing once §4 below is implemented.** |

The only plugin actively breaking the checkout is **system − privacyconsent**. Two others (**user − terms**, **user − profile**) represent a *separate* problem: the checkout-register path silently bypasses the Joomla consent mechanism, so no record is ever written that the customer agreed to anything.

---

## 4. Recommended fixes

Three discrete recommendations, ordered by priority. Each becomes its own follow-up issue.

### 4a. Render and capture core consent fields inside the checkout register step (fixes #958 and the silent bypass) — **IMPLEMENTED**

**Goal:** Make the J2C checkout register form participate in Joomla's standard `onContentPrepareForm` pipeline so every core or third-party plugin that injects into `com_users.registration` automatically appears in the checkout-register card, and so the consent record is written before the auto-login fires.

**Implementation sketch:**

1. In `CheckoutController::register()` (the GET-step that renders the register card), build a transient empty `Joomla\CMS\Form\Form` named **`com_users.registration`**, dispatch `onContentPrepareForm` on it, and pass the resulting `XMLElement` set into the template alongside the J2C custom fields. The template renders each plugin-injected field via `$form->renderField($name)`.
2. In `CheckoutController::registerValidate()`, before `new User()`:
   - Pull the same plugin-injected values from POST.
   - Validate them by dispatching `onUserBeforeSave` *with a synthetic input snapshot* (`option=com_users`, `task=registration.register`, `jform=...`) so each plugin's existing enforcement runs unmodified. Wrap the existing input briefly via `$this->app->getInput()->set(...)` and restore afterwards.
3. After `$user->save()`, dispatch `onUserAfterSave` against the same synthetic snapshot so each plugin's write-side logic (the `#__privacy_consents` insert, action-log entry, etc.) runs.
4. Only then call `$app->login()`.

Net result: by the time `onAfterRoute` runs on the next AJAX step the user is already consented; `isUserConsented()` short-circuits and no redirect fires. Same path fixes the silent bypass for **user − terms** and **user − profile** for free.

**Edits required (all core, will need explicit per-edit permission):**

- `components/com_j2commerce/src/Controller/CheckoutController.php` — `register()` and `registerValidate()`.
- `components/com_j2commerce/tmpl/checkout/default_register.php` — render the plugin-injected fields.
- `components/com_j2commerce/language/en-US/com_j2commerce.ini` + `en-GB` mirror — labels if any wrapper text is added.

**No code change required in any Joomla core plugin.** All enforcement stays in the upstream plugin where it belongs.

### 4b. Surface Joomla's profile-edit screen from the J2C MyProfile view

Brian asked "where can this info be clicked/viewed (myprofile view?)". Yes, with no new event:

- `Myprofile/HtmlView::display()` already dispatches `onJ2CommerceMyProfileTab` / `onJ2CommerceMyProfileTabContent` to let plugins inject tabs.
- Add a small `app_*` plugin (or extend an existing core app) that listens to `onJ2CommerceMyProfileTab` and adds an **Account & Privacy** tab whose content is a single link/iframe to `index.php?option=com_users&view=profile&layout=edit&Itemid=...`. That page already renders **every** core plugin's profile fields (privacy consent, terms, profile, token, webauthn, actionlogs) in one place, fully styled and fully translated.
- No duplicated logic, no new form, no new event. The link target is the same URL `plg_system_privacyconsent::onAfterRoute()` redirects to — so if a customer ever does land in the redirect, the new tab takes them back to the exact same form.

### 4c. Document a short-term mitigation for site owners on existing installs

For merchants who do not want to wait for §4a:

- **Option 1:** Disable the System − Privacy Consent plugin if the site is not subject to GDPR/CCPA.
- **Option 2:** In a custom `app_*` plugin, listen to `onJ2CommerceCheckoutAfterRegister` and write the `#__privacy_consents` row immediately for the new user. Documented sample SQL + listener stub to ship in `docs/recipes/auto-consent-on-checkout-register.md` once §4a is in flight.
- **Option 3 (config-only):** Disable "Force Consent" in the privacy plugin params. Field still appears on the standalone registration form but is not enforced.

---

## 5. Out of scope

- Third-party `user.*` / `system.*` plugins (e.g. Akeeba Subscriptions consent, Community Builder profile injection). The §4a pattern handles them automatically since they all subscribe via the same `onContentPrepareForm` mechanism — no per-extension shim required.
- Multi-factor authentication. The MFA captive flow runs on a separate event chain (`onUserAfterLogin`) and its allowlist already includes `captive.*` / `method.*` / `methods.*` / `callback.*` tasks. Re-test after §4a lands; if MFA captive still trips inside the checkout AJAX context, raise a follow-up issue against the captive-detection logic in `CheckoutController::register()`.

---

## 6. Evidence (file:line references)

| Claim | Source |
|---|---|
| Privacy consent redirect target & condition | `plugins/system/privacyconsent/src/Extension/PrivacyConsent.php:266-322` |
| Privacy consent allowed tasks (no `com_j2commerce`) | `plugins/system/privacyconsent/src/Extension/PrivacyConsent.php:299-316` |
| Privacy consent `onUserBeforeSave` gate | `plugins/system/privacyconsent/src/Extension/PrivacyConsent.php:142-147` |
| Terms plugin event list (no AfterRoute) | `plugins/user/terms/src/Extension/Terms.php:40-46` |
| Profile plugin event list | `plugins/user/profile/src/Extension/Profile.php:58-65` |
| WebAuthn skip on registration form | `plugins/system/webauthn/src/PluginTraits/UserProfileFields.php:144-149` |
| Token plugin profile-only | `plugins/user/token/src/Extension/Token.php:48` |
| Actionlogs profile-only | `plugins/system/actionlogs/src/Extension/ActionLogs.php:55-160` |
| J2C checkout register validate (the bypass) | `components/com_j2commerce/src/Controller/CheckoutController.php:357-504` |
| J2C T&C checkbox slot (precedent for §4a UI) | `components/com_j2commerce/tmpl/checkout/default_confirm.php:55-62` |
| J2C MyProfile tab event (used by §4b) | `components/com_j2commerce/src/View/Myprofile/HtmlView.php:157` |
