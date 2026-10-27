<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// A module of functions.php: The WYSIWYG editor, the image editor and the code editor includes, rich text preparation.
//
// Loaded by functions.php through require_once, never on its own. The
// functions were moved here from functions.php as they were; PG_FUNCTIONS_DIR
// is the software directory (what dirname(__FILE__) was in functions.php),
// see the note at the top of functions.php.
if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}
// get the javascript code for initializing tiny_mce wysiwyg editors
// You can pass a $style_theme_name in order to override the activated theme that is loaded for the rich-text editor.
// If a theme is currently being previewed, then that will be used instead of the style theme.
function get_wysiwyg_editor_code($editor_ids, $activate_editors = true, $folder_id = 0, $edit_region_dialog = false, $style_theme_name = '')
{
    // get values for page editor buttons from config table so that we will know which buttons to output
    $query = "SELECT
            page_editor_version,
            page_editor_font,
            page_editor_font_size,
            page_editor_font_style,
            page_editor_font_color,
            page_editor_background_color
        FROM config";
    $result = mysqli_query(db::$con, $query) or output_error(lang('Query failed.'));
    $row = mysqli_fetch_assoc($result);
    $page_editor_version = $row['page_editor_version'];
    $page_editor_font_style = $row['page_editor_font_style'];
    $page_editor_font = $row['page_editor_font'];
    $page_editor_font_size = $row['page_editor_font_size'];
    $page_editor_font_color = $row['page_editor_font_color'];
    $page_editor_background_color = $row['page_editor_background_color'];

    if ($page_editor_version == 'latest') {
        // if editor version is latest entegrate ckeditor 4
        $theme_name = '';
        // If the visitor is previewing a theme, then use that theme.
        if (isset($_SESSION['software']['preview_theme_id']) == true) {
            // If the visitor has selected a theme to preview, then use that theme.
            // This check is necessary because a user might have selected the none theme in the previous pick list.
            if ($_SESSION['software']['preview_theme_id'] != '') {
                $theme_name = db_value("SELECT name FROM files WHERE id = '" . escape($_SESSION['software']['preview_theme_id']) . "'");
            }
            // Otherwise if there is a style theme name, then use that.
        } else if ($style_theme_name != '') {
            $theme_name = $style_theme_name;
            // Otherwise use activated theme.
        } else {
            // get theme name differently based on the device type (i.e. desktop or mobile)
            switch (isset($_SESSION['software']['device_type']) ? $_SESSION['software']['device_type'] : 'desktop') {
                // if the device type is desktop then get the activated desktop theme name
                case 'desktop':
                default:
                    $theme_name = db_value("SELECT name FROM files WHERE activated_desktop_theme = '1'");
                    break;
                // if the device type is mobile, then get the activated mobile theme name
                // and fall back to the activated desktop theme if necessary
                case 'mobile':
                    $theme_name = db_value("SELECT name FROM files WHERE activated_mobile_theme = '1'");
                    // if an activated mobile theme could not be found, then get activated desktop theme name
                    if ($theme_name == '') {
                        $theme_name = db_value("SELECT name FROM files WHERE activated_desktop_theme = '1'");
                    }
                    break;
            }
        }
        $output_custom_format_toolbar_property = '';
        $output_editor_properties = '';
        $custom_formats_found = false;
        // If a theme was found and the theme file exists, then use stylesheet for editor.
        if (($theme_name != '') && (file_exists(FILE_DIRECTORY_PATH . '/' . $theme_name) == true)) {
            $output_editor_properties .= 'contentsCss: ["' . PATH . h($theme_name) . '", "' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/assets/lib/ckeditor_4_20/contents.css"]';
            $output_custom_formats = '';
            $custom_formats = get_custom_formats($theme_name);
            // if there is at least one custom format, then loop through all custom formats in order to prepare lists for editor
            if (count($custom_formats) > 0) {
                $output_custom_format_toolbar_property = '["Styles"],';
                // loop through the custom formats in order to prepare list of data
                foreach ($custom_formats as $custom_format) {
                    $output_class_name = escape_javascript($custom_format['name']);
                    // if this is not the first custom format, then add separation
                    if ($output_custom_formats != '') {
                        $output_custom_formats .= ',';
                    }
                    // prepare style format for this custom format differently based on its class name
                    // we do special things for the default custom formats
                    switch ($custom_format['name']) {
                        case 'heading-primary':
                        case 'heading-secondary':
                            $output_custom_formats .= '{name: "' . $output_class_name . '", element: ["h1", "h2", "h3", "h4", "h5", "h6"], attributes: {"class": "' . $output_class_name . '"}}';
                            break;
                        case 'image-primary':
                        case 'image-secondary':
                        case 'image-left-primary':
                        case 'image-left-secondary':
                        case 'image-right-primary':
                        case 'image-right-secondary':
                        case 'image-mobile-hide':
                        case 'image-desktop-hide':
                            $output_custom_formats .= '{name: "' . $output_class_name . '", element: "img", attributes: {"class": "' . $output_class_name . '"}}';
                            break;
                        case 'link-button-primary-large':
                        case 'link-button-primary-small':
                        case 'link-button-secondary-large':
                        case 'link-button-secondary-small':
                        case 'link-content-more':
                        case 'link-menu-item':
                        case 'link-mobile-hide':
                        case 'link-desktop-hide':
                        case 'list-accordion-expanded':
                            $output_custom_formats .= '{name: "' . $output_class_name . '", element: "a", attributes: {"class": "' . $output_class_name . '"}}';
                            break;
                        case 'list-accordion':
                        case 'list-tabs':
                            $output_custom_formats .= '{name: "' . $output_class_name . '", element: ["ul", "ol"], attributes: {"class": "' . $output_class_name . '"}}';
                            break;
                        case 'paragraph-indent':
                        case 'paragraph-box-example':
                        case 'paragraph-box-notice':
                        case 'paragraph-box-primary':
                        case 'paragraph-box-secondary':
                        case 'paragraph-box-warning':
                        case 'paragraph-no-margin':
                        case 'paragraph-no-margin-top':
                        case 'paragraph-no-margin-bottom':
                        case 'paragraph-mobile-hide':
                        case 'paragraph-desktop-hide':
                        case 'video-primary':
                        case 'video-secondary':
                        case 'video-left-primary':
                        case 'video-left-secondary':
                        case 'video-right-primary':
                        case 'video-right-secondary':
                        case 'video-mobile-hide':
                        case 'video-desktop-hide':
                            $output_custom_formats .= '{name: "' . $output_class_name . '", element: "p", attributes: {"class": "' . $output_class_name . '"}}';
                            break;
                        case 'table-primary':
                        case 'table-secondary':
                        case 'table-left':
                        case 'table-right':
                        case 'table-center':
                        case 'table-mobile-hide':
                        case 'table-desktop-hide':
                            $output_custom_formats .= '{name: "' . $output_class_name . '", element: "table", attributes: {"class": "' . $output_class_name . '"}}';
                            break;
                        case 'table-row-header':
                            $output_custom_formats .= '{name: "' . $output_class_name . '", element: "thead", attributes: {"class": "' . $output_class_name . '"}}';
                            break;
                        case 'table-row-body':
                            $output_custom_formats .= '{name: "' . $output_class_name . '", element: "tbody", attributes: {"class": "' . $output_class_name . '"}}';
                            break;
                        case 'table-row-footer':
                            $output_custom_formats .= '{name: "' . $output_class_name . '", element: "tfoot", attributes: {"class": "' . $output_class_name . '"}}';
                            break;
                        case 'table-cell-header':
                            $output_custom_formats .= '{name: "' . $output_class_name . '", element: "th", attributes: {"class": "' . $output_class_name . '"}}';
                            break;
                        case 'table-cell-data':
                        case 'table-cell-mobile-fill':
                        case 'table-cell-mobile-wrap':
                        case 'table-cell-mobile-hide':
                        case 'table-cell-desktop-hide':
                            $output_custom_formats .= '{name: "' . $output_class_name . '", element: "td", attributes: {"class": "' . $output_class_name . '"}}';
                            break;
                        default:
                            // If an element was supplied with the custom format, then use that.
                            if ($custom_format['element']) {
                                $element = escape_javascript($custom_format['element']);
                                // Otherwise use span element.
                            } else {
                                $element = 'span';
                            }
                            $output_custom_formats .= '{name: "' . $output_class_name . '", element: "' . $element . '", attributes: {"class": "' . $output_class_name . '"}}';
                            break;
                    }
                }
                if ($output_editor_properties != '') {
                    $output_editor_properties .= ',';
                }
                $output_editor_properties .= 'stylesSet: [' . $output_custom_formats . ']';
                $custom_formats_found = true;
            }
        } else {
            $output_editor_properties .= 'contentsCss: "' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/assets/lib/ckeditor_4_20/contents.css"';
        }
        if ($custom_formats_found == false) {
            if ($output_editor_properties != '') {
                $output_editor_properties .= ',';
            }
            $output_editor_properties .= 'stylesSet: []';
        }
        $output_folder_id = '';
        if ($folder_id) {
            $output_folder_id = '&folder_id=' . $folder_id;
        }
        $output_filebrowser_properties = '';
        // If the user is logged in and the user is a manager or above,
        // or if the user has edit access to at least one folder,
        // then output settings and plugin so that user can browse images/link items,
        // and upload files.
        if ((USER_LOGGED_IN == true) && ((USER_ROLE < 3) || (no_acl_check(USER_ID) == true))) {
            $output_filebrowser_properties = 'filebrowserBrowseUrl: "' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/editor_select_page_or_file.php",
                filebrowserImageBrowseUrl: "' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/editor_select_image.php",
                filebrowserWindowWidth: "850",
                filebrowserWindowHeight: "740",';
        }
        $output_include_source_plugin = '';
        $output_remove_sourcedialog_plugin = '';
        $output_editor_initialization = '';
        if ($edit_region_dialog == true) {
            // do nothing
        } else {
            $output_include_source_plugin = ',"Source"';
            $output_remove_sourcedialog_plugin = ',sourcedialog';
            foreach ($editor_ids as $editor_id) {
                $output_editor_initialization .= 'CKEDITOR.replace("' . $editor_id . '", software_ckeditor_config);';
            }
            $output_editor_initialization = 'if (typeof(software_$) != "undefined") {
                    software_$(document).ready(function() {
                        ' . $output_editor_initialization . '
                    });
                } else {
                    $(document).ready(function() {
                        ' . $output_editor_initialization . '
                    });
                }';
        }
        $output_font_style_toolbar_property = '';
        if ($page_editor_font_style == 1) {
            $output_font_style_toolbar_property = '["Format"],';
        }
        $output_font_toolbar_property = '';
        if ($page_editor_font == 1) {
            $output_font_toolbar_property = '["Font"], ';
        }
        $output_font_size_toolbar_property = '';
        if ($page_editor_font_size == 1) {
            $output_font_size_toolbar_property = '["FontSize"], ';
        }
        $output_font_color_toolbar_property = '';
        if ($page_editor_font_color == 1) {
            $output_font_color_toolbar_property = '["TextColor"], ';
        }
        $output_background_color_toolbar_property = '';
        if ($page_editor_background_color == 1) {
            $output_background_color_toolbar_property = '["BGColor"],';
        }

        // We have to remove the various plugins listed by removePlugins,
        // because we were using those plugins at one point during testing
        // and we told ckbuilder to add them to the bundled ckeditor.js file.
        // We are no longer using those plugins in production.
        // We don't want to bother having to run ckbuilder again for now, so we are just
        // dynamically telling ckeditor to remove them.  We have to tell ckeditor
        // to remove them, because we have since deleted their directories
        // so a JavaScript error would appear otherwise.  When we update ckeditor
        // in the future, then tell ckbuilder next time, that we don't want those plugins,
        // and then we can remove them from the list below.
        // Setting customConfig to an empty string is necessary so that CKEditor
        // does not try to load the non-existent config.js file in the ckeditor directory.
        // We load a dynamic config object and do not use the external config.js feature.
        // This avoids slow load-speed and browser generating 404 errors.
        return
            '<script src="' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/assets/lib/ckeditor_4_20/ckeditor.js"></script>
            ' . pg_code_editor_includes() . '
            <script src="' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/assets/js/pg_ckeditor_source.js?v=' . pg_code_editor_asset_version('/assets/js/pg_ckeditor_source.js') . '"></script>
            <script>
                var software_editor_version = "' . $page_editor_version . '";
                CKEDITOR.config.language = "' . lang(array('info' => '')) . '";
                CKEDITOR.config.disableAutoInline = true;
                CKEDITOR.config.toolbarCanCollapse = true;
                

                
                var software_ckeditor_config = {
                    allowedContent: true,
                    autoGrow_onStartup: true,
                    customConfig: "",
                    dialogFieldsDefaultValues: {
                        table: {
                            info: {
                                txtWidth: "100%",
                                txtBorder: "0",
                                txtCellSpace: "",
                                txtCellPad: ""
                            }
                        }
                    },
                    disableNativeSpellChecker: false,
                    ' . $output_filebrowser_properties . '
                    filebrowserUploadMethod: "form",
                    filebrowserUploadUrl: "' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/editor_add_file.php?token=' . escape_javascript($_SESSION['software']['token'] ?? '') . $output_folder_id . '",
                    filebrowserImageUploadUrl: "' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/editor_add_file.php?token=' . escape_javascript($_SESSION['software']['token'] ?? '') . $output_folder_id . '",
                    image_previewText: " ",
                    removeButtons: "addFile,addImage",
                    removeDialogTabs: "image:Link",
                    removePlugins: "div,pagebreak,smiley,youtube' . $output_remove_sourcedialog_plugin . '",
                    resize_dir: "vertical",
                    title: false,
                    toolbar: [
                        ["Maximize"], 
                        ["Find","Cut","PasteText","PasteFromWord"],
                        ' . $output_font_style_toolbar_property . $output_custom_format_toolbar_property . $output_font_toolbar_property . $output_font_size_toolbar_property . $output_font_color_toolbar_property . $output_background_color_toolbar_property . '
                        ["Bold", "Italic", "Underline", "Strike"],
                        ["JustifyLeft", "JustifyCenter", "JustifyRight", "RemoveFormat"],
                        [ "NumberedList","BulletedList","Outdent","Indent"],
                        ["Link", "Image", "oembed","Table", "HorizontalRule", "Blockquote", "Anchor", "SpecialChar"], 
                        ["ShowBlocks"' . $output_include_source_plugin . ', "Sourcedialog"]
                    ],
                    ' . $output_editor_properties . '
                };
                CKEDITOR.config.skin = "moono-lisa";
                // Source mode and the Source dialog show the HTML in the code
                // editor every other code field uses (pg_ckeditor_source.js).
                CKEDITOR.config.extraPlugins = "sourcedialog,pgcodeeditor";
                ' . $output_editor_initialization . '

            </script>';

    } else if ($page_editor_version == 'previous') {
        // else if set the version path to the older version
        $page_editor_font = '';
        if ($row['page_editor_font'] == 1) {
            $page_editor_font = "fontselect,";
        }
        $page_editor_font_size = '';
        if ($row['page_editor_font_size'] == 1) {
            $page_editor_font_size = "fontsizeselect,";
        }
        $page_editor_font_style = '';
        if ($row['page_editor_font_style'] == 1) {
            $page_editor_font_style = "formatselect,";
        }
        $page_editor_font_color = '';
        if ($row['page_editor_font_color'] == 1) {
            $page_editor_font_color = "forecolor,";
        }
        $page_editor_background_color = '';
        if ($row['page_editor_background_color'] == 1) {
            $page_editor_background_color = "backcolor,|,";
        }
        $page_editor_style_select = '';
        $page_editor_style_sheet = '';
        $output_theme_advanced_styles = '';
        $output_style_formats = '';
        $theme_name = '';
        // If the visitor is previewing a theme, then use that theme.
        if (isset($_SESSION['software']['preview_theme_id']) == true) {
            // If the visitor has selected a theme to preview, then use that theme.
            // This check is necessary because a user might have selected the none theme in the previous pick list.
            if ($_SESSION['software']['preview_theme_id'] != '') {
                $theme_name = db_value("SELECT name FROM files WHERE id = '" . escape($_SESSION['software']['preview_theme_id']) . "'");
            }
            // Otherwise if there is a style theme name, then use that.
        } else if ($style_theme_name != '') {
            $theme_name = $style_theme_name;
            // Otherwise use activated theme.
        } else {
            // get theme name differently based on the device type (i.e. desktop or mobile)
            switch (isset($_SESSION['software']['device_type']) ? $_SESSION['software']['device_type'] : 'desktop') {
                // if the device type is desktop then get the activated desktop theme name
                case 'desktop':
                default:
                    $theme_name = db_value("SELECT name FROM files WHERE activated_desktop_theme = '1'");
                    break;
                // if the device type is mobile, then get the activated mobile theme name
                // and fall back to the activated desktop theme if necessary
                case 'mobile':
                    $theme_name = db_value("SELECT name FROM files WHERE activated_mobile_theme = '1'");
                    // if an activated mobile theme could not be found, then get activated desktop theme name
                    if ($theme_name == '') {
                        $theme_name = db_value("SELECT name FROM files WHERE activated_desktop_theme = '1'");
                    }
                    break;
            }
        }
        // if a theme was found and the theme file exists, then use stylesheet for editor and get custom formats
        if (($theme_name != '') && (file_exists(FILE_DIRECTORY_PATH . '/' . $theme_name) == true)) {
            $page_editor_style_sheet = 'content_css : "' . PATH . h($theme_name) . '",';
            $custom_formats = get_custom_formats($theme_name);
            // if there is at least one custom format, then loop through all custom formats in order to prepare lists for TinyMCE
            // we still use theme_advanced_styles so that pick lists in dialog windows contain the correct list of classes
            if (count($custom_formats) > 0) {
                $page_editor_style_select = 'styleselect,';
                // loop through the custom formats in order to prepare list of data
                foreach ($custom_formats as $custom_format) {
                    $output_class_name = escape_javascript($custom_format['name']);
                    // if this is not the first custom format, then add separation
                    if ($output_theme_advanced_styles != '') {
                        $output_theme_advanced_styles .= ';';
                    }
                    $output_theme_advanced_styles .= $output_class_name . '=' . $output_class_name;
                    // if this is not the first custom format, then add separation
                    if ($output_style_formats != '') {
                        $output_style_formats .= ',';
                    }
                    // prepare style format for this custom format differently based on its class name
                    // we do special things for the default custom formats
                    switch ($custom_format['name']) {
                        case 'heading-primary':
                        case 'heading-secondary':
                            $output_style_formats .= '{title: \'' . $output_class_name . '\', selector: \'h1,h2,h3,h4,h5,h6\', classes: \'' . $output_class_name . '\'}';
                            break;
                        case 'image-primary':
                        case 'image-secondary':
                        case 'image-left-primary':
                        case 'image-left-secondary':
                        case 'image-right-primary':
                        case 'image-right-secondary':
                        case 'image-mobile-hide':
                        case 'image-desktop-hide':
                            $output_style_formats .= '{title: \'' . $output_class_name . '\', selector: \'img\', classes: \'' . $output_class_name . '\'}';
                            break;
                        case 'link-button-primary-large':
                        case 'link-button-primary-small':
                        case 'link-button-secondary-large':
                        case 'link-button-secondary-small':
                        case 'link-content-more':
                        case 'link-menu-item':
                        case 'link-mobile-hide':
                        case 'link-desktop-hide':
                        case 'list-accordion-expanded':
                            $output_style_formats .= '{title: \'' . $output_class_name . '\', selector: \'a\', classes: \'' . $output_class_name . '\'}';
                            break;
                        case 'list-accordion':
                        case 'list-tabs':
                            $output_style_formats .= '{title: \'' . $output_class_name . '\', selector: \'ul, ol\', classes: \'' . $output_class_name . '\'}';
                            break;
                        case 'paragraph-indent':
                        case 'paragraph-box-example':
                        case 'paragraph-box-notice':
                        case 'paragraph-box-primary':
                        case 'paragraph-box-secondary':
                        case 'paragraph-box-warning':
                        case 'paragraph-no-margin':
                        case 'paragraph-no-margin-top':
                        case 'paragraph-no-margin-bottom':
                        case 'paragraph-mobile-hide':
                        case 'paragraph-desktop-hide':
                        case 'video-primary':
                        case 'video-secondary':
                        case 'video-left-primary':
                        case 'video-left-secondary':
                        case 'video-right-primary':
                        case 'video-right-secondary':
                        case 'video-mobile-hide':
                        case 'video-desktop-hide':
                            $output_style_formats .= '{title: \'' . $output_class_name . '\', block: \'p\', classes: \'' . $output_class_name . '\'}';
                            break;
                        case 'table-primary':
                        case 'table-secondary':
                        case 'table-left':
                        case 'table-right':
                        case 'table-center':
                        case 'table-mobile-hide':
                        case 'table-desktop-hide':
                            $output_style_formats .= '{title: \'' . $output_class_name . '\', selector: \'table\', classes: \'' . $output_class_name . '\'}';
                            break;
                        case 'table-row-header':
                            $output_style_formats .= '{title: \'' . $output_class_name . '\', selector: \'thead\', classes: \'' . $output_class_name . '\'}';
                            break;
                        case 'table-row-body':
                            $output_style_formats .= '{title: \'' . $output_class_name . '\', selector: \'tbody\', classes: \'' . $output_class_name . '\'}';
                            break;
                        case 'table-row-footer':
                            $output_style_formats .= '{title: \'' . $output_class_name . '\', selector: \'tfoot\', classes: \'' . $output_class_name . '\'}';
                            break;
                        case 'table-cell-header':
                            $output_style_formats .= '{title: \'' . $output_class_name . '\', selector: \'th\', classes: \'' . $output_class_name . '\'}';
                            break;
                        case 'table-cell-data':
                        case 'table-cell-mobile-fill':
                        case 'table-cell-mobile-wrap':
                        case 'table-cell-mobile-hide':
                        case 'table-cell-desktop-hide':
                            $output_style_formats .= '{title: \'' . $output_class_name . '\', selector: \'td\', classes: \'' . $output_class_name . '\'}';
                            break;
                        default:
                            // If an element was supplied with the custom format, then use that.
                            if ($custom_format['element']) {
                                $output_style_formats .= '{title: \'' . $output_class_name . '\', selector: \'' . escape_javascript($custom_format['element']) . '\', classes: \'' . $output_class_name . '\'}';
                                // Otherwise use span element.
                            } else {
                                $output_style_formats .= '{title: \'' . $output_class_name . '\', inline: \'span\', classes: \'' . $output_class_name . '\'}';
                            }
                            break;
                    }
                }
                $output_theme_advanced_styles = 'theme_advanced_styles: "' . $output_theme_advanced_styles . '",';
                $output_style_formats = 'style_formats: [' . $output_style_formats . '],';
            }
        }
        $output_editor_initialization = 'elements : "';
        foreach ($editor_ids as $editor_id) {
            $output_editor_initialization .= $editor_id . ',';
        }
        $output_editor_initialization = mb_substr($output_editor_initialization, 0, -1);
        $output_editor_initialization .= '",';
        $output_editor_activation = '';
        if ($activate_editors == true) {
            $output_editor_activation = 'mode : "exact",';
        } else {
            $output_editor_activation = 'mode : "none",';
        }
        $version_path = '3_5_10';
        $output_plugins = 'lists,';
        $output_spellchecker_button = '';
        $output_browser_spellcheck = 'browser_spellcheck: true,';
        $output_edit_region_dialog_properties = '';
        // if this is for the edit region dialog, then output properties for it
        if ($edit_region_dialog == true) {
            $output_theme_advanced_resizing = 'false';
            // init_instance_callback is used in order to call a function to resize the editor after it is fully loaded
            // auto_focus is used in order to place the cursor in the editor.
            // the following function causes the cursor to sometimes be hidden in Firefox, so don't use it instead.
            // tinyMCE.execCommand('mceFocus', false, 'software_edit_region_textarea')
            $output_edit_region_dialog_properties = 'init_instance_callback: function() {software_activate_edit_region_dialog();},

                auto_focus: "software_edit_region_textarea",';
        } else {
            $output_theme_advanced_resizing = 'true';
        }
        // Assume that CDN is disabled until we find out otherwise.
        // We pass the CDN value so we know which jQuery location to use for plugin pages in the rich-text editor.
        $cdn = 'false';
        // if CDN is enabled, then use Google CDN for jQuery for performance reasons
        if ((defined('CDN') == false) || (CDN == true)) {
            $cdn = 'true';
        }
        // we removed shape from the list of allowed attributes for an anchor in extended_valid_elements below
        // in order to workaround a bug with IE 9 where updates would not be saved if a link existed in the content
        // "media_strict: false" was added in order to prevent TinyMCE from removing embed tag (using extended_valid_elements did not work),
        // which resulted in Flash movies not working in IE 9 (64 bit).  Not sure if this affected IE 9 (32 bit).
        return '<script language="javascript"  src="' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/assets/lib/tiny_mce_' . $version_path . '/tiny_mce.js"></script>
            <script language="javascript" >
                var software_editor_version = "' . $page_editor_version . '";
                tinyMCE.init({
                    ' . $output_editor_activation . '
                    ' . $output_editor_initialization . '
                    ' . $output_edit_region_dialog_properties . '
                    theme: "advanced",
                    skin: "cirkuit",
                    plugins: "advimage,advlink,' . $output_plugins . 'contextmenu,fullscreen,inlinepopups,media,paste,searchreplace,tabfocus,table",
                    theme_advanced_buttons1: "' . $page_editor_font_style . $page_editor_style_select . $page_editor_font . $page_editor_font_size . $page_editor_font_color . $page_editor_background_color . 'bold,italic,underline,strikethrough,blockquote' . $output_spellchecker_button . '",
                    theme_advanced_buttons2: "' . 'cut,copy,paste,pastetext,pasteword,selectall,newdocument,|,sub,sup,|,justifyleft,justifycenter,justifyright,justifyfull,|,bullist,numlist,outdent,indent,|,hr,link,unlink,anchor,image,media",
                    theme_advanced_buttons3: "tablecontrols,|,visualaid,|,charmap,|,removeformat,cleanup,|,search,replace,|,undo,redo,|,fullscreen,code",
                    theme_advanced_toolbar_location: "top",
                    theme_advanced_toolbar_align: "left",
                    theme_advanced_path_location: "bottom",
                    media_strict: false,
                    ' . $page_editor_style_sheet . '
                    ' . $output_theme_advanced_styles . '
                    ' . $output_style_formats . '
                    extended_valid_elements: "#p[id|class|align|style],-div[id|class|align|style],span[id|class|align|style],a[accesskey|align|charset|class|coords|dir|href|hreflang|id|lang|name|onblur|onclick|ondblclick|onfocus|onkeydown|onkeypress|onkeyup|onmousedown|onmousemove|onmouseout|onmouseover|onmouseup|rel|rev|style|tabindex|title|target|type],-ul[type|class|compact|align],-ol[type|class|compact|align],iframe[align|class|frameborder|height|id|longdesc|marginheight|marginwidth|name|scrolling|src|style|title|width]",
                    external_link_list_url: "' . PATH . SOFTWARE_DIRECTORY . '/assets/lib/tiny_mce_' . $version_path . '/get_page_list.php",
                    external_files_list_url: "' . PATH . SOFTWARE_DIRECTORY . '/assets/lib/tiny_mce_' . $version_path . '/get_file_list.php",
                    external_folder_list_url: "' . PATH . SOFTWARE_DIRECTORY . '/assets/lib/tiny_mce_' . $version_path . '/get_folder_list.php",
                    external_image_list_url: "' . PATH . SOFTWARE_DIRECTORY . '/assets/lib/tiny_mce_' . $version_path . '/get_image_list.php",
                    media_external_list_url: "' . PATH . SOFTWARE_DIRECTORY . '/assets/lib/tiny_mce_' . $version_path . '/get_media_list.php",
                    width: \'100%\',
                    theme_advanced_resizing: ' . $output_theme_advanced_resizing . ',
                    theme_advanced_resizing_use_cookie: false,
                    convert_urls: false,
                    ' . $output_browser_spellcheck . '
                    remove_script_host: false,
                    folder_id: "' . $folder_id . '",
                    environment_suffix: "' . ENVIRONMENT_SUFFIX . '",
                    cdn: ' . $cdn . '
                });
            </script>';
    }
}

