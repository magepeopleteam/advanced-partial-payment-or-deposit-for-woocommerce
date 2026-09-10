# Advanced Partial Payment or Deposit for WooCommerce

Accept deposits and partial payments on your WooCommerce store. A customer pays part of a
product's price at checkout, then pays off the remaining balance later from **My Account**.
Built for high-value items, services, and bookings where full payment up front isn't practical.

This is the free plugin. See [advanced-partial-payment-or-deposit-for-woocommerce-pro](https://github.com/magepeopleteam/advanced-partial-payment-or-deposit-for-woocommerce-pro)
for the Pro addon (payment plans, min/max deposits, gateway rules, reminders, reports, and
booking-plugin integrations).

## Requirements

- WordPress 5.8+
- PHP 7.4+
- WooCommerce 5.0+

## Features

- **Fixed or percentage deposits** — collect a flat amount or a percentage of the product price
- **Per-product overrides** — a product can use its own deposit rule instead of the global one
- **Category-wise rules** — set a deposit rule for every product in a category
- **Cart & checkout integration** — deposit/balance breakdown shown at every step
- **`Partially Paid` order status** — a first-class WooCommerce order status, not a workaround
- **Balance tracking** — deposit amount, amount paid, and balance due are all tracked per order
- **Pay Remaining Balance** — customers pay off what's left from My Account, whenever they're ready
- **Manual payment recording** — record a payment taken outside WooCommerce (phone, bank transfer) from the order screen
- **Email notifications** — deposit received, balance due, payment complete, each with editable templates
- **Admin dashboard** — a tabbed settings screen for global rules, labels, and email content

## How it works

1. A shop enables deposits globally, per category, or per product, and chooses **Fixed Amount** or **Percentage**.
2. At checkout, WooCommerce charges only the deposit amount; the order is marked `Partially Paid`.
3. The customer returns to **My Account → Deposits** and pays the remaining balance when ready.
4. Once the balance reaches zero the order moves to `Completed` and a payment-complete email is sent.

Balance finalization only happens on an actual payment-capture event
(`woocommerce_payment_complete`, or a `processing`/`completed` status transition from a gateway that
*captures* funds) — an offline gateway like Cash on Delivery or Bank Transfer never closes out a
balance on its own. See [`includes/class-apd-order.php`](includes/class-apd-order.php) for the
guard.

## Installation

1. Upload the plugin to `/wp-content/plugins/` (or install via the Plugins screen).
2. Activate it — WooCommerce is required and the plugin will prompt to install/activate it if missing.
3. Configure deposit rules under **Deposits** in the admin sidebar.

## Project structure

```
admin/       Settings dashboard, product/category deposit fields, order metabox
includes/    Core engine — deposit calculation, order/balance state, emails
public/      Cart, checkout, My Account, and the Pay Balance endpoint
templates/   Email HTML/plain-text templates
```

## Filters

| Filter | Purpose |
|---|---|
| `apd_deposit_enabled` | Override whether deposits are enabled for a product |
| `apd_deposit_amount` | Adjust the calculated deposit amount |
| `apd_deposit_type` / `apd_deposit_value` | Override the resolved deposit type/value |
| `apd_allow_partial_balance_payments` | Let the balance be paid in any amount, any number of times (Pro) |
| `apd_min_balance_payment` | Minimum for a single balance payment when flexible payments are on (Pro) |
| `apd_allow_payment_on_cancelled_order` | Allow paying a balance on a cancelled order (off by default) |
| `apd_offline_payment_methods` | Payment gateways treated as non-capturing (`cod`, `bacs`, `cheque` by default) |

## Support

- WordPress.org plugin page and support forum: see [readme.txt](readme.txt) for the full changelog
- MagePeople: https://www.mage-people.com

## License

GPL-2.0+ — see [license.txt](license.txt)
