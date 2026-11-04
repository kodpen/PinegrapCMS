<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The login region form, shared by the create and the edit screens
 * (add_login_region.php, edit_login_region.php): the form column with the
 * Options card, the sticky status column with the Summary, the sticky save
 * bar, the name check and the values a save writes. The screens keep what
 * differs: the edit screen loads the record, adds its last change to the
 * Summary and offers delete.
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
 * The form column with the Options card: name between <login> tags, then
 * the header, login form switch and footer shown when not logged in, and
 * the header and footer shown when logged in.
 *
 * @param liveform $liveform
 * @param bool $defaults  Check Show Login Form as a new region's default
 * @return string
 */
function pg_login_region_form_card($liveform, $defaults = false)
{
    $checked = $defaults ? array('checked' => 'checked') : array();

    return '
                        <div class="col-12 lg:col-8 2xl:col-9 order-1">
                            <div class="card mb-7">
                                <h2 class="card-header bg-reset border-0 text-uppercase h5 fg-primary fw-bold">
                                    ' . lang('Options') . '
                                </h2>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-12 md:col-6 my-3">
                                            <label for="name" class="form-label">' . lang(array('string'=>'{var:1} Name','vars'=>lang('Login Region'))) . '</label>
                                            <div class="input-group">
                                                <div class="input-group-text">' . h('<login>') . '</div>
                                                ' . $liveform->output_field(array('type'=>'text', 'name'=>'name', 'id'=>'name', 'class'=>'form-control', 'maxlength'=>'100', 'required'=>'required')) . '
                                                <div class="input-group-text pg-tag-close">' . h('</login>') . '</div>
                                            </div>
                                        </div>
                                        <h3 class="h5 mt-12">' . lang('Not Logged In') . '</h3>
                                        <div class="col-12 my-3">
                                            <label for="not_logged_in_header" class="form-label">' . lang('Not Logged In Header') . '</label>
                                            ' . $liveform->output_field(array('type'=>'textarea', 'name'=>'not_logged_in_header', 'id'=>'not_logged_in_header', 'style'=>'height: 200px')) . '
                                            ' . pg_code_editor_attach(array('id' => 'not_logged_in_header', 'code_type' => 'mixed', 'height' => '200px')) . '
                                        </div>
                                        <div class="col-12 my-3">
                                            <div class="form-field">
                                                <div class="switch">' . $liveform->output_field($checked + array('type'=>'checkbox', 'name'=>'login_form', 'id'=>'login_form', 'value'=>'1', 'class'=>'no-bs')) . '</div>
                                                <label class="" for="login_form">' . lang('Show Login Form') . '</label>
                                            </div>
                                        </div>
                                        <div class="col-12 my-3">
                                            <label for="not_logged_in_footer" class="form-label">' . lang('Not Logged In Footer') . '</label>
                                            ' . $liveform->output_field(array('type'=>'textarea', 'name'=>'not_logged_in_footer', 'id'=>'not_logged_in_footer', 'style'=>'height: 200px')) . '
                                            ' . pg_code_editor_attach(array('id' => 'not_logged_in_footer', 'code_type' => 'mixed', 'height' => '200px')) . '
                                        </div>
                                        <h3 class="h5 mt-12">' . lang('Logged In') . '</h3>
                                        <div class="col-12 my-3">
                                            <label for="logged_in_header" class="form-label">' . lang('Logged In Header') . '</label>
                                            ' . $liveform->output_field(array('type'=>'textarea', 'name'=>'logged_in_header', 'id'=>'logged_in_header', 'style'=>'height: 200px')) . '
                                            ' . pg_code_editor_attach(array('id' => 'logged_in_header', 'code_type' => 'mixed', 'height' => '200px')) . '
                                        </div>
                                        <div class="col-12 my-3">
                                            <label for="logged_in_footer" class="form-label">' . lang('Logged In Footer') . '</label>
                                            ' . $liveform->output_field(array('type'=>'textarea', 'name'=>'logged_in_footer', 'id'=>'logged_in_footer', 'style'=>'height: 200px')) . '
                                            ' . pg_code_editor_attach(array('id' => 'logged_in_footer', 'code_type' => 'mixed', 'height' => '200px')) . '
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>';
}

/**
 * Whether Show Login Form is on as the card draws it: the stored or posted
 * value, or the default of a new region's first draw.
 *
 * @param liveform $liveform
 * @param bool $defaults  As for pg_login_region_form_card()
 * @return bool
 */
function pg_login_region_form_login_form_on($liveform, $defaults = false)
{
    return $defaults || ((string) $liveform->get_field_value('login_form') === '1');
}

/**
 * The sticky status column: a Summary of the body tag a page style uses and
 * whether the login form is shown, both following the boxes as they change,
 * then the screen's own rows (the last change on edit).
 *
 * @param liveform $liveform
 * @param bool $defaults  As for pg_login_region_form_card()
 * @param string $status_rows  More rows (pg_login_region_form_status_row()), HTML
 * @return string  HTML
 */