/**
 * Assets for the image editor tool: Pintura plus the modal wrapper.
 *
 * One line for any screen that wants to offer image editing. The tool is
 * opt-in per screen because Pintura is not small, and most screens never
 * show an image the operator can edit.
 *
 * Usage:  echo get_image_editor_includes();
 *         ... window.openImageEditor({ src: ..., onSaved: ... })
 *
 * jQuery must already be on the page — Pintura ships as $.fn.doka.
 */
function get_image_editor_includes()
{
    // Pintura's own labels. They are emitted here, from lang(), rather than
    // written into the JS module: the module has no business knowing which
    // language the operator reads, and image_editor_edit.php already proved
    // the labels have to be overridden one by one.
    $locale = array(
        'labelButtonExport'               => lang('Save'),
        'labelButtonRevert'               => lang('undo all'),
        'labelButtonUndo'                 => lang('Undo'),
        'labelButtonRedo'                 => lang('Redo'),
        'labelDefault'                    => lang('Default'),
        'labelClose'                      => lang('Close'),
        'labelEdit'                       => lang('Edit'),
        'labelNone'                       => lang('None'),
        'labelReset'                      => lang('Reset'),
        'labelAuto'                       => lang('Auto'),
        'cropLabel'                       => lang('Crop'),
        'resizeLabel'                     => lang('Resize'),
        'filterLabel'                     => lang('Filter'),
        'finetuneLabel'                   => lang('Finetune'),
        'decorateLabel'                   => lang('Decorate'),
        'stickerLabel'                    => lang('Sticker'),
        'cropLabelButtonRotateLeft'       => lang('Rotate Left'),
        'cropLabelButtonRotateRight'      => lang('Rotate Right'),
        'cropLabelButtonFlipHorizontal'   => lang('Flip Horizontal'),
        'cropLabelButtonFlipVertical'     => lang('Flip Vertical'),
        'cropLabelButtonRecenter'         => lang('Recenter'),
        'cropLabelTabRotation'            => lang('Rotation'),
        'cropLabelTabZoom'                => lang('Zoom'),
        'cropLabelSelectPreset'           => lang('Crop Shape'),
        'cropLabelCropBoundary'           => lang('Crop Boundary'),
        'finetuneLabelBrightness'         => lang('Brightness'),
        'finetuneLabelContrast'           => lang('Contrast'),
        'finetuneLabelExposure'           => lang('Exposure'),
        'finetuneLabelGamma'              => lang('Gamma'),
        'finetuneLabelSaturation'         => lang('Saturation'),
        'finetuneLabelClarity'            => lang('Clarity'),
        'shapeLabelInputCancel'           => lang('Cancel'),
        'shapeLabelInputConfirm'          => lang('Confirm'),
        'shapeLabelInputText'             => lang('Edit text'),
        'shapeLabelToolText'              => lang('Text'),
        'shapeLabelToolArrow'             => lang('Arrow'),
        'shapeLabelToolLine'              => lang('Line'),
        'shapeLabelToolRectangle'         => lang('Rectangle'),
        'shapeLabelToolEllipse'           => lang('Ellipse'),
        'shapeLabelToolEraser'            => lang('Eraser'),
        'shapeLabelToolSharpie'           => lang('Sharpie'),
        'statusLabelButtonClose'          => lang('Close'),
    );

    // The modal wrapper's own labels. It reads window.pgImageEditorLabels and
    // falls back to the English key, so the map only needs the translations.
    $labels = array(
        'Animated GIF — new file only.' => lang('Animated GIF — new file only.'),
        'Exit full screen' => lang('Exit full screen'),
        'Full screen' => lang('Full screen'),
        'Keeps the original file and points this page at the copy.' => lang('Keeps the original file and points this page at the copy.'),
        'Network error.' => lang('Network error.'),
        'Pintura Image Editor' => lang('Pintura Image Editor'),
        'Replace' => lang('Replace'),
        'Same format' => lang('Same format'),
        'Save as' => lang('Save as'),
        'Save as new file' => lang('Save as new file'),
        'Saving...' => lang('Saving...'),
        'The edited image could not be produced.' => lang('The edited image could not be produced.'),
        'The edited image could not be read.' => lang('The edited image could not be read.'),
        'The image could not be opened.' => lang('The image could not be opened.'),
        'The image could not be saved.' => lang('The image could not be saved.'),
        'The image editor is not loaded on this screen.' => lang('The image editor is not loaded on this screen.'),
        'Writes over the file. Every page using it changes.' => lang('Writes over the file. Every page using it changes.'),
    );

    // The vendor bundle only changes when Pintura is upgraded, but the modal
    // wrapper changes with the software — and a CDN in front of the site will
    // hold a query-less .js file for as long as it likes. The file's own
    // mtime is the version: stable in production, immediate in development.
    $modal_path    = PG_FUNCTIONS_DIR . '/assets/js/image_editor_modal.js';
    $modal_version = @filemtime($modal_path);
    if (!$modal_version) $modal_version = defined('VERSION') ? rawurlencode(VERSION) : '1';

    return '<link rel="stylesheet" href="' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/assets/lib/image_editor/packages/doka/doka.css">
        <script src="' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/assets/lib/image_editor/packages/jquery_doka/doka.js"></script>
        <script>window.pgImageEditorLocale = ' . encode_json($locale) . '; window.pgImageEditorLabels = ' . encode_json($labels) . ';</script>
        <script src="' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/assets/js/image_editor_modal.js?v=' . $modal_version . '"></script>';
}

