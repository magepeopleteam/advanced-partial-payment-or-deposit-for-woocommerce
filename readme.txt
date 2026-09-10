=== Advanced Partial Payment or Deposit for WooCommerce ===
Contributors: magepeopleteam, aamahin
Tags: woocommerce, deposit, partial payment, installment, payment plan
Requires at least: 5.8
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 4.0.2
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Accept partial payments, deposits, and installments on your WooCommerce store.

== Description ==

**Advanced Partial Payment or Deposit for WooCommerce** lets you collect deposits and partial payments on your WooCommerce store. Perfect for high-value items, services, bookings, and custom orders.

= Free Features =

* **Fixed & Percentage Deposits** — Collect a flat amount or percentage of the product price
* **Per-Product Settings** — Override global settings on individual products
* **Category-Wise Rules** — Set deposit rules per product category
* **Cart & Checkout Integration** — Beautiful deposit breakdown display
* **Custom Order Status** — "Partially Paid" status for deposit orders
* **Balance Tracking** — Track remaining balance on each order
* **Pay Balance** — Customers can pay balance from My Account
* **Email Notifications** — Deposit received, balance due, payment complete
* **Admin Management** — Deposit metabox on orders, manual payment recording
* **Professional Dashboard** — Tabbed settings page with sidebar navigation

= Pro Features =

* Payment Plans / Installments
* Minimum & Maximum Deposit Amounts
* Payment Gateway Restrictions
* Auto Payment Reminders
* Forced Deposit Mode
* Reports & Analytics
* Tax & Fee Handling
* Bulk Product Settings
* User Role-Based Deposits
* Conditional Deposit Rules

== Installation ==

1. Upload the plugin to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to **Deposits** in the admin sidebar to configure settings

== Changelog ==

= 4.0.2 =
* Security: Offline payment gateways (Cash on delivery, Direct bank transfer, Check payments) no longer close out a deposit balance. Previously an order moving to on-hold or processing was treated as a successful remaining-balance payment, so a customer could mark an order fully paid without any money being captured.
* Security: An order sitting on-hold is no longer treated as paid for balance finalization on any gateway, since on-hold is WooCommerce's "awaiting payment" state.
* Fix: Recording a payment now clears any in-flight balance payment marker, so a stale pending amount can no longer be banked twice.
* Dev: New `apd_offline_payment_methods` and `apd_is_balance_payment_captured` filters to control which gateways may settle a balance.

= 1.0.0 =
* Initial release
