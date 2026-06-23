=== Agent Abilities for MCP ===
Contributors: unaibamir
Tags: mcp, ai, claude, chatgpt, woocommerce-ai
Requires at least: 6.9
Tested up to: 7.0
Requires PHP: 8.0
Stable tag: 1.0.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Give an AI agent scoped, audited access to your WordPress site over the Model Context Protocol. Least privilege by design, off by default.

== Description ==

Give an AI agent access to your WordPress site without handing it the keys. Agent Abilities for MCP connects agents over the Model Context Protocol as a WordPress user you choose. Point it at a dedicated low-privilege account and it can only ever do what that account is allowed to do. Everything is off until you turn it on, and every action is logged. There is no admin-equivalent key and no custom transport to trust: it runs on the WordPress Abilities API and the official MCP Adapter.

Eighty-three core abilities cover reading and, when you allow it, writing posts, pages, terms, comments, media, post meta, and site structure, plus revision history and a search that spans every post type at once. You decide, per ability, what an agent can touch.

When you run a supported plugin, Agent Abilities adds abilities for it under the same rules: detected automatically, off until you turn them on, capability-gated, and logged. Version 1.0.0 covers SEO (Yoast, Rank Math, and All in One SEO), Advanced Custom Fields, and WooCommerce, which brings the full catalog to 168 abilities. The WooCommerce and ACF abilities can read and write real customer and order data, including personal data, so they sit behind a clear notice in the admin and stay off until you switch them on. More integrations are planned.

Most plugins in this space compete on how many tools they expose. This one competes on control: nothing is on by default, the agent only ever acts as a WordPress user you pick, and you can read back every call it made. You add reach as you trust it, not all at once.

Highlights:

* Least privilege by design. The agent connects as a real, scoped WordPress user through OAuth or Application Passwords, not an admin-equivalent key.
* Off by default. Nothing is exposed until you enable it, and updates never silently widen access.
* Two-layer capability gating. A connection only sees the tools its user can call, and every call re-checks the user's capability before it runs.
* Honest audit log. Every call is recorded, including denied attempts, with the principal and the argument keys (never the values).
* Safe by construction. No arbitrary option or meta access, no remote URL fetch, and no code execution. Uploads are decoded from inline data and checked by their real bytes against an image allow-list, never fetched from a URL. When an agent creates a user it gets the site's default role, never admin, and it can never remove the last administrator. Anything destructive is off by default and capability-gated.
* Optional safety controls. Switch on a per-minute rate limit, an IP allowlist, a force-to-draft mode, or a title-length cap. All four stay off until you set them.
* Works with your stack. Optional, auto-detected integrations for Yoast, Rank Math, All in One SEO, Advanced Custom Fields, and WooCommerce. Each appears only while its plugin is active and stays off until you enable it.
* Two ways to connect. Approve an agent in the browser over OAuth, with no secret to copy or store, or point a dedicated low-privilege user at an Application Password. A guided screen builds the client config and checks the endpoint for you.

== Installation ==

1. Upload the plugin to the `/wp-content/plugins/agent-abilities-for-mcp` directory, or install it from the WordPress plugins screen.
2. Activate it from the Plugins screen.
3. Open the Agent Abilities menu in your admin sidebar. On the Abilities tab, turn on only the abilities you want the agent to have. Everything starts off.
4. On the Connection tab, copy your site's MCP endpoint. The simplest path is OAuth: paste the endpoint into your MCP client and approve the connection once in the browser, where the agent acts as your own account.
5. Prefer not to use OAuth, or on a client that can't? Create the dedicated low-privilege agent user the Connection tab offers, generate an Application Password for it, and connect with that instead.
6. Use the connection check on the Connection tab to confirm the endpoint is reachable from your server.

== Frequently Asked Questions ==

= Does the agent get admin access? =

No. The agent authenticates as whatever WordPress user you bind it to. Point it at the dedicated low-privilege user the plugin can create for you, and it can only do what that user can do. Each ability also re-checks the user's capability before it runs, so a connection can never call a tool its user is not allowed to use.

= What can an agent actually do? =

Only the abilities you have enabled, and only within the bound user's capabilities. The catalog is reads and guarded writes over posts, pages, terms, comments, media, post meta, and site structure, plus revision history and a search that spans every post type at once. There is no ability to change options arbitrarily, change roles, fetch a remote URL, or run code. An agent can only write post meta for keys an administrator has explicitly allowlisted, and protected, underscore-prefixed, and authentication keys can never be allowlisted. Deletes move content to Trash where the ability supports it, and the permanent ones are off by default and capability-gated.

= Does it work with my other plugins? =

Yes, for a set of supported plugins. When one is active, Agent Abilities adds abilities for it under the same rules as the core: detected automatically, off until you turn them on, capability-gated, and logged. Version 1.0.0 covers SEO (Yoast, Rank Math, and All in One SEO), Advanced Custom Fields, and WooCommerce. The WooCommerce and ACF abilities can read and write real customer and order data, including personal data such as names, emails, and addresses, so they sit behind a clear notice in the admin and stay off until you switch them on. More integrations are planned.

= Is this the same as the WordPress Abilities API, or the official MCP adapter? =

It is built on both. WordPress 6.9 ships the Abilities API and the official MCP Adapter; Agent Abilities registers a curated, governed set of abilities on top of them rather than inventing its own protocol or transport. So there is no bespoke server to trust, and the plugin inherits the standard's behavior. What it adds is the governance layer: the off-by-default catalog, the capability gating, the safety controls, and the audit log.

