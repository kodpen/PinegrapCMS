<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Site Settings - the Fediverse cards: the site's account (switch, handle,
 * profile, "Look me up"), what is published, the followers and blocks, and
 * the delivery log with "Try again".
 *
 * Fills $pg_settings_cards, one grid column per card, in reading order. Which
 * cards belong here is said once, in includes/settings/registry.php; this file
 * holds them, and fediverse.save.php writes what they edit. The buttons that
 * do a job beside the save (look up, block, lift, try again) are named
 * submit buttons the save module asks for.
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

$ap_card = function ($id, $title, $body) {
    return '
    <div id="' . $id . '" class="pg-set-card">
        <div class="card">
            <div class="card-header bg-reset border-0 text-uppercase h5 fg-primary fw-bold">
                ' . $title . '
            </div>
            <div class="card-body">
                <div class="row gy-5">' . $body . '
                </div>
            </div>
        </div>
    </div>';
};

// Before the 2026.11.0 tables every card says so and the save writes
// nothing (fediverse.save.php).
if (!pg_ap_outbox_ready() || !pg_schema_has('ap_blocks') || !pg_schema_has('config_text')) {

    $pg_settings_cards[] = $ap_card('pgset-fediverse', lang('Fediverse account'), '
                    <div class="col-12">
                        <div class="alert theme-secondary mb-0 py-3 small">' . lang('These settings arrive with the 2026.11.0 upgrade, which this site has not run yet.') . '</div>
                    </div>');

    return;
}

$ap_stored = pg_ap_settings_read(json_encode(pg_ap_settings_stored()));
$ap_publish = $ap_stored['publish'];
$ap_host = HOSTNAME_SETTING;

// ── Fediverse account ──
$ap_type_options = '';

foreach (array('Organization' => lang('Organization'), 'Person' => lang('Person')) as $ap_value => $ap_label) {
    $ap_type_options .= '<option value="' . $ap_value . '"' . (($ap_stored['type'] === $ap_value) ? ' selected="selected"' : '') . '>' . h($ap_label) . '</option>';
}

// The avatar is shown to everyone, so only pictures every visitor may open
// (settings-public-image-pickers).
$ap_icon_options = '<option value="">' . lang('(the organization logo)') . '</option>';
$ap_icon_matched = ($ap_stored['icon'] === '');

foreach ((array) db_items("SELECT name FROM files WHERE (type IN ('" . implode("', '", pg_public_image_file_types()) . "')) AND " . pg_public_image_files_where() . " ORDER BY name ASC") as $ap_file) {
    $ap_selected = ($ap_file['name'] === $ap_stored['icon']);
    $ap_icon_matched = $ap_icon_matched || $ap_selected;
    $ap_icon_options .= '<option value="' . h($ap_file['name']) . '"' . ($ap_selected ? ' selected="selected"' : '') . '>' . h($ap_file['name']) . '</option>';
}

if (!$ap_icon_matched) {
    $ap_icon_options .= pg_public_image_stored_option($ap_stored['icon']);
}

$ap_on = $ap_stored['enabled'] && pg_ap_handle_valid($ap_stored['handle']);
$ap_address = $ap_on ? '@' . $ap_stored['handle'] . '@' . $ap_host : '';

$pg_settings_cards[] = $ap_card('pgset-fediverse', lang('Fediverse account'), '
                    <div class="col-12">
                        <div class="form-field">
                            <div class="switch"><input value="1"' . ($ap_stored['enabled'] ? ' checked="checked"' : '') . ' type="checkbox" id="ap_enabled" name="ap_enabled"/></div>
                            <label for="ap_enabled">' . lang('Let the site be followed from the Fediverse') . '</label>
                            <div class="form-text">' . lang('People on Mastodon and other Fediverse services can follow the site; what it publishes reaches them and their replies become comments.') . '</div>
                        </div>
                    </div>
                    <div class="pg-f-md">
                        <label for="ap_handle" class="form-label">' . lang('Handle') . '</label>
                        <label class="form-control form-adorn form-adorn-end">
                            <span class="form-adorn-text" aria-hidden="true">@' . h($ap_host) . '</span>
                            <input type="text" name="ap_handle" id="ap_handle" class="form-ghost" value="' . h($ap_stored['handle']) . '" maxlength="64" autocomplete="off" spellcheck="false" pattern="[A-Za-z0-9_]{1,64}"/>
                        </label>
                        <div class="form-text">' . lang('Letters, digits and underscores. It cannot be a panel user\'s name, and changing it later loses the followers.') . '</div>
                    </div>
                    <div class="pg-f-sm">
                        <label for="ap_type" class="form-label">' . lang('Account type') . '</label>
                        <select name="ap_type" id="ap_type" class="form-control">' . $ap_type_options . '</select>
                    </div>
                    <div class="pg-f-lg">
                        <label for="ap_name" class="form-label">' . lang('Profile name') . '</label>
                        <input type="text" name="ap_name" id="ap_name" class="form-control" value="' . h($ap_stored['name']) . '" maxlength="' . PG_AP_NAME_MAX . '" placeholder="' . h((string) ORGANIZATION_NAME) . '"/>
                        <div class="form-text">' . lang('Empty: the organization name.') . '</div>
                    </div>
                    <div class="col-12">
                        <label for="ap_summary" class="form-label">' . lang('Profile summary') . '</label>
                        <textarea name="ap_summary" id="ap_summary" class="form-control" rows="3" maxlength="' . PG_AP_SUMMARY_MAX . '">' . h($ap_stored['summary']) . '</textarea>
                        <div class="form-text">' . lang('Plain text. Empty: the site\'s meta description.') . '</div>
                    </div>
                    <div class="pg-f-lg">
                        <label for="ap_icon" class="form-label">' . lang('Avatar') . '</label>
                        <select name="ap_icon" id="ap_icon" class="form-control">' . $ap_icon_options . '</select>
                    </div>
                    <div class="col-12">' . ($ap_on ? '
                        <div class="form-text mb-3">' . lang(array('string' => 'Followed as {var:1}.', 'vars' => '<strong>' . h($ap_address) . '</strong>')) . '</div>
                        <button type="submit" name="ap_lookup" value="1" class="btn btn-sm btn-outline theme-secondary"><i class="bi bi-search me-1" aria-hidden="true"></i><span class="pg-btn-label">' . lang('Look me up') . '</span></button>
                        <div class="form-text">' . lang('Saves, then asks the site\'s own WebFinger through its public address, as a Fediverse server would.') . '</div>' : '
                        <div class="form-text">' . lang('While this is off the site has no Fediverse address.') . '</div>') . '
                    </div>');

