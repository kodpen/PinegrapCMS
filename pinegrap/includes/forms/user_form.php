<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The user account parts shared by the create and the edit screens
 * (add_user.php, edit_user.php): the two "User Rights" cards around the
 * permission rows of includes/user_permissions.php, the folder rights a
 * posted form gives, and the access records (folders, and for a user role
 * common regions, menus, contact groups, calendars and ad regions) written
 * from it. The screens keep what differs: the account card, validation, the
 * user row itself, sessions, notifications and redirects.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

/**
 * The "User Rights" cards: the full-access card a privileged role shows and
 * the card that holds the permission rows.
 *
 * @param string $permission_rows  pg_user_permission_ui()['rows']
 * @return string
 */
function pg_user_form_rights_cards($permission_rows)
{
    return '
                    <div class="card mb-5 d-none" id="pg_permissions_everything">
                        <div class="card-header bg-reset border-0 d-flex justify-content-between align-items-center">
                            <h2 class="pg-title-section mb-0">' . lang('User Rights') . '</h2>
                        </div>
                        <div class="card-body">
                            ' . pg_user_permission_everything() . '
                        </div>
                    </div>

                    <div class="card mb-5" id="pg_permissions_block">
                        <div class="card-header bg-reset border-0 d-flex justify-content-between align-items-center">
                            <h2 class="pg-title-section mb-0">' . lang('User Rights') . '</h2>
                            <span class="small fg-secondary" data-pg-tally="' . h(lang(array('string' => '{var:1} of {var:2} areas on', 'vars' => array('{n}', '{m}')))) . '"></span>
                        </div>
                        <div class="card-body p-0">
                            <div class="pg-perms" id="pg_permissions" data-pg-empty="' . h(lang('No selection')) . '">' . $permission_rows . '
                            </div>
                        </div>
                    </div>';
}

/**
 * The wide edit screen layout (ui/edit-screen-wide-layout.md): the form
 * column, the status column after it and the sticky save bar. $sticky_status
 * pins the status column's box below the top bar; a box taller than the
 * window would hide its bottom, so a tall one scrolls with the page.
 *
 * @param string $form_html  The cards the operator fills
 * @param string $status_html  The cards that are read
 * @param string $buttons_html  The save bar's buttons
 * @param bool $sticky_status
 * @return string
 */
function pg_user_form_layout($form_html, $status_html, $buttons_html, $sticky_status = true)
{
    // 3.3rem: the fixed control panel header covers the top of the window.
    $box = $sticky_status ? '<div class="position-sticky" style="top:3.3rem;">' : '<div>';

    return '
                    <div class="row">
                        <div class="col-12 lg:col-8 2xl:col-9 order-1">' . $form_html . '
                        </div>
                        <div class="col-12 lg:col-4 2xl:col-3 order-2">
                            ' . $box . $status_html . '
                            </div>
                        </div>
                        <div class="col-12 order-3 position-sticky" style="bottom:0; z-index:3;">
                            <nav class="buttons navigation text-center mb-7" aria-label="' . h(lang('data edit buttons')) . '">
                                <div class="container">
                                    <div class="btn-group flex-wrap justify-content-center">' . $buttons_html . '
                                    </div>
                                </div>
                            </nav>
                        </div>
                    </div>';
}

/**
 * The edit screen's "Reset Password & Send Link" link. The confirmation's texts go
 * into the handler as one JSON object and the handler through h(), so an
 * apostrophe or a quote in any language's wording cannot end a JavaScript
 * string and leave the link doing nothing.
 *
 * @return string
 */
