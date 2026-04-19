# Paydibs Magento 2 extensions (monorepo)

This repository ships **two Composer packages** built from the **same module source**:

| Path | Composer name | Use |
|------|----------------|-----|
| `packages/magento-open-source` | `paydibs/module-paymentgateway` | Magento Open Source |
| `packages/magento-commerce-cloud` | `paydibs/module-paymentgateway-commerce` | Adobe Commerce / Commerce Cloud |

Only **`name`**, **`description`**, and **`README.md` install line** differ. After editing the Open Source package, sync into the Commerce package before tagging a release.

## Sync Commerce copy from Open Source

From the repository root:

```bash
rsync -a --delete "packages/magento-open-source/" "packages/magento-commerce-cloud/"
```

Then restore the Commerce **`composer.json`** identity:

- `"name": "paydibs/module-paymentgateway-commerce"`
- `"description": "Paydibs payment gateway for Adobe Commerce and Magento Commerce Cloud (Magento 2.4.x)."`

And fix **`packages/magento-commerce-cloud/README.md`** so `composer require` uses `paydibs/module-paymentgateway-commerce`.

## Documentation for Marketplace

See **`docs/INSTALLATION.md`** and **`docs/USER_GUIDE.md`**. Export them to PDF if Adobe Commerce Marketplace requires PDF uploads (≤ 5 MB).

## Adobe Commerce Marketplace (technical readiness)

The extension is structured to align with [Adobe Commerce PHP technical guidelines](https://developer.adobe.com/commerce/php/coding-standards/technical-guidelines), including dependency injection (no `ObjectManager` in module code), encrypted sensitive configuration, admin configuration gated by the core **`Magento_Payment::payment`** ACL (see `etc/adminhtml/system.xml`), PSR-3 logging, and hardened handling of external/gateway data (escaped where shown to customers; internal errors logged, not leaked in IPN responses).

Before you submit the package in the Marketplace portal, complete Adobe’s current checklist: **coding standard / static tests**, **MFTF or manual test evidence** as required, **User Guide** (PDF if requested), **privacy / data handling** disclosures for payment data, and **Composer** metadata from the published listing. The extension is licensed under the **Apache License 2.0** (`LICENSE.txt`, `NOTICE.txt`); **`composer.json`** includes **`license`** and **`version`** for Marketplace verification.

