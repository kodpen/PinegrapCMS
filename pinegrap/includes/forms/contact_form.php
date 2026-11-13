<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The contact form, shared by the create and the edit screens
 * (add_contact.php, edit_contact.php): the name and picture row, the work,
 * home, membership, notes, affiliate and contact group cards, the checks of
 * a posted contact, the column values it is saved with and the writes that
 * follow a save (same-address opt-in sync, group memberships, the affiliate
 * group offer key code). The screens keep what differs: the subscriber row,
 * the edit screen's toolbar, picture, connected account, Parasut field and
 * delete, the create screen's role-3 group check, the audit lines and the
 * activity log lines.
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
 * The plain text columns a posted contact is saved with as they are posted.
 *
 * @return array
 */
function pg_contact_form_text_columns()
{
    return array(
        'salutation', 'first_name', 'last_name', 'suffix', 'nickname',
        'company', 'title', 'department', 'office_location',
        'business_address_1', 'business_address_2', 'business_city', 'business_state',
        'business_country', 'business_zip_code', 'business_phone', 'business_fax',
        'home_address_1', 'home_address_2', 'home_city', 'home_state',
        'home_country', 'home_zip_code', 'home_phone', 'home_fax', 'mobile_phone',
        'email_address', 'website', 'lead_source', 'opt_in', 'description', 'member_id',
    );
}

/**
 * A posted value as a string; a missing field or one posted as an array is ''.
 *
 * @param array $post
 * @param string $name
 * @return string
 */
function pg_contact_form_posted($post, $name)
{
    return (isset($post[$name]) && is_scalar($post[$name])) ? (string) $post[$name] : '';
}

/**
 * The column values both screens save from a posted contact: the text
 * columns, the expiration date, the tax fields (trimmed), the picture (a
 * file id or an image URL, '' when none) and the user who saved it.
 *
 * @param array $post
 * @param int|string $user_id
 * @return array  column => string value
 */
function pg_contact_form_columns($post, $user_id)
{
    $columns = array();

    foreach (pg_contact_form_text_columns() as $name) {
        $columns[$name] = pg_contact_form_posted($post, $name);
    }

    // An empty box is a lifetime membership (0000-00-00); a date that is no
    // date is refused before the save (pg_contact_form_refusal()).
    $expiration = pg_date_input_iso(pg_contact_form_posted($post, 'expiration_date'));
    $columns['expiration_date'] = ($expiration === '') ? '0000-00-00' : $expiration;
    $columns['tax_number'] = trim(pg_contact_form_posted($post, 'tax_number'));
    $columns['tax_office'] = trim(pg_contact_form_posted($post, 'tax_office'));
    $columns['file_id'] = pg_contact_form_posted($post, 'file_id');
    $columns['image'] = pg_contact_form_posted($post, 'image_url');
    $columns['user'] = (string) $user_id;

    return $columns;
}

/**
 * Why a posted contact cannot be saved, '' when it can: a membership
 * expiration date that is no date (dotted, impossible or with a two-digit
 * year was stored as a lifetime membership or the year 0027), a negative
 * commission rate (its minus was dropped, so -7,5 was saved as 7.50) or one
 * above 100 (the column holds up to 999.99, which would credit the affiliate
 * more than the order is worth).
 *
 * @param array $post
 * @return string
 */
function pg_contact_form_refusal($post)
{
    $expiration = trim(pg_contact_form_posted($post, 'expiration_date'));

    if (($expiration !== '') && (pg_date_input_iso($expiration) === '')) {
        return lang('The membership expiration date is not valid.');
    }

    $rate = trim(pg_contact_form_posted($post, 'affiliate_commission_rate'));

    if (strpos($rate, '-') === 0) {
        return lang('The commission rate cannot be negative.');
    }

    if (($rate !== '') && ((float) pg_parse_commission_rate($rate) > 100)) {
        return lang('The commission rate cannot be more than 100.');
    }

    return '';
}

/**
 * Whether a user with the user role (3) keeps the contact in at least one
 * group they may edit; the other roles always do. Create asked it, edit did
 * not: a role 3 user could uncheck every group and lose the contact.
 *
 * @param array    $user           the viewer (role)
 * @param array    $contact_groups list of array('id')
 * @param array    $post
 * @param callable $may_edit       group id => bool (validate_contact_group_access() for $user)
 * @return bool
 */
function pg_contact_form_keeps_a_group($user, $contact_groups, $post, $may_edit)
{
    if ((int) ($user['role'] ?? 0) != 3) {
        return true;
    }

    foreach ($contact_groups as $contact_group) {
        if ((pg_contact_form_posted($post, 'contact_group_' . $contact_group['id']) == '1') && call_user_func($may_edit, $contact_group['id'])) {
            return true;
        }
    }

    return false;
}

/**
 * The affiliate column values of a posted contact; the code is the one
 * pg_contact_form_affiliate_code() settled on.
 *
 * @param array $post
 * @param string $affiliate_code
 * @return array  column => string value
 */