function pg_user_form_reset_password_button()
{
    $options = array(
        'title'       => lang('Reset Password & Send Link'),
        'message'     => lang('Are you sure you want to reset the user\'s password and email the user a link to set a new password?'),
        'confirmText' => lang('Reset Password & Send Link'),
        'cancelText'  => lang('Cancel'),
        'variant'     => 'warning',
    );

    $onclick = 'event.preventDefault(); pgConfirm(' . json_encode($options) . ').then(function(ok){if(ok) document.reset_password_form.submit();}); return false;';

    return '<a class="btn btn-sm btn-outline theme-warning rounded-pill px-5" href="#" onclick="' . h($onclick) . '"><i class="bi bi-key me-1"></i>' . h(lang('Reset Password & Send Link')) . '</a>';
}

/**
 * The role a posted form asks for: a whole number from 0 to 3. A value that
 * is not one (text, a decimal, an array) is no role, so it can never be read
 * as 0 (administrator) the way a cast or a lenient server would read it.
 * Pure.
 *
 * @param mixed $value  The posted value; null when the field was not sent
 * @param int|null $missing  The role a missing field stands for
 * @return int|null  0-3, or null for no valid role
 */
function pg_user_form_role($value, $missing = null)
{
    if ($value === null) {
        return $missing;
    }

    if (is_int($value)) {
        $value = (string) $value;
    }

    if (!is_string($value) || !ctype_digit($value)) {
        return null;
    }

    $role = (int) $value;

    return ($role <= 3) ? $role : null;
}

/**
 * The SQL value of a new user's user_contact: the posted contact id, or NULL
 * when there is none. An empty text would be stored as 0 by a lenient
 * server, and the Registered Users view would count the user. Pure.
 *
 * @param mixed $contact_id
 * @return string
 */
function pg_user_contact_sql($contact_id)
{
    if (is_int($contact_id)) {
        $contact_id = (string) $contact_id;
    }

    if (!is_string($contact_id) || !ctype_digit($contact_id) || ((int) $contact_id === 0)) {
        return 'NULL';
    }

    return "'" . (int) $contact_id . "'";
}

/**
 * The posted Reward Points as the integer user_reward_points holds (int
 * unsigned): blank is 0, anything that is not a whole number from 0 to
 * 4294967295 is null. A lenient server would clamp -5 to 0, cut "12,50" to
 * 12 and cap a larger number, and the screen would still say it saved. Pure.
 *
 * @param mixed $value  The posted value; null when the field was not sent
 * @return int|null
 */
function pg_user_form_reward_points($value)
{
    if ($value === null) {
        return 0;
    }

    if (is_int($value)) {
        $value = (string) $value;
    }

    if (!is_string($value)) {
        return null;
    }

    $value = trim($value);

    if ($value === '') {
        return 0;
    }

    if (!ctype_digit($value) || (strlen(ltrim($value, '0')) > 10)) {
        return null;
    }

    $points = (float) $value;

    return ($points <= 4294967295) ? (int) $value : null;
}

/**
 * Whether a user text fits its varchar(100) column (user_username,
 * user_badge_label). A lenient server cuts a longer value, which also gets
 * around the uniqueness check run on the whole value. Pure.
 *
 * @param mixed $value
 * @return bool
 */
function pg_user_form_text_fits($value)
{
    return mb_strlen((string) $value, 'UTF-8') <= 100;
}

/**
 * The posted fields that cannot be stored as sent, field => 'invalid' or
 * 'too_long': Reward Points that are not a whole number in the column's
 * range, a username or badge label longer than its column. A lenient server
 * would clamp or cut them and the screen would still say it saved. Pure.
 *
 * @param array $post
 * @return array
 */
function pg_user_form_field_problems($post)
{
    $problems = array();

    if (pg_user_form_reward_points($post['reward_points'] ?? null) === null) {
        $problems['reward_points'] = 'invalid';
    }

    foreach (array('username', 'badge_label') as $field) {
        if (isset($post[$field]) && is_scalar($post[$field]) && !pg_user_form_text_fits($post[$field])) {
            $problems[$field] = 'too_long';
        }
    }

    return $problems;
}

/**
 * The sentence for a problem of pg_user_form_field_problems(), escaped HTML.
 *
 * @param string $field
 * @param string $problem
 * @return string
 */
