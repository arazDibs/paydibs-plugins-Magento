# Paydibs Payment Gateway — User guide

## Overview

The extension adds **Paydibs** as a payment method. Checkout creates an order in **pending payment**, then redirects the customer to **Paydibs** to complete payment. Order status is updated from **customer return URLs**, **server-to-server notifications (IPN)**, and optionally **cron** and a **manual requery** URL.

## Configuration (Admin)

**Path:** **Stores → Configuration → Sales → Payment Methods → Paydibs Payment Gateway**

| Field | Description |
|--------|-------------|
| **Enabled** | Turns the payment method on or off. |
| **Title** | Label shown to shoppers at checkout. |
| **API Environment** | **Test** or **Production** — selects the configured Paydibs API endpoints. |
| **Merchant ID** | Provided by Paydibs. |
| **Merchant Password** | Provided by Paydibs; stored **encrypted** in Magento configuration. |
| **Page Timeout** | Hosted payment page timeout in seconds. |
| **Sort Order** | Position in the checkout payment method list. |
| **Enabled Log** | When **Yes**, writes diagnostics to **`var/log/paydibs.log`** (sensitive values such as full signatures and secrets are not logged). |
| **Restore Cart on Failed Payment** | When **Yes**, attempts to restore the quote for checkout after failed or cancelled payments where applicable. |
| **Manual Requery Secret Key** | Optional. If set, an HTTP **GET** to **`paydibs/payment/requery?key=&lt;your-secret&gt;`** runs the same pending-order query logic as cron (JSON response). Leave **empty** to disable this endpoint. |

## Checkout flow (customer)

1. The customer selects **Paydibs** and places the order.
2. The storefront receives a payment URL and redirects the browser to **Paydibs**.
3. After payment, **Paydibs** redirects back to the store; **Response** and **Notify** handlers update the order using signed parameters.

## Operational features

- **Cron** — Job id **`paydibs_query_pending_orders`** runs on the schedule **`*/15 * * * *`** (approximately **every 15 minutes**). It looks up **pending payment** orders for this method and queries Paydibs for status. Defined in **`etc/crontab.xml`**; ensure Magento cron is running in production.
- **Manual requery** — Optional secret key; see table above. Intended for support or automated **GET** triggers, not for customer browsers.

## Security and privacy

- **Response** (return URL) and **Notify** (IPN) verify **Paydibs** payloads using the **signature** rules implemented in this extension.
- Gateway-supplied messages shown to shoppers or stored in comments are handled with **escaping** appropriate to the context.
- **Merchant Password** and the **Manual Requery Secret Key** are stored with Magento’s **encrypted** configuration backend where configured.
- Card and payment details are processed on **Paydibs**’s hosted pages; refer to **Paydibs** and your privacy policy for data processing obligations.

## Support

- Merchant support: your **Paydibs** account channel.
- Extension source and issues: **`composer.json`** fields **`homepage`**, **`support.source`**, and **`support.issues`**.

## License

Open source under the **Apache License, Version 2.0** — see **`LICENSE.txt`** and **`NOTICE.txt`** in the package.