// ── What is published ──
$ap_form_options = '';
$ap_form_ids = array();

foreach ((array) db_items("SELECT page_id, page_name FROM page WHERE page_type = 'custom form' ORDER BY page_name") as $ap_form) {
    $ap_form_ids[] = (int) $ap_form['page_id'];
    $ap_form_options .= '<option value="' . (int) $ap_form['page_id'] . '"' . (in_array((int) $ap_form['page_id'], array_map('intval', isset($ap_publish['forms']) ? (array) $ap_publish['forms'] : array()), true) ? ' selected="selected"' : '') . '>' . h($ap_form['page_name']) . '</option>';
}

$ap_folder_options = '';
$ap_open = array_flip(pg_ap_open_folders(array_column((array) db_items("SELECT folder_id FROM folder WHERE folder_archived = '0'"), 'folder_id')));
$ap_chosen_folders = array_map('intval', isset($ap_publish['folders']) ? (array) $ap_publish['folders'] : array());

foreach ((array) db_items("SELECT folder_id, folder_name FROM folder WHERE folder_archived = '0' ORDER BY folder_name") as $ap_folder) {
    $ap_folder_id = (int) $ap_folder['folder_id'];

    if (!pg_settings_folder_choice_ok($ap_folder_id) || (!isset($ap_open[$ap_folder_id]) && !in_array($ap_folder_id, $ap_chosen_folders, true))) {
        continue;
    }

    $ap_folder_options .= '<option value="' . $ap_folder_id . '"' . (in_array($ap_folder_id, $ap_chosen_folders, true) ? ' selected="selected"' : '') . '>' . h($ap_folder['folder_name']) . (isset($ap_open[$ap_folder_id]) ? '' : ' ' . lang('(not public: not published)')) . '</option>';
}