function pg_user_form_field_problem_message($field, $problem)
{
    $labels = array(
        'reward_points' => lang('Reward Points'),
        'username' => lang('Username'),
        'badge_label' => lang('Badge Label'),
    );

    $label = $labels[$field] ?? $field;

    if ($problem === 'too_long') {
        return h(lang(array('string' => '{var:1} is too long.', 'vars' => $label)));
    }

    return h(lang(array('string' => '{var:1} is invalid', 'vars' => $label))) . '.';
}

/**
 * Why a new account may not be linked to the posted contact record, or ''.
 * $lookup is what pg_user_contact_holder() read: 'found' (the contact
 * exists) and 'username' (the account already linked to it, null when
 * none). A contact id that is not a positive whole number links nothing
 * (pg_user_contact_sql()) and is no problem. Pure.
 *
 * @param mixed $contact_id
 * @param array $lookup
 * @return string  '', 'contact_missing' or 'contact_taken'
 */
function pg_user_form_contact_problem($contact_id, $lookup)
{
    if (pg_user_contact_sql($contact_id) === 'NULL') {
        return '';
    }

    if (empty($lookup['found'])) {
        return 'contact_missing';
    }

    if (isset($lookup['username']) && ($lookup['username'] !== null)) {
        return 'contact_taken';
    }

    return '';
}

/**
 * The sentence for a problem of pg_user_form_contact_problem(), escaped HTML.
 *
 * @param string $problem
 * @param string $username  The account the contact is linked to
 * @return string
 */
function pg_user_form_contact_problem_message($problem, $username = '')
{
    if ($problem === 'contact_taken') {
        return h(lang(array('string' => 'This contact record already belongs to the user "{var:1}".', 'vars' => (string) $username)));
    }

    return h(lang('The contact record was not found.'));
}

/**
 * The user form's markup with the values a refused save sent, so the screen
 * it goes back to shows what was typed: text fields take the sent value,
 * checkboxes and radios are checked only when they were sent, a select picks
 * the sent option. Hidden, password, file, button and disabled inputs keep
 * what the screen drew (the token, the account id, a role the editor may not
 * change). Fields not in $values keep their markup, except checkboxes and
 * radios, which a form does not send when unchecked. Pure.
 *
 * @param string $html
 * @param array $values  name => value as the form sent them
 * @return string
 */
function pg_user_form_refill($html, $values)
{
    $html = preg_replace_callback('/<input\s[^>]*>/is', function ($match) use ($values) {
        $tag = $match[0];

        if (!preg_match('/\sname="([^"]*)"/i', $tag, $name_match)) {
            return $tag;
        }

        $name = html_entity_decode($name_match[1], ENT_QUOTES, 'UTF-8');
        $type = preg_match('/\stype="([^"]*)"/i', $tag, $type_match) ? strtolower($type_match[1]) : 'text';

        if (in_array($type, array('hidden', 'password', 'file', 'submit', 'button', 'image', 'reset'), true)
            || preg_match('/\sdisabled(\s|=|\/?>)/i', $tag)) {
            return $tag;
        }

        $sent = $values[$name] ?? null;

        if (($type === 'checkbox') || ($type === 'radio')) {
            $value = preg_match('/\svalue="([^"]*)"/i', $tag, $value_match)
                ? html_entity_decode($value_match[1], ENT_QUOTES, 'UTF-8') : 'on';
            $checked = is_array($sent) ? in_array($value, array_map('strval', $sent), true) : (is_scalar($sent) && ((string) $sent === $value));
            $tag = preg_replace('/\schecked(="[^"]*")?(?=[\s\/>])/i', '', $tag);

            return $checked ? preg_replace('/\s*(\/?>)$/', ' checked="checked" $1', $tag) : $tag;
        }

        if (!is_scalar($sent)) {
            return $tag;
        }

        $value_html = ' value="' . h((string) $sent) . '"';

        if (preg_match('/\svalue="[^"]*"/i', $tag)) {
            return preg_replace('/\svalue="[^"]*"/i', str_replace(array('\\', '$'), array('\\\\', '\\$'), $value_html), $tag, 1);
        }

        return preg_replace('/\s*(\/?>)$/', $value_html . ' $1', $tag);
    }, $html);

    return preg_replace_callback('/(<select\s[^>]*>)(.*?)(<\/select>)/is', function ($match) use ($values) {
        if (!preg_match('/\sname="([^"]*)"/i', $match[1], $name_match) || preg_match('/\sdisabled(\s|=|>)/i', $match[1])) {
            return $match[0];
        }

        $sent = $values[html_entity_decode($name_match[1], ENT_QUOTES, 'UTF-8')] ?? null;

        if (!is_scalar($sent)) {
            return $match[0];
        }

        $options = preg_replace_callback('/<option\s[^>]*>/is', function ($option) use ($sent) {
            $tag = preg_replace('/\sselected(="[^"]*")?(?=[\s>])/i', '', $option[0]);
            $value = preg_match('/\svalue="([^"]*)"/i', $tag, $value_match)
                ? html_entity_decode($value_match[1], ENT_QUOTES, 'UTF-8') : null;

            return ($value === (string) $sent) ? substr($tag, 0, -1) . ' selected="selected">' : $tag;
        }, $match[2]);

        return $match[1] . $options . $match[3];
    }, $html);
}