function pg_contact_form_affiliate_columns($post, $affiliate_code)
{
    return array(
        'affiliate_approved' => pg_contact_form_posted($post, 'affiliate_approved'),
        'affiliate_name' => pg_contact_form_posted($post, 'affiliate_name'),
        'affiliate_code' => (string) $affiliate_code,
        'affiliate_commission_rate' => pg_contact_form_commission_rate_posted($post),
    );
}

/**
 * The posted commission rate as the decimal(5,2) column stores it: "8,25"
 * and "8.25" are both 8.25 (pg_parse_commission_rate(), as the importer and
 * Site Settings read it), so a comma is not dropped into 825 or cut to 8.
 * A blank box stays blank: the contact uses the default rate.
 *
 * @param array $post
 * @return string
 */
function pg_contact_form_commission_rate_posted($post)
{
    $raw = trim(pg_contact_form_posted($post, 'affiliate_commission_rate'));

    return ($raw === '') ? '' : pg_parse_commission_rate($raw);
}

/**
 * A value of the record the form is filled from, escaped for an attribute;
 * '' on the create screen, which passes no record.
 *
 * @param array $values
 * @param string $name
 * @return string
 */
function pg_contact_form_value($values, $name)
{
    return h((string) ($values[$name] ?? ''));
}

/**
 * The commission rate the affiliate card shows: a stored 0 is shown blank,
 * as "leave blank for default".
 *
 * @param mixed $rate
 * @return string
 */
function pg_contact_form_commission_rate_value($rate)
{
    if (($rate === null) || ($rate === '') || ((float) $rate == 0)) {
        return '';
    }

    return (string) $rate;
}

/**
 * The name card of the form column.
 *
 * @param array $values The stored contact (raw values), array() for a new one
 * @param bool $is_new
 * @return string
 */
function pg_contact_form_name_card($values, $is_new)
{
    $output_fields = '';

    $fields = array(
        'salutation' => lang('Salutation'),
        'first_name' => lang('First Name'),
        'last_name' => lang('Last Name'),
        'suffix' => lang('Suffix'),
        'nickname' => lang('Nickname'),
    );

    foreach ($fields as $name => $label) {
        $output_fields .= '
                                        <div class="col-12 md:col-6 xl:col-4 my-3">
                                            <label for="' . $name . '" class="form-label">' . $label . '</label>
                                            <input value="' . pg_contact_form_value($values, $name) . '" type="text" name="' . $name . '" id="' . $name . '" class="form-control" size="30" maxlength="50" />
                                        </div>';
    }

    return '
                            <div class="card mb-7">
                                <h2 class="card-header bg-reset border-0 pg-title-section">
                                    ' . ($is_new ? lang('New Contact\'s Name') : lang('Contact\'s Name')) . '
                                </h2>
                                <div class="card-body">
                                    <div class="row">' . $output_fields . '
                                    </div>
                                </div>
                            </div>';
}

/**
 * The picture card of the status column.
 *
 * @param bool $is_new
 * @param string $image_html The current picture's markup, '' for none
 * @return string
 */
function pg_contact_form_picture_card($is_new, $image_html)
{
    // Both screens post the picked file under file_id (and an Unsplash
    // image under image_url), the names the save reads: under the picker's
    // own name, selected_images[], the create screen dropped the picture.
    $picker = 'software_image_picker({initialize:true,SingleImage:true,file_input_name:\'file_id\'});';

    // Change Image opens the picture picker (software_image_picker() in
    // backend.src.js through pgEditorPicker); its scripts are printed once.
    return pg_editor_bridge_script() . '
                                <div class="card mb-7 display-on-hover overflow-hidden position-relative">
                                    <h2 class="card-header bg-reset border-0 pg-title-section">
                                        ' . lang('Picture') . '
                                    </h2>
                                    <div class="card-body py-0">
                                        <div id="software_image_picker_container" ondblclick="' . $picker . '" class="user-select-none sortable-list img-list bg-1 rounded-4 p-3 mx-0 mt-0 row g-7">' . $image_html . '</div>
                                    </div>
                                    <div class="card-footer border-0 bg-transparent">
                                        <button type="button" class="btn"  onclick="' . $picker . '"><span class="material-icons me-3" aria-hidden="true">image_search</span>' . lang('Change Image') . '</button>
                                    </div>
                                </div>';
}

/**
 * The edit screen layout (ui/edit-screen-wide-layout.md): the form column
 * with the cards the operator fills, the status column whose box sticks
 * below the 50px top bar, and the sticky save bar. Below lg the status
 * column follows the form column, so the name fields stay first on a phone.
 *
 * @param string $form_html    The form column's cards
 * @param string $status_html  The status column's cards
 * @param string $buttons_html The save bar's buttons
 * @return string
 */