/**
 * The code editor facade (assets/js/pg_code_editor.js, window.PgCodeEditor)
 * for a panel screen: the engine's translated texts, added to the addresses
 * the panel header already names (window.pgCodeEditorConfig, see
 * pg_code_editor_urls()).
 *
 * Printed once per request, by pg_code_editor_attach() or by a screen that
 * opens an editor of its own. Nothing is fetched here: window.pgCodeEditorUse()
 * (backend.src.js) loads the facade the first time a field asks for it, and
 * the facade loads its engine, the CodeMirror 6 bundle, when the first editor
 * opens. The engine's own texts (the search panel, the line prompt, the fold
 * markers) are translated here; a screen's labels stay with the screen. Being
 * a plain inline script, it also works in markup put into a page later (a
 * pane of the settings dialog), where a script tag with a src would be
 * fetched by jQuery with a blocking request.
 */
function pg_code_editor_includes()
{
    static $printed = false;

    if ($printed) {
        return '';
    }

    $printed = true;

    $config = pg_code_editor_urls();
    $config['phrases'] = pg_code_editor_phrases();
    $config['labels'] = array('syntaxError' => lang('Syntax error'));

    return '<script>window.pgCodeEditorConfig = ' . pg_code_editor_json($config) . ';</script>';
}

// Where the facade and its engine are, each with a cache-busting stamp.
function pg_code_editor_urls()
{
    $base = OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY;
    $facade = '/assets/js/pg_code_editor.js';
    $bundle = '/assets/lib/code_editor/cm6.bundle.js';

    return array(
        'facade' => $base . $facade . '?v=' . pg_code_editor_asset_version($facade),
        'bundle' => $base . $bundle . '?v=' . pg_code_editor_asset_version($bundle),
    );
}

