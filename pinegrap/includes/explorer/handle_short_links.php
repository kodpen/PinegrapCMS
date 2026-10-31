<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The file manager's short-link sub-actions: the listing, the wizard's
 * choices, create, rename, update, duplicate and delete. Each handler answers
 * through respond(), which ends the request. Called by pg_explorer_handle()
 * and loaded by view_folder_and_files_f.php.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

// Every destination column of a short link, the ones the posted type does not
// use set back to empty, so a link turned from a product into an address keeps
// no product id it no longer means.
function pg_explorer_short_link_destination_columns($read_columns)
{
    $short_link_columns = array(
        'product_group_id' => 0,
        'product_id' => 0,
        'url' => '',
        'file_id' => 0);

    foreach ($read_columns as $short_link_column => $short_link_value) {
        $short_link_columns[$short_link_column] = $short_link_value;
    }

    return $short_link_columns;
}

// ── Short links: the names that stand for something else ─────────
function pg_explorer_handle_short_links_list($type, $request, $user)
{
    if (isset($request['view_type']) && in_array($request['view_type'], array('grid', 'list'), true)) {
        $_SESSION['software']['explorer']['folder']['view_type'] = $request['view_type'];
    }

    $view_type = ($_SESSION['software']['explorer']['folder']['view_type'] ?? 'grid');

    if (in_array($view_type, array('grid', 'list'), true) == false) {
        $view_type = 'grid';
    }

    $short_link_files = array();

    foreach (pg_short_link_visible_rows($user, false) as $short_link_row) {
        $short_link_files[] = pg_short_link_item($short_link_row);
    }

    // A binned short link is in the Recycle Bin with the folders, the
    // pages and the files, so the button on this screen carries the
    // same number the folder screens carry and leads to the same place.
    $short_link_recycle = array(
        'available' => pg_recycle_ready(),
        'folder_id' => pg_recycle_folder_id(false),
        'inside' => false,
        'retention_days' => pg_recycle_ready() ? pg_recycle_retention_days() : 0,
        'count' => pg_recycle_ready()
            ? (int) db_value("SELECT COUNT(*) FROM recycle_bin WHERE item_type IN (" . pg_recycle_item_types_sql($user) . ")")
            : 0);

    respond(array(
        'status' => 'success',
        'request' => $type,
        'view_type' => $view_type,
        // The role travels with it because this view can be opened
        // straight from a link, with no folder listing before it to
        // have said who is looking -- and the Recycle Bin button is
        // drawn from the role.
        'capabilities' => array(
            'role' => (int) $user['role'],
            'show_product_images' => (bool) ECOMMERCE_SHOW_PRODUCT_IMAGES),
        'recycle' => $short_link_recycle,
        'folders' => array(),
        'files' => $short_link_files,
        'total' => count($short_link_files)));
}

