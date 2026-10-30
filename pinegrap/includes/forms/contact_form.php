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

    $columns['expiration_date'] = (string) prepare_form_data_for_input(pg_contact_form_posted($post, 'expiration_date'), 'date');
    $columns['tax_number'] = trim(pg_contact_form_posted($post, 'tax_number'));
    $columns['tax_office'] = trim(pg_contact_form_posted($post, 'tax_office'));
    $columns['file_id'] = pg_contact_form_posted($post, 'file_id');
    $columns['image'] = pg_contact_form_posted($post, 'image_url');
    $columns['user'] = (string) $user_id;

    return $columns;
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
        'affiliate_commission_rate' => pg_contact_form_posted($post, 'affiliate_commission_rate'),
    );
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
 * The name card and the picture card.
 *
 * @param array $values      The stored contact (raw values), array() for a new one
 * @param bool $is_new
 * @param string $image_html The current picture's markup, '' for none
 * @return string
 */
function pg_contact_form_name_row($values, $is_new, $image_html)
{
    // The edit screen posts the picked file under file_id, which also holds
    // the stored picture; the create screen keeps the picker's own name.
    $picker = 'software_image_picker({initialize:true,SingleImage:true' . ($is_new ? '' : ',file_input_name:\'file_id\'') . '});';

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
                                        <div class="col-12 md:col-4 my-3">
                                            <label for="' . $name . '" class="form-label">' . $label . '</label>
                                            <input value="' . pg_contact_form_value($values, $name) . '" type="text" name="' . $name . '" id="' . $name . '" class="form-control" size="30" maxlength="50" />
                                        </div>';
    }

    return '
                    <div class="row justify-content-center">
                        <div class="col-12 sm:col sm:order-2 align-self-end">
                            <div class="card my-7">
                                <h2 class="card-header bg-reset border-0 text-uppercase h5 fg-primary fw-bold">
                                    ' . ($is_new ? lang('New Contact\'s Name') : lang('Contact\'s Name')) . '
                                </h2>
                                <div class="card-body">
                                    <div class="row">' . $output_fields . '
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-auto sm:order-1 ">
                            <div class="card my-7 display-on-hover overflow-hidden position-relative" style="width:200px;height:auto;">
                                <div id="software_image_picker_container" ondblclick="' . $picker . '" class="user-select-none sortable-list img-list bg-1 rounded-4 p-3 row g-7">' . $image_html . '</div>
                                <div class="card-footer border-0 bg-transparent">
                                    <button type="button" class="btn"  onclick="' . $picker . '"><span class="material-icons me-3">image_search</span>' . lang('Change Image') . '</button>
                                </div>
                            </div>
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
                                        <div class="col-12 md:col-4 my-3">
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
        pg_contact_form_text_field($values, 'title', lang('Title'), 50) .
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
                    <div class="row">
                        <div class="col-12 ">
                            <div class="card my-7">
                                <h2 class="card-header bg-reset border-0 text-uppercase h5 fg-primary fw-bold">
                                    ' . ($is_new ? lang('New Contact\'s Work Information') : lang('Contact\'s Work Information')) . '
                                </h2>
                                <div class="card-body" >
                                    <div class="row">' . $output_work . '
                                    </div>
                                    <div class="row">' . $output_business . '
                                        <div class="col-12 md:col-4 my-3">
                                            <label for="tax_number" class="form-label">VKN / TCKN</label>
                                            <input value="' . pg_contact_form_value($values, 'tax_number') . '" type="text" name="tax_number" id="tax_number" class="form-control" maxlength="11" inputmode="numeric" />
                                            <div class="form-text">' . lang('11-digit TCKN for individuals, 10-digit VKN for companies.') . '</div>
                                        </div>
                                        <div class="col-12 md:col-4 my-3">
                                            <label for="tax_office" class="form-label">' . lang('Tax Office') . '</label>
                                            <input value="' . pg_contact_form_value($values, 'tax_office') . '" type="text" name="tax_office" id="tax_office" class="form-control" maxlength="100" />
                                        </div>
                                        ' . $work_extra . '
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-12">
                            <div class="card my-7">
                                <h2 class="card-header bg-reset border-0 text-uppercase h5 fg-primary fw-bold">
                                    ' . ($is_new ? lang('New Contact\'s Home Information') : lang('Contact\'s Home Information')) . '
                                </h2>
                                <div class="card-body">
                                    <div class="row">' . $output_home . '
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-12 md:col-6">
                            <div class="card my-7">
                                <h2 class="card-header bg-reset border-0 text-uppercase h5 fg-primary fw-bold">
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
                                            <label for="expiration_date" class="form-label">' . lang('Expiration Date') . '</label>
                                            <input value="' . pg_contact_form_value($values, 'expiration_date') . '" type="text" name="expiration_date" id="expiration_date" class="form-control" data-pg-date />
                                            <div class="form-text text-end">' . lang('leave blank for lifetime membership') . '</div>
                                            ' . get_date_picker_format() . pg_fields_script() . '
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-12 md:col-6">
                            <div class="card my-7">
                                <h2 class="card-header bg-reset border-0 text-uppercase h5 fg-primary fw-bold">
                                    ' . lang('Additional Notes') . '
                                </h2>
                                <div class="card-body">
                                    <div class="row">
                                        ' . pg_contact_form_lead_source_field($values, pg_referral_sources()) . '
                                        <div class="col-12 my-3">
                                            <label for="description" class="form-label">' . lang('Description') . '</label>
                                            <textarea type="text" name="description" id="description" class="form-control">' . pg_contact_form_value($values, 'description') . '</textarea>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        ' . pg_contact_form_affiliate_card($values) . '

                        <div class="col-12">
                            <div class="card my-7">
                                <h2 class="card-header bg-reset border-0 text-uppercase h5 fg-primary fw-bold">
                                    ' . lang('Contact Groups & Subscriptions') . '
                                </h2>
                                <div class="card-body">
                                    <div class="row">
                                        ' . $groups_html . '
                                    </div>
                                </div>
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

    return '<div class="col-12">
            <div class="card my-7">
                <h2 class="card-header bg-reset border-0 text-uppercase h5 fg-primary fw-bold">
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
                        <div class="col-12 md:col-3 my-3">
                            <label for="affiliate_name" class="form-label">' . lang('Affiliate Name') . '</label>
                            <input value="' . pg_contact_form_value($values, 'affiliate_name') . '" type="text" name="affiliate_name" id="affiliate_name" class="form-control" maxlength="100" />
                        </div>
                        <div class="col-12 md:col-6 my-3">
                            <label for="affiliate_code" class="form-label">' . lang('Affiliate Code') . '</label>
                            <input value="' . pg_contact_form_value($values, 'affiliate_code') . '" type="text" name="affiliate_code" id="affiliate_code" class="form-control" maxlength="100" />
                            <div class="form-text text-end">' . lang('leave blank to automatically generate code') . '</div>
                        </div>
                        <div class="col-12 md:col-3 my-3">
                            <label for="affiliate_commission_rate" class="form-label">' . lang('Commission Rate') . '</label>
                            <label class="form-control form-adorn form-adorn-end">
                                <span class="form-adorn-text" aria-hidden="true">%</span>
                                <input value="' . h(pg_contact_form_commission_rate_value($values['affiliate_commission_rate'] ?? '')) . '" type="text" name="affiliate_commission_rate" id="affiliate_commission_rate" class="form-ghost" size="6" maxlength="6" inputmode="decimal" data-inputmask-alias="decimal" data-inputmask-placeholder="0" style="text-align: right;">
                            </label>
                            <div class="form-text text-end">' . lang(array('string'=>'leave blank for default: {var:1}%','vars'=>AFFILIATE_DEFAULT_COMMISSION_RATE)) . '</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>';
}

