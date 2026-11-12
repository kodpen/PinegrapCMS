---
name: workspace-channel-settings
description: Channel templates, voice transcripts and GitHub links in Site Settings (Channels, mail and voice) and Workspace Settings: names, shared saves, the GitHub drawer
---

## Where are the three cards?
- PR #8865 (SW6a of the Workspace settings move, [[workspace-settings-overview]]): in Channels, mail and voice, sections `pgset-ws-templates`, `pgset-ws-voice`, `pgset-ws-github`; cards in `includes/settings/workspace_channels.php`, writes in `workspace_channels.save.php`. The old screen's address redirects here (PR #8931).
- `pg_settings_legacy_anchors()` maps the three anchors to the new cards. The mailboxes followed in SW6b ([[workspace-mail-settings-dialog]]).

## How do the templates move?
- `ws_templates_settings_card($self_url, $viewer, $form)`: Edit, Copy and New template still open `workspace_template.php`; Archive and Bring it back are named submit buttons `ws_template_archive` / `ws_template_restore` (value the template's id), run after the pane's save by `ws_templates_settings_post($viewer, $liveform, $id, $archive, $refuse)`, a refusal a warning.

## How does the voice card save?
- Fields `ws_voice_*` (old `voice_*`), saved with the pane's Save: `ws_voice_settings_input($post, $prefix)`, `ws_voice_settings_check()` (each refused field, marked `ws_voice_<field>`, nothing stored), `ws_voice_settings_write($input, $liveform, $refuse)`. The old screen's `ws_voice_settings_post()` gives the first refusal as its error.
- A write clears both services' last errors and the transcripts' wait, so `ws_voice_settings_changed()` (pure) skips an unchanged card; a typed key or a ticked remove box is a change. Role 0 only: others see the fields disabled, not posted.

## How do GitHub links move?
- Remove is `ws_github_link_remove` (value the link's id, behind `confirm()`). "Link a repository" opens the drawer `pgset-ws-github-link` (`ws_settings_github_link_drawer()`): fields `ws_github[...]` joined by `form=` (`lists` entry `ws_github` 2, events `ws_github[events][]`), no `required`, button `ws_github_link_save`.
- `ws_github_links_settings_dialog_input()` gives the old names to `ws_github_links_settings_post($viewer, $action, $liveform, $input, $refuse)`; both screens draw `ws_github_links_settings_form()`.

Tests: `tests/settings_workspace_channels_test.php`.