// ── What the wizard offers, built from the classic screen's lists ─
//
// Products, groups, pages and files in the Recycle Bin are left
// out, as on the classic screens. Asked with item_id, for a link
// being changed, the one that link points at stays, marked as in the
// bin: without it the window opened with nothing chosen and Save
// asked for a product the link already had.
function pg_explorer_handle_short_link_options($type, $request, $user)
{
    $short_link_row = array_key_exists('item_id', $request) ? pg_short_link_by_id($user, $request['item_id']) : null;
    $short_link_keep_product = $short_link_row ? (int) $short_link_row['product_id'] : 0;
    $short_link_keep_group = $short_link_row ? (int) $short_link_row['product_group_id'] : 0;
    $short_link_keep_page = $short_link_row ? (int) $short_link_row['page_id'] : 0;
    $short_link_keep_file = $short_link_row ? (int) $short_link_row['file_id'] : 0;

    respond(array(
        'status' => 'success',
        'request' => $type,
        'options' => array(
            'page' => pg_short_link_options_list(get_page_options('', '', 'edit', false, $short_link_keep_page)),
            'catalog_page' => pg_short_link_options_list(get_page_options('', 'catalog', 'edit', false, $short_link_keep_page)),
            'catalog_detail_page' => pg_short_link_options_list(get_page_options('', 'catalog detail', 'edit', false, $short_link_keep_page)),
            'product_group' => pg_short_link_options_list(get_product_group_options(0, 0, 0, 0, array(), TRUE, 'array', TRUE, TRUE, FALSE, $short_link_keep_group)),
            'product' => pg_short_link_options_list(get_product_options(true, $short_link_keep_product)),
            'file' => pg_short_link_options_list(get_file_options(true, $short_link_keep_file))),
        // The lists hold a binned entry kept for this one link, so the
        // screen does not keep them for the next window.
        'kept' => (($short_link_keep_product > 0) && pg_catalog_product_binned($short_link_keep_product))
            || (($short_link_keep_group > 0) && pg_catalog_group_binned($short_link_keep_group))
            || (($short_link_keep_page > 0) && pg_recycle_page_binned($short_link_keep_page))
            || (($short_link_keep_file > 0) && pg_recycle_file_binned($short_link_keep_file)),
        // Once or until a time (2026.4.5, 5.112).
        'modes' => pg_short_link_modes_ready(),
        'durations' => array_map(function ($seconds, $label) {
            return array('v' => (string) $seconds, 't' => $label);
        }, array_keys(pg_short_link_durations()), array_values(pg_short_link_durations())),
        // How it answers (2026.11.0, 11.0.12), the choices the classic
        // screens offer; none before the upgrade, so the window draws
        // no field and the stored answer stays.
        'answers' => pg_short_link_redirects_ready()
            ? array_map(function ($label, $code) {
                return array('v' => (string) $code, 't' => $label);
            }, array_keys(pg_short_link_answer_options()), array_values(pg_short_link_answer_options()))
            : array(),
        // The name field takes a whole old address, cut down to its
        // path on save, so it is as long as an address may be, as on
        // the classic screens.
        'name_max' => pg_short_link_url_max(),
        'url_max' => pg_short_link_url_max()));
}

