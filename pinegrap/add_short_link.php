<?php
/**
 * PineGrap - Enterprise Website Platform
 *
 * Originally developed as LiveSite by Camelback Web Architects.
 * Since 2017, maintained and evolved by Erdal Güral (Kodpen) under the name PineGrap.
 * The final LiveSite update (2019) has been integrated into PineGrap.
 * LiveSite remains available as a separate downloadable legacy version.
 *
 * @author      Camelback Web Architects
 *              Erdal Güral (Kodpen)
 * @link        https://livesite.com
 *              https://kodpen.com
 * @copyright   2001–2019 Camelback Consulting, Inc.
 *              2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

include('init.php');
$user = validate_user();
validate_area_access($user, 'manager');

include_once('liveform.class.php');
require_once(PG_FUNCTIONS_DIR . '/includes/forms/short_link_form.php');
$liveform = new liveform('add_short_link');

// if the form has not been submitted
if (!$_POST) {
    // Opened from the 404 log (pg_not_found_log_redirect_url()): the missing
    // address as the name, answering 301. Only a form not filled in yet takes
    // it, and the save checks the name as it checks any other.
    if (($liveform->get_field_value('name') == '') && isset($_GET['name']) && is_string($_GET['name'])) {
        $liveform->assign_field_value('name', pg_short_link_name_from_address($_GET['name']));

        if (pg_short_link_redirects_ready() && isset($_GET['redirect_code']) && is_string($_GET['redirect_code'])
            && in_array((int) $_GET['redirect_code'], pg_short_link_answer_codes(), true)) {
            $liveform->assign_field_value('redirect_code', (string) (int) $_GET['redirect_code']);
        }
    }

    // How it opens (2026.4.5, 5.112): for good, once (a generated address, no
    // name to type) or until a time chosen here.
    $output_link_mode = '';

    if (pg_short_link_modes_ready()) {
        $link_mode_options = array(
            lang('Permanent: an address you name, for good') => 'permanent',
            lang('One-time: a generated address, for the first visit only') => 'once',
            lang('Timed: an address you name, until a time you choose') => 'timed');

        $duration_options = array();

        foreach (pg_short_link_durations() as $duration_seconds => $duration_label) {
            $duration_options[$duration_label] = $duration_seconds;
        }

        if ($liveform->get_field_value('link_mode') == '') {
            $liveform->assign_field_value('link_mode', 'permanent');
            $liveform->assign_field_value('duration', 604800);
        }

        $output_link_mode = '
                                                <div class="col-12 md:col-8 lg:col-6 my-3" id="link_mode_row">
                                                    <label for="link_mode" class="form-label">' . lang('How it opens') . '</label>
                                                    ' . $liveform->output_field(array('type'=>'select', 'name'=>'link_mode', 'id'=>'link_mode', 'class'=>'form-control', 'options'=>$link_mode_options, 'onchange'=>'change_short_link_mode(this.options[this.selectedIndex].value)')) . '
                                                </div>
                                                <div class="col-12 md:col-4 lg:col-3 my-3" id="duration_row" style="display: none">
                                                    <label for="duration" class="form-label">' . lang('Valid for') . '</label>
                                                    ' . $liveform->output_field(array('type'=>'select', 'name'=>'duration', 'id'=>'duration', 'class'=>'form-control', 'options'=>$duration_options)) . '
                                                    <div class="invalid-feedback">' . lang('Required Area') . '</div>
                                                </div>
                                                <script>
                                                    // A one-time link has no name: its address is made for it.
                                                    function change_short_link_mode(mode) {
                                                        var name = document.getElementById(\'name\');
                                                        document.getElementById(\'name_row\').style.display = (mode === \'once\') ? \'none\' : \'\';
                                                        document.getElementById(\'duration_row\').style.display = (mode === \'timed\') ? \'\' : \'none\';
                                                        if (mode === \'once\') { name.removeAttribute(\'required\'); } else { name.setAttribute(\'required\', \'required\'); }
                                                    }
                                                    $(document).ready(function() {
                                                        change_short_link_mode($("select#link_mode option:selected").val());
                                                    });
                                                </script>';
    }

    // How it answers (2026.11.0, 11.0.12): at its own address, as short links
    // always did, or by sending the visitor to the destination's address.
    if (pg_short_link_redirects_ready() && ($liveform->get_field_value('redirect_code') == '')) {
        $liveform->assign_field_value('redirect_code', '0');
    }

    print

    pg_page_shell(
        array(
            'title'=> lang('Create Short Link'),
            'extra classes'=>'page',
            'icon'=>'page',
            'heading'=>lang('Create Short Link'),
            'heading_description' => lang('Create a shortcut alias for a Page, Product Group, Product, or URL.'),
            'cancel'=>array('enable'=>'true','url'=>'view_short_links.php'),
            'breadcrumb' => array(
                array('label' => lang('Short Links'), 'url' => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_short_links.php'),
                array('label' => lang('Create Short Link')),
            ),
        )
    )  . '
<main id="content" class="container-fluid">
            <div class="row">
            <div class="col-12">
                ' . $liveform->output_errors() . '
                ' . $liveform->output_notices() . '
                
                <form name="form" action="add_short_link.php" method="post">
                    ' . get_token_field() . '
                    ' . pg_short_link_form_cards($liveform, array('extra_rows' => $output_link_mode . '
                                                ' . pg_short_link_form_redirect_code_row($liveform))) . '
                    <nav class="buttons navigation text-center position-sticky mb-7" style="bottom:.5rem;" aria-label="' . h(lang('data edit buttons')) . '">
                        <div class="container">
                            <div class=" btn-group flex-wrap justify-content-center">
                                <button type="submit" id="create_button" name="submit_create" value="Create" class="btn my-1  btn-solid theme-success "><span class="bi bi-plus-circle me-3"></span><span class="pg-btn-label">' . lang(array('string'=>'Create') ) . '</span></button>
                            </div>
                        </div>
                    </nav>
                </form>
            </div>
        </div>
    
</main>' .
    output_footer();
    
    $liveform->remove_form();

} else {
    validate_token_field();
    
    $liveform->add_fields_to_session();

    // A field posted as a list (product_id[]=x) counts as not given: the
    // escaping and the checks below stopped the request on it with a 500.
    pg_liveform_single_values($liveform, array('name', 'destination_type', 'page_id', 'catalog_page_id', 'catalog_detail_page_id', 'product_group_id', 'product_id', 'url', 'file', 'tracking_code', 'link_mode', 'duration', 'redirect_code'));

    $handle = @fopen(HTACCESS_FILE_PATH, 'a');

    // If the rewrite file does not exist or can not be written to, then add error.
    if ($handle == FALSE) {
        $liveform->mark_error('', lang(array('string'=>'{var:1} is not writeable so a short link cannot be added.','vars'=>HTACCESS_FILE_NAME)) );

    // Otherwise the rewrite file is writeable, so close handle.
    } else {
        @fclose($handle);
    }
    
    // How it opens (2026.4.5, 5.112): for good, once (a generated address, no
    // name) or until a time chosen here.
    $link_mode = pg_short_link_modes_ready() ? (string) $liveform->get_field_value('link_mode') : 'permanent';

    if (!in_array($link_mode, array('permanent', 'once', 'timed'), true)) {
        $link_mode = 'permanent';
    }

    $link_expires = 0;

    if ($link_mode === 'timed') {
        $link_duration = (int) $liveform->get_field_value('duration');

        if (!isset(pg_short_link_durations()[$link_duration])) {
            $liveform->mark_error('duration', lang('Choose how long the link is valid.'));
        } else {
            $link_expires = time() + $link_duration;
        }
    }

    $name = '';

    if ($link_mode !== 'once') {
        $name = pg_short_link_form_validate_name($liveform, null);
    }

    $page_id = pg_short_link_form_validate_destination($liveform, null);

    // How it answers, checked with the name and the destination it goes
    // with: an address may not lead back to the link or round a loop.
    $short_link_answer = pg_short_link_form_answer($liveform, 0, $name, 0);

    // If there is an error, forward user back to previous screen.
    if ($liveform->check_form_errors() == TRUE) {
        header('Location: ' . URL_SCHEME . HOSTNAME . PATH . SOFTWARE_DIRECTORY . '/add_short_link.php');
        exit();
    }

    $sql_field = "";
    $sql_value = "";
    $sql_values = array();

    // The destination's own column; a page keeps its target in page_id.
    $destination_column = pg_short_link_form_destination_column($liveform->get_field_value('destination_type'));

    if ($destination_column !== null) {
        $sql_field = $destination_column[0] . ",";
        $sql_value = "?,";
        $sql_values[] = (string) $liveform->get_field_value($destination_column[1]);
    }
    
    // Once or until a time; a one-time link gets a token for its address,
    // kept only as its hash.
    $link_token = '';

    if (pg_short_link_modes_ready()) {
        $sql_field .= "link_mode, expires_at,";
        $sql_value .= "?, ?,";
        $sql_values[] = (string) $link_mode;
        $sql_values[] = (string) (int) $link_expires;

        if ($link_mode === 'once') {
            $link_token = pg_short_link_new_token();
            $sql_field .= "token_hash, token_hint,";
            $sql_value .= "?, ?,";
            $sql_values[] = hash('sha256', $link_token);
            $sql_values[] = (string) substr($link_token, 0, 6);
        }
    }

    foreach ($short_link_answer['columns'] as $answer_column => $answer_value) {
        $sql_field .= $answer_column . ",";
        $sql_value .= "?,";
        $sql_values[] = (string) (int) $answer_value;
    }

    // create short link
    $query =
        "INSERT INTO short_links (
            name,
            destination_type,
            page_id,
            " . $sql_field . "
            tracking_code,
            created_user_id,
            created_timestamp,
            last_modified_user_id,
            last_modified_timestamp)
        VALUES (
            ?,
            ?,
            ?,
            " . $sql_value . "
            ?,
            ?,
            UNIX_TIMESTAMP(),
            ?,
            UNIX_TIMESTAMP())";
    $result = db_q($query, array_merge(
        array((string) $name, (string) $liveform->get_field_value('destination_type'), (string) $page_id),
        $sql_values,
        array((string) $liveform->get_field_value('tracking_code'), (string) USER_ID, (string) USER_ID)
    )) or output_error('Query failed.');
    
    log_activity(lang(array('string'=>'short link ({var:1}) was created','vars'=>($link_token !== '') ? substr($link_token, 0, 6) . '…' : $name)), $_SESSION['sessionusername']);
    $liveform->remove_form();

    $liveform_view_short_links = new liveform('view_short_links');

    // The whole address of a one-time link, this once: only its hash is kept.
    if ($link_token !== '') {
        $liveform_view_short_links->add_notice(lang('The one-time link has been created. Its address is shown only now; copy it and send it:')
            . ' <code class="user-select-all">' . h(URL_SCHEME . HOSTNAME_SETTING . PATH . $link_token) . '</code>');
    } else {
        $liveform_view_short_links->add_notice(lang('The short link has been created.'));
    }

    // forward user to view short links screen
    header('Location: ' . URL_SCHEME . HOSTNAME . PATH . SOFTWARE_DIRECTORY . '/view_short_links.php');
    
    
}
?>