/**
 * The contact groups the user may put a contact in, by name.
 *
 * @param array $user
 * @return array  Rows of id, name, email_subscription
 */
function pg_contact_form_groups($user)
{
    $result = mysqli_query(db::$con, "SELECT id, name, email_subscription FROM contact_groups ORDER BY name") or output_error('Query failed.');

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

    foreach ($contact_groups as $contact_group) {
        $id = h($contact_group['id']);
        $state = $states[$contact_group['id']] ?? array('in' => false, 'opt_in' => true);

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
        '<div class="col-12 md:col-4 my-3">
            <div class="form-field">
                <div class="switch"><input type="checkbox" name="contact_group_' . $id . '" id="contact_group_' . $id . '" value="1" class="collapse-switcher"' . ($state['in'] ? ' checked="checked"' : '') . $contact_group_target . '/></div>
                <label class="" for="contact_group_' . $id . '"> ' . h($contact_group['name']) . '</label>
            </div>
            ' . $output_opt_in . '
        </div>';
    }

    return $output;
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
 * The affiliate code a posted contact is saved with: the posted one, which
 * no other contact may use (an error ends the request), or a new one when
 * the contact is approved without a code.
 *
 * @param array $post
 * @param int|string $contact_id The contact itself, 0 for a new one
 * @return string
 */
function pg_contact_form_affiliate_code($post, $contact_id)
{
    $affiliate_code = pg_contact_form_posted($post, 'affiliate_code');

    if ($affiliate_code) {
        $result = db_q("SELECT id FROM contacts WHERE affiliate_code = ? AND id != ?", array($affiliate_code, (string) $contact_id)) or output_error('Query failed.');

        if (mysqli_num_rows($result) > 0) {
            output_error(lang('That affiliate code is already in use. Please use a different code') . '. <a href="javascript:history.go(-1);">' . lang('Go back') . '</a>.');
        }
    }

    // An unticked Approved box is not posted at all.
    if (!empty($post['affiliate_approved']) && (!$affiliate_code)) {
        $affiliate_code = generate_affiliate_code();
    }

    return $affiliate_code;
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
 * Every contact group, unordered, for the save.
 *
 * @return array  Rows of id, name, email_subscription
 */
function pg_contact_form_all_groups()
{
    $result = mysqli_query(db::$con, "SELECT id, name, email_subscription FROM contact_groups") or output_error('Query failed.');

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
