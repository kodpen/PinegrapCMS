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

// Validate the users access
validate_area_access($user, 'administrator');

include_once('liveform.class.php');
$liveform = new liveform('private_label');

// if the form has not been submitted
if (!$_POST) {
    // get private label value from config table in order to set check box value
    $query = "SELECT private_label FROM config";
    $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
    $row = mysqli_fetch_assoc($result);
    $private_label = $row['private_label'];
    
    $liveform->assign_field_value('private_label', $private_label);
    
    // The fields behind the switch start open when the private label is on;
    // the collapse switcher keeps them in step with the switch afterwards.
    $private_label_fields_show = ($private_label == 0) ? '' : ' show';
    
    // If LOGO_URL global variable is not set to the default value, display it to the user
    if (LOGO_URL != PATH . SOFTWARE_DIRECTORY . '/assets/images/logo.png?v=' . @filemtime(dirname(__FILE__) . '/assets/images/logo.png')) {
        $liveform->assign_field_value('logo_url', LOGO_URL);
    }
    
    // If BACKEND_STYLESHEET_URL global variable is not set to the default value, display it to the user
    if (CONTROL_PANEL_STYLESHEET_URL != pg_default_control_panel_stylesheet_url()) {
        $liveform->assign_field_value('control_panel_stylesheet_url', CONTROL_PANEL_STYLESHEET_URL);
    }
    
    // if there is a FOOTER_LINK_1_LABEL, then assign field value
    if (defined('FOOTER_LINK_1_LABEL') == true) {
        $liveform->assign_field_value('footer_link_1_label', FOOTER_LINK_1_LABEL);
    }
    
    // if there is a FOOTER_LINK_1_URL, then assign field value
    if (defined('FOOTER_LINK_1_URL') == true) {
        $liveform->assign_field_value('footer_link_1_url', FOOTER_LINK_1_URL);
    }
    
    // if there is a FOOTER_LINK_2_LABEL, then assign field value
    if (defined('FOOTER_LINK_2_LABEL') == true) {
        $liveform->assign_field_value('footer_link_2_label', FOOTER_LINK_2_LABEL);
    }
    
    // if there is a FOOTER_LINK_2_URL, then assign field value
    if (defined('FOOTER_LINK_2_URL') == true) {
        $liveform->assign_field_value('footer_link_2_url', FOOTER_LINK_2_URL);
    }
    
    // if there is a FOOTER_LINK_3_LABEL, then assign field value
    if (defined('FOOTER_LINK_3_LABEL') == true) {
        $liveform->assign_field_value('footer_link_3_label', FOOTER_LINK_3_LABEL);
    }
    
    // if there is a FOOTER_LINK_3_URL, then assign field value
    if (defined('FOOTER_LINK_3_URL') == true) {
        $liveform->assign_field_value('footer_link_3_url', FOOTER_LINK_3_URL);
    }
    
    // if there is a FOOTER_LINK_4_LABEL, then assign field value
    if (defined('FOOTER_LINK_4_LABEL') == true) {
        $liveform->assign_field_value('footer_link_4_label', FOOTER_LINK_4_LABEL);
    }
    
    // if there is a FOOTER_LINK_4_URL, then assign field value
    if (defined('FOOTER_LINK_4_URL') == true) {
        $liveform->assign_field_value('footer_link_4_url', FOOTER_LINK_4_URL);
    }
    
    // if there is a FOOTER_LINK_5_LABEL, then assign field value
    if (defined('FOOTER_LINK_5_LABEL') == true) {
        $liveform->assign_field_value('footer_link_5_label', FOOTER_LINK_5_LABEL);
    }
    
    // if there is a FOOTER_LINK_5_URL, then assign field value
    if (defined('FOOTER_LINK_5_URL') == true) {
        $liveform->assign_field_value('footer_link_5_url', FOOTER_LINK_5_URL);
    }
    
    // The five footer links, each a label and a URL side by side.
    $output_footer_link_rows = '';

    for ($footer_link = 1; $footer_link <= 5; $footer_link++) {
        $output_footer_link_rows .= '
                                    <div class="row">
                                        <div class="col-12 md:col-6 my-3">
                                            <label for="footer_link_' . $footer_link . '_label" class="form-label">' . lang(array('string' => 'Footer Link {var:1} Label', 'vars' => array((string) $footer_link))) . '</label>
                                            ' . $liveform->output_field(array('type'=>'text', 'name'=>'footer_link_' . $footer_link . '_label', 'id'=>'footer_link_' . $footer_link . '_label', 'class'=>'form-control')) . '
                                        </div>
                                        <div class="col-12 md:col-6 my-3">
                                            <label for="footer_link_' . $footer_link . '_url" class="form-label">' . lang(array('string' => 'Footer Link {var:1} URL', 'vars' => array((string) $footer_link))) . '</label>
                                            ' . $liveform->output_field(array('type'=>'text', 'name'=>'footer_link_' . $footer_link . '_url', 'id'=>'footer_link_' . $footer_link . '_url', 'class'=>'form-control')) . '
                                        </div>
                                    </div>';
    }

    print
        pg_page_shell([
            'title'=> lang('Private Label Branding'),
            'extra classes'=>'settings',
            'icon'=>'settings',
            'heading'=>lang('Private Label Branding')
        ]) . '
<main id="content" class="container-fluid">
                    <div class="row">
                <div class="col-12">
                    ' . $liveform->output_errors() . '
                    ' . $liveform->output_notices() . '
                    <div class="row mb-5">
                        <div class="col-12">
                            <p class="fg-secondary">' . lang('Update the branding for the control panel.') . '</p>
                        </div>
                    </div>
            <form name="form" action="private_label.php" method="post">
                ' . get_token_field() . '
                <div class="row">
                    <div class="col-12">
                        <div class="card my-7">
                            <div class="card-header bg-reset border-0 text-uppercase h5 fg-primary fw-bold">
                                ' . lang('Branding') . '
                            </div>
                            <div class="card-body">
                                <div class="form-field my-3">
                                    <div class="switch">' . $liveform->output_field(array('type'=>'checkbox', 'name'=>'private_label', 'id'=>'private_label', 'value'=>'1', 'class'=>'collapse-switcher no-bs', 'data-bs-target'=>'#private_label_fields')) . '</div>
                                    <label for="private_label">' . lang('Enable Private Label') . '</label>
                                </div>
                                <div class="collapse' . $private_label_fields_show . '" id="private_label_fields">
                                    <div class="row">
                                        <div class="col-12 md:col-6 my-3">
                                            <label for="logo_url" class="form-label">' . lang('Logo URL') . '</label>
                                            ' . $liveform->output_field(array('type'=>'text', 'name'=>'logo_url', 'id'=>'logo_url', 'class'=>'form-control')) . '
                                            <div class="form-text">' . lang('leave blank to use the default product logo') . '</div>
                                        </div>
                                        <div class="col-12 md:col-6 my-3">
                                            <label for="control_panel_stylesheet_url" class="form-label">' . lang('Control Panel Stylesheet URL') . '</label>
                                            ' . $liveform->output_field(array('type'=>'text', 'name'=>'control_panel_stylesheet_url', 'id'=>'control_panel_stylesheet_url', 'class'=>'form-control')) . '
                                        </div>
                                    </div>
                                    <h3 class="h6 fw-bold mt-5">' . lang('Footer Links') . '</h3>
                                    ' . $output_footer_link_rows . '
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <nav class="buttons navigation text-center position-sticky mb-7" style="bottom:.5rem;" aria-label="' . h(lang('data edit buttons')) . '">
                    <div class="container">
                        <div class="btn-group flex-wrap justify-content-center">
                            <button type="submit" name="submit_save" value="Save" class="btn my-1 btn-solid theme-success"><span class="material-icons me-3" aria-hidden="true">save</span><span class="pg-btn-label">' . lang('Save') . '</span></button>
                        </div>
                    </div>
                </nav>
            </form>
                </div>
            </div>
        
</main>' . output_footer();
    
    $liveform->remove_form();

// else the form has been submitted
} else {
    validate_token_field();

    // Every field here holds one value. One posted as a list (logo_url[]=x)
    // stopped the request in escape() or handed a list to the config.php
    // writer; reading it as not given would switch the private label off or
    // remove that setting. Nothing is written, so every setting stays as it
    // was.
    $private_label_lists = pg_request_unexpected_lists($_POST);

    if ($private_label_lists) {
        $liveform->add_error(lang(array('string' => 'Sorry, the value you entered for {var:1} is not valid.', 'vars' => h(implode(', ', $private_label_lists)))));
        header('Location: ' . URL_SCHEME . $_SERVER['HTTP_HOST'] . PATH . SOFTWARE_DIRECTORY . '/private_label.php');
        exit;
    }

    // update the private label value in the config table
    $query = "UPDATE config SET private_label = ?";
    $result = db_q($query, array((string) ($_POST['private_label'] ?? ''))) or output_error('Query failed.');
    
    $config_file_content = '';
    
    // Open the config file and read its contents
    $fd = @fopen(CONFIG_FILE_PATH, "r");
    // if the config file was able to be opened
    if ($fd) {
        while (!feof ($fd))
        {
            // Read the config file parts into a variable
            $config_file_content .= fgets($fd, 4096);
        }
        fclose ($fd);
    }

    $config_file_original = $config_file_content;

    // Each value is stored as typed (pg_private_label_config_content()):
    // update_config_define() escapes apostrophes and backslashes, and the
    // panel prints these values with h(), so nothing is removed from them.
    // The constants as this request loaded them tell a changed value from
    // one left as it was.
    $private_label_current = array();

    foreach (pg_private_label_config_keys() as $constant) {
        $private_label_current[$constant] = defined($constant) ? (string) constant($constant) : null;
    }

    $config_file_content = pg_private_label_config_content(
        $config_file_content,
        (($_POST['private_label'] ?? '') == 1),
        $_POST,
        $private_label_current
    );

    // Rewrite the config file only from a successful read: an empty result
    // would replace config.php with an empty file. The write itself goes
    // through a temp file and rename, so a failure leaves the old file intact.
    if ($config_file_content === '') {
        $liveform->add_error(lang('Config file could not be opened.'));
    } elseif (($config_file_content !== $config_file_original) && !pg_write_config_file($config_file_content)) {
        $liveform->add_error(lang('The config file could not be written.'));
    }
    
    // Send the user back to private_label.php
    if (!$liveform->check_form_errors()) {
        $liveform->add_notice(lang('The private label settings have been saved.'));
    }
    header('Location: ' . URL_SCHEME . $_SERVER['HTTP_HOST'] . PATH . SOFTWARE_DIRECTORY . '/private_label.php');
    exit;
}
?>