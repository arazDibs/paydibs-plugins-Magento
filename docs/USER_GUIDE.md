# Paydibs Payment Gateway — User guide

## Overview

The extension adds **Paydibs** as a payment method. Checkout places an order in **pending payment**, then sends the customer to **Paydibs** to pay. The store updates order status from **return URLs**, **server notifications (IPN)**, and optional **cron** / **manual requery** flows.

## Configuration (Admin)

**Stores → Configuration → Sales → Payment Methods → Paydibs Payment Gateway**

| Field | Description |
|--------|-------------|
| **Enabled** | Turn the method on or off. |
| **Title** | Label shoppers see at checkout. |
| **API Environment** | **Test** vs **Production** (affects API endpoints from configuration). |
| **Merchant ID** | Issued by Paydibs. |
| **Merchant Password** | Issued by Paydibs; stored encrypted by Magento. |
| **Page Timeout** | Paydibs hosted page timeout (seconds). |
| **Sort Order** | Checkout payment list order. |
| **Enabled Log** | When **Yes**, diagnostic messages are written to **`var/log/paydibs.log`** (signatures and secrets are not logged in full). |
| **Restore Cart on Failed Payment** | When **Yes**, failed/cancelled payments attempt to reactivate the quote for checkout. |
| **Manual Requery Secret Key** | If set, calling `paydibs/payment/requery?key=<secret>` runs the pending-order status query job; leave empty to disable. |

## Checkout flow (customer)

1. Customer chooses **Paydibs** and places the order.
2. The browser is sent to **Paydibs** using the prepared payment URL.
3. On success or failure, Paydibs redirects back to the store; the order is updated accordingly.

## Operational features

- **Cron** (`paydibs_query_pending_orders`): Periodically queries **pending payment** Paydibs orders (schedule in `etc/crontab.xml`).
- **Manual requery**: Optional secret-protected URL for support (see **Manual Requery Secret Key**).

## Security notes

- **Response** and **Notify** validate Paydibs payloads using **signed fields** as implemented in the extension.
- When **Enabled Log** is on, logs avoid full **Sign** values and avoid logging full query URLs that contain secrets.

## Support

Use your Paydibs merchant support channel and the extension **GitHub** issue tracker listed in `composer.json` **support.issues**, if applicable.