/**
 * A code field on a panel screen: the editor of the facade takes the place
 * of the textarea $properties['id']. The textarea keeps the value current, so
 * the form that submits it sees every edit and the screen's save path does
 * not change.
 *
 * $properties:
 *   'id'        the textarea
 *   'code_type' 'mixed', 'css', 'javascript', 'application/json', 'php',
 *               'xml', 'plain', …; see pg_code_editor_language()
 *   'readonly'  true shows the text and refuses changes (the cursor, the
 *               keyboard and the search panel still work)
 *   'height'    a CSS height; without it the editor grows with the text
 *
 * F11 fills the window with the editor, Esc leaves. The instance is kept in
 * window.pgCodeEditors[id] for a screen that needs it, such as one that
 * changes the language after the file name; the textarea raises
 * "pgcodeeditorready" (detail: the instance) once it is there.
 */
function pg_code_editor_attach($properties)
{
    $id = isset($properties['id']) ? (string) $properties['id'] : 'code';

    $options = array(
        'language'  => pg_code_editor_language(isset($properties['code_type']) ? $properties['code_type'] : ''),
        'readOnly'  => !empty($properties['readonly']),
        'theme'     => pg_code_editor_theme(),
        'className' => 'pg-code-field' . (!empty($properties['readonly']) ? ' pg-code-field-ro' : ''),
        'labels'    => array('syntaxError' => lang('Syntax error')),
    );

    if (!empty($properties['height'])) {
        $options['height'] = (string) $properties['height'];
    }

    // Plain text (a list of options, a .txt file) is shown with the HTML
    // grammar for its colours only; a stray < in it is not a syntax error.
    if (pg_code_editor_is_plain(isset($properties['code_type']) ? $properties['code_type'] : '')) {
        $options['lint'] = false;
    }

    // Every field gets a row under it with a minimap toggle (off on panel
    // screens until it is pressed). An editable field also gets a "Format
    // code" button there, shown while the editor's language has a formatter
    // (create_file.php changes the language after the start, so the button
    // follows setLanguage()).
    $format = '';

    if (empty($properties['readonly'])) {
        $format = '
                    var formats = ["css","javascript","json"];
                    var format = document.createElement("button");
                    format.type = "button";
                    format.className = "btn btn-sm btn-outline-secondary pg-code-field-format";
                    format.title = "Shift+Alt+F";
                    format.textContent = ' . pg_code_editor_json(lang('Format code')) . ';
                    format.addEventListener("click", function () {
                        editor.format();
                        editor.focus();
                    });
                    tools.insertBefore(format, minimap);
                    var sync = function () { format.classList.toggle("d-none", formats.indexOf(editor.getLanguage()) === -1); };
                    var setLanguage = editor.setLanguage;
                    editor.setLanguage = function () {
                        var result = setLanguage.apply(editor, arguments);
                        sync();
                        return result;
                    };
                    sync();';
    }

    $tools = '
                    var tools = document.createElement("div");
                    tools.className = "pg-code-field-tools gap-2";
                    var minimap = document.createElement("button");
                    minimap.type = "button";
                    minimap.className = "btn btn-sm btn-outline-secondary pg-code-field-minimap";
                    minimap.textContent = ' . pg_code_editor_json(lang('Minimap')) . ';
                    var syncMinimap = function () {
                        var on = editor.hasMinimap();
                        minimap.setAttribute("aria-pressed", on ? "true" : "false");
                        minimap.classList.toggle("active", on);
                    };
                    minimap.addEventListener("click", function () {
                        editor.setMinimap(!editor.hasMinimap());
                        syncMinimap();
                        editor.focus();
                    });
                    syncMinimap();
                    tools.appendChild(minimap);' . $format . '
                    var destroy = editor.destroy;
                    editor.destroy = function () {
                        if (tools.parentNode) tools.parentNode.removeChild(tools);
                        return destroy.apply(editor, arguments);
                    };
                    editor.container.parentNode.insertBefore(tools, editor.container.nextSibling);';

    return pg_code_editor_includes() . '<script>
        (function () {
            var field = document.getElementById(' . pg_code_editor_json($id) . ');
            if (!field || (typeof window.pgCodeEditorUse !== "function")) return;
            var options = ' . pg_code_editor_json($options) . ';
            var editor = null;
            var full = function (on) {
                if (!editor || !editor.host) return false;
                editor.host.classList.toggle("pg-code-field-full", on);
                document.body.classList.toggle("pg-code-field-open", on);
                return true;
            };
            var isFull = function () { return !!(editor && editor.host && editor.host.classList.contains("pg-code-field-full")); };
            options.keys = [
                { key: "F11", run: function () { return full(!isFull()); } },
                { key: "Escape", run: function () { return isFull() ? full(false) : false; } }
            ];
            window.pgCodeEditors = window.pgCodeEditors || {};
            window.pgCodeEditorUse(function () {
                // The facade or its engine could not be fetched: the textarea
                // stays a plain field, which is what a missing editor looked
                // like before.
                if (typeof PgCodeEditor === "undefined") return;
                PgCodeEditor.attach(field, options).then(function (instance) {
                    editor = instance;
                    window.pgCodeEditors[field.id] = instance;' . $tools . '
                    // A screen that changes the editor (its language, after a
                    // choice made before it arrived) hears when it is there.
                    try { field.dispatchEvent(new CustomEvent("pgcodeeditorready", { detail: instance })); } catch (e) {}
                }, function () {});
            });
        })();
    </script>';
}

