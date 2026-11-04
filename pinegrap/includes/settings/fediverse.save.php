<?php

/**
 * Pinegrap - Enterprise Website Platform
 *
 * Site Settings - what the Fediverse screen writes: the ap_settings row of
 * config_text, then the job of the button that was pressed (look the site
 * up, block, lift a block, try a delivery again).
 *
 * Runs in the scope of includes/settings/screen.php, after the token check and
 * after post_value() is defined. Every box is checked before anything is
 * written, so a refused save leaves the account and its blocks as they were.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_SETTINGS_ENTRY')) {
    exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/activitypub_settings.php');

// Before the upgrade the cards draw no control (includes/settings/fediverse.php).
if (!pg_ap_outbox_ready() || !pg_schema_has('ap_blocks') || !pg_schema_has('config_text')) {
    return;
}

$ap_stored = pg_ap_settings_stored();
$ap_stored_read = pg_ap_settings_read(json_encode($ap_stored));

$ap_allowed = array(
    'forms'   => array_map('intval', array_column((array) db_items("SELECT page_id FROM page WHERE page_type = 'custom form'"), 'page_id')),
    'folders' => array(),
);

foreach ((array) db_items("SELECT folder_id FROM folder WHERE folder_archived = '0'") as $ap_folder) {
    if (pg_settings_folder_choice_ok((int) $ap_folder['folder_id'])) {
        $ap_allowed['folders'][] = (int) $ap_folder['folder_id'];
    }
}

$ap_row = pg_ap_settings_build(array(
    'enabled' => post_value('ap_enabled'),
    'handle'  => post_value('ap_handle'),
    'type'    => post_value('ap_type'),
    'name'    => post_value('ap_name'),
    'summary' => post_value('ap_summary'),
    'icon'    => post_value('ap_icon'),
    'forms'   => post_value('ap_forms'),
    'folders' => post_value('ap_folders'),
), $ap_stored, $ap_allowed);

// An empty handle is refused only while the switch is on; a typed one is
// checked either way, so a wrong one is never kept for later.
$ap_handle_error = pg_ap_settings_handle_error($ap_row['handle'], array_column((array) db_items("SELECT user_username FROM user"), 'user_username'));

if (($ap_handle_error === 'empty') && $ap_row['enabled']) {
    $liveform->mark_error('ap_handle', lang('Type a handle to switch the Fediverse account on.'));
} elseif ($ap_handle_error === 'format') {
    $liveform->mark_error('ap_handle', lang('A handle is made of letters, digits and underscores, at most 64.'));
} elseif ($ap_handle_error === 'taken') {
    $liveform->mark_error('ap_handle', lang('A panel user has this name; choose another handle.'));
}

if (!pg_public_image_choice_ok($ap_row['icon'], $ap_stored_read['icon'], pg_public_image_file_types())) {
    $liveform->mark_error('ap_icon', lang('Choose a picture from the Files screen that everyone may see.'));
}

$ap_block_kind = (post_value('ap_block_kind') === 'domain') ? 'domain' : 'actor';
$ap_block_value = '';

if (post_value('ap_block_add')) {
    $ap_block_value = pg_ap_block_normalize($ap_block_kind, post_value('ap_block_value'));

    if ($ap_block_value === '') {
        $liveform->mark_error('ap_block_value', ($ap_block_kind === 'domain')
            ? lang('Type a server name such as mastodon.example, an address on it or a handle.')
            : lang('Type the account\'s address, such as https://mastodon.example/users/name.'));
    }
}

if ($liveform->check_form_errors()) {
    return;
}

if (!pg_config_text_save('ap_settings', json_encode($ap_row, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE))) {
    $liveform->add_warning(h(lang('The Fediverse settings could not be saved: the settings texts table is missing. Run the upgrade.')));
    return;
}

// A handle that changed is a new account to the Fediverse: say so once.
if ($ap_stored_read['handle'] !== '' && $ap_row['handle'] !== $ap_stored_read['handle'] && ((int) db_value("SELECT COUNT(*) FROM ap_followers") > 0)) {
    $liveform->add_warning(h(lang('The handle changed: followers of the old address do not follow the new one.')));
}

// ── The pressed button's job, after the save ──
if (post_value('ap_block_add')) {
    pg_ap_block_add($ap_block_kind, $ap_block_value);
    $liveform->add_notice(lang(array('string' => '{var:1} is blocked.', 'vars' => $ap_block_value)));
}

if (post_value('ap_block_remove')) {
    if (pg_ap_block_remove((int) post_value('ap_block_remove'))) {
        $liveform->add_notice(lang('The block is lifted.'));
    }
}

if (post_value('ap_retry')) {
    if (pg_ap_delivery_retry((int) post_value('ap_retry'))) {
        $liveform->add_notice(lang('The delivery is queued again and goes out with the next scheduled job.'));
    } else {
        $liveform->add_warning(h(lang('That delivery is no longer a failed one.')));
    }
}

if (post_value('ap_lookup')) {

    // pg_ap_site() reads the settings as they were when the request began;
    // the address is built from the handle just saved.
    $ap_site = pg_ap_site();
    $ap_site['handle'] = $ap_row['handle'];

    if (!$ap_row['enabled'] || !pg_ap_handle_valid($ap_row['handle'])) {
        $liveform->add_warning(h(lang('Switch the Fediverse account on first.')));
    } else {
        $ap_lookup = pg_ap_lookup($ap_site);
        $ap_lookup_texts = array(
            'unreachable' => lang('The site could not be reached at its public address. A Fediverse server cannot find it either.'),
            'status'      => lang(array('string' => 'The public address answered {var:1}: a server rule or a proxy keeps the request from the site.', 'vars' => $ap_lookup['status'])),
            'json'        => lang('The public address answered with something other than WebFinger: a server rule sends the request elsewhere.'),
            'actor'       => lang('The answer names another account: the site\'s address in General settings is not the one the request reached.'),
        );

        if ($ap_lookup['verdict'] === '') {
            $liveform->add_notice(lang(array('string' => 'Found: {var:1} can be followed.', 'vars' => '@' . $ap_row['handle'] . '@' . $ap_site['host'])));
        } else {
            $liveform->add_warning(h($ap_lookup_texts[$ap_lookup['verdict']] . ' ' . $ap_lookup['url']));
        }
    }
}
