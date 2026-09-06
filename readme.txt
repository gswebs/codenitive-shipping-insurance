=== Codenitive Shipping Insurance ===
Contributors: codenitive
Tags: woocommerce, shipping insurance, package protection, cart, checkout
Requires at least: 6.2
Tested up to: 7.0
Requires PHP: 7.4
Requires Plugins: woocommerce
Stable tag: 2.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Add optional shipping insurance to WooCommerce cart and checkout pages with configurable fees, tax, default state, and display locations.

== Description ==

Codenitive Shipping Insurance adds a configurable package-protection option to WooCommerce. Customers can enable or disable insurance from the cart or checkout page. The selected state is stored in the WooCommerce session and remains synchronized while order totals refresh.

The plugin provides independent display-location settings for the cart and checkout pages. The insurance fee is added to the WooCommerce totals only when the customer enables the option.

= Features =

* Enable or disable shipping insurance globally.
* Set a custom heading, option label, and description.
* Choose between a single-fee toggle and tiered coverage choices.
* Configure a fixed fee or manage multiple coverage and fee levels with repeatable rows.
* Choose whether the insurance fee is taxable.
* Choose whether insurance is enabled by default.
* Select the insurance location on the classic cart page.
* Select the insurance location on the classic checkout page.
* Hide the insurance option independently on the cart or checkout page.
* Synchronize the selected state during WooCommerce AJAX updates.
* Add optional shipping instructions when using tiered coverage.
* Save the insurance selection, coverage, fee, and instructions in order metadata.
* Display the selected insurance fee in WooCommerce order administration.
* Support WooCommerce High-Performance Order Storage (HPOS).
* Include compatibility handling for CheckoutWC checkout updates.

== Installation ==

1. Upload the `codenitive-shipping-insurance` folder to `/wp-content/plugins/`, or install the ZIP through Plugins > Add New > Upload Plugin.
2. Activate Codenitive Shipping Insurance from the Plugins screen.
3. Go to WooCommerce > Shipping Insurance.
4. Configure the content, fee, tax setting, default state, and display locations.
5. Save the settings and test the cart and checkout pages.

== Configuration ==

= Enable =

Turns the shipping-insurance feature on or off across the store.

= Insurance type =

Choose "Single fee toggle" for the original package-protection switch or "Coverage choices" for radio-button coverage levels with an explicit decline option.

= Cart location =

Choose where the insurance option appears on the classic WooCommerce cart page. Select "Do not display on cart page" to hide it from the cart.

= Checkout location =

Choose where the insurance option appears within the classic WooCommerce checkout form. For CheckoutWC, "After billing form" is the recommended location. Select "Do not display on checkout page" to hide it from checkout.

= Heading, option label, and description =

Customize the text displayed in the insurance panel.

= Fee =

Enter the fixed insurance amount using the store currency. The fee appears in the totals when insurance is selected.

= Coverage choices =

Enter one coverage level and fee per line using `coverage|fee` format. For example, `300|15.00` provides up to $300 of coverage for a $15 fee. The plugin validates each selected level before adding its fee.

= Shipping instructions =

Optionally show a checkout textarea in Coverage choices mode. Instructions are sanitized and saved with the order.

= Default state =

When enabled, insurance is initially selected for a customer who does not already have a saved state in the current WooCommerce session.

= Tax =

Enable this setting if WooCommerce should calculate tax on the insurance fee.

== Frequently Asked Questions ==

= Does this plugin support WooCommerce Cart and Checkout blocks? =

No. Version 2.0.0 is designed for the classic WooCommerce cart and checkout templates.

= Does it work with CheckoutWC? =

Yes. The plugin includes support for CheckoutWC checkout refreshes. Use the "After billing form" location for the most reliable compatibility because available hooks can vary between CheckoutWC layouts and versions.

= Can customers remove insurance after enabling it? =

Yes. The checkbox updates the WooCommerce session for both enable and disable actions and then recalculates the totals.

= Can I show insurance only on the cart or only on checkout? =

Yes. Set the unwanted page location to "Do not display" under WooCommerce > Shipping Insurance.

= Is the insurance fee saved with the order? =

Yes. The selection and configured fee are saved as order metadata and are compatible with WooCommerce HPOS.

= Does changing the display location change the fee? =

No. Display locations control only where the selector appears. The configured fee and the customer's session selection remain unchanged.

== Screenshots ==

1. Shipping insurance displayed on the cart page.
2. Package-protection toggle displayed on the checkout page.
3. Shipping Insurance settings and display-location controls.

== Changelog ==

= 2.1.0 =

* Redesigned the settings screen with a professional card-based layout.
* Added General, Display, Coverage Choices, and Advanced settings tabs.
* Replaced the `coverage|fee` textarea with user-friendly repeatable coverage rows.
* Added controls to add, remove, and drag coverage choices into the preferred order.
* Added conditional settings so only fields relevant to the selected insurance type are shown.
* Added clearer setting descriptions, currency prefixes, and a sticky save panel.

= 2.0.0 =

* Added an alternative tiered coverage mode with radio-button choices.
* Added configurable coverage and fee levels using `coverage|fee` entries.
* Added a required decline choice so customers can explicitly reject insurance.
* Added optional shipping instructions on checkout.
* Added selected coverage and shipping instructions to order metadata and admin order details.
* Preserved the original single-fee toggle mode.

= 1.9.0 =

* Added selectable display locations for the classic WooCommerce cart page.
* Added selectable display locations for the classic WooCommerce checkout page.
* Added an option to hide the insurance selector independently on either page.
* Kept "Before cart totals" as the default cart location.
* Kept "After billing form" as the recommended and default CheckoutWC-compatible checkout location.

= 1.8.0 =

* Rendered one checkout selector after the billing form.
* Avoided duplicate desktop and mobile cart-summary selectors in CheckoutWC.

= 1.7.0 =

* Changed package protection to disabled by default.
* Reset the previous automatic opt-in setting during upgrades.

= 1.6.0 =

* Improved synchronization between CheckoutWC desktop and mobile checkbox copies.
* Fixed an issue that could prevent customers from disabling package protection.

== Upgrade Notice ==

= 2.1.0 =

Introduces a redesigned settings page and a user-friendly coverage choice manager. Existing coverage levels are preserved automatically.

= 2.0.0 =

Adds optional tiered coverage choices and shipping instructions. The existing single-fee toggle remains selected after upgrading.

= 1.9.0 =

Adds independent cart and checkout display-location settings. Review WooCommerce > Shipping Insurance after updating if you want to change the default positions.