function pg_contact_form_layout($form_html, $status_html, $buttons_html)
{
    return '
                    <div class="row">
                        <div class="col-12 lg:col-8 2xl:col-9 order-1">' . $form_html . '
                        </div>
                        <div class="col-12 lg:col-4 2xl:col-3 order-2">
                            <!-- 3.3rem: the fixed control panel header covers the top of the window. -->
                            <div class="position-sticky" style="top:3.3rem;">' . $status_html . '
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
 * The attributes that open the right phone keyboard on a text box: 'tel'
 * opens the telephone keypad, 'url' the web address keyboard without
 * capitalising or spellchecking. The box stays type="text" because phone
 * values carry spaces, + and parentheses and a bare www.example.com is a
 * valid website.
 *
 * @param string $keyboard 'tel', 'url' or '' for the default keyboard
 * @return string
 */
function pg_contact_form_keyboard_attributes($keyboard)
{
    if ($keyboard === 'tel') {
        return ' inputmode="tel"';
    }

    if ($keyboard === 'url') {
        return ' inputmode="url" spellcheck="false" autocapitalize="off"';
    }

    return '';
}

/**
 * One text field of the work and home cards.
 *
 * @param array $values
 * @param string $name
 * @param string $label
 * @param int $maxlength
 * @param string $keyboard Phone keyboard (pg_contact_form_keyboard_attributes())
 * @return string
 */
function pg_contact_form_text_field($values, $name, $label, $maxlength, $keyboard = '')
{
    return '
                                        <div class="col-12 md:col-6 xl:col-4 my-3">
                                            <label for="' . $name . '" class="form-label">' . $label . '</label>
                                            <input value="' . pg_contact_form_value($values, $name) . '" type="text" name="' . $name . '" id="' . $name . '" class="form-control" maxlength="' . (int) $maxlength . '"' . pg_contact_form_keyboard_attributes($keyboard) . ' />
                                        </div>';
}

/**
 * The lead source field: a select of the referral sources with an empty
 * choice; a stored value outside the list stays as its own selected choice
 * (pg_contact_lead_source_options()).
 *
 * @param array $values  The stored contact (raw values), array() for a new one
 * @param array $sources Rows of name and code (pg_referral_sources())
 * @return string
 */
function pg_contact_form_lead_source_field($values, $sources)
{
    $current = (string) ($values['lead_source'] ?? '');
    $options = '';
    $hint = '';

    foreach (pg_contact_lead_source_options($sources, $current) as $option) {
        $options .= '<option value="' . h($option['value']) . '"' . (($option['value'] === $current) ? ' selected="selected"' : '') . '>' . h($option['label']) . '</option>';

        if ($option['kept']) {
            $hint = '<div class="form-text">' . lang('The stored value is not one of the referral sources; it is kept until another one is chosen.') . '</div>';
        }
    }

    return '<div class="col-12 my-5">
                                            <label for="lead_source" class="form-label">' . lang('Lead Source') . '</label>
                                            <select name="lead_source" id="lead_source" class="form-control">' . $options . '</select>' . $hint . '
                                        </div>';
}

/**
 * The work, home, membership, notes, affiliate and contact group cards.
 *
 * @param array $values      The stored contact (raw values), array() for a new one
 * @param bool $is_new
 * @param string $work_extra Markup added at the end of the work card's address row
 * @param string $groups_html The contact group cells (pg_contact_form_group_cells())
 * @return string
 */
function pg_contact_form_detail_cards($values, $is_new, $work_extra, $groups_html)
{
    $output_work =
        pg_contact_form_text_field($values, 'company', lang('Company'), 50) .
        pg_contact_form_text_field($values, 'title', lang('Job Title'), 50) .
        pg_contact_form_text_field($values, 'department', lang('Department'), 50) .
        pg_contact_form_text_field($values, 'office_location', lang('Office Location'), 50) .
        pg_contact_form_text_field($values, 'website', lang('Website'), 255, 'url');

    $output_business =
        pg_contact_form_text_field($values, 'business_address_1', lang('Business Address') . ' 1', 50) .
        pg_contact_form_text_field($values, 'business_address_2', lang('Business Address') . ' 2', 50) .
        pg_contact_form_text_field($values, 'business_city', lang('Business City'), 50) .
        pg_contact_form_text_field($values, 'business_state', lang('Business State'), 50) .
        pg_contact_form_text_field($values, 'business_country', lang('Business Country'), 50) .
        pg_contact_form_text_field($values, 'business_zip_code', lang('Business Zip Code'), 10) .
        pg_contact_form_text_field($values, 'business_phone', lang('Business Phone'), 50, 'tel') .
        pg_contact_form_text_field($values, 'business_fax', lang('Business Fax'), 50, 'tel');

    $output_home =
        pg_contact_form_text_field($values, 'home_address_1', lang('Home Address') . ' 1', 50) .
        pg_contact_form_text_field($values, 'home_address_2', lang('Home Address') . ' 2', 50) .
        pg_contact_form_text_field($values, 'home_city', lang('Home City'), 50) .
        pg_contact_form_text_field($values, 'home_state', lang('Home State'), 50) .
        pg_contact_form_text_field($values, 'home_country', lang('Home Country'), 50) .
        pg_contact_form_text_field($values, 'home_zip_code', lang('Home Zip Code'), 10) .
        pg_contact_form_text_field($values, 'home_phone', lang('Home Phone'), 50, 'tel') .
        pg_contact_form_text_field($values, 'home_fax', lang('Home Fax'), 50, 'tel') .
        pg_contact_form_text_field($values, 'mobile_phone', lang('Mobile Phone'), 50, 'tel');

    return '
                            <div class="card mb-7">
                                <h2 class="card-header bg-reset border-0 pg-title-section">
                                    ' . ($is_new ? lang('New Contact\'s Work Information') : lang('Contact\'s Work Information')) . '
                                </h2>
                                <div class="card-body" >
                                    <div class="row">' . $output_work . '
                                    </div>
                                    <div class="row">' . $output_business . '
                                        <div class="col-12 md:col-6 xl:col-4 my-3">
                                            <label for="tax_number" class="form-label">VKN / TCKN</label>
                                            <input value="' . pg_contact_form_value($values, 'tax_number') . '" type="text" name="tax_number" id="tax_number" class="form-control" maxlength="11" inputmode="numeric" />
                                            <div class="form-text">' . lang('11-digit TCKN for individuals, 10-digit VKN for companies.') . '</div>
                                        </div>
                                        <div class="col-12 md:col-6 xl:col-4 my-3">
                                            <label for="tax_office" class="form-label">' . lang('Tax Office') . '</label>
                                            <input value="' . pg_contact_form_value($values, 'tax_office') . '" type="text" name="tax_office" id="tax_office" class="form-control" maxlength="100" />
                                        </div>
                                        ' . $work_extra . '
                                    </div>
                                </div>
                            </div>

                            <div class="card mb-7">
                                <h2 class="card-header bg-reset border-0 pg-title-section">
                                    ' . ($is_new ? lang('New Contact\'s Home Information') : lang('Contact\'s Home Information')) . '
                                </h2>
                                <div class="card-body">
                                    <div class="row">' . $output_home . '
                                    </div>
                                </div>
                            </div>

                            <div class="card mb-7">
                                <h2 class="card-header bg-reset border-0 pg-title-section">
                                    ' . lang('Membership Access') . '
                                </h2>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-12 my-3">
                                            <label for="member_id" class="form-label">' . h(MEMBER_ID_LABEL) . '</label>
                                            <input value="' . pg_contact_form_value($values, 'member_id') . '" type="text" name="member_id" id="member_id" class="form-control" maxlength="50" />
                                            <div class="form-text text-end">' . lang('leave blank for no membership') . '</div>
                                        </div>
                                        <div class="col-12 my-3">
                                            <label for="expiration_date" class="form-label">' . lang('Membership Expiration Date') . '</label>
                                            <input value="' . pg_contact_form_value($values, 'expiration_date') . '" type="text" name="expiration_date" id="expiration_date" class="form-control" data-pg-date />
                                            <div class="form-text text-end">' . lang('leave blank for lifetime membership') . '</div>
                                            ' . get_date_picker_format() . pg_fields_script() . '
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="card mb-7">
                                <h2 class="card-header bg-reset border-0 pg-title-section">
                                    ' . lang('Additional Notes') . '
                                </h2>
                                <div class="card-body">
                                    <div class="row">
                                        ' . pg_contact_form_lead_source_field($values, pg_referral_sources()) . '
                                        <div class="col-12 my-3">
                                            <label for="description" class="form-label">' . lang('Description') . '</label>
                                            <textarea name="description" id="description" class="form-control" rows="6">' . pg_contact_form_value($values, 'description') . '</textarea>
                                        </div>
                                    </div>
                                </div>
                            </div>

                        ' . pg_contact_form_affiliate_card($values) . '

                            <div class="card mb-7">
                                <h2 class="card-header bg-reset border-0 pg-title-section">
                                    ' . lang('Contact Groups & Subscriptions') . '
                                </h2>
                                <div class="card-body">
                                    <div class="row">
                                        ' . $groups_html . '
                                    </div>
                                </div>
                            </div>';
}

