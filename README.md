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