/**
 * The facade's language for a code type of pg_code_editor_attach():
 * 'css', 'javascript', 'json' or 'html'. PHP, XML, SVG and plain text are
 * shown with the HTML grammar, which the facade carries; MIME names
 * ('text/css', 'application/json', …) are read as well.
 */
function pg_code_editor_language($code_type)
{
    $type = mb_strtolower(trim((string) $code_type));

    if (($type === 'css') || ($type === 'text/css')) {
        return 'css';
    }

    if (($type === 'javascript') || ($type === 'js') || ($type === 'text/javascript') || ($type === 'application/javascript')) {
        return 'javascript';
    }

    if (($type === 'json') || ($type === 'application/json')) {
        return 'json';
    }

    return 'html';
}

// Whether a code type of pg_code_editor_attach() means plain text.
function pg_code_editor_is_plain($code_type)
{
    $type = mb_strtolower(trim((string) $code_type));

    return in_array($type, array('plain', 'text', 'text.txt', 'text/plain'), true);
}

// The editor follows the panel's colour scheme: the cookie the panel sets
// when the person picks dark mode.
function pg_code_editor_theme()
{
    return (isset($_COOKIE['prefers-color-scheme']) && ($_COOKIE['prefers-color-scheme'] == 'dark')) ? 'dark' : 'light';
}