/**
 * The affiliate card, '' while the affiliate program is off.
 *
 * @param array $values The stored contact (raw values), array() for a new one
 * @return string
 */
function pg_contact_form_affiliate_card($values)
{
    if (AFFILIATE_PROGRAM != true) {
        return '';
    }

    return '
            <div class="card mb-7">
                <h2 class="card-header bg-reset border-0 pg-title-section">
                    ' . lang('Affiliate Information') . '
                </h2>
                <div class="card-body">
                    <div class="row">

                        <div class="col-12 my-1">
                            <div class="form-field">
                                <div class="switch"><input class="" type="checkbox" id="affiliate_approved" name="affiliate_approved" value="1"' . (!empty($values['affiliate_approved']) ? ' checked' : '') . '></div>
                                <label class="" for="affiliate_approved">' . lang('Approved') . '</label>
                            </div>
                        </div>
                        <div class="col-12 md:col-6 xl:col-3 my-3">
                            <label for="affiliate_name" class="form-label">' . lang('Affiliate Name') . '</label>
                            <input value="' . pg_contact_form_value($values, 'affiliate_name') . '" type="text" name="affiliate_name" id="affiliate_name" class="form-control" maxlength="100" />
                        </div>
                        <div class="col-12 md:col-6 my-3">
                            <label for="affiliate_code" class="form-label">' . lang('Affiliate Code') . '</label>
                            <input value="' . pg_contact_form_value($values, 'affiliate_code') . '" type="text" name="affiliate_code" id="affiliate_code" class="form-control" maxlength="100" />
                            <div class="form-text text-end">' . lang('leave blank to generate a code when the affiliate is approved') . '</div>
                        </div>
                        <div class="col-12 md:col-6 xl:col-3 my-3">
                            <label for="affiliate_commission_rate" class="form-label">' . lang('Commission Rate') . '</label>
                            <label class="form-control form-adorn form-adorn-end">
                                <span class="form-adorn-text" aria-hidden="true">%</span>
                                <input value="' . h(pg_contact_form_commission_rate_value($values['affiliate_commission_rate'] ?? '')) . '" type="text" name="affiliate_commission_rate" id="affiliate_commission_rate" class="form-ghost" size="6" maxlength="6" inputmode="decimal" data-inputmask-alias="decimal" data-inputmask-allowminus="false" data-inputmask-max="100" data-inputmask-placeholder="0" style="text-align: right;">
                            </label>
                            <div class="form-text text-end">' . lang(array('string'=>'leave blank for default: {var:1}%','vars'=>pg_format_number(AFFILIATE_DEFAULT_COMMISSION_RATE, 2))) . '</div>
                        </div>
                    </div>
                </div>
            </div>';
}

