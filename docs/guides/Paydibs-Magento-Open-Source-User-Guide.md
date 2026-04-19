# Paydibs Payment Gateway — Magento Open Source

*A practical guide for merchants and developers. Package: `paydibs/module-paymentgateway` · Magento Open Source and compatible 2.4.x installs.*

![Paydibs](../images/user-guide/paydibs-header-banner.png)

---

## What this extension does

If you run a Magento Open Source store and want customers to pay through **Paydibs**, this module wires your checkout to Paydibs’ hosted payment flow. The shopper places an order in your store, gets sent to Paydibs to pay, then comes back while Magento updates the order from Paydibs’ responses (browser return, server notification, and optional background checks). You keep the usual Magento order and invoice workflow—you’re not replacing the admin, just the payment rail.

---

## Before you install

- **Magento** Open Source **2.4.x** (the extension targets `magento/framework` **~103.0.0||~104.0.0**—see `composer.json` in the package).
- **PHP** whatever your Magento version requires; the package also lists supported PHP ranges in `composer.json`.
- **Paydibs** merchant credentials for **Test** first, then **Production** when you go live.

---

## Installing with Composer (recommended)

From your Magento project root:

```bash
composer require paydibs/module-paymentgateway
bin/magento module:enable Paydibs_PaymentGateway
bin/magento setup:upgrade
bin/magento setup:di:compile
bin/magento cache:flush
```

If you install from a **ZIP** or a **path** repository, point Composer at the folder that contains this package’s `composer.json`, then require it (for example `paydibs/module-paymentgateway:@dev` for a path). After that, run the same `bin/magento` lines as above.

Check that the module is really on:

```bash
bin/magento module:status Paydibs_PaymentGateway
```

You want to see **enabled**.

---

## Turning it on in the admin

Go to **Stores → Configuration → Sales → Payment Methods** and open **Paydibs Payment Gateway**. You’ll see something like this:

![Paydibs admin settings](../images/user-guide/admin-paydibs-settings.png)

Fill in **Merchant ID** and **Merchant Password** from Paydibs, pick **Test** or **Production** under **API Environment**, and set **Enabled** to **Yes** when you’re ready. The **Title** is what shoppers see at checkout. **Sort Order** only changes where Paydibs appears in the payment list. **Enabled Log** sends diagnostics to `var/log/paydibs.log`—handy while testing; turn it down in production if you don’t need the noise.

**Restore cart on failed payment** does what it says: if Paydibs sends back a failed or cancelled payment, Magento can put the quote back so the customer isn’t stuck with an empty cart.

---

## What the customer sees

On the **Review & Payments** step, Paydibs shows up next to your other methods:

![Checkout — Paydibs as a payment option](../images/user-guide/storefront-checkout-payment-methods.png)

After they place the order, the browser goes to **Paydibs’ hosted page** to complete payment (your store never types card data into Magento):

![Hosted Paydibs payment page](../images/user-guide/hosted-paydibs-payment-page.png)

In **sandbox / test**, you may see Paydibs’ **Bank Host Simulator** so you can force success or failure:

![Paydibs test simulator](../images/user-guide/paydibs-test-simulator.png)

On a **successful** payment, the customer typically sees a confirmation on Paydibs before returning to your store:

![Payment success on Paydibs](../images/user-guide/hosted-payment-success.png)

---

## Orders in the admin

After payment, open the order in Magento. Under payment information you should see Paydibs transaction details similar to:

![Order — Paydibs payment details](../images/user-guide/admin-order-paydibs-details.png)

That’s what you’ll use for support and reconciliation.

---

## Status codes (in plain language)

- **Success (0)** — Order moves to **Processing**, invoice is created when Magento allows it, customer gets your success flow.
- **Pending (2)** — Order can stay **pending payment** until Paydibs confirms; cron or manual tools can recheck.
- **Failed / cancelled (1, 9, 17, -1, -2, etc.)** — Order is cancelled; with **restore cart** on, the quote can come back for another try.

There’s a **cron** job that runs about **every 15 minutes** to query still-pending Paydibs orders—make sure Magento cron is actually running in production. Optionally you can set a **manual requery secret** and call the requery URL documented in the module (support-only; leave blank if you don’t need it).

---

## License

This package is released under the **Apache License, Version 2.0**. See `LICENSE.txt` and `NOTICE.txt` in the root of the Composer package.

---

## Need help?

Use your **Paydibs** merchant support channel for gateway or account issues. For the extension itself, see the `support` URLs in `composer.json` on GitHub.