// The engine's own texts, keyed by its English phrases ($ is the number the
// engine fills in). The designer builds the same map in JavaScript
// (_sdCodePhrases(), style_designer.js); the keys are shared in tr.json.
function pg_code_editor_phrases()
{
    return array(
        'Find'                      => lang('Find'),
        'Replace'                   => lang('Replace'),
        'next'                      => lang('next'),
        'previous'                  => lang('previous'),
        'all'                       => lang('all'),
        'match case'                => lang('match case'),
        'by word'                   => lang('by word'),
        'regexp'                    => lang('regexp'),
        'replace'                   => lang('replace'),
        'replace all'               => lang('replace all'),
        'close'                     => lang('close'),
        'current match'             => lang('current match'),
        'replaced $ matches'        => lang('replaced $ matches'),
        'replaced match on line $'  => lang('replaced match on line $'),
        'on line'                   => lang('on line'),
        'Go to line'                => lang('Go to line'),
        'go'                        => lang('go'),
        'to'                        => lang('to'),
        'Diagnostics'               => lang('Diagnostics'),
        'No diagnostics'            => lang('No diagnostics'),
        'Completions'               => lang('Completions'),
        'Control character'         => lang('Control character'),
        'Selection deleted'         => lang('Selection deleted'),
        'folded code'               => lang('folded code'),
        'unfold'                    => lang('unfold'),
        'Fold line'                 => lang('Fold line'),
        'Unfold line'               => lang('Unfold line'),
        'Folded lines'              => lang('Folded lines'),
        'Unfolded lines'            => lang('Unfolded lines'),
    );
}

