=== Postcode Radius Checker ===
Contributors: meridianmedia
Tags: postcode, radius, geolocation, service area, coverage
Requires at least: 5.5
Tested up to: 6.6
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A lightweight postcode radius checker with admin-set base address and radius. Shortcode: [postcode_checker]

== Description ==

- Set your base address or postcode
- Set radius in miles or km
- Users enter their postcode to see if they are covered
- Success and fail messages with CTA buttons and customizable colours
- Uses OpenStreetMap Nominatim for geocoding, with caching
- Haversine distance calculation

== Installation ==

1. Upload the ZIP via Plugins → Add New → Upload Plugin.
2. Activate the plugin.
3. Go to Settings → Postcode Radius to configure.
4. Add `[postcode_checker]` shortcode to a page.

== Notes ==
Please add a contact email in Settings to include in the User-Agent header for Nominatim API compliance.