/**
 * The contact groups the user may put a contact in, by name.
 *
 * @param array $user
 * @return array  Rows of id, name, email_subscription, kind
 */
function pg_contact_form_groups($user)
{
    $result = mysqli_query(db::$con, "SELECT id, name, email_subscription, " . pg_contact_form_kind_column() . " FROM contact_groups ORDER BY name") or output_error('Query failed.');

    $contact_groups = array();

    while ($row = mysqli_fetch_assoc($result)) {
        if (validate_contact_group_access($user, $row['id']) == true) {
            $contact_groups[] = $row;
        }
    }

    return $contact_groups;
}

/**
 * Whether a stored contact is in each group and opted in to it: opted in
 * when the group's opt-in row is missing or set.
 *
 * @param int|string $contact_id
 * @param array $contact_groups Rows of pg_contact_form_groups()
 * @return array  group id => array('in' => bool, 'opt_in' => bool)
 */
function pg_contact_form_group_states($contact_id, $contact_groups)
{
    $states = array();

    foreach ($contact_groups as $contact_group) {
        $result = db_q("SELECT contact_id FROM contacts_contact_groups_xref WHERE (contact_id = ?) AND (contact_group_id = ?)", array((string) $contact_id, (string) $contact_group['id'])) or output_error('Query failed.');
        $in = (mysqli_num_rows($result) > 0);

        $opt_in = true;

        if ($contact_group['email_subscription'] == 1) {
            $result = db_q("SELECT opt_in FROM opt_in WHERE (contact_id = ?) AND (contact_group_id = ?)", array((string) $contact_id, (string) $contact_group['id'])) or output_error('Query failed.');
            $row = mysqli_fetch_assoc($result);
            $opt_in = ((mysqli_num_rows($result) == 0) || ($row['opt_in'] == 1));
        }

        $states[$contact_group['id']] = array('in' => $in, 'opt_in' => $opt_in);
    }

    return $states;
}

/**
 * The contact group switches, each with its opt-in switch when the group
 * has e-mail subscription on. A group missing from $states is unticked and
 * opted in, as on the create screen.
 *
 * @param array $contact_groups Rows of pg_contact_form_groups()
 * @param array $states         pg_contact_form_group_states(), array() for a new contact
 * @return string
 */