// A cache-busting stamp for a file under the software directory: its
// modification time, or the version when the file cannot be read.
function pg_code_editor_asset_version($path)
{
    $stamp = @filemtime(PG_FUNCTIONS_DIR . $path);

    return $stamp ? $stamp : (defined('VERSION') ? rawurlencode(VERSION) : '1');
}

// JSON for an inline <script>: < > & are escaped so a translated phrase can
// never close the script element.
function pg_code_editor_json($value)
{
    return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP);
}

// create function that replaces the actual path in the content from a rich-text editor with the {path} placeholder,
// so that the actual path is not stored in the database. $path is the site path
// the addresses start with: PATH unless a caller names it (the unit tests run
// without PATH).
function prepare_rich_text_editor_content_for_input($content, $path = null)
{
    if ($path === null) {
        $path = PATH;
    }

    $content = str_replace('href="' . $path, 'href="{path}', $content);
    $content = str_replace('src="' . $path, 'src="{path}', $content);
    $content = str_replace('value="' . $path, 'value="{path}', $content);
    return $content;
}
/**
 * Reduce rich text that came from an untrusted browser to an allow-list of
 * harmless HTML.
 *
 * A WYSIWYG text area on a custom form or a product form is filled in by an
 * anonymous visitor or a shopper, and its value is stored with the form data
 * type "html" so that the screens print it verbatim: the public form item and
 * list views, the confirmation screen, the order screens in the panel, printed
 * orders and receipts. Nothing a visitor typed may run there. The filter keeps
 * the formatting the editor produces (paragraphs, inline styling, lists,
 * tables, links, images) and removes everything that can execute or load
 * code: script-like elements, event handler attributes, javascript:/data:
 * URLs and CSS that pulls in resources.
 *
 * The markup is parsed and re-serialised, so entity-encoded tricks
 * (`&#106;avascript:`) are judged on their decoded form. The filter runs both
 * when a value is stored and when a stored value is printed unescaped, so
 * records saved before it existed are covered as well.
 */
function pg_sanitize_rich_text($content)
{
    $content = (string) $content;

    if (trim($content) === '') {
        return $content;
    }

    // Without ext/dom the markup cannot be parsed reliably, so it is reduced
    // to escaped text instead of being trusted.
    if (!class_exists('DOMDocument')) {
        return h(strip_tags($content));
    }

    $previous_setting = libxml_use_internal_errors(true);
    $document = new DOMDocument('1.0', 'UTF-8');
    // The processing instruction tells libxml the bytes are UTF-8; the
    // wrapper keeps leading text out of the implied <p> the parser would
    // otherwise add.
    $loaded = $document->loadHTML('<?xml encoding="UTF-8"><html><body><div>' . $content . '</div></body></html>', LIBXML_NONET);
    libxml_clear_errors();
    libxml_use_internal_errors($previous_setting);

    $body = $loaded ? $document->getElementsByTagName('body')->item(0) : null;

    if (!$body) {
        return h(strip_tags($content));
    }

    $wrapper = $body->firstChild;
    _pg_sanitize_rich_text_children($body);

    // A stray closing tag in the input ends the wrapper early and leaves the
    // rest as siblings of it; both parts are kept.
    $output = '';

    foreach ($body->childNodes as $node) {
        if ($wrapper && $node->isSameNode($wrapper)) {
            foreach ($wrapper->childNodes as $child) {
                $output .= pg_dom_html($child);
            }
        } else {
            $output .= pg_dom_html($node);
        }
    }

    // libxml percent-encodes the {path} placeholder that
    // prepare_rich_text_editor_content_for_input() writes at the front of
    // an address; the placeholder has to survive so the path is restored on
    // output.
    return str_replace(array('href="%7Bpath%7D', 'src="%7Bpath%7D'), array('href="{path}', 'src="{path}'), $output);
}

