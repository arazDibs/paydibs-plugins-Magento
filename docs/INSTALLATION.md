# Installation (overview)

Detailed, screenshot-based guides live here:

| Audience | Document |
|----------|----------|
| **Magento Open Source** | **[guides/Paydibs-Magento-Open-Source-User-Guide.md](guides/Paydibs-Magento-Open-Source-User-Guide.md)** — Composer package `paydibs/module-paymentgateway` |
| **Adobe Commerce** (on-prem & Cloud) | **[guides/Paydibs-Adobe-Commerce-User-Guide.md](guides/Paydibs-Adobe-Commerce-User-Guide.md)** — Composer package `paydibs/module-paymentgateway-commerce` |

**Print / PDF for Adobe Marketplace:** open the matching HTML under **`docs/marketplace/`** in a browser → Print → Save as PDF (under 5 MB):

- [Paydibs-Guide-Magento-Open-Source.html](marketplace/Paydibs-Guide-Magento-Open-Source.html)
- [Paydibs-Guide-Adobe-Commerce.html](marketplace/Paydibs-Guide-Adobe-Commerce.html)

Screenshots are stored in **`docs/images/user-guide/`** (extracted from the Paydibs Magento User Guide PDF).

Quick command reference (Open Source):

```bash
composer require paydibs/module-paymentgateway
bin/magento module:enable Paydibs_PaymentGateway
bin/magento setup:upgrade
bin/magento setup:di:compile
bin/magento cache:flush
```

Adobe Commerce package: replace the first line with `composer require paydibs/module-paymentgateway-commerce`.
