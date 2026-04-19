# Paydibs Payment Gateway — Installation

## License

This extension is distributed under the **Apache License, Version 2.0**. See **`LICENSE.txt`** in the package root for the full license text and **`NOTICE.txt`** for copyright and trademark notices. The SPDX identifier in **`composer.json`** is **`Apache-2.0`**.

## Requirements

- **Adobe Commerce** or **Magento Open Source 2.4.x** — the package requires `magento/framework` **~103.0.0||~104.0.0** (and related Magento modules at compatible versions). See **`composer.json`** for exact constraints.
- **PHP** — use a version supported by your Magento release and by this package (see **`require.php`** in **`composer.json`**).
- **Package version** — Marketplace releases use **`composer.json`** **`version`** (e.g. **103.0.0**) for listing verification; keep it in sync with your Marketplace **version number**.

## Install via Composer (recommended)

**Magento Open Source** (Packagist or VCS when published, or a **path** repository pointing at this package):

```bash
composer require paydibs/module-paymentgateway
bin/magento module:enable Paydibs_PaymentGateway
bin/magento setup:upgrade
bin/magento setup:di:compile
bin/magento cache:flush
```

**Adobe Commerce / Commerce Cloud** (use the Commerce Composer package name):

```bash
composer require paydibs/module-paymentgateway-commerce
bin/magento module:enable Paydibs_PaymentGateway
bin/magento setup:upgrade
bin/magento setup:di:compile
bin/magento cache:flush
```

If you use a **path repository**, point the **`url`** at the directory that contains this package’s **`composer.json`** (for example `packages/magento-open-source` or `packages/magento-commerce-cloud` in this monorepo).

## Install from ZIP (Adobe Commerce Marketplace / manual)

1. Unzip so you have a single folder whose **root** contains **`composer.json`**, **`registration.php`**, **`etc/`**, **`Controller/`**, and the rest of the module tree (do not add an extra parent folder when referencing paths).
2. Register that folder as a **Composer path repository** in the Magento project’s **`composer.json`**, then run **`composer require paydibs/module-paymentgateway:@dev`** (or the Commerce package name) **or** install via Marketplace when the package is available from there.
3. Run:

```bash
bin/magento module:enable Paydibs_PaymentGateway
bin/magento setup:upgrade
bin/magento setup:di:compile
bin/magento cache:flush
```

Using **`app/code`** copy-only installs is possible for local testing but is **not** the documented Composer-based flow; prefer **`vendor/`** installation through Composer.

## Verify installation

```bash
bin/magento module:status Paydibs_PaymentGateway
```

Expected: **Module is enabled**.

## Admin access

Configuration lives under **Stores → Configuration → Sales → Payment Methods → Paydibs Payment Gateway**. Admin users need permission for payment configuration (Magento’s **`Magento_Payment::payment`** ACL scope).

## After installation

Configure **Merchant ID**, **Merchant Password**, and **API Environment** (Test vs Production). See **USER_GUIDE.md** for all fields and operational behavior.

## Uninstall (optional)

Disable the module, remove the Composer package (or remove code from `app/code` if you used a non-Composer copy), then run **`setup:upgrade`** and **`cache:flush`**. Consult Magento documentation for full uninstall practices in your environment.
