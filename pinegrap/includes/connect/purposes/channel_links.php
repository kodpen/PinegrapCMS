<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Linking a repository to a Workspace channel: the site adds its own webhook
 * to each linked repository and removes it when the link goes, and the
 * repository's events (CI results, pull requests, labels) come in as lines of
 * the channel.
 *
 * admin:repo_hook is the narrowest GitHub scope that creates and deletes a
 * repository's webhooks, in public and private repositories alike; it gives
 * no access to the code. A private repository is therefore named by its
 * owner/name rather than picked from a list, which would need the repo scope
 * (full access to every private repository of the account).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

return array(
    'label'       => lang('Channel links'),
    'description' => lang('Adds a webhook to the repositories linked to a Workspace channel, so their events are written into the channel.'),
    'owner'       => 'site',
    'scopes'      => array(
        'github' => array('admin:repo_hook'),
    ),
);
