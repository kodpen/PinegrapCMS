<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The contact group form, shared by the create and the edit screens
 * (add_contact_group.php, edit_contact_group.php): the form and status
 * columns with the save bar, the name check and the values a save writes. The screens keep what
 * differs: the edit screen loads the record, checks the user's access to
 * the group and offers delete while the group holds no contact.
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
 * The form column (Main Informations and E-mail Subscription cards, then
 * the screen's own cards) and the sticky status column (a Summary of the
 * subscription and its type, plus the screen's own rows), in the edit
 * screen layout of product_builder.php. Below lg the status column follows
 * the form in source order.
 *
 * @param liveform $liveform
 * @param array $form  'required' (mark the name field required in the
 *                     browser), 'cards' (cards printed after the shared
 *                     ones: the edit screen's rule card), 'status' (extra
 *                     Summary rows, HTML)
 * @return string
 */
function pg_contact_group_form_cards($liveform, $form = array())
{
    $form = array_merge(array('required' => false, 'cards' => '', 'status' => ''), $form);
    $required = $form['required'] ? array('required' => 'required') : array();

    $subscription_labels = pg_contact_group_form_subscription_labels();
    $subscribed = ((string) $liveform->get_field_value('email_subscription') === '1');
    $type = (string) $liveform->get_field_value('email_subscription_type');
    $type_labels = array('open' => lang('Open'), 'closed' => lang('Closed'));

    return '
                        <div class="col-12 lg:col-8 2xl:col-9 order-1">
                            <div class="card mb-7">
                                <h2 class="card-header bg-reset border-0 pg-title-section">
                                    ' . lang('Main Informations') . '
                                </h2>
                                <div class="card-body pt-0">
                                    <div class="row">
                                        <div class="col-12 md:col-8 lg:col-6 my-3">
                                            <label for="name" class="form-label">' . lang('Contact Group Name') . '</label>
                                            ' . $liveform->output_field(array('type'=>'text', 'name'=>'name', 'id'=>'name', 'placeholder'=>lang('Please enter a name'), 'maxlength'=>'100', 'class'=>'form-control') + $required) . '
                                            <div class="invalid-feedback">' . lang('Required Area') . '</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="card mb-7">
                                <h2 class="card-header bg-reset border-0 pg-title-section">
                                    ' . lang('E-mail Subscription') . '
                                </h2>
                                <div class="card-body pt-0">
                                    <div class="row">
                                        <div class="col-12 my-3">
                                            <div class="form-field">
                                                <div class="switch">' . $liveform->output_field(array('type'=>'checkbox', 'name'=>'email_subscription', 'id'=>'email_subscription', 'value'=>'1', 'class'=>'collapse-switcher no-bs', 'data-bs-target'=>'#email_subscription_type_row')) . '</div>
                                                <label class="" for="email_subscription">' . lang('Enable E-mail Subscription') . '</label>
                                                <div class="form-text">' . lang('Allow Campaigns to send to this Contact Group') . '</div>
                                            </div>
                                        </div>
                                        <div class="collapse popover fade bs-popover-bottom p-0 mb-3" id="email_subscription_type_row">
                                            <div class="popover-arrow" style="position: absolute; left: 0px; transform: translate(59px, 0px);"></div>
                                            <div class="popover-body">
                                                <div class="row">
                                                    <div class="col-12 my-5" role="radiogroup" aria-labelledby="email_subscription_type_label">
                                                        <div class="form-label" id="email_subscription_type_label">' . lang('E-mail Subscription Type') . '</div>
                                                        <div class="form-field">
                                                        ' . $liveform->output_field(array('type'=>'radio', 'name'=>'email_subscription_type', 'id'=>'open', 'value'=>'open', 'checked'=>'checked', 'class'=>'radio')) . '
                                                            <label class="" for="open">' . lang('Open') . '</label>
                                                        </div>
                                                        <div class="form-field">
                                                            ' . $liveform->output_field(array('type'=>'radio', 'name'=>'email_subscription_type', 'id'=>'closed', 'value'=>'closed', 'class'=>'radio')) . '
                                                            <label class="" for="closed">' . lang('Closed') . '</label>
                                                        </div>
                                                    </div>
                                                    <div class="col-12 my-1">
                                                        <label for="description" class="form-label">' . lang('Description') . '</label>
                                                        ' . $liveform->output_field(array('type'=>'textarea', 'name'=>'description', 'id'=>'description', 'class'=>'form-control', 'rows'=>'5', 'cols'=>'50', 'maxlength'=>'255')) . '
                                                        <div class="form-text text-end">' . lang('Description/Subscription Message on My Account Pages') . '</div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>' . $form['cards'] . '
                        </div>
                        <div class="col-12 lg:col-4 2xl:col-3 order-2">
                            <!-- 3.3rem: the fixed control panel header covers the top of the window. -->
                            <div class="position-sticky" style="top:3.3rem;">
                                <div class="card mb-7">
                                    <h2 class="card-header bg-reset border-0 pg-title-section">
                                        ' . lang('Summary') . '
                                    </h2>
                                    <div class="card-body pt-0">
                                        <dl class="mb-0">
                                            <div class="d-flex justify-content-between py-2 border-bottom">
                                                <dt class="fw-normal">' . lang('E-mail Subscription') . '</dt>
                                                <dd class="mb-0 fw-bold" id="contact_group_summary_subscription" data-labels="' . h(json_encode($subscription_labels)) . '">' . h($subscription_labels[$subscribed ? 'on' : 'off']) . '</dd>
                                            </div>
                                            <div class="d-flex justify-content-between py-2' . ($form['status'] !== '' ? ' border-bottom' : '') . '">
                                                <dt class="fw-normal">' . lang('E-mail Subscription Type') . '</dt>
                                                <dd class="mb-0 fw-bold" id="contact_group_summary_type" data-labels="' . h(json_encode($type_labels)) . '">' . h($type_labels[$type] ?? $type_labels['open']) . '</dd>
                                            </div>' . $form['status'] . '
                                        </dl>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <script>
                            $(document).on(\'change\', \'#email_subscription\', function () {
                                var $value = $(\'#contact_group_summary_subscription\');
                                $value.text($value.data(\'labels\')[this.checked ? \'on\' : \'off\']);
                            });
                            $(document).on(\'change\', \'input[name="email_subscription_type"]\', function () {
                                var $value = $(\'#contact_group_summary_type\');
                                $value.text($value.data(\'labels\')[this.value] || \'\');
                            });
                        </script>';
}

/**
 * The shown state of the e-mail subscription switch.
 *
 * @return array  'on', 'off' => label
 */
function pg_contact_group_form_subscription_labels()
{
    return array('on' => lang('Enabled'), 'off' => lang('Disabled'));
}

/**
 * The Summary rows of the edit screen: how many contacts the group holds
 * and, once rules exist, whether rules or people fill it.
 *
 * @param int $contact_count  Every member, those the rules added included
 * @param string|null $kind   'static' or 'dynamic'; null hides the row
 * @return string
 */
function pg_contact_group_form_members_status($contact_count, $kind = null)
{
    $output = '
                                            <div class="d-flex justify-content-between py-2' . ($kind !== null ? ' border-bottom' : '') . '">
                                                <dt class="fw-normal">' . lang('Members') . '</dt>
                                                <dd class="mb-0 fw-bold" id="contact_group_summary_contacts">' . (int) $contact_count . '</dd>
                                            </div>';

    if ($kind !== null) {
        $output .= '
                                            <div class="d-flex justify-content-between py-2">
                                                <dt class="fw-normal">' . lang('Filled By') . '</dt>
                                                <dd class="mb-0 fw-bold">' . (($kind === 'dynamic') ? lang('Rules') : lang('By Hand')) . '</dd>
                                            </div>';
    }

    return $output;
}

/**
 * The screen row: the cards (pg_contact_group_form_cards()) and the sticky
 * save bar with the given buttons.
 *
 * @param string $cards    pg_contact_group_form_cards()
 * @param string $buttons  The buttons inside the bar's group
 * @return string
 */
function pg_contact_group_form_layout($cards, $buttons)
{
    return '
                    <div class="row">' . $cards . '
                        <div class="col-12 order-3 position-sticky" style="bottom:0; z-index:3;">
                            <nav class="buttons navigation text-center mb-7" aria-label="' . h(lang('data edit buttons')) . '">
                                <div class="container">
                                    <div class="btn-group flex-wrap justify-content-center">' . $buttons . '
                                    </div>
                                </div>
                            </nav>
                        </div>
                    </div>';
}

/**
 * Checks the posted name: required, at most 100 characters (the
 * contact_groups.name column; a longer name would be cut on save, so two
 * long names sharing their first 100 characters would pass the duplicate
 * check and be stored as one), not used by another contact group.
 * Errors are marked on the form.
 *
 * @param liveform $liveform
 * @param string|null $id  Edited group's id, null on create
 */
function pg_contact_group_form_validate_name($liveform, $id)
{
    $liveform->validate_required_field('name', lang(array('string'=>'{var:1} is required','vars'=>lang('Name'))));

    if ($liveform->check_field_error('name') == true) {
        return;
    }

    if (mb_strlen((string) $liveform->get_field_value('name'), 'UTF-8') > 100) {
        $liveform->mark_error('name', lang(array('string' => 'The name can be at most {var:1} characters long.', 'vars' => array(100))));
        return;
    }

    if ($id === null) {
        $result = db_q("SELECT id FROM contact_groups WHERE (name = ?)", array((string) $liveform->get_field_value('name'))) or output_error('Query failed.');
    } else {
        $result = db_q("SELECT id FROM contact_groups WHERE (name = ?) AND (id != ?)", array((string) $liveform->get_field_value('name'), (string) $id)) or output_error('Query failed.');
    }

    if (mysqli_num_rows($result) > 0) {
        $liveform->mark_error('name', lang('The name that you entered is already in use, so please enter a different name.'));
    }
}

/**
 * The four values a save writes, in INSERT/UPDATE order: name, e-mail
 * subscription, subscription type, description.
 *
 * @param liveform $liveform
 * @return array
 */
function pg_contact_group_form_values($liveform)
{
    return array(
        (string) $liveform->get_field_value('name'),
        (string) $liveform->get_field_value('email_subscription'),
        (string) $liveform->get_field_value('email_subscription_type'),
        (string) $liveform->get_field_value('description'),
    );
}

/**
 * The "Members by rule" card of the edit screen: the switch that makes the
 * group fill itself, the rule editor (assets/js/pg_audience_editor.js), the
 * live count, the last run and Refresh now. A viewer who may not edit rules
 * (the user role) sees a read-only line for a group filled by rules and no
 * card for another.
 *
 * @param liveform $liveform kind and rules as the form holds them
 * @param array    $group    the stored group: id, kind, computed_at, compute_cursor
 * @param bool     $may_edit
 * @param array|null $review pg_audience_switch_counts() of a static group:
 *                   its hand, form and product rows, reviewed on the switch
 * @return string
 */
function pg_contact_group_rules_card($liveform, array $group, $may_edit, $review = null)
{
    $stored_dynamic = (($group['kind'] ?? 'static') === 'dynamic');

    if (!$may_edit) {
        if (!$stored_dynamic) {
            return '';
        }

        return '
                            <div class="alert theme-info mb-7" role="note">' . h(lang('The rules of this group decide who is in it; a Manager or above can change them.')) . '</div>';
    }

    $dynamic = ($liveform->get_field_value('kind') === 'dynamic');
    $rules = pg_audience_rules_clean((string) $liveform->get_field_value('rules'));

    // HTML: get_relative_time() writes a <time> element with the full date
    // as its title, so only the plain texts are escaped.
    if (!empty($group['compute_cursor'])) {
        $last_run = h(lang('A refresh is under way.'));
    } elseif (!empty($group['computed_at'])) {
        $last_run = lang(array('string' => 'Last refreshed {var:1}.', 'vars' => array(get_relative_time(array('timestamp' => $group['computed_at'])))));
    } else {
        $last_run = h(lang('Not refreshed yet.'));
    }

    $config = array(
        'catalogue' => pg_audience_editor_data(),
        'rules' => $rules,
        'labels' => pg_audience_ref_labels($rules),
        'source' => 'edit_contact_group.php?request=pick&kind=',
        'group' => (int) $group['id'],
        'text' => array(
            'match' => lang('Contacts that match'),
            'all' => lang('all'),
            'any' => lang('any'),
            'of' => lang('of these conditions'),
            'nested' => lang('Group of conditions'),
            'add' => lang('Add condition'),
            'add_nested' => lang('Add a group of conditions'),
            'remove' => lang('Remove'),
            'condition' => lang('Condition'),
            'operator' => lang('Operator'),
            'value' => lang('Value'),
            'field' => lang('Field'),
            'days' => lang('days'),
            'window' => lang('within the last (days)'),
            'search' => lang('Type to search'),
            'counting' => lang('Counting…'),
            'count_failed' => lang('The contacts could not be counted.'),
            'refresh_failed' => lang('The group could not be refreshed.'),
            'refreshed' => lang('Refreshed: {added} added, {removed} removed, {members} members.'),
            'refreshing' => lang('Refreshing: {added} added, {removed} removed so far.'),
        ),
    );

    $output_refresh = '';
    $output_review = '';

    // A static group about to be switched: its other rows stay unless the
    // switch is saved with one of the two actions.
    if (!$stored_dynamic && is_array($review) && ($review['total'] > 0)) {
        $choice = pg_audience_switch_action(array('switch_rows' => $liveform->get_field_value('switch_rows')));
        $options = array(
            'keep' => lang('Keep them'),
            'remove' => lang('Remove them'),
            'to_rule' => lang('Let the rules decide them'),
        );
        $output_options = '';

        foreach ($options as $value => $label) {
            $output_options .= '
                                            <div class="my-1">
                                                <input type="radio" class="radio" name="switch_rows" id="switch_rows_' . $value . '" value="' . $value . '"' . (($value === $choice) ? ' checked' : '') . '>
                                                <label for="switch_rows_' . $value . '">' . h($label) . '</label>
                                            </div>';
        }

        $output_review = '
                                        <fieldset class="alert theme-warning my-12" id="pg_audience_switch_review">
                                            <legend class="fs-md fw-bold">' . h(lang(array('string' => 'This group already has members: {var:1}.', 'vars' => array(pg_audience_switch_review_text($review['counts']))))) . '</legend>
                                            <p class="form-text">' . h(lang('Rules never remove these members. Remove them now, or let the rules decide them: those the rules do not select leave at the next refresh.')) . '</p>' . $output_options . '
                                        </fieldset>';
    }

    if ($stored_dynamic) {
        $output_refresh = '
                                            <button type="button" id="pg_audience_refresh" class="btn btn-sm btn-outline theme-primary"><span class="material-icons me-3" aria-hidden="true">refresh</span><span class="pg-btn-label">' . h(lang('Refresh now')) . '</span></button>
                                            <span class="form-text">' . h(lang('Refresh now uses the saved rules.')) . '</span>';
    }

    return '
                            <div class="card mb-7" id="pg_audience_card">
                                <h2 class="card-header bg-reset border-0 pg-title-section">
                                    ' . lang('Members by rule') . '
                                </h2>
                                <div class="card-body">
                                    <div class="form-field">
                                        <div class="switch"><input type="checkbox" name="kind" id="kind" value="dynamic" class="collapse-switcher no-bs" data-bs-target="#pg_audience_rules_row"' . ($dynamic ? ' checked="checked"' : '') . '></div>
                                        <label for="kind">' . lang('Fill this group by rules') . '</label>
                                        <div class="form-text">' . lang('Contacts who match the rules are added and taken out by themselves, about once an hour. Contacts added by hand, by a form or by a product stay.') . '</div>
                                    </div>
                                    <div class="collapse" id="pg_audience_rules_row">
                                        ' . $output_review . '
                                        <input type="hidden" name="rules" id="rules" value="' . h(json_encode($rules, JSON_UNESCAPED_UNICODE)) . '">
                                        <div id="pg_audience_editor" class="my-5" data-pg-audience="' . h(json_encode($config, JSON_UNESCAPED_UNICODE)) . '"></div>
                                        <p class="fw-bold my-3" id="pg_audience_count" role="status" aria-live="polite"></p>
                                        <div class="d-flex flex-wrap align-items-center gap-3 my-3">
                                            <span id="pg_audience_last_run">' . $last_run . '</span>' . $output_refresh . '
                                            <span id="pg_audience_refresh_status" role="status" aria-live="polite"></span>
                                        </div>
                                    </div>
                                </div>
                            </div>';
}

/**
 * The script tags the rule card needs: the field wrappers for the pickers
 * and the editor.
 *
 * @return string
 */
function pg_contact_group_rules_scripts()
{
    return pg_fields_script()
        . '<script src="' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/assets/js/pg_audience_editor.js?v=' . (int) @filemtime(PG_FUNCTIONS_DIR . '/assets/js/pg_audience_editor.js') . '" defer></script>';
}
