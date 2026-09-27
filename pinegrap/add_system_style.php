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
 *              2016–2025 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

include('init.php');
$user = validate_user();
// Creating a design is a design job: a content-level operator edits pages
// that already exist, they do not start new ones.
validate_area_access($user, 'designer');

include_once('liveform.class.php');
include_once('includes/designer_screen.php');

$liveform = new liveform('add_system_style');

$from_pages = (isset($_GET['from']) && $_GET['from'] === 'pages')
           || (isset($_POST['from']) && $_POST['from'] === 'pages')
           || ($liveform->field_in_session('from') && $liveform->get_field_value('from') === 'pages');

// Straight onto the canvas. ?start=import opens the file-import dialog on top
// of it and ?start=paste the paste-HTML box; the choice is made on
// view_system_styles.php.
$start = isset($_GET['start']) ? (string)$_GET['start'] : '';

// The framework the new design is built on (view_system_styles.php asks), or
// the template it starts from - a template brings its own framework. An
// import or a paste starts on Bootstrap 5, the framework the importer
// converts to.
$template = null;
if ($start === 'template') {
    $template = pg_design_template(isset($_GET['template']) ? $_GET['template'] : '');
    if (!$template) {
        $lf_view = new liveform('view_system_styles');
        $lf_view->add_error(lang('The template could not be found.'));
        header('Location: ' . URL_SCHEME . HOSTNAME . PATH . SOFTWARE_DIRECTORY . '/view_system_styles.php');
        exit();
    }
}
$framework = $template ? $template['framework']
                       : pg_design_framework_key(isset($_GET['framework']) ? $_GET['framework'] : '');

// The look and the palette chosen with the template (the template dialog
// sends them; ?look= may be empty for plain Bootstrap). A template opened
// without a choice wears the default look; a blank design starts plain.
$look = isset($_GET['look']) ? pg_design_look_key($_GET['look'])
                             : ($template ? pg_design_default_look() : '');
$palette = isset($_GET['palette']) ? pg_design_palette_key($_GET['palette']) : '';

if (!$_POST) {
    if (isset($_SESSION['software']['liveforms']['add_system_style'][0]) == FALSE) {
        $liveform->add_fields_to_session();
        if (SOCIAL_NETWORKING == TRUE) {
            $liveform->assign_field_value('social_networking_position', 'bottom_left');
        }
        $liveform->assign_field_value('collection', 'a');
    }
    $liveform->assign_field_value('id', 0);
    $liveform->assign_field_value('send_to', '');

    // A new design: no style row yet, one blank page tab. The first save
    // creates both and sends the browser to the edit screen.
    pg_designer_screen_render(array(
        'mode'           => 'add',
        'style_id'       => 0,
        'style'          => array(
            'name'                              => '',
            'theme_id'                          => $liveform->get_field_value('theme_id'),
            'collection'                        => $liveform->get_field_value('collection'),
            'social_networking_position'        => $liveform->get_field_value('social_networking_position'),
            'additional_body_classes'           => '',
            'style_head'                        => '',
            'style_empty_cell_width_percentage' => '',
            'style_custom_css'                  => '',
            'style_custom_js'                   => '',
            'style_custom_fonts'                => '',
            'last_modified_timestamp'           => 0,
            'last_modified_username'            => '',
            'framework'                         => $framework,
            'template'                          => $template ? $template['id'] : '',
            'template_version'                  => $template ? $template['version'] : '',
            'look'                              => $look,
            'palette'                           => $palette,
        ),
        'pages'          => array(),
        'active_page_id' => 0,
        'liveform'       => $liveform,
        'user'           => $user,
        'send_to'        => '',
        'from_pages'     => $from_pages,
        'delete_button'  => '',
        'auto_import'    => ($start === 'import'),
        'auto_paste'     => ($start === 'paste'),
        'template'       => $template ? pg_design_template_summary($template) : null,
    ));

    $liveform->remove_form();

} else {
    validate_token_field();
    $liveform->add_fields_to_session();

    pg_designer_screen_post(array(
        'mode'       => 'add',
        'style_id'   => 0,
        'liveform'   => $liveform,
        'user'       => $user,
        'from_pages' => $from_pages,
    ));
}
?>