/**
 * The values a refused save of $form left in the session
 * (liveform::add_fields_to_session()), name => value; empty when the screen
 * is opened afresh.
 *
 * @param string $form
 * @return array
 */
function pg_user_form_sent_values($form)
{
    $fields = $_SESSION['software']['liveforms'][$form][0] ?? array();

    if (!is_array($fields) || (($fields['__prefill'] ?? true) !== false)) {
        return array();
    }

    $values = array();

    foreach ($fields as $name => $field) {
        if (is_array($field) && array_key_exists('value', $field)) {
            $values[$name] = $field['value'];
        }
    }

    return $values;
}

/**
 * The private folders whose posted expiration date is a problem: 'invalid'
 * (a checked folder whose date is not a date, which would save no access)
 * and 'past' (a date already gone, so the access ends as it is given). Only
 * folders given view rights are read. Pure.
 *
 * @param array $post  The posted form
 * @param array $folders  folder id => folder name
 * @param string $today  'Y-m-d'
 * @return array  'invalid' => names, 'past' => names
 */
function pg_user_form_expiry_problems($post, $folders, $today)
{
    $problems = array('invalid' => array(), 'past' => array());

    foreach ((array) $folders as $folder_id => $folder_name) {
        if ((($post['view_' . $folder_id] ?? '') != 1) || (($post['edit_' . $folder_id] ?? '') == 1)) {
            continue;
        }

        if (trim((string) ($post['view_' . $folder_id . '_expiration_date'] ?? '')) === '') {
            continue;
        }

        $rights = pg_user_form_folder_rights($post, $folder_id);

        if ($rights['rights'] != 1) {
            $problems['invalid'][] = (string) $folder_name;
        } elseif ($rights['expiration_date'] < $today) {
            $problems['past'][] = (string) $folder_name;
        }
    }

    return $problems;
}

/**
 * Stops the save with an error when a checked private folder has an
 * expiration date that is not a date: saving it would silently give no
 * access. Called before anything is written.
 *
 * @param array $post  The posted form
 * @return array  The names of the folders whose date is already gone, for a
 *                warning after the save
 */