function pg_login_region_form_status($liveform, $defaults = false, $status_rows = '')
{
    $labels = array('1' => lang('Shown'), '' => lang('Hidden'));
    $login_form = pg_login_region_form_login_form_on($liveform, $defaults) ? '1' : '';

    return '
                        <div class="col-12 lg:col-4 2xl:col-3 order-2">
                            <!-- 3.3rem: the fixed control panel header covers the top of the window. -->
                            <div class="position-sticky" style="top:3.3rem;">
                                <div class="card mb-7">
                                    <h2 class="card-header bg-reset border-0 text-uppercase h5 fg-primary fw-bold">
                                        ' . lang('Summary') . '
                                    </h2>
                                    <div class="card-body pt-0">
                                        <dl class="mb-0">
                                            <div class="py-2 border-bottom">
                                                <dt class="fw-normal">' . lang('Page Style Body Tag') . '</dt>
                                                <dd class="mb-0 text-break"><code id="login_region_summary_tag">' . h('<login>' . (string) $liveform->get_field_value('name') . '</login>') . '</code></dd>
                                            </div>'
        . pg_login_region_form_status_row(lang('Login Form'), '<span id="login_region_summary_login_form" data-labels="' . h(json_encode($labels)) . '">' . h($labels[$login_form]) . '</span>', $status_rows === '')
        . $status_rows . '
                                        </dl>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <script>
                            // The Summary follows the name and the switch as they change.
                            $(document).on(\'input\', \'#name\', function () {
                                $(\'#login_region_summary_tag\').text(\'<login>\' + this.value + \'</login>\');
                            });
                            $(document).on(\'change\', \'#login_form\', function () {
                                var $shown = $(\'#login_region_summary_login_form\');
                                $shown.text($shown.data(\'labels\')[this.checked ? \'1\' : \'\']);
                            });
                        </script>';
}

/**
 * One Summary row of the status column: a label and its value.
 *
 * @param string $label  The label, plain text
 * @param string $value  The value, already HTML
 * @param bool   $last   False draws the line under the row
 * @return string  HTML
 */
function pg_login_region_form_status_row($label, $value, $last = true)
{
    return '
                                            <div class="d-flex justify-content-between gap-3 py-2' . ($last ? '' : ' border-bottom') . '">
                                                <dt class="fw-normal">' . h($label) . '</dt>
                                                <dd class="mb-0 fw-bold text-end text-break">' . $value . '</dd>
                                            </div>';
}

/**
 * The screen row: the form column, the status column and the sticky save
 * bar with the given buttons.
 *
 * @param string $columns pg_login_region_form_card() . pg_login_region_form_status()
 * @param string $buttons The buttons inside the bar's group
 * @return string  HTML
 */
function pg_login_region_form_layout($columns, $buttons)
{
    return '
                    <div class="row">' . $columns . '
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
 * The name check a save runs: required, at most 100 characters, and not used by another login
 * region. The uniqueness query runs only when a name was given.
 *
 * @param liveform $liveform
 * @param string|null $id  The region being edited, null on create
 * @return void
 */
function pg_login_region_form_validate_name($liveform, $id)
{
    $liveform->validate_required_field('name', lang('Name is required.'));

    // login_regions.name is varchar(100) and the session is not strict: a
    // longer name would be cut on save, past the uniqueness check below, and
    // two regions could end up with the same name.
    if (($liveform->check_field_error('name') == false) && pg_name_too_long($liveform->get_field_value('name'))) {
        $liveform->mark_error('name', lang(array('string' => 'The name can be at most {var:1} characters long.', 'vars' => array(pg_name_max_length()))));
    }

    if ($liveform->check_field_error('name') == false) {
        $query = "SELECT id FROM login_regions WHERE (name = ?)";
        $values = array((string) $liveform->get_field_value('name'));

        if ($id !== null) {
            $query .= " AND (id != ?)";
            $values[] = (string) $id;
        }

        $result = db_q($query, $values) or output_error('Query failed.');

        if (mysqli_num_rows($result) > 0) {
            $liveform->mark_error('name', lang('The name that you entered is already in use, so please enter a different name.'));
        }
    }
}

/**
 * The six login region values a save writes, in INSERT/UPDATE order: name,
 * not logged in header, login form, not logged in footer, logged in header,
 * logged in footer.
 *
 * @param liveform $liveform
 * @return array
 */
function pg_login_region_form_values($liveform)
{
    return array(
        (string) $liveform->get_field_value('name'),
        (string) $liveform->get_field_value('not_logged_in_header'),
        (string) $liveform->get_field_value('login_form'),
        (string) $liveform->get_field_value('not_logged_in_footer'),
        (string) $liveform->get_field_value('logged_in_header'),
        (string) $liveform->get_field_value('logged_in_footer'),
    );
}
