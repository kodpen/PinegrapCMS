<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The login region form, shared by the create and the edit screens
 * (add_login_region.php, edit_login_region.php): the Options card, the
 * name check and the values a save writes. The screens keep what differs:
 * the edit screen loads the record, shows its body tag and offers delete.
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
 * The Options card: name between <login> tags, then the header, login form
 * switch and footer shown when not logged in, and the header and footer
 * shown when logged in.
 *
 * @param liveform $liveform
 * @param bool $defaults  Check Show Login Form as a new region's default
 * @return string
 */
function pg_login_region_form_card($liveform, $defaults = false)
{
    $checked = $defaults ? array('checked' => 'checked') : array();

    return '
                    <div class="row">
                        <div class="col-12">
                            <div class="card my-7">
                                <div class="card-header bg-reset border-0 text-uppercase h5 fg-primary fw-bold">
                                    ' . lang('Options') . '
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-12 md:col-6 my-3">
                                            <label for="name" class="form-label">' . lang(array('string'=>'{var:1} Name','vars'=>lang('Login Region'))) . '</label>
                                            <div class="input-group">
                                                <div class="input-group-text">' . h('<login>') . '</div>
                                                ' . $liveform->output_field(array('type'=>'text', 'name'=>'name', 'id'=>'name', 'class'=>'form-control', 'maxlength'=>'100')) . '
                                                <div class="input-group-text">' . h('</login>') . '</div>
                                            </div>
                                        </div>
                                        <h5 class="mt-12">' . lang('Not Logged In') . '</h5>
                                        <div class="col-12 my-3">
                                            <label for="not_logged_in_header" class="form-label">' . lang('Header') . '</label>
                                            ' . $liveform->output_field(array('type'=>'textarea', 'name'=>'not_logged_in_header', 'id'=>'not_logged_in_header', 'style'=>'width: 600px; height: 200px')) . '
                                            ' . pg_code_editor_attach(array('id' => 'not_logged_in_header', 'code_type' => 'mixed', 'height' => '200px')) . '
                                        </div>
                                        <div class="col-12 my-3">
                                            <div class="form-field">
                                                <div class="switch">' . $liveform->output_field($checked + array('type'=>'checkbox', 'name'=>'login_form', 'id'=>'login_form', 'value'=>'1', 'class'=>'no-bs')) . '</div>
                                                <label class="" for="login_form">' . lang('Show Login Form') . '</label>
                                            </div>
                                        </div>
                                        <div class="col-12 my-3">
                                            <label for="not_logged_in_footer" class="form-label">' . lang('Footer') . '</label>
                                            ' . $liveform->output_field(array('type'=>'textarea', 'name'=>'not_logged_in_footer', 'id'=>'not_logged_in_footer', 'style'=>'width: 600px; height: 200px')) . '
                                            ' . pg_code_editor_attach(array('id' => 'not_logged_in_footer', 'code_type' => 'mixed', 'height' => '200px')) . '
                                        </div>
                                        <h5 class="mt-12">' . lang('Logged In') . '</h5>
                                        <div class="col-12 my-3">
                                            <label for="logged_in_header" class="form-label">' . lang('Header') . '</label>
                                            ' . $liveform->output_field(array('type'=>'textarea', 'name'=>'logged_in_header', 'id'=>'logged_in_header', 'style'=>'width: 600px; height: 200px')) . '
                                            ' . pg_code_editor_attach(array('id' => 'logged_in_header', 'code_type' => 'mixed', 'height' => '200px')) . '
                                        </div>
                                        <div class="col-12 my-3">
                                            <label for="logged_in_footer" class="form-label">' . lang('Footer') . '</label>
                                            ' . $liveform->output_field(array('type'=>'textarea', 'name'=>'logged_in_footer', 'id'=>'logged_in_footer', 'style'=>'width: 600px; height: 200px')) . '
                                            ' . pg_code_editor_attach(array('id' => 'logged_in_footer', 'code_type' => 'mixed', 'height' => '200px')) . '
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
';
}

/**
 * The name check a save runs: required, and not used by another login
 * region. The uniqueness query runs only when a name was given.
 *
 * @param liveform $liveform
 * @param string|null $id  The region being edited, null on create
 * @return void
 */
function pg_login_region_form_validate_name($liveform, $id)
{
    $liveform->validate_required_field('name', lang('Name is required.'));

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