function pg_contact_form_group_cells($contact_groups, $states)
{
    $output = '';
    $output_by_rule = '';

    foreach ($contact_groups as $contact_group) {
        $id = h($contact_group['id']);
        $state = $states[$contact_group['id']] ?? array('in' => false, 'opt_in' => true);

        // A group filled by rules shows as a read-only chip when the contact
        // is in it, and has no switch.
        if (($contact_group['kind'] ?? 'static') === 'dynamic') {
            if ($state['in']) {
                $output_by_rule .= '<li><a class="badge theme-secondary fw-normal" href="edit_contact_group.php?id=' . $id . '">' . h($contact_group['name']) . '</a></li>';
            }

            continue;
        }

        $contact_group_target = '';
        $output_opt_in = '';

        if ($contact_group['email_subscription'] == 1) {
            $contact_group_target = ' data-bs-target="#contact_group_opt_in_cell_' . $id . '"';
            $output_opt_in = '
            <div class="collapse popover  fade bs-popover-bottom p-0 mb-3" id="contact_group_opt_in_cell_' . $id . '">
                <div class="popover-arrow" style="position: absolute; left: 0px; transform: translate(45px, 0px);"></div>
                <div class="popover-body py-0">
                    <div class="row">
                        <div class="col-12 my-3">
                            <div class="form-field">
                                <div class="switch"><input type="checkbox" name="contact_group_opt_in_' . $id . '" id="contact_group_opt_in_' . $id . '" value="1"' . ($state['opt_in'] ? ' checked="checked"' : '') . ' class="" /></div>
                                <label class="" for="contact_group_opt_in_' . $id . '"> ' . lang('Opt-In Status') . '</label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>';
        }

        $output .=
        '<div class="col-12 md:col-6 xl:col-4 my-3">
            <div class="form-field">
                <div class="switch"><input type="checkbox" name="contact_group_' . $id . '" id="contact_group_' . $id . '" value="1" class="collapse-switcher"' . ($state['in'] ? ' checked="checked"' : '') . $contact_group_target . '/></div>
                <label class="" for="contact_group_' . $id . '"> ' . h($contact_group['name']) . '</label>
            </div>
            ' . $output_opt_in . '
        </div>';
    }

    if ($output_by_rule !== '') {
        $output .= '
        <div class="col-12 my-3">
            <div class="form-label" id="contact_groups_by_rule_label">' . lang('Members by rule') . '</div>
            <ul class="list-unstyled d-flex flex-wrap gap-2 m-0" aria-labelledby="contact_groups_by_rule_label">' . $output_by_rule . '</ul>
            <div class="form-text">' . lang('The rules of these groups decide who is in them; they are not changed here.') . '</div>
        </div>';
    }

    return $output;
}

/**
 * The kind column of contact_groups for a SELECT list: the column once the
 * upgrade has added it, 'static' for every group before.
 *
 * @return string
 */
function pg_contact_form_kind_column()
{
    return pg_audience_ready() ? 'kind' : "'static' AS kind";
}

/**
 * Ends the request with an error when the posted contact cannot be saved
 * (pg_contact_form_refusal()) or a user with the user role leaves it in no
 * group they may edit (pg_contact_form_keeps_a_group()).
 *
 * @param array $user           the viewer (validate_user())
 * @param array $contact_groups pg_contact_form_all_groups()'s answer
 * @param array $post
 * @return void
 */
function pg_contact_form_check($user, $contact_groups, $post)
{
    $refusal = pg_contact_form_refusal($post);

    if ($refusal !== '') {
        output_error(h($refusal) . ' <a href="javascript:history.go(-1);">' . lang('Go back') . '</a>.');
    }

    $may_edit = function ($group_id) use ($user) {
        return validate_contact_group_access($user, $group_id) == true;
    };

    if (!pg_contact_form_keeps_a_group($user, $contact_groups, $post, $may_edit)) {
        output_error(lang('Please select at least one contact group for the contact') . '. <a href="javascript:history.go(-1);">' . lang('Go back') . '</a>.');
    }
}

/**
 * The posted e-mail address, trimmed; an invalid one ends the request with
 * an error.
 *
 * @param mixed $email_address
 * @return string
 */
function pg_contact_form_email($email_address)
{
    $email_address = trim(is_scalar($email_address) ? (string) $email_address : '');

    if (($email_address != '') && (validate_email_address($email_address) == false)) {
        output_error(lang('The email address is invalid') . '. <a href="javascript:history.go(-1);">' . lang('Go back') . '</a>.');
    }

    return $email_address;
}

/**
 * The affiliate code a posted contact is saved with: the posted one,
 * trimmed, which no other contact may use (an error ends the request), or a
 * new one when the contact is approved without a code. A code of only
 * spaces is blank: under PAD SPACE it would match every contact with the
 * empty code, and checkout matches the trimmed offer code, so a padded code
 * would never credit its affiliate.
 *
 * @param array $post
 * @param int|string $contact_id The contact itself, 0 for a new one
 * @param callable $taken Lookup as pg_contact_form_affiliate_code_taken() (tests stub it)
 * @param callable $generate Code generator (tests stub it)
 * @return string
 */
