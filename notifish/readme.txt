=== Notifish ===
Contributors: notifish
Tags: whatsapp, notifications, messaging, automation, posts
Requires at least: 5.0
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 2.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Send automatic WhatsApp notifications when you publish posts on WordPress. Integrates with the Notifish API.

== Description ==

Notifish is a WordPress plugin that automatically sends WhatsApp notifications when you publish a post. It integrates with the [Notifish API](https://notifish.com) to send messages to WhatsApp groups.

= Key Features =

* **Automatic Notifications** - Send WhatsApp messages automatically when posts are published
* **Scheduled Posts Support** - Messages are sent when scheduled posts go live (via WP-Cron)
* **WordPress App Compatible** - Works with posts created via the official WordPress iOS/Android app
* **REST API Support** - Full REST API integration for developers
* **Duplicate Prevention** - Plugin checks if a post was already sent to avoid duplicates
* **Send History** - View all sent messages in the Notifish Logs page
* **Resend Messages** - Easily resend messages from the logs page
* **WhatsApp Status (API v2)** - View QR Code, connection status, and manage WhatsApp session

= How It Works =

1. Configure your Notifish API credentials in the plugin settings
2. When editing a post, check the "Share on WhatsApp" checkbox
3. Publish the post - the message is sent automatically
4. View sent messages in the Notifish Logs page

= Requirements =

* A Notifish account with API access
* API Key and Instance UUID from your Notifish dashboard

= Third-Party Service =

This plugin connects to the **Notifish API** to send WhatsApp messages. When you use this plugin:

* Your post title and URL are sent to the Notifish API servers
* The API sends the message to your configured WhatsApp groups
* No personal user data is collected or stored by the plugin

**Notifish Service:**
* Website: [https://notifish.com](https://notifish.com)
* Terms of Service: [https://notifish.com/termos-de-uso](https://notifish.com/termos-de-uso)
* Privacy Policy: [https://notifish.com/polica-de-privacidade](https://notifish.com/polica-de-privacidade)

== Installation ==

1. Upload the `notifish` folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to the Notifish menu and configure your API settings
4. Enter your API URL, Instance UUID, and API Key
5. Choose your API version (v1 or v2)
6. Enable "WhatsApp by default" if you want the checkbox pre-checked for new posts
7. Start publishing posts with WhatsApp notifications!

== Frequently Asked Questions ==

= Do I need a Notifish account? =

Yes, you need a Notifish account to use this plugin. Visit [notifish.com](https://notifish.com) to create an account and get your API credentials.

= Does it work with scheduled posts? =

Yes! When a scheduled post is automatically published by WordPress, the plugin will send the WhatsApp message at that time.

= Does it work with the WordPress mobile app? =

Yes! The plugin is fully compatible with the official WordPress app for iOS and Android. Posts created via the app will use the default settings configured in the plugin.

= Can I resend a message? =

Yes, go to Notifish Logs in your WordPress admin and click the "Resend" button next to any message.

= What's the difference between API v1 and v2? =

API v2 includes additional features like WhatsApp session management, QR Code display, and link previews. API v1 is the legacy version.

= Is my data safe? =

The plugin only sends post titles and URLs to the Notifish API. No personal user data is collected. All communications use HTTPS.

== Screenshots ==

1. Plugin settings page - Configure your Notifish API credentials
4. WhatsApp Status page (API v2) - View QR Code and manage WhatsApp session

== Changelog ==

= 2.0.0 =
* Added: REST API support for WordPress mobile app compatibility
* Added: Scheduled posts support via transition_post_status hook
* Added: XML-RPC support for legacy apps
* Added: Default value for posts created via REST API
* Improved: Better logging for debugging
* Improved: Code organization with separate classes

= 1.0.0 =
* Initial release

== Upgrade Notice ==

= 2.0.0 =
This version adds support for scheduled posts and the WordPress mobile app. Update recommended for all users.

== Privacy Policy ==

This plugin connects to an external service (Notifish API) to send WhatsApp messages. The following data is transmitted:

* Post ID
* Post title
* Post URL
* Blog name (for message identification)

No personal user data is collected or transmitted. The plugin does not track users or collect analytics.

For more information about how Notifish handles your data, please visit their [Privacy Policy](https://notifish.com/polica-de-privacidade).
