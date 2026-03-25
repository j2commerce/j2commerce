# J2Commerce

**Ecommerce for Joomla 6** — a native, full-featured online store component built on modern Joomla MVC architecture.

[![License: GPL v2+](https://img.shields.io/badge/License-GPL%20v2%2B-blue.svg)](https://www.gnu.org/licenses/old-licenses/gpl-2.0.html)
[![Joomla 6](https://img.shields.io/badge/Joomla-6.x-orange.svg)](https://www.joomla.org/)
[![PHP 8.3+](https://img.shields.io/badge/PHP-8.3%2B-purple.svg)](https://www.php.net/)

---

## About

J2Commerce is the next generation of [J2Store](https://www.j2store.org/), rebuilt from the ground up for Joomla 6. It replaces the legacy FOF 2 framework with native Joomla MVC, swaps jQuery for vanilla ES6+ JavaScript, and ships as a single installable package with 33+ bundled extensions.

If you've used J2Store, J2Commerce is the upgrade path. If you're new, it's a complete ecommerce solution that runs inside Joomla — no external platform, no SaaS dependency.

**Website:** [j2commerce.com](https://www.j2commerce.com)

---

## Requirements

| Requirement | Version |
|---|---|
| Joomla | 6.x |
| PHP | 8.3 or later |
| MySQL / MariaDB | 8.0+ / 10.6+ |

---

## Installation

1. Download the latest release from [j2commerce.com](https://www.j2commerce.com) or the [GitHub releases](https://github.com/newlinewebdesign/j2commerce/releases) page
2. In Joomla admin, go to **System > Install > Extensions**
3. Upload the `com_j2commerce_v6.x.x.zip` package
4. Follow the guided setup wizard

The package installs the core component, library, and all bundled plugins and modules in one step.

---

## Documentation

| Resource | Link |
|---|---|
| **User Guide** | [docs.j2commerce.com/v6](https://docs.j2commerce.com/v6) |
| **Developer Docs** | [docs.j2commerce.com/developer](https://docs.j2commerce.com/developer/) |
| **Support** | [j2commerce.com](https://www.j2commerce.com) |

---

## Extensions

The core package includes payment, shipping, and app plugins for common use cases. Additional extensions are available in the [j2commerce6extensions](https://github.com/j2commerce/j2commerce6extensions) repository.

Developers can build custom payment, shipping, app, and report plugins using the [developer documentation](https://docs.j2commerce.com/developer/).

---

## Migrating from J2Store

J2Commerce includes a migration tool (`plg_system_j2commerce_migration_tool`) that handles the transition from J2Store v4. Original `#__j2store_*` tables are preserved untouched — J2Commerce uses its own `#__j2commerce_*` tables, so you can roll back if needed.

---

## Architecture

J2Commerce is built on native Joomla 6 patterns:

- **Namespaced PHP 8.3+** — PSR-12, strict types, constructor promotion, match expressions
- **Native MVC** — Joomla's `AdminController`, `ListModel`, `FormModel`, `HtmlView`, `Table`
- **Dependency injection** — service providers, MVC factory
- **Vanilla JavaScript** — ES6+ with fetch/async-await, event delegation, no jQuery
- **Web Asset Manager** — all CSS/JS registered through Joomla's asset pipeline
- **Event-driven plugins** — `SubscriberInterface` with named events, no legacy triggers

---

## Security

If you discover a security vulnerability, please report it privately via email to [support@j2commerce.com](mailto:support@j2commerce.com) rather than opening a public issue.

---

## License

J2Commerce is licensed under the [GNU General Public License v2 or later](https://www.gnu.org/licenses/old-licenses/gpl-2.0.html).

Copyright (C) 2024-2026 J2Commerce, LLC. All rights reserved.