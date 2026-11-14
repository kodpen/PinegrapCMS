---
name: claude-plugin-census
description: Claude plugin bundle as Anthropic's pages state it (read 2026-11-14): folder and manifest, directory checks and policy, which Claude app loads what, and how a bundle can (not) ask for a site's address
---

## What is a plugin bundle?
- A folder holding `.claude-plugin/plugin.json` (only `name` required, kebab-case, at most 64 characters, no `claude`/`anthropic`/`mcp`/`plugin` as the whole name), beside it `skills/<name>/SKILL.md`, `commands/*.md`, `agents/`, `hooks/hooks.json` and `.mcp.json`.
- Listing fields read from `plugin.json`: `icon` (an image inside the folder), `documentationUrl`, `supportUrl`, `privacyPolicyUrl`, `termsOfServiceUrl` (all `https://`).
- The directory needs a `README.md` of at least 40 words (words in code blocks do not count) saying what it does, how to use it and what data it sends, and a `LICENSE` file or `license`.
- Submitted from a public GitHub repository at `claude.ai/directory/manage` (paid plan; on Team/Enterprise an Owner). The first organization to submit a folder owns the listing. Later versions: merge to the tracked branch; each commit is scanned.

## What do the directory's checks block?
- Blocking: a path outside the folder, symlinks/submodules/LFS, `.DS_Store`-type files, credentials in any file, an unpinned `npx`/`uvx`, a non-`https` MCP `url`, invalid JSON, a skill whose front matter does not parse.
- Held for a reviewer: a file over 256 KiB (not image/font), more than 512 files, binaries other than PNG/JPEG/GIF/WebP/fonts, a `.mcpb` bundle, any local server or script the validator cannot follow.
- A security scan flags undisclosed sending of data; a first submission that fails it is rejected.
- A plain Markdown-and-JSON bundle (skills + `.mcp.json` with a remote `url`) meets all of these.

## What does the Software Directory Policy ask of the tools?
- Tool names at most 64 characters (5C); `title`, `readOnlyHint`, `destructiveHint` on every tool (5E); Streamable HTTP (5F); OAuth 2.0 with certificates from recognised authorities (5D).
- No software that transfers money or other financial assets autonomously; finance needs human-in-the-loop safeguards (section 4, 1A-B).
- No extraneous conversation data, not even in logs (1E); a privacy policy link (3A); a test account with sample data (3D); at least three working example prompts (3E).
- 3F: the developer owns or controls every endpoint the software connects to; a plugin may also connect to any approved connector.
- Read/write in separate tools is a connector review rule, not in the policy text; our routes are already one tool each.

## Which Claude app loads what?
- Skills: chat, Cowork, Claude Code. Commands: everywhere (chat loads them as skills). Agents and hooks: Cowork and Claude Code only. A top-level `bin/` stops chat and Cowork from installing the plugin.
- A remote MCP server with a fixed `url`: on the plugin's Connectors tab in chat and Cowork (the person adds and connects it, OAuth), loaded directly in Claude Code.

## How can the bundle ask for the person's site address?
- `userConfig` in `plugin.json` (`type: string`, `title`, `description`, `required`) and `"url": "${user_config.site_mcp_url}"` in `.mcp.json`: Claude Code prompts for it. The checklist accepts a bare `${user_config.KEY}` as the URL; the address with `/mcp.php` is asked whole.
- Chat ignores a server whose URL holds a `${user_config.*}` reference; Cowork ignores it when the option has no default and never prompts.
- `"url": ""` is accepted ("no fixed endpoint"); Claude Code shows it as not configured.
- So in chat and Cowork the person adds the site themselves under Customize > Connectors as a custom connector (`https://site/mcp.php`); the bundle's skills still load and use its tools.
- Tool names reach Claude as `mcp__plugin_<plugin>_<server>__<tool>`, limit 128 there; skills should name the bare tool (`pages_list`).

Related: docs/memory/api/claude-plugin-tool-check.md, docs/memory/api/api-mcp-server.md
