=== Paid Memberships Pro - Keap Integration ===
Contributors: strangerstudios
Tags: paid memberships pro, keap, crm
Requires at least: 5.0
Tested up to: 6.6.2
Stable tag: 1.0.3

Subscribe and tag your Paid Memberships Pro members in Keap.

== Description ==

This plugin integrates Paid Memberships Pro with Keap (formerly Infusionsoft) CRM. This allows you to tag your members in Keap based on their membership level and status.

== Installation ==

= Prerequisites =
1. You must have Paid Memberships Pro installed and activated on your site.

= Download, Install and Activate! =
1. Download the latest version of the plugin.
1. Unzip the downloaded file to your computer.
1. Upload the /pmpro-keap/ directory to the /wp-content/plugins/ directory of your site.
1. Activate the plugin through the 'Plugins' menu in WordPress.

= How to Use =
1. Navigate to Memberships > Keap in the WordPress dashboard.
1. You will need to enter your Keap API Key and Secret Key. You can find these in your Keap account by navigating to https://keys.developer.keap.com/.
1. Once authorized, you may select the tags to apply to users based on their membership level.

View full documentation at: https://www.paidmembershipspro.com/add-ons/keap-integration/

== Changelog ==
= 1.0.3 - 2024-10-31 =
* BUG FIX: Check for null before accessing users_tag element in the options array.

== Changelog ==
= 1.0.2 - 2024-09-13 =
* ENHANCEMENT: Adjusting the format of settings page and fields; updating descriptions; using PMPro-native styles for scroll boxes; labels with correct for name (@kimcoleman)
* ENHANCEMENT: Adding repo files; fixing links to Add On page, Add On name, removing admin.css (@kimcoleman)
* ENHANCEMENT: Update README.MD, Update .gitattributes (@kimcoleman)
* ENHANCEMENT: Adding banner file (@kimcoleman)

= 1.0.1 - 2024-08-27 =
* BUG FIX: Fixed a fatal error when User Tags weren't selected and calling on NULL when it should be an empty array.

= 1.0 =
* Initial version.