function pg_user_form_check_expiry_dates($post)
{
    $folders = array();

    foreach (db_items("SELECT folder_id, folder_name FROM folder") as $folder) {
        $folders[$folder['folder_id']] = $folder['folder_name'];
    }

    $problems = pg_user_form_expiry_problems($post, $folders, date('Y-m-d'));

    if ($problems['invalid']) {
        output_error(h(lang(array('string' => 'The expiration date of these private folders is not a valid date: {var:1}', 'vars' => array(implode(', ', $problems['invalid']))))) . ' <a href="javascript:history.go(-1);">' . lang('Go back') . '</a>.');
    }

    return $problems['past'];
}

/**
 * The warning that names the private folders whose expiration date has
 * already passed, '' when there are none.
 *
 * @param array $folder_names
 * @return string  Escaped HTML
 */
function pg_user_form_past_expiry_warning($folder_names)
{
    if (!$folder_names) {
        return '';
    }

    return h(lang(array('string' => 'The access to these private folders has already expired: {var:1}', 'vars' => array(implode(', ', $folder_names)))));
}

/**
 * The rights a posted form gives on one folder: edit (2), view (1), or none
 * (0). View may carry an expiration date; an invalid date gives no access.
 *
 * @param array $post  The posted form
 * @param int|string $folder_id
 * @return array  'rights' => 0, 1 or 2; 'expiration_date' => 'Y-m-d' or ''
 */
function pg_user_form_folder_rights($post, $folder_id)
{
    $expiration = '';

    // if the user was given edit rights to this folder, then set rights value to 2
    if (($post['edit_' . $folder_id] ?? '') == 1) {
        $rights = 2;

    // else if the user was given view rights to this folder, then deal with that.
    } elseif (($post['view_' . $folder_id] ?? '') == 1) {
        // Remove spaces from beginning and end of date.
        $expiration_date = trim($post['view_' . $folder_id . '_expiration_date'] ?? '');

        // If an expiration date was entered, then validate it.
        if ($expiration_date != '') {
            // Convert date to storage format.
            $expiration_date = prepare_form_data_for_input($expiration_date, 'date');

            // Split date into parts.
            $expiration_date_parts = explode('-', $expiration_date);
            $year = $expiration_date_parts[0];
            $month = $expiration_date_parts[1] ?? '';
            $day = $expiration_date_parts[2] ?? '';

            // If the expiration date is valid, then give user access and keep the expiration date.
            if ((is_numeric($month) == true) && (is_numeric($day) == true) && (is_numeric($year) == true) && (checkdate($month, $day, $year) == true)) {
                $rights = 1;
                $expiration = $expiration_date;

            // Otherwise the expiration date is not valid, so do not give user access.
            } else {
                $rights = 0;
            }

        // Otherwise an expiration date was not entered, so just give user access.
        } else {
            $rights = 1;
        }

    // else the user was was given no rights to this folder, so set rights value to 0
    } else {
        $rights = 0;
    }

    return array('rights' => $rights, 'expiration_date' => $expiration);
}


/**
 * The user columns of the optional modules that the edit save writes, as
 * column => posted field, for the rows the edit screen drew. A row that was
 * not drawn posts nothing; writing its column anyway would store '' or 0 and
 * take the right away while the module is off. $drawn keys: password_hint,
 * forms, calendars, ecommerce, offline_payment, erp (true when drawn). The
 * offline payment switch sits in the Commerce panel, so it needs both.
 */
function pg_user_form_module_columns($drawn)
{
    $on = function ($key) use ($drawn) {
        return !empty($drawn[$key]);
    };

    $columns = array();

    if ($on('password_hint')) {
        $columns['user_password_hint'] = 'password_hint';
    }

    if ($on('forms')) {
        $columns['user_manage_forms'] = 'manage_forms';
    }

    if ($on('calendars')) {
        $columns['user_manage_calendars'] = 'manage_calendars';
        $columns['user_publish_calendar_events'] = 'publish_calendar_events';
    }

    if ($on('ecommerce')) {
        $columns['user_manage_ecommerce'] = 'manage_ecommerce';
        $columns['user_view_card_data'] = 'view_card_data';
        $columns['manage_ecommerce_reports'] = 'manage_ecommerce_reports';

        if ($on('offline_payment')) {
            $columns['user_set_offline_payment'] = 'set_offline_payment';
        }
    }

    if ($on('erp')) {
        $columns['manage_erp'] = 'manage_erp';
        $columns['manage_erp_cash'] = 'manage_erp_cash';
        $columns['manage_erp_settings'] = 'manage_erp_settings';
    }

    return $columns;
}

