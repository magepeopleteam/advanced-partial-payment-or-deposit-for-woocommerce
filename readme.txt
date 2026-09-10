=== Advanced Partial Payment or Deposit for WooCommerce ===
Contributors: magepeopleteam, aamahin
Tags: woocommerce, deposit, partial payment, installment, payment plan
Requires at least: 5.8
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 4.0.9
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

= 4.0.9 =
* Improvement: Renamed the Flexible Payments minimum fields so they cannot be mistaken for a second Deposit Type. "Minimum Type" offered the same option labels as Deposit Type ("Fixed Amount", "Percentage"), so two identical-looking Type dropdowns appeared on the same screen. It now reads "The minimum above is: a flat amount of money / a percentage of the booking total".

= 4.0.8 =
* Improvement: Flexible Payments now sits in its own "Remaining Balance" card on the product Deposit tab. It was separated only by a hairline rule, so it read as part of whichever deposit-type fields happened to sit above it, even though it is a separate setting that governs the balance after the deposit.

= 4.0.7 =
* Fix: Flexible Payments stays visible at all times. It was being hidden whenever the deposit type was Payment Plan, which left no way to switch it back off. Now it is the Payment Plan option that becomes unavailable while Flexible Payments is on, with a note explaining why.
* Fix: An explicit Yes on the product now beats a leftover instalment schedule on the order, so the saved setting is what actually applies.

= 4.0.6 =
* Improvement: Flexible Payments now sits below Deposit Type under a "Remaining Balance" heading, since it governs what happens after the deposit rather than the deposit itself.
* Improvement: Flexible Payments is hidden when the Deposit Type is Payment Plan, replaced by a line explaining that the plan already schedules the remaining balance. Showing both read as two competing systems.
* Fix: An order created from a Payment Plan no longer offers flexible balance amounts, so the behaviour matches what the product screen shows.

= 4.0.5 =
* Feature: Choosing "Yes" for Flexible Payments on a product or category now reveals a Minimum Per Payment and Minimum Type of its own, so each booking can have its own floor instead of sharing the global one. The section shows and hides as you change the dropdown.
* Percentage minimums are measured against the full booking total, so the floor does not creep downwards as the customer pays. If less than the minimum is left, the customer can still clear the balance.

= 4.0.4 =
* Feature: Flexible Payments can now be set per product and per category, not just globally. Each has a "Use Global Setting / Yes / No" control, and an explicit Yes or No overrides the global toggle in both directions.
* Fix: A minimum payment amount is now applied when Flexible Payments is switched on by a product or category override, instead of only when the global toggle is on.

= 4.0.3 =
* Security: The "Pay Balance Now" button in the balance reminder email now routes through the balance payment flow. It previously linked straight to the order payment page, which charged the customer the deposit amount again and credited the payment to nothing, leaving the balance still owed.
* Security: Paying a balance no longer reinstates a cancelled order. Cancelling is a shop decision and a customer payment must not silently reverse it (filter `apd_allow_payment_on_cancelled_order` restores the old behaviour).
* Security: Hardened the balance payment permission check. Guest orders have no owner, so a logged-out visitor now has to present the order key rather than passing an owner comparison of 0 against 0.
* Security: A recorded payment is now capped at the amount still outstanding, so the total paid can never exceed the order total.
* Feature: Support for paying a balance in freely chosen amounts, over as many payments as the customer likes, until the booking is paid in full. Off by default; enable it under Deposits > Flexible Payments with the Pro addon.
* Fix: The cart payment-type toggle now rejects products that have no deposit enabled, matching the bulk toggle.
* Dev: New filters `apd_allow_partial_balance_payments`, `apd_min_balance_payment` and `apd_allow_payment_on_cancelled_order`.

= 4.0.2 =
* Security: Offline payment gateways (Cash on delivery, Direct bank transfer, Check payments) no longer close out a deposit balance. Previously an order moving to on-hold or processing was treated as a successful remaining-balance payment, so a customer could mark an order fully paid without any money being captured.
* Security: An order sitting on-hold is no longer treated as paid for balance finalization on any gateway, since on-hold is WooCommerce's "awaiting payment" state.
* Fix: Recording a payment now clears any in-flight balance payment marker, so a stale pending amount can no longer be banked twice.
* Dev: New `apd_offline_payment_methods` and `apd_is_balance_payment_captured` filters to control which gateways may settle a balance.

= 1.0.0 =
* Initial release