$pg_settings_cards[] = $ap_card('pgset-fediverse-publish', lang('What is published'), '
                    <div class="col-12">
                        <label for="ap_forms" class="form-label">' . lang('Records of these forms') . '</label>
                        <select multiple data-pg-combobox name="ap_forms[]" id="ap_forms" class="form-control" data-placeholder="' . h(lang('Choose the forms')) . '">' . $ap_form_options . '</select>
                        <div class="form-text">' . lang('A complete record goes out once a public page shows it to everyone.') . '</div>
                    </div>
                    <div class="col-12">
                        <label for="ap_folders" class="form-label">' . lang('Pages in these folders') . '</label>
                        <select multiple data-pg-combobox name="ap_folders[]" id="ap_folders" class="form-control" data-placeholder="' . h(lang('Choose the folders')) . '">' . $ap_folder_options . '</select>
                        <div class="form-text">' . lang('Standard pages directly in a public folder; subfolders are not included. What was published before the switch was turned on is not sent.') . '</div>
                    </div>
                    <div class="d-none">' . pg_fields_script() . '</div>');

// ── Followers and blocks ──
$ap_followers = pg_ap_followers_list(200);
$ap_follower_count = (int) db_value("SELECT COUNT(*) FROM ap_followers");
$ap_follower_rows = '';

foreach ($ap_followers as $ap_follower) {
    $ap_follower_rows .= '
                                <tr>
                                    <td>' . h(($ap_follower['name'] !== '') ? $ap_follower['name'] : $ap_follower['host']) . '</td>
                                    <td class="text-break"><a href="' . h($ap_follower['actor_id']) . '" target="_blank" rel="noopener noreferrer">' . h($ap_follower['actor_id']) . '</a></td>
                                    <td class="text-nowrap">' . ((int) $ap_follower['followed_at'] ? get_absolute_time(array('timestamp' => (int) $ap_follower['followed_at'])) : '') . '</td>
                                </tr>';
}

$ap_block_rows = '';

foreach (pg_ap_block_list() as $ap_block) {
    $ap_block_rows .= '
                                <tr>
                                    <td>' . (($ap_block['kind'] === 'domain') ? lang('Server') : lang('Account')) . '</td>
                                    <td class="text-break">' . h($ap_block['value']) . '</td>
                                    <td class="text-end"><button type="submit" name="ap_block_remove" value="' . (int) $ap_block['id'] . '" class="btn btn-sm btn-outline theme-secondary"><span class="pg-btn-label">' . lang('Lift') . '</span></button></td>
                                </tr>';
}

$pg_settings_cards[] = $ap_card('pgset-fediverse-followers', lang('Followers and blocks'), '
                    <div class="col-12">
                        <div class="form-text mb-3">' . lang(array('string' => 'Followers: {var:1}', 'vars' => $ap_follower_count)) . (($ap_follower_count > count($ap_followers)) ? ' ' . lang(array('string' => '(the newest {var:1} are listed)', 'vars' => count($ap_followers))) : '') . '</div>' . ($ap_follower_rows !== '' ? '
                        <div class="table-responsive">
                            <table class="table table-sm align-middle mb-0">
                                <thead><tr><th scope="col">' . lang('Name') . '</th><th scope="col">' . lang('Address') . '</th><th scope="col">' . lang('Since') . '</th></tr></thead>
                                <tbody>' . $ap_follower_rows . '
                                </tbody>
                            </table>
                        </div>' : '') . '
                    </div>
                    <div class="col-12">
                        <div class="form-label">' . lang('Blocked accounts and servers') . '</div>' . ($ap_block_rows !== '' ? '
                        <div class="table-responsive">
                            <table class="table table-sm align-middle mb-0">
                                <tbody>' . $ap_block_rows . '
                                </tbody>
                            </table>
                        </div>' : '
                        <div class="form-text">' . lang('Nothing is blocked.') . '</div>') . '
                    </div>
                    <div class="pg-f-sm">
                        <label for="ap_block_kind" class="form-label">' . lang('Block') . '</label>
                        <select name="ap_block_kind" id="ap_block_kind" class="form-control">
                            <option value="actor">' . lang('Account') . '</option>
                            <option value="domain">' . lang('Server') . '</option>
                        </select>
                    </div>
                    <div class="pg-f-lg">
                        <label for="ap_block_value" class="form-label">' . lang('Account address or server') . '</label>
                        <input type="text" name="ap_block_value" id="ap_block_value" class="form-control" value="" autocomplete="off" spellcheck="false" placeholder="https://mastodon.example/users/name"/>
                    </div>
                    <div class="col-12">
                        <button type="submit" name="ap_block_add" value="1" class="btn btn-sm btn-outline theme-danger" onclick="return confirm(' . h(json_encode(lang('Block it? Its followers, reactions and comments are removed and do not come back when the block is lifted.'))) . ');"><i class="bi bi-slash-circle me-1" aria-hidden="true"></i><span class="pg-btn-label">' . lang('Block') . '</span></button>
                    </div>');

// ── Deliveries ──
$ap_delivery_rows = '';
$ap_states = array('queued' => lang('Waiting'), 'sent' => lang('Sent'), 'failed' => lang('Failed'));

foreach (pg_ap_delivery_log(50) as $ap_delivery) {
    $ap_delivery_rows .= '
                                <tr>
                                    <td>' . h($ap_delivery['type']) . '</td>
                                    <td class="text-break">' . h($ap_delivery['host']) . '</td>
                                    <td>' . h(isset($ap_states[$ap_delivery['state']]) ? $ap_states[$ap_delivery['state']] : $ap_delivery['state']) . '</td>
                                    <td class="text-end">' . (int) $ap_delivery['attempts'] . '</td>
                                    <td class="text-nowrap">' . ($ap_delivery['created_at'] ? get_absolute_time(array('timestamp' => $ap_delivery['created_at'])) : '') . '</td>
                                    <td class="text-end">' . ($ap_delivery['retry'] ? '<button type="submit" name="ap_retry" value="' . $ap_delivery['id'] . '" class="btn btn-sm btn-outline theme-secondary"><span class="pg-btn-label">' . lang('Try again') . '</span></button>' : '') . '</td>
                                </tr>';
}

$pg_settings_cards[] = $ap_card('pgset-fediverse-deliveries', lang('Deliveries'), '
                    <div class="col-12">' . ($ap_delivery_rows !== '' ? '
                        <div class="table-responsive">
                            <table class="table table-sm align-middle mb-0">
                                <thead><tr><th scope="col">' . lang('Activity') . '</th><th scope="col">' . lang('Server') . '</th><th scope="col">' . lang('Status') . '</th><th scope="col" class="text-end">' . lang('Attempts') . '</th><th scope="col">' . lang('Queued') . '</th><th scope="col"><span class="visually-hidden">' . lang('Actions') . '</span></th></tr></thead>
                                <tbody>' . $ap_delivery_rows . '
                                </tbody>
                            </table>
                        </div>
                        <div class="form-text mt-3">' . lang('Failures first, then the newest 50. A delivery is tried six times over about a day; sent and failed rows are removed after 30 days.') . '</div>' : '
                        <div class="form-text">' . lang('Nothing has been sent yet.') . '</div>') . '
                    </div>');