// ── Create ──────────────────────────────────────────────────────
//
// The wizard settles where the link goes; the name is settled after,
// in place, the way a new folder is.  So the row is written with a
// free placeholder name and the screen walks straight into renaming
// it.  Nothing is written until the wizard is finished: close it and
// this action is never called.
function pg_explorer_handle_short_link_create($type, $request, $user)
{
    $short_link_read = pg_short_link_read_request($request, $user);

    if (isset($short_link_read['error'])) {
        respond(array(
            'status' => 'error',
            'field' => $short_link_read['error'],
            'message' => $short_link_read['message']));
    }

    // How it may be opened: for good, once (a token for its address,
    // no name, spent by the first visit) or until a time chosen now.
    $short_link_mode = (pg_short_link_modes_ready() && is_scalar($request['link_mode'] ?? null)) ? (string) $request['link_mode'] : 'permanent';

    if (!in_array($short_link_mode, array('permanent', 'once', 'timed'), true)) {
        $short_link_mode = 'permanent';
    }

    $short_link_expires = 0;
    $short_link_token = '';

    if ($short_link_mode === 'timed') {

        $short_link_duration = (int) ($request['duration'] ?? 0);

        if (!isset(pg_short_link_durations()[$short_link_duration])) {
            respond(array('status' => 'error', 'field' => 'duration', 'message' => lang('Choose how long the link is valid.')));
        }

        $short_link_expires = time() + $short_link_duration;
    }

    if ($short_link_mode === 'once') {

        $short_link_token = pg_short_link_new_token();
        $short_link_name = '';

    } else {

        $short_link_name = pg_short_link_free_name(isset($request['name']) ? $request['name'] : '');

        if ($short_link_name == '') {
            respond(array('status' => 'error', 'message' => lang('The name that you entered is already in use, so please enter a different name.')));
        }
    }

    // How it answers (the window's choice, 0 when it sent none) and
    // whether its name is a pattern; an address that would send
    // visitors to the link itself or round a loop is refused here.
    $short_link_answer = pg_short_link_redirect_columns(array(
        'id' => 0,
        'name' => $short_link_name,
        'destination_type' => $short_link_read['type'],
        'url' => isset($short_link_read['columns']['url']) ? $short_link_read['columns']['url'] : ''),
        pg_short_link_answer_posted($request['redirect_code'] ?? null),
        0);

    if ($short_link_answer['problem'] !== null) {
        respond(array('status' => 'error', 'field' => $short_link_answer['problem']['field'], 'message' => $short_link_answer['problem']['message']));
    }

    $short_link_read['columns'] = array_merge($short_link_read['columns'], $short_link_answer['columns']);

    $short_link_fields = '';
    $short_link_values = '';

    if (pg_short_link_modes_ready()) {
        $short_link_read['columns']['link_mode'] = $short_link_mode;
        $short_link_read['columns']['expires_at'] = $short_link_expires;

        if ($short_link_token !== '') {
            $short_link_read['columns']['token_hash'] = hash('sha256', $short_link_token);
            $short_link_read['columns']['token_hint'] = substr($short_link_token, 0, 6);
        }
    }

    foreach ($short_link_read['columns'] as $short_link_column => $short_link_value) {
        $short_link_fields .= $short_link_column . ', ';
        $short_link_values .= "'" . e($short_link_value) . "', ";
    }

    db("INSERT INTO short_links (
            name,
            destination_type,
            page_id,
            " . $short_link_fields . "
            tracking_code,
            created_user_id,
            created_timestamp,
            last_modified_user_id,
            last_modified_timestamp)
        VALUES (
            '" . e($short_link_name) . "',
            '" . e($short_link_read['type']) . "',
            '" . e($short_link_read['page_id']) . "',
            " . $short_link_values . "
            '" . e($short_link_read['tracking_code']) . "',
            '" . e($user['id']) . "',
            UNIX_TIMESTAMP(),
            '" . e($user['id']) . "',
            UNIX_TIMESTAMP())");

    $short_link_id = (int) mysqli_insert_id(db::$con);

    log_activity(lang(array('string' => 'short link ({var:1}) was created', 'vars' => ($short_link_token !== '') ? substr($short_link_token, 0, 6) . '…' : $short_link_name)), $_SESSION['sessionusername']);

    respond(array(
        'status' => 'success',
        'request' => $type,
        'id' => $short_link_id,
        'name' => $short_link_name,
        // The whole address of a one-time link, this once: only its
        // hash is kept.
        'token_url' => ($short_link_token !== '') ? URL_SCHEME . HOSTNAME_SETTING . PATH . $short_link_token : '',
        // The same address as an inline SVG QR code, drawn here so the
        // token is never put into an image URL.
        'token_qr' => ($short_link_token !== '')
            ? pg_qr_svg(URL_SCHEME . HOSTNAME_SETTING . PATH . $short_link_token, 0, array('label' => lang('QR code for the link'), 'class' => 'd-block w-100'))
            : '',
        'message' => lang('The short link has been created.')));
}

