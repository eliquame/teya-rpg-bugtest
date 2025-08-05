=== Payment gateway via Teya RPG for WooCommerce ===
Contributors: tacticais
Tags: credit card, gateway, teya, woocommerce
Requires at least: 4.4
Requires PHP: 7.0
Tested up to: 6.8.2
WC tested up to: 10.0.4
WC requires at least: 3.2.3
Stable tag: 1.0.37
License: GPLv3
License URI: http://www.gnu.org/licenses/gpl-3.0.html

Take payments in your WooCommerce store using the Teya Restful Payment Gateway

== Description ==

Teya's Restful Payment Gateway enables merchants to accept payments in a variety of ways. After receiving Public and Private access tokens from Teya you can start accepting payments.
Teya RPG can be used to charge cards as well as working with transactions such as cancelling and capturing authorizations, refunding transactions and getting information on transactions.
Supports 3DSecure which is resulting in less chance of fraudulent transactions. 3DSecure is mandatory for ecommerce transactions in Europe.

This plugin is maintained and supported by Tactica

== Installation ==

Once you have installed WooCommerce on your Wordpress setup, follow these simple steps:

1. Upload the plugin files to the `/wp-content/plugins/woocommerce-gateway-borgun-rpg` directory, or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the 'Plugins' screen in WordPress.
3. Insert the merchant ID, Private Key and Public Key in the Checkout settings for the Teya RPG payment plugin and activate it.

== Frequently Asked Questions ==

= Does the plugin support test mode? =

Yes, the plugin supports test mode.

= Does the plugin support <a href="https://woocommerce.com/products/woocommerce-subscriptions" target="_blank">WooCommerce Subscriptions</a>? =

Yes, the plugin supports <a href="https://woocommerce.com/products/woocommerce-subscriptions" target="_blank">WooCommerce Subscriptions</a>.


== Changelog ==

= 1.0.37 =
* Tested with WordPress 6.8.2 and WooCommerce 10.0.4
* Add single token life time

= 1.0.36 =
* Tested with WooCommerce 9.9.5

= 1.0.35 =
* Updated payment intent page functionality

= 1.0.34 =
* Improve 3ds payment process

= 1.0.33 =
* Update Teya logo

= 1.0.32 =
* Tested with WordPress 6.8.1 and WooCommerce 9.8.5

= 1.0.31 =
* Improving payments processing

= 1.0.30 =
* Tested with WordPress 6.7.2 and WooCommerce 9.7.0
* Fixed PHP json_encode() float issue in enrollment, payment, refund requests
* Adjusted plugin scripts load

= 1.0.29 =
* Tested with WooCommerce 9.5.1
* Fixed issue with subscription renewal after changing payment gateway

= 1.0.28 =
* Tested with WordPress 6.7.1 and WooCommerce 9.4.2
* Improve orders metadata storage to prevent usage postmeta's if HPOS is enabled

= 1.0.27 =
* Fixed payment refund in new WC
* Tested with WordPress 6.5.3 and WooCommerce 8.8.3

= 1.0.26 =
* Added more currencies and languages support
* Tested with WordPress 6.4.3 and WooCommerce 8.5.2

= 1.0.25 =
* Tested with WordPress 6.4.2 and WooCommerce 8.3.1
* Payment Method Integration for the Checkout Block
* Fix refund payment response

= 1.0.24 =
* Tested with Wordpress 6.4, Woocommerce 8.2.1
* Changed plugin name 'Payment gateway via Borgun RPG for WooCommerce' to 'Payment gateway via Teya RPG for WooCommerce'
* Fixed 'dynamic property declaration' warnings(PHP 8.2+)

= 1.0.23 =
* Tested with Wordpress 6.3, Woocommerce 7.9.0

= 1.0.22 =
* Tested with Wordpress 6.2 and Woocommerce 7.6.0

= 1.0.21 =
* Added authorization request using the MpiToken after MPI enrollment response with MdStatus is 1
* Tested with Wordpress 6.0.1 and Woocommerce 6.7.0

= 1.0.20 =
* Updated MPI enrollment process
* Added GBP to accepted currencies
* Tested with Wordpress 5.8.1 and Woocommerce 5.8.0

= 1.0.19 =
* Changed api payment request TransactionDate format

= 1.0.18 =
* Fixed order-pay page payment

= 1.0.17 =
* Adjusted card token request for more plugins compability
* Tested with Wordpress 5.7.1 and Woocommerce 5.2.2

= 1.0.16 =
* Fixed issue with pay for existing order

= 1.0.15 =
* Added debug logs

= 1.0.14 =
* Adjusted 3D Secure handling

= 1.0.13 =
* Adjusted errors handling

= 1.0.12 =
* Fixed issue with subscription renewal

= 1.0.11 =
* Tested with Wordpress 5.6

= 1.0.10 =
* Tested with Wordpress 5.5.3 and Woocommerce 4.7.0
* Fixed session start issue

= 1.0.9 =
* Changed plugin name and description to meet Wordpress naming requirements.

= 1.0.8 =
* Sanitized and escaped data
* Tested with Wordpress 5.5.1 and Woocommerce 4.6.0

= 1.0.7 =
* Tested with Wordpress 5.5.1 and Woocommerce 4.5.2
* Updated checkout the error placement

= 1.0.6 =
* Tested with Wordpress 5.4.2 and Woocommerce 4.2.0

= 1.0.5 =
* Added warning message
* Fixed Subscription behavior

= 1.0.4 =
* Added support 3D Secure
* Fixed bug with refund order

= 1.0.3 =
* Tested with Wordpress 5.3.2 and Woocommerce 4.0.1

= 1.0.2 =
* Tested with Wordpress 5.3.2 and Woocommerce 3.9.3

= 1.0.1 =
* Updated errors handling

= 1.0.0 =
* Initial release
