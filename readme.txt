=== Agent Abilities for MCP ===
Contributors: unaibamir
Tags: mcp, ai, agents, abilities, model context protocol
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

Highlights:

* Least privilege by design. The agent connects as a real, scoped WordPress user through Application Passwords, not an admin-equivalent key.
* Off by default. Nothing is exposed until you enable it, and updates never silently widen access.
* Two-layer capability gating. A connection only sees the tools its user can call, and every call re-checks the user's capability before it runs.
* Honest audit log. Every call is recorded, including denied attempts, with the principal and the argument keys (never the values).
* Safe by construction. No arbitrary option or meta access, no URL fetch, no user creation, no code execution. Deletes go to Trash, not gone. Uploads are decoded from inline data, checked by their real bytes against an image allow-list, and never fetched from a URL.
* Optional safety controls. Switch on a per-minute rate limit, an IP allowlist, a force-to-draft mode, or a title-length cap. All four stay off until you set them.
* Guided setup. Create the agent user, copy a client config, and run a connection check from one screen.

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/agent-abilities-for-mcp` directory, or install through the WordPress plugins screen.
2. Activate the plugin through the Plugins screen in WordPress.
3. Open Settings → Agent Abilities. On the Connection tab, create the dedicated agent user and copy the endpoint and client config.
4. On the Abilities tab, enable only the abilities you want the agent to have. Everything starts off.
5. Create an Application Password for the agent user, point your MCP client at the endpoint, and use the Connection tab to confirm it is reachable.

== Frequently Asked Questions ==

= Does the agent get admin access? =

No. The agent authenticates as whatever WordPress user you bind it to. Point it at the dedicated low-privilege user the plugin can create for you, and it can only do what that user can do. Each ability also re-checks the user's capability before it runs, so a connection can never call a tool its user is not allowed to use.

= What can an agent actually do? =

Only the abilities you have enabled, and only within the bound user's capabilities. The catalog is reads and guarded writes over posts, pages, terms, comments, media, post meta, and site structure, plus revision history and a search that spans every post type at once. There is no ability to change options arbitrarily, create users, change roles, fetch a remote URL, or run code. An agent can only write post meta for keys an administrator has explicitly allowlisted, and protected, underscore-prefixed, and authentication keys can never be allowlisted. Deletes move content to Trash so they are recoverable.

= Which AI clients work? =

Any MCP client that can reach a WordPress REST endpoint with an Application Password. Claude Desktop, Claude Code, Cursor, Windsurf, and Gemini CLI all connect through the @automattic/mcp-wordpress-remote proxy. The hosted ChatGPT and Gemini apps want a streamable HTTP/SSE remote connector, which the underlying adapter does not serve natively yet.

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

= Does it send data anywhere? =

No. The plugin contacts no external service. Your agent talks directly to your site.

= What gets logged? =

Every ability call — started, succeeded, errored, or denied — with the acting user, the ability name, and the argument keys. Argument values are never stored. The activity log lives in your own database and can be cleared from the admin screen.

== Changelog ==

= 1.0.0 =
* Initial release: 83 governed core abilities (reads and guarded writes across posts, pages, terms, comments, media, post meta, revisions, and search), least-privilege Application Password auth, per-connection tool filtering, two-layer capability gating, optional safety controls (rate limit, IP allowlist, force-draft, title-length cap), an audit log that records denials, and a guided connection wizard with diagnostics.
