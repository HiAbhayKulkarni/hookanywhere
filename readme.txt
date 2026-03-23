=== HookAnywhere - Trigger Any Action, Send Anywhere ===
Contributors: hiabhaykulkarni
Donate Link: https://abhay.co/coffee
Tags: webhooks, api, automation, zapier, n8n
Requires at least: 5.6
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

HookAnywhere connects WordPress to the outside world by triggering any action hook and sending data to virtually any API or webhook endpoint.

== Description ==

HookAnywhere is a powerful and flexible WordPress webhook plugin that lets you trigger outgoing webhooks from any WordPress action hook — including hooks from other plugins installed on your site.

Unlike basic webhook plugins that only support limited core events, HookAnywhere allows you to select a specific WordPress plugin and dynamically access all available action hooks defined inside it. Simply choose the hook you want to listen to and instantly send structured data to any external API or webhook endpoint.

Whether you're integrating with Zapier, n8n, custom CRMs, marketing tools, or internal automation systems, this plugin gives you full control over how your WordPress site communicates with the outside world.

== Why HookAnywhere? ==

Most free webhook plugins only support predefined WordPress core events. HookAnywhere goes further by allowing you to trigger webhooks from ANY action hook available on your site — including third-party plugins.

This makes it a true universal WordPress integration tool.

== Core Features ==

* **Trigger Any Action Hook** – Select a specific WordPress plugin and access all registered action hooks inside it.
* **Send Data to Any API or Webhook Endpoint** – Works with REST APIs, automation platforms, and custom servers.
* **Custom Headers & Authentication** – Add custom HTTP headers, pass authorization tokens, or configure authentication for secure API communication.
* **Flexible Body Parameters** – Send structured JSON, form data, or custom request bodies.
* **Advanced Logging System** – View complete request and response logs including headers, payload, response body, status codes, and execution time.
* **Log Retention Control** – Automatically delete logs after a user-defined number of days to keep your database optimized.
* **Role-Based Access Control** – Restrict webhook and log access based on user roles.
* **Log Export** – Export webhook logs for reporting or migration.

== Perfect For ==

* WordPress developers
* Automation engineers
* No-code / low-code builders
* Agencies managing integrations
* Site owners connecting WordPress to external systems

HookAnywhere removes limitations and gives you complete transparency and control over your WordPress API integrations.

== Installation ==

= Using The WordPress Dashboard =
1. Navigate to the 'Add New' in the plugins dashboard.
2. Search for 'HookAnywhere'
3. Click 'Install Now'
4. Activate the plugin on the Plugin dashboard

= Uploading in WordPress Dashboard =
1. Navigate to the 'Add New' in the plugins dashboard.
2. Navigate to the 'Upload' area
3. Select `hookanywhere.zip` from your computer
4. Click 'Install Now'
5. Activate the plugin in the Plugin dashboard

== Frequently Asked Questions ==

= What makes HookAnywhere different from other webhook plugins? =
Most free webhook plugins only support a limited number of predefined WordPress core events. HookAnywhere allows you to select any installed WordPress plugin and trigger webhooks from its available action hooks, making it significantly more flexible and powerful.

= Can I trigger webhooks from third-party plugins? =
Yes. You can select a specific WordPress plugin and view all available action hooks defined inside that plugin. You can then attach a webhook to any of those hooks.

= Can I send data to any API endpoint? =
Yes. You can send outgoing HTTP requests to any REST API or webhook endpoint. The plugin supports custom headers, authentication, and custom request body parameters.

= Does this plugin support authentication? =
Yes. You can configure authentication and pass custom headers such as API keys, Bearer tokens, or other authorization credentials required by your endpoint.

= What kind of data formats are supported? =
You can configure and customize the request payload, including structured JSON or other supported formats depending on your API requirements.

= Does the plugin store webhook logs? =
Yes. Every webhook request is logged, including request headers, payload, response body, HTTP status code, and timestamp.

= Can I automatically delete old logs? =
Yes. You can define a log retention period. Logs older than your selected number of days will be automatically deleted to keep your database clean and optimized.

= Can I control who has access to webhooks and logs? =
Yes. HookAnywhere includes role-based access control so you can manage which user roles can view or manage webhook settings.

= Does this plugin support incoming webhooks? =
Currently, HookAnywhere focuses on outgoing webhooks — sending data from WordPress to external systems when events occur.

= Is this compatible with Zapier, Make, or n8n? =
Yes. HookAnywhere works with Zapier, Make (Integromat), n8n, and any platform that accepts incoming webhook requests.

== External services ==

HookAnywhere includes an optional newsletter subscription feature during the onboarding process. If a user explicitly opts in, their Name and Email Address are transmitted to a self-hosted instance of n8n (an open-source workflow automation platform) operated by the plugin author, for the purpose of sending plugin-related and promotional email updates.

* n8n is open-source: https://github.com/n8n-io/n8n
* n8n Privacy Policy: https://n8n.io/legal/privacy/

No data is sent without the user's explicit consent.

== Changelog ==

= 1.0.0 =
* Initial release of HookAnywhere.