= Which AI clients work? =

Any MCP client that can reach your site's endpoint. With OAuth you paste the endpoint URL into the client and approve the connection once in the browser; clients like Claude Desktop, Claude Code, Cursor, VS Code, Windsurf, and Gemini CLI connect this way, some directly and some through the mcp-remote bridge that runs on your own machine. You can also connect with an Application Password instead of OAuth. The hosted ChatGPT and Gemini apps want a streamable HTTP/SSE remote connector, which the underlying adapter does not serve natively yet.

= I'm on Windows and the config won't start. =

Windows MCP clients can't launch the npx shim by name. Wrap it in cmd: set "command" to "cmd" and put "/c", "npx" at the front of "args". The Connection tab has a Windows tab that generates this for you.

= My agent can't connect to a local or staging site. =

Local stacks like DDEV, Local, and Valet serve a self-signed certificate that Node rejects, so the proxy never reaches WordPress. For local testing only, add "NODE_TLS_REJECT_UNAUTHORIZED": "0" to the "env" block (the Connection tab adds it automatically when it detects a local site). Don't ship that setting to production — a public site has a trusted certificate and doesn't need it.

= OAuth discovery returns 403 or 404 on my server. =

When OAuth is enabled, clients find your site by fetching two documents under /.well-known/: /.well-known/oauth-protected-resource and /.well-known/oauth-authorization-server. WordPress serves both, but the request has to actually reach WordPress. Some servers deny anything that starts with a dot before PHP runs, and that blocks discovery.

On nginx the usual cause is a dotfile deny rule (location ~ /\. { deny all; }). Add a more specific block ahead of it so /.well-known/ falls through to WordPress:

location ^~ /.well-known/ {
    try_files $uri $uri/ /index.php?$args;
}

The ^~ prefix tells nginx to prefer this block over the dotfile deny. Other hidden files stay denied.

Apache usually works as-is, because the WordPress .htaccess sends anything that isn't a real file to index.php, /.well-known/ included. If a host or security plugin is blocking dotfiles, look for that rule (often in the vhost or a hardening snippet, not WordPress itself) and let /.well-known/ through.

To check, request https://your-site/.well-known/oauth-protected-resource. A working setup returns a JSON document instead of a 403 or 404.

= Is there rate limiting? =

Yes. Set a per-minute cap on the Settings tab under "Rate limit (per minute)". Each connection can make that many agent calls a minute, counted per agent user; 0 turns the limit off. Calls over the cap are denied and logged on the Activity Log tab, so you can spot a connection that keeps hitting it.

= Does it send my content to OpenAI, Anthropic, or Google? =

No. The plugin connects to no AI provider and makes no outbound requests of its own. It does the opposite of a bundled-AI plugin: your own AI client connects in to your site and calls the abilities you have enabled. Whatever your AI client does with the results afterward is between you and whoever makes that client.

= Does it send data anywhere? =

No. The plugin contacts no external service and has no telemetry. Your agent talks directly to your site.

= What gets logged? =

Every ability call — started, succeeded, errored, or denied — with the acting user, the ability name, and the argument keys. Argument values are never stored. The activity log lives in your own database and can be cleared from the admin screen.

= How do I report a security issue? =

Please report security issues privately rather than in the support forum, so a fix can ship before details are public. Use the security contact listed on the plugin's GitHub repository.

== External Services ==

This plugin does not contact any external service. It registers abilities on your own site and answers the requests your AI client sends to it. It makes no outbound requests of its own and includes no analytics or telemetry.

Connecting an AI client to your site is done by the client, not by this plugin. Some MCP clients reach your endpoint directly; others use a small bridge program that runs on your own computer, such as the open-source `mcp-remote` tool or `@automattic/mcp-wordpress-remote`. Neither bridge is bundled with this plugin or run by it — you install and run it yourself, and it talks only to your site and your local AI client. Their terms are on their own pages:

* mcp-remote: https://www.npmjs.com/package/mcp-remote
* @automattic/mcp-wordpress-remote: https://www.npmjs.com/package/@automattic/mcp-wordpress-remote

== Screenshots ==

1. The dashboard walks you through setup with a three-step checklist and shows enabled abilities, agent activity, audit size, and your MCP endpoint at a glance.
2. Connect over OAuth by pasting your site URL and approving once in the browser. An Application Password is there as a fallback.
3. Nothing is exposed until you switch it on. Abilities are grouped by area, each with its own toggle and an enable-all per section.
4. Integrations for SEO, ACF, and WooCommerce show up only while the host plugin is active, and each one stays off until you turn it on.
5. The activity log records every call: who made it, which ability, and whether it succeeded or was denied.

== Changelog ==

= 1.0.0 =
* Initial release. 168 governed abilities: 83 across WordPress core (reads and guarded writes for posts, pages, terms, comments, media, users, post meta, revisions, and site structure, plus a search that spans every post type), and the rest from auto-detected integrations for Yoast, Rank Math, All in One SEO, Advanced Custom Fields, and WooCommerce. Built on the WordPress Abilities API and the official MCP Adapter, with no custom transport. Connect over OAuth in the browser or with a least-privilege Application Password user. Everything off by default, two-layer capability gating, per-connection tool filtering, optional safety controls (rate limit, IP allowlist, force-draft, title-length cap), an audit log that records denials, and a guided connection screen with diagnostics.

== Upgrade Notice ==

= 1.0.0 =
First public release.