/**
 * Inserts the items of one table the posted form selected for a user, from
 * the "<prefix><id>" boxes of its permission panel.
 *
 * @param string $select_sql  SELECT of the item ids, one `id` column
 * @param string $prefix  The posted field name before the id
 * @param string $xref_table
 * @param string $xref_column
 * @param int|string $user_id
 * @param array $post
 */
function pg_user_form_save_xref($select_sql, $prefix, $xref_table, $xref_column, $user_id, $post)
{
    $result = mysqli_query(db::$con, $select_sql) or output_error('Query failed.');

    $items = array();

    while ($row = mysqli_fetch_assoc($result)) {
        $items[] = $row;
    }

    // loop through all items and input them if they were selected
    foreach ($items as $item) {
        if (($post[$prefix . $item['id']] ?? '') == 1) {
            $query =
                "INSERT INTO " . $xref_table . " (
                    user_id,
                    " . $xref_column . ")
                VALUES (
                    ?,
                    ?)";
            db_q($query, array((string) $user_id, (string) $item['id'])) or output_error('Query failed.');
        }
    }
}

/**
 * Writes the access records a posted user form gives: the rights on every
 * folder and, for a user role (3), the common regions, shared components,
 * menus, contact groups (with manage contacts or e-mails), calendars (with
 * manage calendars) and ad regions (when ads are on).
 *
 * On an existing user ($replace) the old records are deleted first; a common
 * region made a shared component is not on the screen, so its grant stays
 * for the day the component is deleted and the region draws itself again.
 *
 * @param int|string $user_id
 * @param int $role  The role the user is saved with
 * @param array $post  The posted form
 * @param bool $replace  The user existed before
 */
function pg_user_form_save_access($user_id, $role, $post, $replace)
{
    if ($replace) {
        // Delete current access control records in order to add new ones.
        db_q("DELETE FROM aclfolder WHERE aclfolder_user = ?", array((string) $user_id)) or output_error('Query failed.');
    }

    // find all folders to write the access control list
    $result = mysqli_query(db::$con, "SELECT folder_id FROM folder") or output_error('Query failed');

    while ($row = mysqli_fetch_array($result)) {
        $folder_rights = pg_user_form_folder_rights($post, $row['folder_id']);

        db_q("INSERT INTO aclfolder (aclfolder_user, aclfolder_folder, aclfolder_rights, expiration_date) VALUES (?, ?, ?, ?)", array((string) $user_id, (string) $row['folder_id'], (string) $folder_rights['rights'], (string) $folder_rights['expiration_date'])) or output_error('Query failed.');
    }

    // Only a user role carries access to single items.
    if ($role != 3) {
        return;
    }

    $sql_regions = "SELECT cregion_id AS id FROM cregion";

    if ($replace) {
        db_q("DELETE FROM users_common_regions_xref WHERE user_id = ? AND " . pg_common_region_not_converted_sql('common_region_id'), array((string) $user_id)) or output_error('Query failed.');

        $sql_regions .= " WHERE " . pg_common_region_not_converted_sql();
    }

    pg_user_form_save_xref($sql_regions, 'common_region_', 'users_common_regions_xref', 'common_region_id', $user_id, $post);

    // the shared components opened to content editors the user was given
    pg_save_shared_component_grants($user_id, $post);

    if ($replace) {
        db_q("DELETE FROM users_menus_xref WHERE user_id = ?", array((string) $user_id)) or output_error('Query failed.');
    }

    pg_user_form_save_xref("SELECT id FROM menus", 'menu_', 'users_menus_xref', 'menu_id', $user_id, $post);

    if ($replace) {
        db_q("DELETE FROM users_contact_groups_xref WHERE user_id = ?", array((string) $user_id)) or output_error('Query failed.');
    }

    // if manage contacts or manage e-mails was checked, check to see which contact groups the user needs to be given access to
    if ((($post['manage_contacts'] ?? '') == 'yes') || (($post['manage_emails'] ?? '') == 'yes')) {
        pg_user_form_save_xref("SELECT id FROM contact_groups", 'contact_group_', 'users_contact_groups_xref', 'contact_group_id', $user_id, $post);
    }

    // The calendar list is drawn only while Calendars is on; while it is
    // off the stored grants stay for when it is switched back on.
    if (CALENDARS == true) {
        if ($replace) {
            db_q("DELETE FROM users_calendars_xref WHERE user_id = ?", array((string) $user_id)) or output_error('Query failed.');
        }

        // if manage calendars was checked, check to see which calendars the user needs to be given access to
        if (($post['manage_calendars'] ?? '') == 'yes') {
            pg_user_form_save_xref("SELECT id FROM calendars", 'calendar_', 'users_calendars_xref', 'calendar_id', $user_id, $post);
        }
    }

    // If ads are enabled, then store access.
    if (ADS === true) {
        if ($replace) {
            db_q("DELETE FROM users_ad_regions_xref WHERE user_id = ?", array((string) $user_id)) or output_error('Query failed.');
        }

        pg_user_form_save_xref("SELECT id FROM ad_regions", 'ad_region_', 'users_ad_regions_xref', 'ad_region_id', $user_id, $post);
    }
}

