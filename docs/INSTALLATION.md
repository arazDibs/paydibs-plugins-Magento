# Paydibs Payment Gateway — Installation

## Requirements

- Adobe Commerce or Magento Open Source **2.4.x** (see `composer.json` for `magento/framework` and module version constraints).
- PHP version supported by your Magento installation (see extension `composer.json` `require.php`).

## Install via Composer (recommended)

**Magento Open Source** (from this repo’s `packages/magento-open-source` or Packagist, when published):

```bash
composer require paydibs/module-paymentgateway
bin/magento module:enable Paydibs_PaymentGateway
bin/magento setup:upgrade
bin/magento setup:di:compile
bin/magento cache:flush
```

**Adobe Commerce / Commerce Cloud** (use the Commerce package name):

```bash
composer require paydibs/module-paymentgateway-commerce
bin/magento module:enable Paydibs_PaymentGateway
bin/magento setup:upgrade
bin/magento setup:di:compile
bin/magento cache:flush
```

If you install from a **path repository**, point Composer at `packages/magento-open-source` or `packages/magento-commerce-cloud` as appropriate.

## Install from zip (Marketplace / manual)

1. Extract the Composer package so it ends up under `vendor/paydibs/...` as Composer would install it, **or** merge into `app/code/Paydibs/PaymentGateway` only if your workflow uses `app/code` (not recommended when a Composer package is provided).
2. Run `bin/magento module:enable Paydibs_PaymentGateway`, then `setup:upgrade`, `setup:di:compile`, `cache:flush` as above.

## After installation

Configure credentials under **Stores → Configuration → Sales → Payment Methods → Paydibs Payment Gateway**. See **USER_GUIDE.md** for field descriptions and operations.
