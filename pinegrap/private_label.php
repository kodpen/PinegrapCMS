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
    
    // assume that the private label table should not be hidden by default until we find out otherwise
    $private_label_table_style = '';
    
    // if private label is disabled, then hide private label table by default
    if ($private_label == 0) {
        $private_label_table_style = '; display: none';
    }
    
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
                    <div class="row mb-3">
                        <div class="col-12">
                            <p class="text-muted">' . lang('Update the branding for the control panel.') . '</p>
                        </div>
                    </div>
            <form name="form" action="private_label.php" method="post" style="margin-bottom: 1em">
                ' . get_token_field() . '
                <script type="text/javascript">
                    function show_or_hide_private_label() {
                        if (document.getElementById("private_label").checked == true) {
                            document.getElementById("private_label_table").style.display = "";
                        } else {
                            document.getElementById("private_label_table").style.display = "none";
                        }
                    }
                </script>
                <div style="margin-bottom: 1em"><label for="private_label">' . lang('Enable Private Label') . ': </label>' . $liveform->output_field(array('type'=>'checkbox', 'name'=>'private_label', 'id'=>'private_label', 'value'=>'1', 'class'=>'checkbox', 'onclick'=>'show_or_hide_private_label()')) . '</div>
                <table id="private_label_table" style="margin-bottom: 1em; margin-left: 1em' . $private_label_table_style . '">
                    <tr>
                        <td>' . lang('Logo URL') . ':</td>
                        <td>' . $liveform->output_field(array('type'=>'text', 'name'=>'logo_url', 'size'=>'80')) . ' (' . lang('leave blank to use the default product logo') . ')</td>
                    </tr>
                    <tr>
                        <td>' . lang('Control Panel Stylesheet URL') . ':</td>
                        <td>' . $liveform->output_field(array('type'=>'text', 'name'=>'control_panel_stylesheet_url', 'size'=>'80')) . '</td>
                    </tr>
                    <tr>
                        <td colspan="2">&nbsp;</td>
                    </tr>
                    <tr>
                        <td>' . lang(array('string' => 'Footer Link {var:1} Label', 'vars' => array('1'))) . ':</td>
                        <td>' . $liveform->output_field(array('type'=>'text', 'name'=>'footer_link_1_label', 'size'=>'80')) . '</td>
                    </tr>
                    <tr>
                        <td>' . lang(array('string' => 'Footer Link {var:1} URL', 'vars' => array('1'))) . ':</td>
                        <td>' . $liveform->output_field(array('type'=>'text', 'name'=>'footer_link_1_url', 'size'=>'80')) . '</td>
                    </tr>
                    <tr>
                        <td colspan="2">&nbsp;</td>
                    </tr>
                    <tr>
                        <td>' . lang(array('string' => 'Footer Link {var:1} Label', 'vars' => array('2'))) . ':</td>
                        <td>' . $liveform->output_field(array('type'=>'text', 'name'=>'footer_link_2_label', 'size'=>'80')) . '</td>
                    </tr>
                    <tr>
                        <td>' . lang(array('string' => 'Footer Link {var:1} URL', 'vars' => array('2'))) . ':</td>
                        <td>' . $liveform->output_field(array('type'=>'text', 'name'=>'footer_link_2_url', 'size'=>'80')) . '</td>
                    </tr>
                    <tr>
                        <td colspan="2">&nbsp;</td>
                    </tr>
                    <tr>
                        <td>' . lang(array('string' => 'Footer Link {var:1} Label', 'vars' => array('3'))) . ':</td>
                        <td>' . $liveform->output_field(array('type'=>'text', 'name'=>'footer_link_3_label', 'size'=>'80')) . '</td>
                    </tr>
                    <tr>
                        <td>' . lang(array('string' => 'Footer Link {var:1} URL', 'vars' => array('3'))) . ':</td>
                        <td>' . $liveform->output_field(array('type'=>'text', 'name'=>'footer_link_3_url', 'size'=>'80')) . '</td>
                    </tr>
                    <tr>
                        <td colspan="2">&nbsp;</td>
                    </tr>
                    <tr>
                        <td>' . lang(array('string' => 'Footer Link {var:1} Label', 'vars' => array('4'))) . ':</td>
                        <td>' . $liveform->output_field(array('type'=>'text', 'name'=>'footer_link_4_label', 'size'=>'80')) . '</td>
                    </tr>
                    <tr>
                        <td>' . lang(array('string' => 'Footer Link {var:1} URL', 'vars' => array('4'))) . ':</td>
                        <td>' . $liveform->output_field(array('type'=>'text', 'name'=>'footer_link_4_url', 'size'=>'80')) . '</td>
                    </tr>
                    <tr>
                        <td colspan="2">&nbsp;</td>
                    </tr>
                    <tr>
                        <td>' . lang(array('string' => 'Footer Link {var:1} Label', 'vars' => array('5'))) . ':</td>
                        <td>' . $liveform->output_field(array('type'=>'text', 'name'=>'footer_link_5_label', 'size'=>'80')) . '</td>
                    </tr>
                    <tr>
                        <td>' . lang(array('string' => 'Footer Link {var:1} URL', 'vars' => array('5'))) . ':</td>
                        <td>' . $liveform->output_field(array('type'=>'text', 'name'=>'footer_link_5_url', 'size'=>'80')) . '</td>
                    </tr>
                </table>
                <nav class="buttons navigation text-center position-sticky mb-4" style="bottom:.5rem;" aria-label="data edit buttons">
                    <div class="container">
                        <div class="btn-group flex-wrap justify-content-center">
                            <button type="submit" name="submit_save" value="Save" class="btn my-1 btn-success"><span class="material-icons me-2">save</span><span class="pg-btn-label">' . lang('Save') . '</span></button>
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
    $query = "UPDATE config SET private_label = '" . escape($_POST['private_label'] ?? '') . "'";
    $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
    
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