/**
 * Whether the edit screen may switch an account off, the same rule that keeps
 * the last administrator from being demoted or deleted: nobody disables his/
 * her own account, and the last enabled administrator (role 0, not disabled)
 * stays enabled, or the site is left with nobody who can reach the
 * admin-only screens. Switching an account back on is always allowed.
 * Asked only when pg_user_has_disabled_at(): it reads the column.
 *
 * @param int $user_id   the account being edited
 * @param int $editor_id the signed-in editor
 * @return bool
 */
function pg_user_form_may_disable($user_id, $editor_id)
{
    $user_id = (int) $user_id;

    if (($user_id <= 0) || ($user_id === (int) $editor_id)) {
        return false;
    }

    $target = db_item("SELECT user_role, user_disabled_at FROM user WHERE user_id = '" . $user_id . "'");

    if (!is_array($target)) {
        return false;
    }

    if (((int) $target['user_role'] !== 0) || pg_user_is_disabled($target)) {
        return true;
    }

    return (bool) db_value("SELECT user_id FROM user
        WHERE (user_role = '0') AND (user_id != '" . $user_id . "')" . pg_user_enabled_sql() . " LIMIT 1");
}

/**
 * The attributes that keep a browser's autofill and password managers out
 * of a user's Username and User Email fields (Create User, Edit User). With
 * no password field on the form, a manager takes the Username for a sign-in
 * name and writes the saved one ("admin") into it: the save is refused
 * ("already in use") or, with a free name, would rename the account.
 * "off" alone is ignored by Chrome on a field it is sure about; a token it
 * does not know is respected. The vendor opt-outs are each manager's own and
 * ignored by the others. data-pg-own-value (assets/js/backend.src.js) takes
 * back a value nobody typed, for the managers that heed none of it; the
 * e-mail field goes without it, since its input mask writes the typed
 * characters itself.
 *
 * @param bool $own_value whether the field also keeps only typed values
 * @return string HTML attributes, each with a leading space
 */
function pg_user_form_no_autofill($own_value = false)
{
    return ' autocomplete="pg-no-autofill" data-lpignore="true" data-1p-ignore data-bwignore data-form-type="other"'
        . ($own_value ? ' data-pg-own-value' : '');
}