function pg_contact_form_affiliate_code($post, $contact_id, $taken = 'pg_contact_form_affiliate_code_taken', $generate = 'generate_affiliate_code')
{
    $affiliate_code = trim(pg_contact_form_posted($post, 'affiliate_code'));

    if ($affiliate_code && $taken($affiliate_code, $contact_id)) {
        output_error(lang('That affiliate code is already in use. Please use a different code') . '. <a href="javascript:history.go(-1);">' . lang('Go back') . '</a>.');
    }

    // An unticked Approved box is not posted at all.
    if (!empty($post['affiliate_approved']) && (!$affiliate_code)) {
        $affiliate_code = $generate();
    }

    return $affiliate_code;
}

/**
 * Whether another contact already has the affiliate code.
 *
 * @param string $affiliate_code
 * @param int|string $contact_id The contact itself (left out), 0 for a new one
 * @return bool
 */
function pg_contact_form_affiliate_code_taken($affiliate_code, $contact_id)
{
    $result = db_q("SELECT id FROM contacts WHERE affiliate_code = ? AND id != ?", array((string) $affiliate_code, (string) $contact_id)) or output_error('Query failed.');

    return mysqli_num_rows($result) > 0;
}

/**
 * Gives every other contact with the same address the saved contact's
 * opt-in status, with their audit lines (read before, written after).
 *
 * @param int $contact_id
 * @param string $email_address
 * @param string $opt_in
 */
function pg_contact_form_sync_opt_in($contact_id, $email_address, $opt_in)
{
    if ($email_address == '') {
        return;
    }

    $audit_address = pg_audit_fetch_by('contacts', 'email_address', $email_address);
    unset($audit_address[(int) $contact_id]);

    db_q("UPDATE contacts SET opt_in = ? WHERE email_address = ?", array((string) $opt_in, (string) $email_address));

    pg_audit_record_many('update', 'contacts', $audit_address);
}

/**
 * The ticked contacts the Contacts list writes, in order: a contact whose
 * address an earlier one already has is left out, because the address-wide
 * sync of the earlier one covers it. Contacts without an address stay.
 *
 * @param array $contacts Rows of id, email_address
 * @return array
 */
function pg_contact_bulk_opt_in_targets(array $contacts)
{
    $targets = array();
    $addresses = array();

    foreach ($contacts as $contact) {
        $address = mb_strtolower(trim((string) $contact['email_address']), 'UTF-8');

        if ($address !== '') {
            if (isset($addresses[$address])) {
                continue;
            }

            $addresses[$address] = true;
        }

        $targets[] = $contact;
    }

    return $targets;
}

/**
 * How many contacts the bulk write changes: those whose status before differs
 * from the new one.
 *
 * @param array $before Contact id => opt_in before the write
 * @param string $opt_in '1' or '0'
 * @return int
 */
function pg_contact_bulk_opt_in_changed(array $before, $opt_in)
{
    $changed = 0;

    foreach ($before as $status) {
        if ((int) $status !== (int) $opt_in) {
            $changed++;
        }
    }

    return $changed;
}

/**
 * Opt-In Selected and Opt-Out Selected of the Contacts list: each ticked
 * contact the user may access gets the status, and every contact with the
 * same address follows as on the single save, with their audit lines.
 *
 * @param array $user
 * @param array $contact_ids Posted contact ids
 * @param string $opt_in '1' or '0'
 * @return int Contacts whose status changed
 */
function pg_contact_bulk_set_opt_in($user, $contact_ids, $opt_in)
{
    $contacts = array();

    foreach ((array) $contact_ids as $contact_id) {
        if (!is_scalar($contact_id) || validate_contact_access($user, $contact_id) == false) {
            continue;
        }

        $result = db_q("SELECT id, email_address FROM contacts WHERE id = ?", array((string) $contact_id)) or output_error('Query failed.');

        if ($row = mysqli_fetch_assoc($result)) {
            $contacts[] = array('id' => (int) $row['id'], 'email_address' => (string) $row['email_address']);
        }
    }

    $before = array();

    foreach (pg_contact_bulk_opt_in_targets($contacts) as $contact) {
        $result = db_q(
            "SELECT id, opt_in FROM contacts WHERE (id = ?) OR ((email_address = ?) AND (email_address != ''))",
            array((string) $contact['id'], $contact['email_address'])) or output_error('Query failed.');

        while ($row = mysqli_fetch_assoc($result)) {
            $before[(int) $row['id']] = $row['opt_in'];
        }

        $audit_before = pg_audit_fetch_many('contacts', array($contact['id']));

        db_q("UPDATE contacts SET opt_in = ? WHERE id = ?", array((string) $opt_in, (string) $contact['id'])) or output_error('Query failed.');

        pg_audit_record_many('update', 'contacts', $audit_before);

        pg_contact_form_sync_opt_in((int) $contact['id'], $contact['email_address'], $opt_in);
    }

    return pg_contact_bulk_opt_in_changed($before, $opt_in);
}

/**
 * Every contact group, unordered, for the save.
 *
 * @return array  Rows of id, name, email_subscription
 */