// ── Rename ──────────────────────────────────────────────────────
function pg_explorer_handle_short_link_rename($type, $request, $user)
{
    $short_link_row = pg_short_link_by_id($user, isset($request['item_id']) ? $request['item_id'] : 0);

    if (!$short_link_row) {
        respond(array('status' => 'error', 'message' => lang('Access denied')));
    }

    if ((string) $short_link_row['name'] === '') {
        respond(array('status' => 'error', 'message' => lang('A link with a generated address has no name to change.')));
    }

    // What is wrong with the name, in the words the classic screens use.
    $short_link_name_read = pg_short_link_name_read(isset($request['name']) ? $request['name'] : '');

    if ($short_link_name_read['problem'] !== '') {
        respond(array('status' => 'error', 'message' => $short_link_name_read['problem']));
    }

    $short_link_name = $short_link_name_read['name'];

    if (check_name_availability(array(
            'name' => $short_link_name,
            'ignore_item_id' => $short_link_row['id'],
            'ignore_item_type' => 'short_link')) == false) {

        respond(array('status' => 'error', 'message' => lang('The name that you entered is already in use, so please enter a different name.')));
    }

    // A new name can be the address the link sends visitors to, or a
    // pattern now.
    $short_link_answer = pg_short_link_redirect_columns(array(
        'id' => (int) $short_link_row['id'],
        'name' => $short_link_name,
        'destination_type' => $short_link_row['destination_type'],
        'url' => $short_link_row['url']), null, (int) ($short_link_row['redirect_code'] ?? 0));

    if ($short_link_answer['problem'] !== null) {
        respond(array('status' => 'error', 'message' => $short_link_answer['problem']['message']));
    }

    $short_link_sql_answer = '';

    foreach ($short_link_answer['columns'] as $short_link_column => $short_link_value) {
        $short_link_sql_answer .= $short_link_column . " = '" . e($short_link_value) . "', ";
    }

    db("UPDATE short_links
        SET name = '" . e($short_link_name) . "',
            " . $short_link_sql_answer . "
            last_modified_user_id = '" . e($user['id']) . "',
            last_modified_timestamp = UNIX_TIMESTAMP()
        WHERE id = '" . e($short_link_row['id']) . "'");

    log_activity(lang(array('string' => 'short link ({var:1}) was renamed', 'vars' => $short_link_name)), $_SESSION['sessionusername']);

    respond(array(
        'status' => 'success',
        'request' => $type,
        'id' => (int) $short_link_row['id'],
        'name' => $short_link_name,
        'message' => lang('The short link has been updated.')));
}

// ── Change where an existing one points, and what it is called ───
//
// The same reading of the same fields the create uses, so the two can
// never disagree about what a valid destination is.
//
// The name is renamed in place in the list as well, the way a folder
// is, and that action stays. This one takes a name too because the
// window that says where a link goes is where somebody looks for what
// it is called; being able to rename in only one of the two places is
// the kind of thing an operator reads as the feature being broken.
function pg_explorer_handle_short_link_update($type, $request, $user)
{
    $short_link_row = pg_short_link_by_id($user, isset($request['item_id']) ? $request['item_id'] : 0);

    if (!$short_link_row) {
        respond(array('status' => 'error', 'message' => lang('Access denied')));
    }

    // Checked before anything is written: a name that cannot be used
    // must not leave the destination half saved behind it.
    $short_link_sql_name = '';

    // A link with a token for its address keeps it: it has no name.
    // A name posted as a list is no name given, so the name stays.
    if (array_key_exists('name', $request) && is_scalar($request['name']) && ((string) $short_link_row['name'] !== '')) {

        $short_link_name_read = pg_short_link_name_read($request['name']);

        if ($short_link_name_read['problem'] !== '') {
            respond(array(
                'status' => 'error',
                'field' => 'name',
                'message' => $short_link_name_read['problem']));
        }

        $short_link_name = $short_link_name_read['name'];

        // Compared as strings: "1e3" and "1000" are equal to ==.
        if ($short_link_name !== (string) $short_link_row['name']) {

            if (check_name_availability(array(
                    'name' => $short_link_name,
                    'ignore_item_id' => $short_link_row['id'],
                    'ignore_item_type' => 'short_link')) == false) {

                respond(array(
                    'status' => 'error',
                    'field' => 'name',
                    'message' => lang('The name that you entered is already in use, so please enter a different name.')));
            }

            $short_link_sql_name = "name = '" . e($short_link_name) . "',";
        }
    }

    $short_link_read = pg_short_link_read_request($request, $user, $short_link_row);

    if (isset($short_link_read['error'])) {
        respond(array(
            'status' => 'error',
            'field' => $short_link_read['error'],
            'message' => $short_link_read['message']));
    }

    // Every column that belongs to a destination is written on every
    // save, the ones this type does not use included. A link changed
    // from a product to an address would otherwise keep the product id
    // it no longer means, and the next screen to read the row would
    // believe it.
    $short_link_columns = pg_explorer_short_link_destination_columns($short_link_read['columns']);

    // The answer the window chose, or the stored one when it sent
    // none; the pattern flag follows the name, and the new address is
    // checked.
    $short_link_answer = pg_short_link_redirect_columns(array(
        'id' => (int) $short_link_row['id'],
        'name' => ($short_link_sql_name != '') ? $short_link_name : $short_link_row['name'],
        'destination_type' => $short_link_read['type'],
        'url' => $short_link_columns['url']),
        pg_short_link_answer_posted($request['redirect_code'] ?? null),
        (int) ($short_link_row['redirect_code'] ?? 0));

    if ($short_link_answer['problem'] !== null) {
        respond(array('status' => 'error', 'field' => $short_link_answer['problem']['field'], 'message' => $short_link_answer['problem']['message']));
    }

    $short_link_sql_answer = '';

    foreach ($short_link_answer['columns'] as $short_link_column => $short_link_value) {
        $short_link_sql_answer .= $short_link_column . " = '" . e($short_link_value) . "',";
    }

    db("UPDATE short_links
        SET " . $short_link_sql_name . $short_link_sql_answer . "
            destination_type = '" . e($short_link_read['type']) . "',
            page_id = '" . e($short_link_read['page_id']) . "',
            product_group_id = '" . e($short_link_columns['product_group_id']) . "',
            product_id = '" . e($short_link_columns['product_id']) . "',
            url = '" . e($short_link_columns['url']) . "',
            file_id = '" . e($short_link_columns['file_id']) . "',
            tracking_code = '" . e($short_link_read['tracking_code']) . "',
            last_modified_user_id = '" . e($user['id']) . "',
            last_modified_timestamp = UNIX_TIMESTAMP()
        WHERE id = '" . e($short_link_row['id']) . "'");

    // A timed link given a new time, counted from now.
    $short_link_renew = (int) ($request['renew'] ?? 0);

    if (($short_link_renew > 0) && pg_short_link_modes_ready() && isset(pg_short_link_durations()[$short_link_renew])
        && ((string) ($short_link_row['link_mode'] ?? '') === 'timed')) {

        db("UPDATE short_links SET expires_at = '" . (time() + $short_link_renew) . "' WHERE id = '" . e($short_link_row['id']) . "'");
    }

    // The log says what the link is called now, not what it was: the
    // name is the address, and the old one no longer answers.
    $short_link_logged = ($short_link_sql_name != '') ? $short_link_name : $short_link_row['name'];

    log_activity(lang(array('string' => 'short link ({var:1}) was updated', 'vars' => $short_link_logged)), $_SESSION['sessionusername']);

    respond(array(
        'status' => 'success',
        'request' => $type,
        'id' => (int) $short_link_row['id'],
        'name' => $short_link_logged,
        'message' => lang('The short link has been updated.')));
}

// ── Duplicate, which is also what paste does here ────────────────
//
// A short link belongs to no folder, so there is nowhere to paste one
// into.  Copy then paste therefore means the same thing duplicate
// does: a second link to the same place, under a free name.
function pg_explorer_handle_short_link_duplicate($type, $request, $user)
{
    $short_link_made = array();
    $short_link_failed = array();

    foreach ((isset($request['items']) && is_array($request['items'])) ? $request['items'] : array() as $short_link_entry) {

        $short_link_row = pg_short_link_by_id($user, isset($short_link_entry['id']) ? $short_link_entry['id'] : 0);

        if (!$short_link_row) {
            $short_link_failed[] = lang('Access denied');
            continue;
        }

        // A one-time link is one address for one visit: a second one
        // is made new, with an address of its own.
        if ((string) $short_link_row['name'] === '') {
            $short_link_failed[] = lang('A one-time link cannot be copied. Create a new one.');
            continue;
        }

        $short_link_name = pg_short_link_free_name($short_link_row['name']);

        if ($short_link_name == '') {
            $short_link_failed[] = lang('The name that you entered is already in use, so please enter a different name.');
            continue;
        }

        // A timed link is copied with its time, and every link with its
        // answer; the pattern flag follows the copy's name.
        $short_link_copy_modes = pg_short_link_modes_ready() ? ', link_mode, expires_at' : '';
        $short_link_copy_answer = pg_short_link_redirects_ready() ? ', redirect_code, is_pattern' : '';
        $short_link_copy_answer_values = pg_short_link_redirects_ready() ? ", redirect_code, '" . (pg_short_link_is_pattern($short_link_name) ? '1' : '0') . "'" : '';

        db("INSERT INTO short_links (
                name, destination_type, page_id, product_group_id, product_id,
                url, file_id, tracking_code" . $short_link_copy_modes . $short_link_copy_answer . ",
                created_user_id, created_timestamp,
                last_modified_user_id, last_modified_timestamp)
            SELECT
                '" . e($short_link_name) . "', destination_type, page_id, product_group_id, product_id,
                url, file_id, tracking_code" . $short_link_copy_modes . $short_link_copy_answer_values . ",
                '" . e($user['id']) . "', UNIX_TIMESTAMP(),
                '" . e($user['id']) . "', UNIX_TIMESTAMP()
            FROM short_links
            WHERE id = '" . e($short_link_row['id']) . "'");

        $short_link_made[] = $short_link_name;

        log_activity(lang(array('string' => 'short link ({var:1}) was created', 'vars' => $short_link_name)), $_SESSION['sessionusername']);
    }

    respond(array(
        'status' => (count($short_link_made) > 0) ? 'success' : 'error',
        'request' => $type,
        'names' => $short_link_made,
        'errors' => $short_link_failed,
        'message' => (count($short_link_made) > 0)
            ? lang('The short link has been created.')
            : (count($short_link_failed) > 0 ? $short_link_failed[0] : lang('Invalid request.'))));
}

// ── Delete ──────────────────────────────────────────────────────
//
// Into the Recycle Bin since 2026.4.4 (pg_short_link_to_bin()); the
// classic edit screen's Delete does the same.
function pg_explorer_handle_short_link_delete($type, $request, $user)
{
    $short_link_deleted = 0;
    $short_link_failed = array();

    foreach ((isset($request['items']) && is_array($request['items'])) ? $request['items'] : array() as $short_link_entry) {

        $short_link_row = pg_short_link_by_id($user, isset($short_link_entry['id']) ? $short_link_entry['id'] : 0);

        if (!$short_link_row) {
            $short_link_failed[] = lang('Access denied');
            continue;
        }

        // Before the upgrade there is nowhere to put it, so it goes
        // the way it always went.
        if (pg_short_link_recycle_ready() == false) {

            db("DELETE FROM short_links WHERE id = '" . e($short_link_row['id']) . "'");

            $short_link_deleted++;

            log_activity(lang(array('string' => 'short link ({var:1}) was deleted', 'vars' => $short_link_row['name'])), $_SESSION['sessionusername']);

            continue;
        }

        pg_short_link_to_bin($short_link_row['id'], $user);

        $short_link_deleted++;

        log_activity(lang(array('string' => 'The short link, {var:1}, was moved to the Recycle Bin.', 'vars' => $short_link_row['name'])), $_SESSION['sessionusername']);
    }

    $short_link_binned = pg_short_link_recycle_ready();

    respond(array(
        'status' => ($short_link_deleted > 0) ? 'success' : 'error',
        'request' => $type,
        'deleted' => $short_link_deleted,
        'errors' => $short_link_failed,
        'message' => ($short_link_deleted > 0)
            ? ($short_link_binned
                ? lang(array('string' => '{var:1} item(s) were moved to the Recycle Bin', 'vars' => $short_link_deleted))
                : lang(array('string' => '{var:1} item(s) were permanently deleted', 'vars' => $short_link_deleted)))
            : (count($short_link_failed) > 0 ? $short_link_failed[0] : lang('Invalid request.'))));
}