// Walk one level of the tree for pg_sanitize_rich_text() and recurse into the
// elements that stay.
function _pg_sanitize_rich_text_children($parent)
{
    // Elements whose content is code or a foreign document: removed with
    // everything inside them.
    static $dropped_tags = array(
        'script' => 1, 'style' => 1, 'iframe' => 1, 'frame' => 1, 'frameset' => 1,
        'object' => 1, 'embed' => 1, 'applet' => 1, 'noscript' => 1, 'noembed' => 1,
        'noframes' => 1, 'template' => 1, 'svg' => 1, 'math' => 1, 'xmp' => 1,
        'plaintext' => 1, 'meta' => 1, 'link' => 1, 'base' => 1, 'title' => 1,
        'head' => 1, 'form' => 1, 'input' => 1, 'button' => 1, 'select' => 1,
        'option' => 1, 'textarea' => 1,
    );

    // Formatting the rich-text editor produces. Anything else is unwrapped:
    // the tag goes, its content stays.
    static $allowed_tags = array(
        'p' => 1, 'br' => 1, 'div' => 1, 'span' => 1, 'b' => 1, 'strong' => 1,
        'i' => 1, 'em' => 1, 'u' => 1, 's' => 1, 'strike' => 1, 'del' => 1,
        'ins' => 1, 'sub' => 1, 'sup' => 1, 'small' => 1, 'mark' => 1, 'abbr' => 1,
        'cite' => 1, 'q' => 1, 'code' => 1, 'pre' => 1, 'blockquote' => 1,
        'h1' => 1, 'h2' => 1, 'h3' => 1, 'h4' => 1, 'h5' => 1, 'h6' => 1,
        'ul' => 1, 'ol' => 1, 'li' => 1, 'dl' => 1, 'dt' => 1, 'dd' => 1,
        'a' => 1, 'img' => 1, 'hr' => 1, 'font' => 1, 'center' => 1,
        'table' => 1, 'caption' => 1, 'thead' => 1, 'tbody' => 1, 'tfoot' => 1,
        'tr' => 1, 'td' => 1, 'th' => 1, 'colgroup' => 1, 'col' => 1,
    );

    // Attributes kept on every allowed element. Event handlers, id and name
    // (DOM clobbering) and data-* hooks are not on the list and so are removed.
    static $allowed_attributes = array(
        'class' => 1, 'style' => 1, 'title' => 1, 'dir' => 1, 'lang' => 1,
        'align' => 1, 'valign' => 1, 'width' => 1, 'height' => 1, 'border' => 1,
        'cellpadding' => 1, 'cellspacing' => 1, 'colspan' => 1, 'rowspan' => 1,
        'color' => 1, 'face' => 1, 'size' => 1, 'start' => 1, 'alt' => 1,
    );

    // The live child list shifts while nodes are removed, so a copy is walked.
    $children = array();

    foreach ($parent->childNodes as $child) {
        $children[] = $child;
    }

    foreach ($children as $child) {
        if ($child->nodeType != XML_ELEMENT_NODE) {
            // Text stays; comments, CDATA and processing instructions go.
            if ($child->nodeType != XML_TEXT_NODE) {
                $parent->removeChild($child);
            }

            continue;
        }

        $tag = strtolower($child->nodeName);

        if (isset($dropped_tags[$tag])) {
            $parent->removeChild($child);
            continue;
        }

        if (!isset($allowed_tags[$tag])) {
            _pg_sanitize_rich_text_children($child);

            while ($child->firstChild) {
                $parent->insertBefore($child->firstChild, $child);
            }

            $parent->removeChild($child);
            continue;
        }

        $attributes = array();

        foreach ($child->attributes as $attribute) {
            $attributes[] = $attribute;
        }

        foreach ($attributes as $attribute) {
            $name = strtolower($attribute->name);
            $value = (string) $attribute->value;
            $keep = false;

            if (isset($allowed_attributes[$name])) {
                $keep = ($name != 'style') || _pg_rich_text_style_is_safe($value);
            } elseif (($name == 'href') && ($tag == 'a')) {
                $keep = _pg_rich_text_url_is_safe($value);
            } elseif (($name == 'src') && ($tag == 'img')) {
                $keep = _pg_rich_text_url_is_safe($value);
            } elseif ((($name == 'target') || ($name == 'rel')) && ($tag == 'a')) {
                $keep = true;
            } elseif (($name == 'type') && (($tag == 'ol') || ($tag == 'ul') || ($tag == 'li'))) {
                $keep = true;
            }

            if (!$keep) {
                $child->removeAttributeNode($attribute);
            }
        }

        _pg_sanitize_rich_text_children($child);
    }
}

// A link or image address may be relative, a {path} placeholder, or use one
// of the schemes a browser only navigates with. Every other scheme
// (javascript:, data:, vbscript:, ...) is rejected. Browsers skip control
// characters and whitespace while reading the scheme, so they are skipped
// here before the check; HTML5 entities the parser left in place (&colon;)
// are decoded for the same reason.
function _pg_rich_text_url_is_safe($url)
{
    $probe = html_entity_decode((string) $url, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $probe = strtolower(preg_replace('/[\x00-\x20\x7f]+/', '', $probe));

    if (($probe === '') || (strpos($probe, '{path}') === 0)) {
        return true;
    }

    $colon = strpos($probe, ':');

    // No scheme at all, or the colon belongs to the path or query part.
    if (($colon === false) || (strcspn($probe, '/?#') < $colon)) {
        return true;
    }

    return in_array(substr($probe, 0, $colon), array('http', 'https', 'mailto', 'tel'), true);
}

// Inline styles from the editor are colours, alignment and sizes. Anything
// that can run code or fetch a resource from a style (legacy expression()
// and behavior, bindings, url(), @import) is dropped; escapes and comments
// are removed first because they can hide those words.
function _pg_rich_text_style_is_safe($style)
{
    $probe = preg_replace('/\/\*.*?\*\//s', '', (string) $style);
    $probe = strtolower(preg_replace('/[\x00-\x20\x7f]+/', '', $probe));

    if (strpos($probe, '\\') !== false) {
        return false;
    }

    foreach (array('expression', 'behavior', 'binding', 'url(', '@import', 'javascript', 'vbscript') as $needle) {
        if (strpos($probe, $needle) !== false) {
            return false;
        }
    }

    return true;
}
/**
 * Flatten WYSIWYG rich text into inline-safe HTML.
 *
 * Fields edited through the rich-text editor (products.out_of_stock_message,
 * details, …) are stored block-wrapped: `<p>Sorry, this item…</p>`. A system
 * widget binds those values into whatever element the designer chose, and that
 * element is very often a <p> — the starter trees use content/paragraph nodes.
 *
 * `<p>` inside `<p>` is illegal HTML. The browser does not nest it; it CLOSES
 * the outer paragraph at the inner opening tag and re-opens an empty one at the
 * stray `</p>`. One bound element therefore becomes three DOM nodes: an empty
 * shell that still carries the designer's classes, the orphaned message, and a
 * trailing empty paragraph. On a plain product the shell collapses (the
 * `[data-pg-bind]:empty` rule hides it) and only the orphan shows. On a variant
 * product pg_civ_variants.js then writes the message into the shell as well —
 * and the visitor sees the same sentence twice, once boxed and once bare.
 *
 * Stripping the block wrappers at the source keeps the value legal inside any
 * host element while preserving the operator's inline emphasis. Consecutive
 * blocks become <br> so multi-paragraph copy stays readable.
 */
function _pg_rich_text_to_inline($html)
{
    $html = (string)$html;
    if ($html === '') return '';

    // Block boundaries become line breaks before the tags are removed, so
    // "<p>A</p><p>B</p>" reads as "A<br>B" rather than "AB".
    $html = preg_replace('~</(?:p|div|h[1-6]|li|tr|blockquote)>\s*~i', '<br>', $html);
    $html = preg_replace('~<br\s*/?>\s*<br\s*/?>~i', '<br>', $html);

    // Keep inline formatting the operator may rely on; drop everything that
    // carries its own box.
    $html = strip_tags($html, '<a><b><strong><i><em><u><s><small><span><br><sup><sub><code>');

    // A single trailing <br> is an artefact of the last closing block tag.
    $html = preg_replace('~(?:\s|<br\s*/?>)+$~i', '', $html);
    $html = preg_replace('~^(?:\s|<br\s*/?>)+~i', '', $html);

    return trim($html);
}

// create function that will replace the {path} placeholder with the actual path so that the content works correctly
// in the rich-text editor
function prepare_rich_text_editor_content_for_output($content)
{
    $content = str_replace('href="{path}', 'href="' . PATH, $content);
    $content = str_replace('src="{path}', 'src="' . PATH, $content);
    $content = str_replace('value="{path}', 'value="' . PATH, $content);
    return $content;
}