function pg_contact_form_all_groups()
{
    // A group filled by rules is never ticked by hand: its members come from
    // the recompute (includes/fn/audiences.php), so the save neither adds to
    // it nor takes an unticked contact out of it.
    $where = pg_audience_ready() ? " WHERE kind <> 'dynamic'" : '';
    $result = mysqli_query(db::$con, "SELECT id, name, email_subscription FROM contact_groups" . $where) or output_error('Query failed.');

    $contact_groups = array();

    while ($row = mysqli_fetch_assoc($result)) {
        $contact_groups[] = $row;
    }

    return $contact_groups;
}

/**
 * Puts the saved contact in the ticked groups the user may manage and takes
 * it out of the unticked ones, with the group opt-in rows; joins and leaves
 * are announced.
 *
 * @param array $user
 * @param int $contact_id
 * @param array $contact_groups Rows of pg_contact_form_all_groups()
 * @param array $post
 */
function pg_contact_form_save_groups($user, $contact_id, $contact_groups, $post)
{
    foreach ($contact_groups as $contact_group) {
        if (validate_contact_group_access($user, $contact_group['id']) == false) {
            continue;
        }

        $ids = array((string) $contact_id, (string) $contact_group['id']);

        if (pg_contact_form_posted($post, 'contact_group_' . $contact_group['id']) == 1) {
            $result = db_q("SELECT contact_id FROM contacts_contact_groups_xref WHERE (contact_id = ?) AND (contact_group_id = ?)", $ids) or output_error('Query failed.');

            if (mysqli_num_rows($result) == 0) {
                db_q("INSERT INTO contacts_contact_groups_xref (contact_id, contact_group_id) VALUES (?, ?)", $ids) or output_error('Query failed.');

                pg_announce_contact_group_members($contact_group['id'], array((int) $contact_id), array(), $contact_group['name']);
            }

            if ($contact_group['email_subscription'] == 1) {
                $opt_in = pg_contact_form_posted($post, 'contact_group_opt_in_' . $contact_group['id']);

                $result = db_q("SELECT contact_id FROM opt_in WHERE (contact_id = ?) AND (contact_group_id = ?)", $ids) or output_error('Query failed.');

                if (mysqli_num_rows($result) == 0) {
                    db_q("INSERT INTO opt_in (contact_id, contact_group_id, opt_in) VALUES (?, ?, ?)", array($ids[0], $ids[1], $opt_in)) or output_error('Query failed.');
                } else {
                    db_q("UPDATE opt_in SET opt_in = ? WHERE (contact_id = ?) AND (contact_group_id = ?)", array($opt_in, $ids[0], $ids[1])) or output_error('Query failed.');
                }
            }

        } else {
            db_q("DELETE FROM contacts_contact_groups_xref WHERE (contact_id = ?) AND (contact_group_id = ?)", $ids) or output_error('Query failed.');

            // Every group the contact is not ticked in comes through here, so
            // only the ones it was actually in are announced.
            if (mysqli_affected_rows(db::$con) > 0) {
                pg_announce_contact_group_members($contact_group['id'], array(), array((int) $contact_id), $contact_group['name']);
            }
        }
    }
}

/**
 * Gives an approved affiliate a key code for the affiliate group offer, when
 * the offer exists and the affiliate has none for it yet.
 *
 * @param string $affiliate_code
 * @param int|string $user_id
 */
function pg_contact_form_affiliate_key_code($affiliate_code, $user_id)
{
    if (AFFILIATE_GROUP_OFFER_ID == 0) {
        return;
    }

    $result = db_q("SELECT code FROM offers WHERE id = ?", array((string) AFFILIATE_GROUP_OFFER_ID)) or output_error('Query failed.');

    if (mysqli_num_rows($result) == 0) {
        return;
    }

    $offer = mysqli_fetch_assoc($result);

    $result = db_q("SELECT id FROM key_codes WHERE (code = ?) AND (offer_code = ?)", array((string) $affiliate_code, (string) $offer['code'])) or output_error('Query failed.');

    if (mysqli_num_rows($result) == 0) {
        db_q(
            "INSERT INTO key_codes (code, offer_code, enabled, user, timestamp)
            VALUES (?, ?, '1', ?, UNIX_TIMESTAMP())",
            array((string) $affiliate_code, (string) $offer['code'], (string) $user_id)) or output_error('Query failed.');
    }
}

/**
 * Sends the user on after a save or delete: to the screen the form was
 * opened from (send_to), else to the contact list.
 */
function pg_contact_form_redirect()
{
    if (isset($_REQUEST['send_to']) && ($_REQUEST['send_to'] != '')) {
        header('Location: ' . URL_SCHEME . HOSTNAME . pg_safe_redirect_path(($_REQUEST['send_to'] ?? '')));
    } else {
        header('Location: ' . URL_SCHEME . HOSTNAME . PATH . SOFTWARE_DIRECTORY . '/view_contacts.php');
    }
}
