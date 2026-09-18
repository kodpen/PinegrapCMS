<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// A module of functions.php: The control panel shell: header, footer, menu, toolbar, page shell, licence check, asset versioning.
//
// Loaded by functions.php through require_once, never on its own. The
// functions were moved here from functions.php as they were; PG_FUNCTIONS_DIR
// is the software directory (what dirname(__FILE__) was in functions.php),
// see the note at the top of functions.php.
if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}


// this function outputs the includes for the files needed for the control panel
function output_control_panel_header_includes($include_assistant = true)
{
    $help_url = '';
    // If the site is not private labeled, then get help URL.  We hide the help button if site
    // is private labeled, so no reason to get help url.
    if (!defined('PRIVATE_LABEL') or PRIVATE_LABEL != true) {
        require(PG_FUNCTIONS_DIR . '/get_help_url.php');
        $languageinfo = lang(array('info' => ''));
        $help_url = get_help_url(array('languageinfo' => $languageinfo));
    }

    if (defined('DB_CONNECTED')) {
        $query = "SELECT custom_css FROM config";
        $result = mysqli_query(db::$con, $query) or exit(mysqli_error(db::$con));
        $row = mysqli_fetch_assoc($result);
        define('CUSTOM_CSS', $row['custom_css']);
    }

    if (!defined('CONTROL_PANEL_STYLESHEET_URL')) {
        define(
            'CONTROL_PANEL_STYLESHEET_URL',
            PATH . SOFTWARE_DIRECTORY . '/assets/css/backend.src.css?v=' .
            @filemtime(PG_FUNCTIONS_DIR . '/assets/css/backend.src.css')
        );
    }
    if (!defined('CUSTOM_CSS')) {
        define('CUSTOM_CSS', '');
    }

    // Session-less pages (the public API console) are served under a strict Content Security
    // Policy and to anonymous readers, so the panel's AI assistant snippet is skipped there.
    $assistant_snippet = '';
    if ($include_assistant) {
        $assistant_snippet = '<script type="module" src="https://f6eda156-883d-45b2-9c7e-e7f09bd50f24.search.ai.cloudflare.com/assets/v0.0.40/search-snippet.es.js"></script>';
    }

    // Strings the panel scripts (backend.src.js, via lang() / pgLang()) ask
    // for at run time. Every key must also exist in the language file; the
    // client falls back to the English key when an entry is missing.
    $translate = array(
        'No data available in table' => lang('No data available in table'),
        'Reset' => lang('Reset'),
        'Column Visiblity' => lang('Column Visiblity'),
        'Column Reorder and Column Visiblity reset?' => lang('Column Reorder and Column Visiblity reset?'),
        'Hold, move and reposition' => lang('Hold, move and reposition'),
        'Pin' => lang('Pin'),
        'Unpin' => lang('Unpin'),
        'Mark as unread' => lang('Mark as unread'),
        'Remove this notification' => lang('Remove this notification'),
        'Delete All Notifications' => lang('Delete All Notifications'),
        'Unlimited' => lang('Unlimited'),
        'Unpin this Link' => lang('Unpin this Link'),
        'Pin this Link' => lang('Pin this Link'),
        'The maximum link pin limit has been reached. Remove one to pin a new one.' => lang('The maximum link pin limit has been reached. Remove one to pin a new one.'),
        'Please Wait' => lang('Please Wait'),
        'Go to the relevant page' => lang('Go to the relevant page'),
        'Add Page' => lang('Add Page'),
        'Add Product' => lang('Add Product'),
        'Files' => lang('Files'),
        'Pages' => lang('Pages'),
        'Products' => lang('Products'),
        'An error occurred.' => lang('An error occurred.'),
        'Pintura Image Editor' => lang('Pintura Image Editor'),
        'Product Groups' => lang('Product Groups'),
        'Add Offer' => lang('Add Offer'),
        'Settings' => lang('Settings'),
        'Menus' => lang('Menus'),
        'Calendars' => lang('Calendars'),
        'Email Campaigns' => lang('Email Campaigns'),
        'Design Files' => lang('Design Files'),
        'Styles' => lang('Styles'),
        'Users' => lang('Users'),
        'Contacts' => lang('Contacts'),
        'Common Regions' => lang('Common Regions'),
        'Login Regions' => lang('Login Regions'),
        'Short Links' => lang('Short Links'),
        'Search...' => lang('Search...'),
        'Load More' => lang('Load More'),
        'Please allow pop-up windows for this site.' => lang('Please allow pop-up windows for this site.'),
        'WARNING: The selected {var}(s) will be opted-in.' => lang('WARNING: The selected {var}(s) will be opted-in.'),
        'WARNING: The selected {var}(s) will be opted-out.' => lang('WARNING: The selected {var}(s) will be opted-out.'),
        'WARNING: The selected duplicate {var}(s) will be merged together.' => lang('WARNING: The selected duplicate {var}(s) will be merged together.'),
        'WARNING: The selected {var}(s) will be permanently deleted.' => lang('WARNING: The selected {var}(s) will be permanently deleted.'),
        'Loading...' => lang('Loading...'),
        'System Fields' => lang('System Fields'),
        'Reference Code' => lang('Reference Code'),
        'Form Fields' => lang('Form Fields'),
        'Current Date' => lang('Current Date'),
        'Current Date & Time' => lang('Current Date & Time'),
        'Day(s) Ago' => lang('Day(s) Ago'),
        'Current Time' => lang('Current Time'),
        'Viewer' => lang('Viewer'),
        'Viewer\'s E-mail Address' => lang('Viewer\'s E-mail Address'),
        'Save & Continue' => lang('Save & Continue'),
        'Save' => lang('Save'),
        'Ad Region' => lang('Ad Region'),
        'Common Region' => lang('Common Region'),
        'Designer Region' => lang('Designer Region'),
        'Dynamic Region' => lang('Dynamic Region'),
        'Login Region' => lang('Login Region'),
        'Menu Region' => lang('Menu Region'),
        'Menu Sequence Region' => lang('Menu Sequence Region'),
        'System Region' => lang('System Region'),
        'Use Page' => lang('Use Page'),
        'Edit Cell Properties' => lang('Edit Cell Properties'),
        'Please select a region name.' => lang('Please select a region name.'),
        'Please add one "Use Page" system region before continuing.' => lang('Please add one "Use Page" system region before continuing.'),
        'Your browser does not support secure key generation. Please use a modern browser.' => lang('Your browser does not support secure key generation. Please use a modern browser.'),
        'An error occurred while generating the key. Please try again.' => lang('An error occurred while generating the key. Please try again.'),
        'Retry Backup' => lang('Retry Backup'),
        'Backing up' => lang('Backing up'),
        'Backup Builder Starting...' => lang('Backup Builder Starting...'),
        'Label Designer' => lang('Label Designer'),
        'Library not loaded.' => lang('Library not loaded.'),
        'Drag fields to canvas' => lang('Drag fields to canvas'),
        'Reset to Default' => lang('Reset to Default'),
        'Cancel' => lang('Cancel'),
        'Save Template' => lang('Save Template'),
        'SKU' => lang('SKU'),
        'Product Name' => lang('Product Name'),
        'Attributes' => lang('Attributes'),
        'Product Image' => lang('Product Image'),
        'Reset to default template?' => lang('Reset to default template?'),
        'Saving...' => lang('Saving...'),
        'Error saving template.' => lang('Error saving template.'),
        'Network error.' => lang('Network error.'),
        'Please allow pop-up windows for this site to print barcodes.' => lang('Please allow pop-up windows for this site to print barcodes.'),
        'Barcode preview' => lang('Barcode preview'),
        'Image' => lang('Image'),
        'Add Text' => lang('Add Text'),
        'Add Rect' => lang('Add Rect'),
        'Add Image' => lang('Add Image'),
        'Rotate 90°' => lang('Rotate 90°'),
        'Bring to Front' => lang('Bring to Front'),
        'Send to Back' => lang('Send to Back'),
        'Delete' => lang('Delete'),
        'X (mm)' => lang('X (mm)'),
        'Y (mm)' => lang('Y (mm)'),
        'W (mm)' => lang('W (mm)'),
        'H (mm)' => lang('H (mm)'),
        'Font size' => lang('Font size'),
        'Color' => lang('Color'),
        'Align' => lang('Align'),
        'Weight' => lang('Weight'),
        'Scroll X' => lang('Scroll X'),
        'Text' => lang('Text'),
        'Format' => lang('Format'),
        'Show text' => lang('Show text'),
        'Border color' => lang('Border color'),
        'Fill color' => lang('Fill color'),
        'Border width' => lang('Border width'),
        'Radius' => lang('Radius'),
        'Dynamic (changes per product)' => lang('Dynamic (changes per product)'),
        'Image URL / path' => lang('Image URL / path'),
        'e.g. uploads/logo.png' => lang('e.g. uploads/logo.png'),
        'Browse' => lang('Browse'),
        'Label Size' => lang('Label Size'),
        'Width (mm)' => lang('Width (mm)'),
        'Height (mm)' => lang('Height (mm)'),
        'Background' => lang('Background'),
        'The primary barcode element cannot be deleted.' => lang('The primary barcode element cannot be deleted.'),
        'Invalid barcode' => lang('Invalid barcode'),
        'Barcode value is required.' => lang('Barcode value is required.'),
        'Barcode saved.' => lang('Barcode saved.'),
        'Error.' => lang('Error.'),
        'Generating...' => lang('Generating...'),
        'Error generating barcode.' => lang('Error generating barcode.'),
        'Barcodes' => lang('Barcodes'),
        'No barcodes assigned to this product.' => lang('No barcodes assigned to this product.'),
        'Print' => lang('Print'),
        'Are you sure you want to delete the barcode for this product?' => lang('Are you sure you want to delete the barcode for this product?'),
        'Save a barcode first before printing.' => lang('Save a barcode first before printing.'),
        'Confirm' => lang('Confirm'),
        'OK' => lang('OK'),
    );

    return '
    <script type="text/javascript">
        var path = "' . escape_javascript(PATH) . '",
            software_directory = "' . escape_javascript(SOFTWARE_DIRECTORY) . '",
            software_system_language = "' . escape_javascript(lang(array('info' => ''))) . '",
            software_token = "' . ($_SESSION['software']['token'] ?? '') . '",
            software_user_role = ' . (defined('USER_ROLE') ? (int) USER_ROLE : 3) . ',
            software_backend_user = ' . (defined('USER_ROLE') ? 'true' : 'false') . ',
            software_user_manage_ecommerce = ' . ((defined('USER_MANAGE_ECOMMERCE') && USER_MANAGE_ECOMMERCE) ? 'true' : 'false') . ',
            software_update_check_needed = ' . ((defined('SOFTWARE_UPDATE_CHECK_NEEDED') && SOFTWARE_UPDATE_CHECK_NEEDED) ? 'true' : 'false') . ',
            help_url = "' . $help_url . '",
            translate = ' . encode_json($translate) . ';
        (function() {
          const storedTheme = localStorage.getItem("pinegrap backend color scheme");
          const prefersDark = window.matchMedia("(prefers-color-scheme: dark)").matches;
          const theme = storedTheme || (prefersDark ? "dark" : "light");
          document.documentElement.setAttribute("data-bs-theme", theme);
        })();   
    </script>
    <link rel="stylesheet" type="text/css" href="' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/assets/lib/Jquery/ui/jquery-ui.min.css?v=' . @filemtime(PG_FUNCTIONS_DIR . '/assets/lib/Jquery/ui/jquery-ui.min.css') . '"/>
    <link rel="stylesheet" type="text/css" href="' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/assets/lib/Jquery/ui/jquery-ui.theme.' . ENVIRONMENT_SUFFIX . '.css?v=' . @filemtime(PG_FUNCTIONS_DIR . '/assets/lib/Jquery/ui/jquery-ui.theme.' . ENVIRONMENT_SUFFIX . '.css') . '" />
    <link rel="stylesheet" type="text/css" href="' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/assets/lib/bootstrap-5.3.8/css/bootstrap.min.css?v=' . @filemtime(PG_FUNCTIONS_DIR . '/assets/lib/bootstrap-5.3.8/css/bootstrap.min.css') . '"/>
    <link rel="stylesheet" type="text/css" href="' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/assets/fonts/bootstrap-icons/bootstrap-icons.min.css?v=' . @filemtime(PG_FUNCTIONS_DIR . '/assets/fonts/bootstrap-icons/bootstrap-icons.min.css') . '">
    <link rel="stylesheet" type="text/css" href="' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/assets/lib/DataTables/datatables.min.css" />
    <link rel="stylesheet" type="text/css" href="' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/assets/lib/select2/select2.min.css" />
    <link rel="stylesheet" type="text/css" href="' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/assets/lib/select2/select2-bootstrap-5-theme.min.css" />
    <link rel="stylesheet" type="text/css" href="' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/assets/lib/DataTables/datatables.min.css" rel="stylesheet">
    <link rel="stylesheet" type="text/css" href="' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/assets/fonts/material-icons/material-icons.css?v=' . @filemtime(PG_FUNCTIONS_DIR . '/assets/fonts/material-icons/material-icons.css') . '"/>
    <script type="text/javascript" src="' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/assets/lib/Jquery/jquery.min.js"></script>
    <script type="text/javascript" src="' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/assets/lib/Jquery/ui/jquery-ui.min.js"></script>
    <script type="text/javascript" src="' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/assets/lib/bootstrap-5.3.8/js/bootstrap.bundle.min.js"></script>
    <script type="text/javascript" src="' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/assets/lib/DataTables/datatables.min.js"></script>
    <script type="text/javascript" src="' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/assets/lib/Jquery/jquery_datatables_checkbox.min.js"></script>
    <script type="text/javascript" src="' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/assets/js/lazy.min.js"></script>
    <script type="text/javascript" src="' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/assets/lib/select2/select2.min.js"></script>
    <script type="text/javascript" src="' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/assets/lib/multiselect-checkbox/jquery-multiselect-checkbox.min.js"></script>
    <script type="text/javascript" src="' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/assets/lib/json2/json.min.js"></script>
    <script type="text/javascript" src="' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/assets/lib/Inputmask-5.x/jquery.inputmask.min.js"></script>
    <script type="text/javascript" src="' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/assets/lib/Inputmask-5.x/bindings/inputmask.binding.js"></script>
    ' . get_codemirror_includes() . '
    <link rel="stylesheet" type="text/css" href="' . CONTROL_PANEL_STYLESHEET_URL . '" />
    ' . $assistant_snippet . '
    <script src="' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/assets/js/backend.src.js?v=' . @filemtime(PG_FUNCTIONS_DIR . '/assets/js/backend.src.js') . '"></script>
    <style>' . CUSTOM_CSS . '</style>';


}

/**
 * The chat launcher, for any screen that wants it.
 *
 * Lives in a function rather than inline in output_footer() because not every
 * screen ends with that footer: the visual editor is a full-height application
 * that draws its own tail, and it was therefore the one screen where two people
 * working on the same design could not talk to each other.
 *
 * Chat must never break a page: a missing module file, the master switch off or
 * a database that has not been upgraded all produce an empty string.
 */
function pg_chat_launcher_html()
{
    if (!defined('USER_LOGGED_IN') || !USER_LOGGED_IN) return '';
    if (!defined('CHAT_ENABLED') || !CHAT_ENABLED)     return '';
    if (!file_exists(PG_FUNCTIONS_DIR . '/chat.php'))  return '';

    require_once(PG_FUNCTIONS_DIR . '/chat.php');
    if (!function_exists('pg_chat_render_backend_launcher')) return '';

    return pg_chat_render_backend_launcher();
}


/**
 * The brand lockup, as inline SVG.
 *
 * Inline rather than an <img> so the gradient can read the theme's
 * --pg-logo-color-* variables and follow light/dark mode. It lives in a
 * function because two places draw it now — the header and the loading
 * curtain — and a second copy of an eight-kilobyte path list is the copy
 * that stops matching.
 *
 * $id_suffix keeps the gradient ids unique when both copies are on the page:
 * duplicate ids resolve to the first element, which happens to look the same
 * here, but only by luck. One regex pass rather than str_replace: str_replace
 * with an array runs each pair over the WHOLE string in turn, so renaming
 * 'pgLogoGradientIcon' first and 'pgLogoGradient' second produced
 * 'pgLogoGradient-plIcon-pl' — the second pass walking back into the first
 * pass's output.
 */
function pg_logo_svg($id_suffix = '')
{
    $svg = <<<'PGLOGOSVG'
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 530.44 142.47" role="img" aria-label="PineGrap" fill="url(#pgLogoGradient)"><defs><linearGradient id="pgLogoGradient" gradientUnits="userSpaceOnUse" x1="0" y1="142.47" x2="530.44" y2="0"><stop offset="0" style="stop-color:var(--pg-logo-color-1)"/><stop offset=".75" style="stop-color:var(--pg-logo-color-2)"/></linearGradient><linearGradient id="pgLogoGradientIcon" gradientUnits="userSpaceOnUse" x1="0" y1="142.47" x2="64.6" y2="0"><stop offset="0" style="stop-color:var(--pg-logo-color-1)"/><stop offset=".75" style="stop-color:var(--pg-logo-color-2)"/></linearGradient></defs><path d="M64.38,1.66c-.04-.09-.16-.06-.16,0l-.1,2.74c-.24,1.08-.08,2.15.26,3.15l.06,1.21c-14.53.04-27.91,5.61-38.28,15.32-3.38,3.16-6.16,6.51-8.54,10.36l-1.44,2.33c-.76,1.23-1.44,2.44-1.82,3.94,1.51-2.34,3.03-4.36,4.98-6.32,3.66-3.68,7.73-6.81,12.35-8.9l22.66,22.66,2.56-1.44c2.44-.93,4.81-1.75,7.49-1.65v3.17c-2.94.09-5.58.88-8.13,2.46l1.97,2c1.95-1.01,4.23-1.84,6.15-1.81v3.03c-4.86.35-8.76,3.6-10.04,8.44-1.74,6.6,3.14,13.16,10.03,13.61l-.02,2.97c-2.7-.1-5.3-.89-7.43-2.56-.26-.2-.69-.9-1.05-.58l-1.84,1.62c2.51,2.5,6.75,4.22,10.36,4.19l-.05,3.21c-3.63-.15-7.06-1.18-10.11-3.24l-2.49,2.39-1.84-1.18c-1.1-.47-3.08-.57-4.06.4l-10.5,10.5-8.01,8c-1.19,1.19-2.87,1.43-4.45,1.46l-1.58.03c-1.07,1.14-2.28,2.09-3.42,3.16l-1.87,1.75c-5.7-6.39-10.2-13.53-12.89-21.52-1.36-4.04-2.57-8.08-2.91-12.36l-.24-17.76c1.69-11.47,5.87-22.29,13.11-31.49C23.4,11.86,37.96,3.28,53.83.87c3.61-.55,6.93-.98,10.58-.85l-.03,1.63ZM17.72,87.12c1.85.53,3.61.58,5.48.75,3.84.35,6.04,4.12,5.19,8.2l2.55-2.41,21.81-21.77c.33-.33-.33-.8-.44-1.05-2.26-5.13-1.37-11.13,2.28-15.39-1.24-1-2.24-2.05-3.33-3.16l-10.01-10.07-3.17-3.24c-.59.45-1.19.56-1.83.78-5.46,1.88-10.05,5.21-14.15,9.31-4.94,4.95-9.45,10.12-13.8,15.57-1.64,2.06-5.05,6.31-2.51,7.02.88.25,2.12.16,2.95-.28l1.96-1.05c.53,1.02-.1,1.81-.48,2.61-.58,1.24-1.43,2.19-2.67,3.01.45.4.35,1.01.57,1.54.6,1.53,1.26,3.05,2.19,4.36,1.86,2.63,4.37,4.42,7.41,5.29Z"/><path d="M41.18,127.17c-1.68,3.05-2.92,6.2-3.68,9.59-.44,1.95-.94,3.78-.99,5.72h-9.32c.5-5.97,2.2-11.56,5.22-16.87l-11.64-.11c-1.48-.01-2.37-1.17-3.11-2.48v-13.32c-.39-.16-.55-.38-.71-.77l4.46-4.04c1.02-.06,2.16.26,3.26-.09,1.31-.43,2.92-.85,3.94-1.86l8.91-8.88c.56-.56,1.02-1.08,1.58-1.65l1.22-1.25.58,3.75-4.47,4.29-10,9.87-.02,7.47,14.1.05c5.52-4.53,12.26-7.35,19.48-8.26,1.57-.2,2.99-.52,4.58-.39l-.16,2.02c-4.78.64-9.05,2.46-12.87,5.21-1.99,1.43-3.82,2.89-5.4,4.93,5.92,2.14,11.99,3.16,18.28,3.35v8.53c-3.91.11-7.61-.26-11.43-1.09-4.01-.76-7.76-1.81-11.79-3.7Z"/><path d="M55.59,37.01c1.74-.65,3.5-.87,5.25-1.17,1.19-.2,2.32-.36,3.56-.26l-.03,6.16c-3.57,0-7.04.88-10.1,2.66l-18.81-18.91c1.26-.88,2.62-1.66,4.18-1.6l2.72.58c4.37-1.16,8.72-1.92,13.36-2.41l1.16,3.29-2.27.18-6.35,1.06-5.74,1.43,7.83,7.72c1.62.97,3.39,1.42,5.24,1.27Z"/><path d="M64.38,59.35c-.47,1.8-.54,4.22,0,6.06l.13,1.44-4.38,4.43c-3.45-2.84-4.06-7.84-1.31-11.29,1.38-1.74,3.35-2.72,5.57-3l-.02,2.36Z"/><path d="M64.38,22.85c-.04-.09-.14-.06-.14,0l-.13,3.97c-.02.75-.08,1.43.27,2.09l.02,3.96-3.72.46-.46-1.17-1.39-5.06c-.47-1.72-.86-3.44-1.5-5.16l4.53-.26,2.52.02v1.15Z"/><path d="M56.09,94.87c-.08.23-.15.41-.23.49-.2.19-.38-.1-.52-.15-2.72-.92-5.3-1.98-7.71-3.77l6.03-6c1.44.85,2.85,1.46,4.44,1.94l-2.01,7.49Z"/><path d="M64.4,96.51c-2.45.17-4.74-.22-7.08-.77l.7-2.21,1.54-5.76c1.64.32,3.14.69,4.85.58l-.02,8.17Z"/><path d="M55.75,34.38c-2.01.5-3.83-.12-5.03-1.66-.74-.95-1.86-1.19-1.96-2.19-.03-.33.1-1.17.55-1.24l6.69-.98c2.42-.35,1.87,2.36,3.09,5.24l-3.34.83Z"/><path d="M42.24,93.42l-.73-3.57,4.55-4.55c1.32-1.32,3.31-1.35,4.44.03l-8.26,8.09Z"/><path d="M64.39,65.41c-.54-1.83-.48-4.26,0-6.06v6.06Z"/><path d="M64.38,7.54c-.34-.99-.5-2.07-.26-3.15l.1-2.74c0-.06.12-.09.16,0v5.89Z"/><path d="M64.39,28.91c-.35-.66-.29-1.34-.27-2.09l.13-3.97c0-.06.1-.09.14,0v6.06Z"/><path d="M40.26,57.95c.09,3.32-2.42,5.9-5.35,6.19-3.22.31-6.1-1.91-6.53-5.06-.42-3.1,1.4-6.01,4.71-6.75s7.07,1.89,7.18,5.63Z"/><path d="M48.8,68.84l1.02,3.09-2.25,2.25c-3.58-6.72-2.9-14.81,1.51-21.02l2.29,2.28c-2.62,3.91-3.7,8.77-2.56,13.4Z"/><path d="M121.76,33.52c-2.12-3.5-5.02-6.26-8.69-8.27-3.68-2.01-7.67-3.02-11.98-3.02h-23.96v74.22h13.78v-27.25h10.18c4.31,0,8.29-1.08,11.93-3.23,3.64-2.16,6.54-5.07,8.69-8.75,2.16-3.67,3.23-7.67,3.23-11.98s-1.06-8.22-3.18-11.72ZM108.46,53.56c-1.8,2.23-4.26,3.34-7.37,3.34h-10.18v-22.37h10.18c3.11,0,5.57.97,7.37,2.92,1.8,1.94,2.7,4.54,2.7,7.79s-.9,6.1-2.7,8.32Z"/><path d="M133.16,35.43c-1.59-1.59-2.39-3.55-2.39-5.88s.79-4.19,2.39-5.78c1.59-1.59,3.55-2.39,5.88-2.39s4.19.79,5.78,2.39c1.59,1.59,2.39,3.52,2.39,5.78s-.79,4.29-2.39,5.88c-1.59,1.59-3.52,2.39-5.78,2.39s-4.29-.79-5.88-2.39Z"/><rect x="132.15" y="43.44" width="13.78" height="53.01"/><path d="M201.28,96.45h-13.78v-27.88c0-4.31-.76-7.47-2.28-9.49-1.52-2.01-3.94-3.02-7.26-3.02s-5.74,1.01-7.26,3.02c-1.52,2.01-2.28,5.18-2.28,9.49v27.88h-13.78v-27.88c0-8.62,2.12-15.11,6.36-19.46,4.24-4.35,9.9-6.52,16.96-6.52s12.72,2.17,16.96,6.52c4.24,4.35,6.36,10.83,6.36,19.46v27.88Z"/><path d="M261.71,68.77c-.14-5.37-1.57-10.04-4.29-14-2.72-3.96-6.17-6.98-10.34-9.07-4.17-2.08-8.52-3.13-13.04-3.13-4.95,0-9.49,1.26-13.62,3.76-4.13,2.51-7.39,5.87-9.75,10.07-2.37,4.21-3.55,8.75-3.55,13.62s1.22,9.51,3.66,13.68c2.44,4.17,5.74,7.47,9.91,9.91,4.17,2.44,8.76,3.66,13.78,3.66,5.51,0,10.53-1.52,15.05-4.56,4.52-3.04,7.88-7,10.07-11.87l-16.43-.11c-1.13.92-2.38,1.66-3.76,2.23-1.38.57-3.16.85-5.35.85-3.11,0-5.74-.76-7.9-2.28-2.16-1.52-3.55-3.59-4.19-6.2h39.33c.35-2.12.49-4.31.42-6.57ZM221.95,64.32c1.13-2.54,2.76-4.56,4.88-6.04,2.12-1.48,4.67-2.23,7.63-2.23,2.69,0,5.18.76,7.47,2.28,2.3,1.52,3.98,3.52,5.04,5.99h-25.02Z"/><path d="M318.06,56.21c-2.44-4.2-5.76-7.53-9.97-9.97-4.21-2.44-8.78-3.66-13.73-3.66s-9.53,1.22-13.73,3.66c-4.21,2.44-7.53,5.76-9.97,9.97-2.44,4.21-3.66,8.78-3.66,13.73s1.22,9.53,3.66,13.73c2.44,4.21,5.76,7.53,9.97,9.97,4.21,2.44,8.78,3.66,13.73,3.66s9.15-1.17,13.25-3.5v.11c0,3.18-1.29,5.69-3.87,7.53-2.58,1.84-5.71,2.76-9.38,2.76-1.56,0-2.99-.23-4.29-.69-1.31-.46-2.63-1.22-3.98-2.28h-16.75c2.12,4.95,5.44,8.92,9.97,11.93,4.52,3,9.54,4.51,15.06,4.51,4.95,0,9.52-1.22,13.73-3.66,4.21-2.44,7.53-5.76,9.97-9.97,2.44-4.21,3.66-8.78,3.66-13.73v-20.36c0-4.95-1.22-9.52-3.66-13.73ZM304.12,79.85c-2.54,2.65-5.76,3.98-9.65,3.98s-7.14-1.33-9.75-3.98c-2.62-2.65-3.92-5.95-3.92-9.91s1.31-7.26,3.92-9.91c2.61-2.65,5.87-3.98,9.75-3.98s7.1,1.33,9.65,3.98,3.82,5.96,3.82,9.91-1.27,7.26-3.82,9.91Z"/><path d="M328.08,68.56c0-8.41,2.08-14.74,6.26-18.98,4.17-4.24,9.65-6.36,16.43-6.36h.64v13.46h-.53c-3.04,0-5.3.95-6.79,2.86-1.48,1.91-2.23,4.91-2.23,9.01v27.88h-13.78v-27.88Z"/><path d="M406.06,56.21c-2.44-4.2-5.74-7.53-9.91-9.97-4.17-2.44-8.73-3.66-13.68-3.66s-9.51,1.22-13.68,3.66c-4.17,2.44-7.49,5.76-9.97,9.97-2.47,4.21-3.71,8.78-3.71,13.73s1.24,9.53,3.71,13.73c2.47,4.21,5.79,7.53,9.97,9.97,4.17,2.44,8.73,3.66,13.68,3.66,2.97,0,5.76-.64,8.38-1.91,2.61-1.27,4.31-2.79,5.09-4.56v5.62h13.78v-26.51c0-4.95-1.22-9.52-3.66-13.73ZM392.23,79.85c-2.54,2.65-5.76,3.98-9.65,3.98s-7.14-1.33-9.75-3.98c-2.62-2.65-3.92-5.95-3.92-9.91s1.31-7.26,3.92-9.91c2.61-2.65,5.87-3.98,9.75-3.98s7.1,1.33,9.65,3.98,3.82,5.96,3.82,9.91-1.27,7.26-3.82,9.91Z"/><path d="M466.97,56.21c-2.48-4.2-5.8-7.53-9.97-9.97-4.17-2.44-8.73-3.66-13.68-3.66s-9.51,1.22-13.68,3.66c-4.17,2.44-7.47,5.76-9.91,9.97-2.44,4.21-3.66,8.78-3.66,13.73v47.71h13.89v-24.39c3.75,2.69,8.2,4.03,13.36,4.03s9.51-1.22,13.68-3.66c4.17-2.44,7.49-5.76,9.97-9.97,2.47-4.21,3.71-8.78,3.71-13.73s-1.24-9.52-3.71-13.73ZM453.08,79.85c-2.54,2.65-5.76,3.98-9.65,3.98s-7.14-1.33-9.75-3.98c-2.62-2.65-3.92-5.95-3.92-9.91s1.31-7.26,3.92-9.91c2.61-2.65,5.87-3.98,9.75-3.98s7.1,1.33,9.65,3.98c2.54,2.65,3.82,5.96,3.82,9.91s-1.27,7.26-3.82,9.91Z"/><polygon points="497.86 28.36 497.86 32.57 489.71 32.57 489.71 53.83 484.66 53.83 484.66 32.57 476.51 32.57 476.51 28.36 497.86 28.36"/><path d="M525.38,53.83l-.94-14.98c-.09-1.97-.09-4.4-.19-7.12h-.28c-.66,2.25-1.4,5.24-2.15,7.58l-4.59,14.14h-5.24l-4.59-14.51c-.47-1.97-1.22-4.96-1.78-7.21h-.28c0,2.34-.09,4.78-.19,7.12l-.94,14.98h-4.87l1.87-25.47h7.58l4.4,12.45c.56,1.97,1.03,3.84,1.69,6.46h.09c.66-2.34,1.22-4.49,1.78-6.37l4.4-12.55h7.3l1.97,25.47h-5.06Z"/></svg>
PGLOGOSVG;

    if ($id_suffix !== '') {
        $svg = preg_replace_callback('/pgLogoGradient(Icon)?/', function ($m) use ($id_suffix) {
            return $m[0] . $id_suffix;
        }, $svg);
    }
    return $svg;
}


/**
 * The loading curtain.
 *
 * Two jobs, one element. Arriving: an admin screen assembles itself in the
 * browser — the grid settles, the sidebar snaps into place, tables take their
 * widths — and for a moment the page is a half-built layout sliding sideways,
 * which reads as a fault rather than as loading. Leaving: the curtain goes
 * back up the instant a link is clicked, so the wait for the next screen
 * happens behind it instead of on a frozen copy of the old one. Between the
 * two the panel stops flashing between pages at all.
 *
 * It is the page's own background colour with a progress bar across the top,
 * not a picture: this is on every screen, dozens of times an hour, and
 * anything with a personality of its own becomes tiring by the third visit.
 * The bar is what says "working"; the rest is deliberately nothing.
 *
 * Deliberately self-contained — markup, style hook and script arrive
 * together, right after <body>, so the curtain is up before any stylesheet or
 * script further down has had to load. Nothing else on the page can be relied
 * on at that point, so nothing else is.
 *
 * Four ways out, because a curtain that sticks makes the panel unusable: the
 * document being ready (the normal one), any interaction after a navigation
 * that did not happen (a cancelled "unsaved changes" prompt), a safety
 * timeout, and a CSS animation that hides the element after ten seconds even
 * if this script never ran at all.
 *
 * toolbar.php is excluded — it is the strip inside the frontend edit frame,
 * a few controls in a thin bar, and a curtain over the site being edited
 * would be the opposite of reassuring.
 */
function pg_preloader_markup()
{
    $script = basename(isset($_SERVER['SCRIPT_NAME']) ? (string)$_SERVER['SCRIPT_NAME'] : '');
    if ($script === 'toolbar.php') {
        return '';
    }

    // The mark in the middle. On a private-label install there is no mark to
    // show — the whole point of that mode is that the software does not name
    // itself — so those screens get the three dots and nothing else.
    $mark = '';
    if (!defined('PRIVATE_LABEL') or !PRIVATE_LABEL) {
        $mark = '<span class="pg-preloader-logo">' . pg_logo_svg('-pl') . '</span>';
    }

    $markup = '<div id="pg-preloader" class="pg-preloader" aria-hidden="true">'
            . '<div class="pg-preloader-bar"><i></i></div>'
            . '<div class="pg-preloader-mark">' . $mark
            . '<div class="pg-preloader-dots"><i></i><i></i><i></i></div>'
            . '</div></div>'
            . '<noscript><style>#pg-preloader{display:none !important;}</style></noscript>';

    return $markup . <<<'PGPRELOADER'

    <script>
    (function () {
        var host = document.getElementById('pg-preloader');
        if (!host) return;
        var timer = 0;

        function hide() {
            clearTimeout(timer);
            // The CSS failsafe hides the curtain after ten seconds in case
            // this script never ran. Getting here proves it did — and leaving
            // the animation armed would let it fire `forwards` on a page left
            // open, after which the curtain could never be raised again on
            // the way out.
            host.style.animation = 'none';
            host.classList.add('is-done');
        }
        function show() {
            clearTimeout(timer);
            host.classList.remove('is-done');
        }

        window.pgPreloaderDone = hide;
        window.pgPreloaderShow = show;

        // A screen that keeps building after the document is ready — the
        // visual editor is the one — sets pgPreloaderHold and calls
        // pgPreloaderDone() itself once it has actually painted.
        if (!window.pgPreloaderHold) {
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', hide);
            } else {
                hide();
            }
        }
        setTimeout(hide, 9000);

        // Coming back through the browser's back button hands us the page
        // exactly as it was — curtain and all, if it was up when we left.
        window.addEventListener('pageshow', function (e) { if (e.persisted) hide(); });

        /* ── On the way out ──────────────────────────────────────────────
         * Raised again the moment a navigation is asked for. The one thing
         * it must not do is stay up when the navigation never happens — an
         * unsaved-changes prompt that the operator cancels, a handler that
         * calls preventDefault. So it comes down again on the first sign
         * that somebody is still here, and on a timer regardless.
         */
        function leaving() {
            show();
            timer = setTimeout(hide, 6000);
            ['pointerdown', 'keydown', 'wheel'].forEach(function (evt) {
                document.addEventListener(evt, hide, { once: true, capture: true });
            });
        }

        document.addEventListener('click', function (e) {
            if (e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;
            var a = e.target && e.target.closest ? e.target.closest('a[href]') : null;
            if (!a) return;
            if (a.target && a.target !== '_self') return;
            if (a.hasAttribute('download') || a.hasAttribute('data-bs-toggle')) return;
            // A link the settings dialog answers is not a navigation, and a
            // curtain over a page that stays put has to be waited out. Asked
            // of the dialog rather than matched here, so there is one list of
            // the addresses it claims.
            if (window.pgSettingsModal && window.pgSettingsModal.asked(a)) return;
            var href = a.getAttribute('href') || '';
            if (!href || href.charAt(0) === '#') return;
            if (/^(javascript|mailto|tel|sms|data):/i.test(href)) return;
            // A link to this page with only the fragment changed is not a
            // navigation; neither is one that leaves this origin, where the
            // curtain would be covering a page we no longer control.
            var url;
            try { url = new URL(a.href, location.href); } catch (err) { return; }
            if (url.origin !== location.origin) return;
            if (url.href.split('#')[0] === location.href.split('#')[0]) return;
            leaving();
        }, true);

        document.addEventListener('submit', function (e) {
            var f = e.target;
            if (e.defaultPrevented || !f || f.tagName !== 'FORM') return;
            if (f.target && f.target !== '_self') return;   // posting into a frame
            if (f.hasAttribute('data-pg-no-curtain')) return;
            leaving();
        }, true);
    })();
    </script>
PGPRELOADER;
}


function pg_page_shell($properties = array())
{
    if (!is_array($properties)) {
        $properties = array();
    }

    // A screen that names a tour says so here.  The engine itself is already
    // in the head, put there by the header for the tour of the frame, so all
    // this adds is the one thing the screen's own tour cannot work out: has
    // this person watched it.
    if ((isset($properties['tour'])) && ($properties['tour'] != '')) {
        $properties['head'] = (isset($properties['head']) ? $properties['head'] : '') . pg_tour_head($properties['tour']);
    }

    $output = output_header($properties);

    $extra_class = isset($properties['action_bar_class']) ? (string) $properties['action_bar_class'] : 'mb-3';
    $extra_class = htmlspecialchars($extra_class, ENT_QUOTES, 'UTF-8');

    // Opt-in raw HTML printable between <body>/header and <main>.
    // Used by pages like view_log.php that render an advanced filter bar outside <main>.
    if (isset($properties['pre_main_html']) && is_string($properties['pre_main_html']) && $properties['pre_main_html'] !== '') {
        $output .= $properties['pre_main_html'];
    }

    $buttons_html = '';

    $render_button = function ($btn, $default_variant) {
        if (!is_array($btn) || empty($btn['label'])) return '';
        $label = htmlspecialchars((string) $btn['label'], ENT_QUOTES, 'UTF-8');
        $url   = isset($btn['url']) ? htmlspecialchars((string) $btn['url'], ENT_QUOTES, 'UTF-8') : '#';
        $icon  = isset($btn['icon']) ? htmlspecialchars((string) $btn['icon'], ENT_QUOTES, 'UTF-8') : '';
        $variant = isset($btn['variant']) ? preg_replace('/[^a-z0-9_-]/i', '', (string) $btn['variant']) : $default_variant;
        $icon_html = $icon !== '' ? '<i class="bi ' . $icon . ' me-2" aria-hidden="true"></i>' : '';
        return '<a class="btn btn-' . $variant . ' me-2" href="' . $url . '">' . $icon_html . $label . '</a>';
    };

    if (isset($properties['primary_button']) && is_array($properties['primary_button'])) {
        $buttons_html .= $render_button($properties['primary_button'], 'primary');
    }

    if (isset($properties['actions']) && is_array($properties['actions'])) {
        foreach ($properties['actions'] as $btn) {
            $buttons_html .= $render_button($btn, 'outline-secondary');
        }
    }

    if ($buttons_html !== '') {
        $output .= '<div class="pg-page-actions d-flex flex-wrap align-items-center ' . $extra_class . '">' . $buttons_html . '</div>';
    }

    return $output;
}


/**
 * The address of the settings, for a link.
 *
 * Site Settings is a dialog now. pgSettingsModal reads this address off the
 * href and opens over the screen the operator is already on, so nothing has to
 * travel with the link -- which matters, because the breadcrumb, the cancel
 * button and the widget helpers all emit a bare <a href> and pass no
 * attributes through. The address stays a real screen, and that is what the
 * link does where the dialog is not printed, on a middle click and in a new
 * tab.
 *
 * Named here rather than written out at the call sites because the category
 * list is registry.php's to answer, including which category comes first.
 *
 * @param string $pane    a category key, or '' for the one last used
 * @param string $section a card inside it, e.g. 'pgset-waf'
 * @return string
 */
function pg_settings_link($pane = '', $section = '')
{
    if (!defined('PG_SETTINGS_MENU')) {
        define('PG_SETTINGS_MENU', true);
    }

    include_once(PG_FUNCTIONS_DIR . '/includes/settings/registry.php');

    // There is no hub screen: the sidebar of the dialog is the hub, and the
    // page path is the eight category screens with nothing above them. So the
    // address for "the settings, no category in particular" is the first of
    // them. The dialog then opens on THAT category rather than on the one last
    // used, because the address names one -- the gear in the header is what
    // carries "wherever I was".
    if ($pane === '') {

        foreach (pg_settings_categories() as $first => $category) {
            $pane = $first;
            break;
        }
    }

    $url = pg_settings_url($pane);

    return ($section !== '') ? ($url . '#' . $section) : $url;
}


/**
 * Where to send a browser that has finished with a tool and wants the settings.
 *
 * There is no settings screen to redirect TO any more, so the address names a
 * screen and the dialog on top of it; pgSettingsModal opens it from the
 * fragment on arrival, and settings_pane.php hands over whatever notice the
 * tool left in the form on its way out. Returned as a bare file name, the way
 * each caller builds its own prefix.
 *
 * @param string $pane    a category key, or '' for the one last used
 * @param string $section a card inside it, e.g. 'pgset-channel'
 * @return string
 */
function pg_settings_return_url($pane = '', $section = '')
{
    $hash = '#settings';

    if ($pane !== '') {
        $hash .= '/' . $pane . (($section !== '') ? '/' . $section : '');
    }

    return 'welcome.php' . $hash;
}


/**
 * Validate a candidate "send_to" / back-navigation URL against an allowlist.
 * Use to avoid open-redirect when echoing user-supplied $_REQUEST['send_to']
 * into a cancel-button URL.
 *
 * Accepts:
 *   - Empty / missing candidate → returns $fallback
 *   - Relative same-script paths like "view_pages.php", "edit_user.php?id=5"
 *   - Absolute URLs whose host matches the current HTTP_HOST
 * Everything else (other hosts, javascript:, data:, protocol-relative //evil.com, etc.)
 *   → returns $fallback
 *
 * @param string $candidate Untrusted URL (typically from $_REQUEST/$_GET)
 * @param string $fallback  Safe default to return when the candidate is rejected
 * @return string A safe URL string suitable to put inside a JS location assignment
 */
function pg_safe_back_url($candidate, $fallback)
{
    $candidate = is_string($candidate) ? trim($candidate) : '';
    if ($candidate === '') {
        return $fallback;
    }

    // Reject protocol-relative URLs and dangerous schemes outright
    if (strpos($candidate, '//') === 0) {
        return $fallback;
    }
    $lower = strtolower($candidate);
    if (strpos($lower, 'javascript:') === 0 || strpos($lower, 'data:') === 0 || strpos($lower, 'vbscript:') === 0) {
        return $fallback;
    }

    // Relative .php path (optionally with query string and #fragment), no host/scheme
    if (preg_match('~^[A-Za-z0-9_./-]+\.php(\?[^\s]*)?(#[^\s]*)?$~', $candidate)) {
        return $candidate;
    }

    // Root-relative path — e.g. "/siparis", "/dukkan/urun-1?q=foo".
    // The protocol-relative "//host" form was already rejected above, so
    // a single leading "/" is unambiguously same-origin. Reject anything
    // that contains control characters, which would let an attacker hide
    // CRLF / NUL sequences inside a Location header.
    if (strlen($candidate) > 0 && $candidate[0] === '/'
        && !preg_match('/[\x00-\x1F\x7F]/', $candidate)) {
        return $candidate;
    }

    // Absolute URL: only allow if host matches current request host
    $parts = @parse_url($candidate);
    if (is_array($parts) && isset($parts['host'])) {
        $current_host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '';
        if ($current_host !== '' && strcasecmp($parts['host'], $current_host) === 0) {
            return $candidate;
        }
    }

    return $fallback;
}


/**
 * Render an empty-state placeholder for list pages with no data.
 * Additive — call sites must opt in.
 *
 * @param string $icon         Bootstrap Icon class without the leading "bi" (e.g. "bi-folder2-open")
 * @param string $title        Visible heading (will be HTML-escaped)
 * @param string $message      Optional sub-message (will be HTML-escaped)
 * @param string $action_label Optional CTA button label (will be HTML-escaped)
 * @param string $action_url   Optional CTA button href (will be HTML-escaped)
 * @return string Bootstrap-styled empty state HTML
 */
function pg_empty_state($icon, $title, $message = '', $action_label = '', $action_url = '')
{
    $icon = (string) $icon;
    $icon_class = ($icon !== '') ? htmlspecialchars($icon, ENT_QUOTES, 'UTF-8') : 'bi-inbox';

    $output = '<div class="pg-empty-state text-center text-muted py-5 px-3">'
        . '<i class="bi ' . $icon_class . ' d-block mb-3" style="font-size:3rem;line-height:1;" aria-hidden="true"></i>'
        . '<h4 class="mb-2">' . htmlspecialchars((string) $title, ENT_QUOTES, 'UTF-8') . '</h4>';

    if ($message !== '') {
        $output .= '<p class="mb-3">' . htmlspecialchars((string) $message, ENT_QUOTES, 'UTF-8') . '</p>';
    }

    if ($action_label !== '' && $action_url !== '') {
        $output .= '<a class="btn btn-primary" href="' . htmlspecialchars((string) $action_url, ENT_QUOTES, 'UTF-8') . '">'
            . htmlspecialchars((string) $action_label, ENT_QUOTES, 'UTF-8')
            . '</a>';
    }

    $output .= '</div>';
    return $output;
}


/**
 * The empty state a dashboard widget shows when it has nothing to list.
 *
 * There were five of these before this function, invented separately by
 * whoever built each card, and three of them were wrong on screen:
 *
 *   - Ten cards centred a paragraph with `position-absolute top-50 start-50
 *     translate-middle`. That positions against the nearest POSITIONED
 *     ancestor, which is the card -- not the box the message belongs in. On a
 *     normal card it merely sat too high, centred over the header as well as
 *     the body. On the Orders card, which is split into two halves, both
 *     halves resolved to the same centre and printed their two messages on top
 *     of each other, one sentence overlapping the other.
 *   - The Calendars card printed a bare string with no wrapper, so it landed
 *     flush against the top left corner.
 *   - The rest each had their own spacing, icon size and muted colour.
 *
 * So the rule here is that the message centres itself inside whatever box it
 * is dropped into, by filling that box, rather than by measuring itself
 * against an ancestor it cannot see. A card body and one half of a split card
 * are then the same case, and no call site has to know which it is.
 *
 * @param string $icon    Bootstrap Icon class, e.g. "bi-cart4". Use the card's
 *                        own header icon so the empty state reads as a quiet
 *                        version of the card, except where empty is good news
 *                        -- an empty out-of-stock list wants a tick, not a
 *                        warning triangle.
 * @param string $message One sentence, escaped here.
 * @param string $tone    '' for the ordinary case, 'good' where empty is the
 *                        outcome the operator wants -- nothing out of stock,
 *                        no comments held for approval, no refund unpaid.
 *                        Those cards were already drawing a green tick by
 *                        hand; this keeps that meaning without keeping four
 *                        private copies of the styling.
 * @param string $action_label Optional button. Only worth offering where the
 *                        card can name the one thing that would change what it
 *                        is reporting -- an off firewall has a switch to point
 *                        at, an empty contact list does not.
 * @param string $action_url   Where that button goes.
 * @return string
 */
function pg_widget_empty($icon, $message, $tone = '', $action_label = '', $action_url = '')
{
    $icon = trim((string) $icon);

    if ($icon === '') {
        $icon = 'bi-inbox';
    }

    $has_action = (($action_label !== '') && ($action_url !== ''));

    $class = 'pg-widget-empty' . (($tone === 'good') ? ' is-good' : '');

    $output = '<div class="' . $class . '">'
        . '<i class="bi ' . h($icon) . '" aria-hidden="true"></i>'
        . '<p>' . h((string) $message) . '</p>';

    if ($has_action) {

        // position-relative so it sits above the stretched-link several cards
        // lay over their whole body; without it the card's own link swallows
        // the click and the button goes wherever the card goes.
        $output .= '<a class="btn btn-sm btn-outline-secondary position-relative" href="'
            . h((string) $action_url) . '">' . h((string) $action_label) . '</a>';
    }

    return $output . '</div>';
}


function output_header_secure($properties = false)
{
    $software_page_title = '';
    if (isset($properties['title'])) {
        $software_page_title = $properties['title'] . ' - ';
    }

    $software_title = '';
    if (!defined('PRIVATE_LABEL') or !PRIVATE_LABEL) {
        $software_title = 'Pinegrap ' . get_short_version() . ' | ';
    } else {
        $software_title = ' | ';
    }

    $output_body_class = '';
    if (isset($properties['extra classes'])) {
        $output_body_class = ' class="' . $properties['extra classes'] . '"';
    }

    $output_fav_icon = '';
    if (isset($properties['icon'])) {
        $output_fav_icon = $properties['icon'];
    }

    $output = '<!DOCTYPE html>
<html lang="' . lang(array('info' => '')) . '">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,minimum-scale=1,user-scalable=no">
    <title>' . $software_page_title . $software_title . h($_SERVER['HTTP_HOST']) . '</title>
    <meta name="application-name" content="Pinegrap CMS">
    <meta name="color-scheme" content="light dark">
    <link rel="icon" href="' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/assets/icons/ico/big/' . $output_fav_icon . '.ico" sizes="16x16 32x32 48x48 64x64 128x128 256x256" type="image/x-icon">
    <link rel="icon" type="image/png" sizes="75x75" href="' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/assets/icons/png/075/' . $output_fav_icon . '.png">
    <link rel="icon" type="image/png" sizes="100x100" href="' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/assets/icons/png/100/' . $output_fav_icon . '.png">
    <link rel="icon" type="image/png" sizes="150x150" href="' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/assets/icons/png/150/' . $output_fav_icon . '.png">
    <link rel="icon" type="image/png" sizes="200x200" href="' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/assets/icons/png/200/' . $output_fav_icon . '.png">
    <meta name="theme-color" content="#111111" media="(prefers-color-scheme: light)">
    <meta name="theme-color" content="#222222" media="(prefers-color-scheme: dark)">
    ' . get_generator_meta_tag() . '
    ' . output_control_panel_header_includes(false) . '
</head>
<body' . $output_body_class . '>';

    return $output;
}


function output_header($properties = false)
{
    //output
    $output = '';
    //user
    global $user;

    // Both the button and the dialog itself are drawn by this function.
    include_once(PG_FUNCTIONS_DIR . '/includes/settings/modal.php');

    $url_filter = '';
    if (isset($_GET['filter'])) {
        $url_filter = $_GET['filter'];
    }

    $url_from = '';
    if (isset($_GET['from'])) {
        $url_from = ($_GET['from'] ?? '');
    }

    // Read up here rather than beside its first old use: the heading below
    // asks whether this is the toolbar, and it is built long before that.
    $toolbar = '';
    if (isset($properties['toolbar'])) {
        $toolbar = $properties['toolbar'];
    }

    $output_body_class = '';
    if (isset($properties['extra classes'])) {
        $output_body_class = $properties['extra classes'];
    }

    $software_page_title = '';
    if (isset($properties['title'])) {
        $software_page_title = $properties['title'] . ' - ';
    }

    if (defined('ADVANCED_VISUAL_EFFECTS') && ADVANCED_VISUAL_EFFECTS == 1) {
        $output_body_class .= ' advanced-visuals';
    }

    $cancel = '';
    if (isset($properties['cancel'])) {
        $cancel = $properties['cancel'];
    }

    $output_cancel_button_class = '';
    $output_cancel_button_action = '';
    $output_cancel_button_title = lang('Cancel');
    if (is_array($cancel)) {
        if ($cancel['enable'] == 'true') {
            $output_cancel_button_class = 'show';
        }
        if (isset($cancel['onclick'])) {
            $output_cancel_button_action = 'OnClick="' . $cancel['onclick'] . '"';
            if (isset($cancel['title'])) {
                $output_cancel_button_title = $cancel['title'];
            }
        } elseif (isset($cancel['url']) && $cancel['url'] !== '') {
            // An explicit cancel URL replaces the fragile history.go(-1)
            // fallback. Through pgSettingsGo() because this is a button and not
            // a link: a cancel that points at the settings has to open the
            // dialog over this screen rather than walk to a page, and the
            // delegated handler that does that for links never sees a button.
            $output_cancel_button_action = 'OnClick="pgSettingsGo(\'' . htmlspecialchars($cancel['url'], ENT_QUOTES, 'UTF-8') . '\');"';
            if (isset($cancel['title'])) {
                $output_cancel_button_title = $cancel['title'];
            }
        } else {
            $output_cancel_button_action = 'OnClick="javascript:history.go(-1);"';
        }
    } else {
        if ($cancel == true) {
            $output_cancel_button_class = 'show';
            $output_cancel_button_action = 'OnClick="javascript:history.go(-1);"';
        }
    }

    // The back control rides at the head of the breadcrumb strip instead of the
    // navbar: both ways out of a screen then sit together, and the bar keeps one
    // left-hand anchor. Titles arrive pre-escaped from some callers, so decode
    // before escaping to keep entities out of the attribute.
    $output_cancel_button = '';
    if ($output_cancel_button_class === 'show') {
        $cancel_title_attr = h(html_entity_decode((string) $output_cancel_button_title, ENT_QUOTES, 'UTF-8'));
        $output_cancel_button = '<button type="button" ' . $output_cancel_button_action . ' class="pg-backbtn no-popover" data-bs-toggle="tooltip" title="' . $cancel_title_attr . '" aria-label="' . $cancel_title_attr . '"><span class="bi bi-arrow-left" aria-hidden="true"></span></button>';
    }





    $output_heading = '';
    if (isset($properties['heading'])) {
        // Page name over an optional one line description. The title keeps its
        // unescaped output because callers have always been allowed to pass markup
        // here; the description is escaped.
        //
        // Filter driven screens build the heading and the description as a pair -
        // view_pages alone has 34 of them - so both follow whatever the filter
        // selected. Entities are decoded before escaping because several of those
        // strings are stored already escaped ("edit &amp; duplicate"), and
        // escaping them a second time would print the entity itself.
        $heading_text = (string) $properties['heading'];
        $heading_description_text = trim((string) ($properties['heading_description'] ?? ''));
        $output_heading_description = '';
        if ($heading_description_text !== '') {
            $heading_description_text = html_entity_decode($heading_description_text, ENT_QUOTES, 'UTF-8');
            $output_heading_description = '
                <span class="pg-navhead-desc no-popover">' . h($heading_description_text) . '</span>';
        }
        // Both lines are clipped with an ellipsis, so the full text stays
        // reachable as a tooltip.
        //
        // Not in the toolbar. There the heading is a page name that carries its
        // own link and its own tooltip, and this popover only repeated the name
        // beside it - two balloons over one word.
        $output_heading_popover = ($toolbar == true) ? '' :
            ' data-bs-toggle="popover" data-bs-custom-class="contextmenu popover shadow-lg backdrop bs-popover-auto popover-info" data-bs-trigger="hover focus" data-bs-title="' . h(html_entity_decode(strip_tags($heading_text), ENT_QUOTES, 'UTF-8')) . '" data-bs-content="' . h($heading_description_text) . '"';

        $output_heading = '
            <div class="pg-navhead"' . $output_heading_popover . '>
                <h1 class="pg-navhead-title">' . $heading_text . '</h1>' . $output_heading_description . '
            </div>';
    }

    // Optional breadcrumb. Pass ['breadcrumb' => [['label'=>'Pages','url'=>'view_pages.php'], ['label'=>'Edit Page']]]
    // Last item is rendered as the active page (no link, aria-current="page"). Empty/missing => no output.
    $output_breadcrumb = '';
    $output_breadcrumb_trail = '';
    if (isset($properties['breadcrumb']) && is_array($properties['breadcrumb']) && count($properties['breadcrumb']) > 0) {
        $crumb_items_html = '';
        $crumbs = array_values($properties['breadcrumb']);
        $last_index = count($crumbs) - 1;
        foreach ($crumbs as $i => $crumb) {
            if (!is_array($crumb) || !isset($crumb['label'])) {
                continue;
            }
            $label = htmlspecialchars((string) $crumb['label'], ENT_QUOTES, 'UTF-8');
            if ($i === $last_index) {
                $crumb_items_html .= '<li class="breadcrumb-item active" aria-current="page">' . $label . '</li>';
            } elseif (isset($crumb['url']) && $crumb['url'] !== '') {
                $url = htmlspecialchars((string) $crumb['url'], ENT_QUOTES, 'UTF-8');
                $crumb_items_html .= '<li class="breadcrumb-item"><a href="' . $url . '">' . $label . '</a></li>';
            } else {
                $crumb_items_html .= '<li class="breadcrumb-item">' . $label . '</li>';
            }
        }
        if ($crumb_items_html !== '') {
            $output_breadcrumb_trail = '<nav aria-label="breadcrumb"><ol class="breadcrumb mb-0">' . $crumb_items_html . '</ol></nav>';
        }
    }

    // Chrome, not content: the strip runs the full width under the header while
    // <main> stays its own container. The <nav> wraps only the trail, so a screen
    // that has a back control but no crumbs still gets the strip without
    // announcing an empty breadcrumb landmark.
    if ($output_breadcrumb_trail !== '' || $output_cancel_button !== '') {
        $output_breadcrumb = '<div class="pg-breadcrumb d-print-none">' . $output_cancel_button . $output_breadcrumb_trail . '</div>';
    }



    $output_parent_target = '';
    $output_menu_title = '';
    // if the header is being outputted for the toolbar, then prepare to output parent target
    if ($toolbar == true) {
        $output_parent_target = ' target="_parent"';
    } else {
        //if not toolbar we output a shortcut
        $output_menu_title = ' (Ctrl+D | &#x2318;+D)';
    }

    $software_title = '';
    if (!defined('PRIVATE_LABEL') or !PRIVATE_LABEL) {
        $software_title = 'Pinegrap ' . get_short_version() . ' | ';
    } else {
        $software_title = ' | ';
    }



    $output_start_page_link = '';
    // if the user has a start page, then prepare to output start page link
    if ($user['home'] != 0) {
        // get start page name
        $start_page_name = get_page_name($user['home']);
        // if a start page name was found, then the page still exists, so prepare to output start page link
        if ($start_page_name != '') {
            $output_start_page_link = '<li><a class="my_start_page_link_url dropdown-item" href="' . OUTPUT_PATH . h($start_page_name) . '"' . $output_parent_target . '><i class="bi bi-bookmark-star"></i><span>' . lang('My Start Page') . '</span></a></li>';
        }
    }

    $output_home_page_link_url = '';
    // get home page(s)
    $query = "SELECT page_name FROM page WHERE page_home = 'yes'";
    $result = mysqli_query(db::$con, $query) or output_error(lang('Query failed.'));
    // if there is one home page, then prepare URL with page name
    if (mysqli_num_rows($result) == 1) {
        $row = mysqli_fetch_assoc($result);
        $home_page_name = $row['page_name'];
        $output_home_page_link_url = OUTPUT_PATH . h(encode_url_path($home_page_name));
        // else there is not a home page or there are multiple home pages, so prepare general home URL
        // so that they receive an error for no home page or receive a random home page if there are multiple
    } else {
        $output_home_page_link_url = OUTPUT_PATH;
    }

    // if the path is more than just "/", then add the path to the home title
    if (PATH != '/') {
        $output_home_title = h(HOSTNAME) . OUTPUT_PATH;
        // else the path is just "/", so set the home title to just the hostname
    } else {
        $output_home_title = h(HOSTNAME);
    }

    $output_software_metanames = '';
    $output_private_label_list = '';
    $output_logo = '';
    $output_feedback_link = '';
    $output_help_link = '';

    // Plays the tour of the frame again.  It belongs beside Help rather than
    // on any one screen, because what it explains is the furniture every
    // screen sits in.  A screen with a tour of its own offers that one from
    // its own menu, which keeps the two apart: this entry always means the
    // software, never whatever happens to be open.
    $output_tour_link = '
            <li id="tour_link">
                <a class="dropdown-item" href="#" title="' . lang('Interface tour') . '">
                    <i class="bi bi-compass"></i><span>' . lang('Interface tour') . '</span>
                </a>
            </li>';


    //output feedback form url for different languages
    if (lang(array('info' => '')) === 'en') {
        $output_feedback_url = 'https://forms.office.com/Pages/ResponsePage.aspx?id=DQSIkWdsW0yxEjajBLZtrQAAAAAAAAAAAAN__hpULwNUMDE3UktZR01OQkg4V0JBNTc4U1RXWFExOC4u';
    } else if (lang(array('info' => '')) === 'tr') {
        $output_feedback_url = 'https://forms.office.com/Pages/ResponsePage.aspx?id=DQSIkWdsW0yxEjajBLZtrQAAAAAAAAAAAAN__hpULwNUN05TSEo4UENETFJFMzhXQTVHTlhFVDVIOS4u';
    }


    if (!defined('PRIVATE_LABEL') or PRIVATE_LABEL == false) {
        $output_feedback_link = '<li><a id="feedback_link" class="dropdown-item " tabindex="-1" aria-disabled="false"  href="' . $output_feedback_url . '"  onclick="window.open(this.href, \'PinegrapFeedback\', \'resizable=yes,status=yes,location=no,toolbar=no,menubar=no,fullscreen=no,scrollbars=yes,dependent=no\'); return false;"><i class="bi bi-exclamation-triangle"></i><span>' . lang('Feedback') . '</span></a></li>';
        $output_software_distributor_link = '<li><a class="software_distributor_url dropdown-item no-popover" title="https://www.kodpen.com/iletisim" href="https://kodpen.com/iletisim" target="_blank"><i class="bi bi-headset"></i><span>' . lang('Developer Customer Support') . '</span></a></li>';
        $output_software_metanames = '<link rel="manifest" href="' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/manifest.php">';
        // Brand lockup: inline SVG (icon + wordmark) plus the version pill.
        // Inline rather than an <img>, so the gradient can read the theme's
        // --pg-logo-color-* variables and follow light/dark mode.
        // Sizing/cropping lives in backend.src.css (.pinegrap-logo).
        $output_logo = '<span class="pinegrap-logo-lockup position-relative">'
            . '<span class="pinegrap-logo">'
            . pg_logo_svg()
            . '</span>'
            . '<span class="pinegrap-version ms-1 border rounded-pill px-1 d-none d-md-inline-block">' . get_short_version() . '</span>'
            . '</span>';
        $output_help_link = '
            <li id="help_link">
                <a class="dropdown-item" href="#" title="' . lang('Help') . '">
                    <i class="bi bi-question-circle"></i><span>' . lang('Help') . '</span>
                </a>
            </li>';
    } else {
        $output_logo = '<li class="nav-item"><a href="' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/welcome.php"' . $output_parent_target . ' type="button" class="nav-link p-0 me-1 position-relative overflow-hidden" style="width:39.97px;;height:39.99px;border-radius:2px;"><img src="' . LOGO_URL . '" style="object-fit: scale-down;height: inherit;width: inherit;transform: rotate3d(0, 1, 0, 180deg);" border="0" alt="logo" title="" /></a></li>';
        $output_private_label_link_1 = '';
        // if the private label link 1 label is set, then prepare to output it
        if ((defined('FOOTER_LINK_1_LABEL') == true) && (FOOTER_LINK_1_LABEL != '')) {
            $output_link_start = '';
            $output_link_end = '';
            // if there is a private label link 1 url, then prepare to output it
            if ((defined('FOOTER_LINK_1_URL') == true) && (FOOTER_LINK_1_URL != '')) {
                $output_link_start = '<li><a class="dropdown-item" href="' . h(FOOTER_LINK_1_URL) . '" target="_blank"><i class="bi bi-link-45deg me-2"></i>';
                $output_link_end = '</a></li>';
            }
            $output_private_label_link_1 = $output_link_start . h(FOOTER_LINK_1_LABEL) . $output_link_end;
        }
        $output_private_label_link_2 = '';
        // if the private label link 2 label is set, then prepare to output it
        if ((defined('FOOTER_LINK_2_LABEL') == true) && (FOOTER_LINK_2_LABEL != '')) {
            $output_link_start = '';
            $output_link_end = '';
            // if there is a private label link 2 url, then prepare to output it
            if ((defined('FOOTER_LINK_2_URL') == true) && (FOOTER_LINK_2_URL != '')) {
                $output_link_start = '<li><a class="dropdown-item" href="' . h(FOOTER_LINK_2_URL) . '" target="_blank"><i class="bi bi-link-45deg me-2"></i>';
                $output_link_end = '</a></li>';
            }
            $output_private_label_link_2 = $output_link_start . h(FOOTER_LINK_2_LABEL) . $output_link_end;
        }
        $output_private_label_link_3 = '';
        // if the private label link 3 label is set, then prepare to output it
        if ((defined('FOOTER_LINK_3_LABEL') == true) && (FOOTER_LINK_3_LABEL != '')) {
            $output_link_start = '';
            $output_link_end = '';
            // if there is a private label link 3 url, then prepare to output it
            if ((defined('FOOTER_LINK_3_URL') == true) && (FOOTER_LINK_3_URL != '')) {
                $output_link_start = '<li><a class="dropdown-item" href="' . h(FOOTER_LINK_3_URL) . '" target="_blank"><i class="bi bi-link-45deg me-2"></i>';
                $output_link_end = '</a></li>';
            }
            $output_private_label_link_3 = $output_link_start . h(FOOTER_LINK_3_LABEL) . $output_link_end;
        }
        $output_private_label_link_4 = '';
        // if the private label link 4 label is set, then prepare to output it
        if ((defined('FOOTER_LINK_4_LABEL') == true) && (FOOTER_LINK_4_LABEL != '')) {
            $output_link_start = '';
            $output_link_end = '';
            // if there is a private label link 4 url, then prepare to output it
            if ((defined('FOOTER_LINK_4_URL') == true) && (FOOTER_LINK_4_URL != '')) {
                $output_link_start = '<li><a class="dropdown-item" href="' . h(FOOTER_LINK_4_URL) . '" target="_blank"><i class="bi bi-link-45deg me-2"></i>';
                $output_link_end = '</a></li>';
            }
            $output_private_label_link_4 = $output_link_start . h(FOOTER_LINK_4_LABEL) . $output_link_end;
        }
        $output_private_label_link_5 = '';
        // if the private label link 5 label is set, then prepare to output it
        if ((defined('FOOTER_LINK_5_LABEL') == true) && (FOOTER_LINK_5_LABEL != '')) {
            $output_link_start = '';
            $output_link_end = '';
            // if there is a private label link 5 url, then prepare to output it
            if ((defined('FOOTER_LINK_5_URL') == true) && (FOOTER_LINK_5_URL != '')) {
                $output_link_start = '<li><a class="dropdown-item" href="' . h(FOOTER_LINK_5_URL) . '" target="_blank"><i class="bi bi-link-45deg me-2"></i>';
                $output_link_end = '</a></li>';
            }
            $output_private_label_link_5 = $output_link_start . h(FOOTER_LINK_5_LABEL) . $output_link_end;
        }
        $output_private_label_list = '<li class="border-top"></li>' .
            $output_private_label_link_1 .
            $output_private_label_link_2 .
            $output_private_label_link_3 .
            $output_private_label_link_4 .
            $output_private_label_link_5;


    }

    $output_environment_information = '';

    if (($user['role'] < 3)) {
        //if this is development environment than show it in help menu.
        if (defined('ENVIRONMENT') and ENVIRONMENT == 'development') {
            $output_environment_information = '<span class="pg-um-metaitem design-color" title="' . lang('Environment') . '">' . lang('Development') . '</span>';
        }
    }
    // Only an administrator may open a user record, so only they get a link.
    $output_user_profile_url = '';
    if ($user['role'] < 1) {
        $output_user_profile_url = OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/edit_user.php?id=' . $user['id'];
    }
    $output_user_role_label = '';
    switch ((string) $user['role']) {
        case '0':
            $output_user_role_label = lang('administrator');
            break;
        case '1':
            $output_user_role_label = lang('designer');
            break;
        case '2':
            $output_user_role_label = lang('manager');
            break;
        case '3':
            $output_user_role_label = lang('user');
            break;
    }

    // Carries its own list wrapper so the whole group disappears when there is
    // nothing to show, rather than leaving an empty <ul> in the row.
    $output_home_button = '';
    if (
        isset($properties['replace_home_with'])
        && $properties['replace_home_with'] == 'close'
    ) {
        $output_home_button = '
        <ul class="navbar-nav pg-siteout">
            <li class="nav-item">
                <button class="nav-link close no-popover align-self-center position-relative" title="' . lang('Close') . '">
                    <span class="bi bi-x-lg"></span>
                </button>
            </li>
        </ul>';
    } elseif ($toolbar != true) {
        // The one control in this cluster that leaves the panel, so it sits at
        // the end of the row past the account menu instead of leading it. Above
        // lg it carries its label; below lg it falls back to the icon. The
        // toolbar already runs inside the site, so the link is dropped there.
        $output_home_button = '
        <ul class="navbar-nav pg-siteout">
            <li class="nav-item">
                <a id="go_home_button" class="btn btn-secondary pg-sitebtn d-none d-lg-inline-flex align-items-center gap-1" href="' . $output_home_page_link_url . '"' . $output_parent_target . ' data-bs-toggle="tooltip" title="' . lang('Homepage') . ' (' . $output_home_title . ')">
                    <span class="bi bi-box-arrow-up-right"></span><span>' . lang('Go to Site') . '</span>
                </a>
                <a class="nav-link align-self-center position-relative d-lg-none" href="' . $output_home_page_link_url . '"' . $output_parent_target . ' data-bs-toggle="tooltip" title="' . lang('Homepage') . ' (' . $output_home_title . ')">
                    <span class="bi bi-box-arrow-up-right"></span>
                </a>
            </li>
        </ul>';
    }

    $output_fav_icon = '';
    if (isset($properties['icon'])) {
        $output_fav_icon = $properties['icon'];
    }






    $menu_expanded_class = '';
    $menu_active_class = '';
    if (isset($_COOKIE['softwaremenustatus']) && $_COOKIE['softwaremenustatus'] === 'expanded') {
        $menu_expanded_class = ' expanded';
        $menu_active_class = ' active';
    }

    $output_user_image_url = 'assets/images/person1.png';
    // Single JOIN to fetch contact file_id, contact image, and the file name in one round-trip.
    // Previously this section issued 2-3 separate db() queries per page render.
    $user_image_row = db_item(
        "SELECT contacts.file_id AS contact_file_id,
                contacts.image    AS contact_image,
                files.name        AS file_name
            FROM user
            LEFT JOIN contacts ON contacts.id = user.user_contact
            LEFT JOIN files    ON files.id    = contacts.file_id
            WHERE user.user_id = '" . e(USER_ID) . "'"
    );
    if (!empty($user_image_row['contact_file_id']) && !empty($user_image_row['file_name'])) {
        $output_user_image_url = PATH . $user_image_row['file_name'];
    } elseif (!empty($user_image_row['contact_image'])) {
        $output_user_image_url = h($user_image_row['contact_image']);
    }





    // Below the lg breakpoint the sidebar is an off-canvas drawer, so its own collapse
    // control slides off screen with it. This navbar button is the only way back in.
    $output_menu_drawer_toggle = '';
    $output_software_sidebar = '';
    if (isset($properties['hide_menu']) && $properties['hide_menu'] == true) {
        $output_software_sidebar = '';
    } else {
        $output_menu_drawer_toggle = '
                    <li class="nav-item d-lg-none">
                        <a id="menu_drawer_toggle" type="button" class="ms-1 btn btn-link link-body-emphasis btn-circle no-popover" title="' . lang('Menu') . '" aria-label="' . lang('Menu') . '" aria-controls="menu" aria-expanded="false">
                            <span class="bi bi-list"></span>
                        </a>
                    </li>';

        $output_software_sidebar = '
        <div class="software-sidebar position-fixed d-print-none bg-software-navbar backdrop">
            <nav id="menu" class="menu z-1  ' . $menu_expanded_class . '" style="position:sticky;left:0;top:0;bottom:0;">
            
                <div class="menu-body">
                    <div class=" list-group list-group-flush" style="--bs-list-group-bg:transparent;">
                        ' . output_menu(array('toolbar' => $toolbar)) . '
                    </div>
                </div>
                <div class="menu-footer d-flex justify-content-start p-1 sticky-bottom">
                    <a id="menu_toggle" title="' . lang('Expand') . '/' . lang('Collapse') . '" class="btn btn-circle no-popover ' . $menu_active_class . '">
                        <i class="bi bi-caret-right-fill"></i>
                    </a>
                </div>
            </nav>
        </div>';

    }


    $output_toolbar_toggle_button = '';
    if ($toolbar != false) {

        // Sits in the normal flow. It used to be absolutely positioned at
        // right:7px with z-index 1020, which parked it on top of the home button
        // on every toolbar page. The stray quote in the old title attribute cut
        // the shortcut hint out of the tooltip.
        $output_toolbar_toggle_button = '
        <ul class="navbar-nav">
            <li class="nav-item">
                <button type="button"
                    class="nav-link align-self-center position-relative no-popover"
                    title="' . lang('Activate Fullscreen Mode') . ' (Ctrl+D | &#8984;+D)"
                    onclick="parent.document.getElementById(\'software_fullscreen_toggle\').click();">
                    <span class="bi bi-arrow-bar-up"></span>
                </button>
            </li>
        </ul>';
    }

    // Identity header for the account menu. Same destination and same
    // permission rule as the username row it replaces; it just carries the
    // face and the role so the menu opens with an answer to "who am I signed
    // in as" instead of a list item.
    $output_user_identity_inner = '
        <span class="pg-um-avatar"><img class="w-100 h-100 object-fit-cover" src="' . $output_user_image_url . '" alt="" /></span>
        <span class="pg-um-idtext">
            <span class="pg-um-name">' . htmlspecialchars($_SESSION['sessionusername'], ENT_QUOTES, 'UTF-8') . '</span>
            <span class="pg-um-role">' . h($output_user_role_label) . '</span>
        </span>';
    if ($output_user_profile_url !== '') {
        $output_user_identity = '<a class="pg-um-identity" href="' . h($output_user_profile_url) . '"' . $output_parent_target . '>'
            . $output_user_identity_inner
            . '<i class="bi bi-chevron-right pg-um-idgo"></i></a>';
    } else {
        $output_user_identity = '<span class="pg-um-identity pg-um-identity-static">' . $output_user_identity_inner . '</span>';
    }

    // State colour for the licence badge on the account avatar, or '' when no
    // subscription key is configured. The full status lives at the top of the
    // account menu.
    //
    // A gem in the upper corner rather than a plain dot in the lower one. The
    // dot was the shape, the size and the position every other product uses for
    // presence, so that is what it was read as -- and a licence that looks like
    // an online light is worse than no badge at all. text-bg-* pairs the ink
    // with the fill, which matters because warning is the one state a white
    // glyph cannot sit on.
    $output_license_badge = '';
    $license_badge_color = license_check(array('output' => 'badge'));
    if ($license_badge_color !== '') {
        $output_license_badge = '<span class="pg-lic-badge no-popover text-bg-' . h($license_badge_color) . '" title="' . lang('Premium features access status') . '"><i class="bi bi-gem" aria-hidden="true"></i></span>';
    }

    // Section rail, for the long add/edit screens that ask for one.
    //
    // The rail itself -- the slot, and the builder a screen uses when it names
    // its own sections -- is in includes/sections.php. Pulled in here rather
    // than by init.php because all 308 entry points load this file and only a
    // handful of them draw a rail: a screen without one never parses a line
    // of it, and the rail has one home to be read and changed in.
    //
    // Opt-in per screen rather than automatic: a rail helps a form with six
    // sections and is noise on one with two, and only the screen knows which
    // it is.
    $output_section_nav_slot = '';

    if (!empty($properties['section_nav']) && $toolbar != true && empty($properties['hide_menu'])) {
        include_once(PG_FUNCTIONS_DIR . '/includes/sections.php');
        $output_section_nav_slot = pg_section_nav_slot($properties['section_nav']);
    }

    // Expanded search, centred in the header, wide screens only.
    //
    // A button dressed as a field rather than a second input. The search is a
    // command palette -- results, keyboard navigation, paging and the Ctrl+K
    // binding all live inside #SearchBox -- and a real input out here would be
    // a second implementation of every one of them. This opens the same
    // dialog, so there is one search with two ways in, and the compact button
    // in the right cluster stands down while this is on screen.
    //
    // The toolbar gets it too. It did not use to: output_toolbar() filled the
    // middle of the header there with a row of buttons, so a centred field had
    // nowhere to go and came out narrow and pushed to the left. Those buttons
    // are in the page panel now and the middle is empty, which is the only
    // thing that was ever in the way.
    //
    // Not on the screens that hide the menu. Those are immersive tools, and the
    // page designer is the case that matters: its search is its own -- styles,
    // regions and design files, opened in the code editor rather than navigated
    // to -- it is wired in page_designer.js against the same dialog, and it
    // answers to a click and not to Ctrl+K. A field advertising the panel-wide
    // palette and a shortcut that does nothing there would be wrong twice, so
    // those screens keep the compact button they have always had.
    // Site Settings is a dialog that opens over whatever screen the operator
    // is on, so both halves of it are printed here rather than in the footer:
    // the visual editor and the page designer are full-height applications
    // that never call output_footer(), and a setting is wanted from those
    // screens as much as from any other.
    //
    // Not in the toolbar. That header is the thin strip inside the frame of
    // the site being edited, and a dialog the size of the viewport has
    // nowhere to open there.
    $output_settings_button = '';
    $output_settings_modal  = '';

    if ($toolbar != true && is_array($user)) {
        $output_settings_button = pg_settings_modal_button();
        $output_settings_modal  = pg_settings_modal_markup($user);
    }

    $output_header_search = '';
    if (empty($properties['hide_menu'])) {
        $output_header_search = '
                <div class="pg-searchbar pg-search-enable d-none">
                    <button type="button" class="pg-searchbar-field no-popover" data-bs-toggle="modal" data-bs-target="#SearchBox" aria-label="' . lang('Search') . '">
                        <span class="bi bi-search pg-searchbar-icon" aria-hidden="true"></span>
                        <span class="pg-searchbar-text">' . lang('Search...') . '</span>
                        <span class="pg-searchbar-kbd" aria-hidden="true">Ctrl+K</span>
                    </button>
                </div>';
    }

    // The tour engine is only sent where there is a tour to run.  The tour of
    // the frame stands down on toolbar screens; a screen that brought a tour of
    // its own still needs the engine, so both are asked before deciding.
    $output_tour_of_frame = pg_tour_shell_head($toolbar);
    $output_tour_head = '';

    if (($output_tour_of_frame !== '') || ((isset($properties['tour'])) && ($properties['tour'] != ''))) {
        $output_tour_head = pg_tour_assets() . $output_tour_of_frame;
    }

    // No tour of the frame here means no entry to play it with either: a menu
    // item that answers nothing is worse than one that is not there.
    if ($output_tour_of_frame === '') {
        $output_tour_link = '';
    }

    $output = '<!DOCTYPE html>
    <html lang="' . lang(array('info' => '')) . '">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width,minimum-scale=1,user-scalable=no">
        <title>' . $software_page_title . $software_title . h($_SERVER['HTTP_HOST']) . ' </title>
        <meta name="application-name" content="Pinegrap CMS">
        <meta name="color-scheme" content="light dark">
        ' . $output_software_metanames . '
        <link rel="icon" href="' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/assets/icons/ico/big/' . $output_fav_icon . '.ico" sizes="16x16 32x32 48x48 64x64 128x128 256x256" type="image/x-icon">
        <link rel="icon" type="image/png" sizes="75x75" href="' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/assets/icons/png/075/' . $output_fav_icon . '.png">
        <link rel="icon" type="image/png" sizes="100x100" href="' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/assets/icons/png/100/' . $output_fav_icon . '.png">
        <link rel="icon" type="image/png" sizes="150x150" href="' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/assets/icons/png/150/' . $output_fav_icon . '.png">
        <link rel="icon" type="image/png" sizes="200x200" href="' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/assets/icons/png/200/' . $output_fav_icon . '.png">
        <meta name="theme-color" content="#111111" media="(prefers-color-scheme: light)">
        <meta name="theme-color" content="#222222" media="(prefers-color-scheme: dark)">
        ' . get_generator_meta_tag() . '
        ' . output_control_panel_header_includes() . '
        ' . $output_tour_head . '
        ' . (isset($properties['head']) ? $properties['head'] : '') . '
    </head>
    <body class="' . $output_body_class . '">
    ' . pg_preloader_markup() . '
    <a class="pg-skip-link" href="#content">' . lang('Skip to main content') . '</a>
    <div class="software-container ' . $menu_expanded_class . '">
        <div class="software-navbar fixed-top d-print-none ">
            <nav id="header" class="z-1 position-sticky h-100 p-0 top-0 start-100 navbar navbar-expand bg-software-navbar backdrop" >
                <ul class="navbar-nav">
                    ' . $output_menu_drawer_toggle . '

                    <li class="nav-item">
                        <a href="' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/welcome.php"' . $output_parent_target . ' class="nav-link rounded-pill fw-bolder">
                            ' . $output_logo . '
                        </a>
                    </li>
                </ul>
                <ul class="navbar-nav d-none d-lg-flex"><li class="vh nav-item pe-1 me-1 border-end my-auto"></li></ul>
                ' . $output_heading . '
                <ul class="navbar-nav mx-auto"></ul>
                ' . $output_header_search . '
                
                ' . output_toolbar($toolbar) . '
                <div class="pg-rightcluster">
                <ul class="navbar-nav d-none" id="EnableSearch">
                    <li class="nav-item  no-popover"  title="' . lang('Search') . ' (Ctrl+K | &#8984;+K)">
                        <button type="button" class="btn" data-bs-toggle="modal" data-bs-target="#SearchBox" aria-label="' . lang('Search') . '"><span class="bi bi-search"></span></button>
                    </li>
                </ul>
                ' . $output_settings_button . '
                <ul class="navbar-nav">
                    <li class="nav-item dropdown no-arrow  no-popover" title="' . lang('Notifications') . ' (Ctrl+Q | ⌘+Q)">
                        <button type="button" id="notification_toggle" class="nav-link nav-link-sm no-popover dropdown-toggle" data-bs-toggle="dropdown" data-bs-target="#notifications" data-bs-auto-close="outside" aria-expanded="false">
                            <span class="has-notification-icon bi bi-bell"></span>
                        </button>
                        <div id="notifications" class="dropdown-menu shadow p-0 bg-body backdrop mt-nav-link-sm border-dropdown-menu position-fixed end-0 top-0" tabindex="-1" aria-labelledby="notification_toggle" aria-hidden="true">
                            <div class="menu-scroll-area position-relative overflow-auto h-100"></div>
                            <div class="notifications_empty text-center my-2 py-2">' . lang('No Notification') . '<br><span class="bi fs-5 bi-wind"></span></div>
                            <div id="push_row" class="d-none align-items-center justify-content-between gap-2 border-top px-3 py-2">
                                <span class="small text-body-secondary">' . lang('Notifications on this device') . '</span>
                                <button type="button" id="push_toggle" class="btn btn-sm btn-outline-secondary no-popover pg-push-toggle"><span class="pg-push-label">' . lang('Turn on') . '</span></button>
                            </div>
                            <div id="push_hint" class="d-none small text-body-secondary border-top px-3 py-2">' . lang('Add the panel to your home screen first, then turn notifications on from there.') . '</div>
                        </div>
                        <script type="application/json" id="pg-push-config">' . json_encode(array(
                            // The worker is registered at this address, so a
                            // changed file is a changed registration and the
                            // browser installs it on the next panel load. Left
                            // to its own revalidation a browser can keep an old
                            // worker for a day, which on a phone means an old
                            // worker until somebody notices.
                            'version' => (string) @filemtime(PG_FUNCTIONS_DIR . '/sw.js'),
                            'strings' => array(
                                'title' => lang('Notification'),
                                'body' => lang('There is something new in your panel.'),
                                'on' => lang('Turn off'),
                                'off' => lang('Turn on'),
                                'working' => lang('Please wait') . '...',
                                'state_on' => lang('On'),
                                'state_off' => lang('Off'),
                                'state_blocked' => lang('Blocked'),
                                'installed_first' => lang('Add the panel to your home screen first, then turn notifications on from there.'),
                                'dismissed' => lang('The browser did not show the permission prompt. Allow notifications from the site settings in your browser, then try again.'),
                                'not_ready' => lang('Notifications are not ready in this browser yet. Reload the page and try again.'),
                                'blocked' => lang('Notifications are turned off for this site. Turn them on in the browser, or in the notification settings of the installed application.')
                            )
                        ), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) . '</script>
                    </li>
                    <li class="nav-item dropdown no-arrow no-popover" title="' . lang('My Menu') . '">
                         <button type="button" class="btn btn-secondary pg-acctbtn dropdown-toggle" data-bs-auto-close="outside" data-bs-toggle="dropdown">
                             <span class="pg-avwrap">
                                 <span class="pg-avatar"><img class="w-100 h-100 object-fit-cover" src="' . $output_user_image_url . '" alt="' . htmlspecialchars($_SESSION['sessionusername'], ENT_QUOTES, 'UTF-8') . '" /></span>
                                 ' . $output_license_badge . '
                             </span>
                             <span class="pg-acctname d-none d-lg-inline">' . htmlspecialchars($_SESSION['sessionusername'], ENT_QUOTES, 'UTF-8') . '</span>
                             <span class="bi bi-chevron-down pg-chev"></span>
                         </button>
                         <ul id="user_menu" class="dropdown-menu shadow pt-0 dropdown-menu-end pb-0 bg-body backdrop mt-nav-link-sm border-dropdown-menu pg-usermenu" aria-labelledby="user_menu-toggle"> 
                            <li>' . $output_user_identity . '</li>
                            ' . license_check(array('output' => 'bar')) . '
                            <li class="pg-um-theme">
                                <span class="pg-um-section">' . lang('Appearance') . '</span>
                                <div class="pg-um-themeswitch" role="group" aria-label="' . lang('Appearance') . '">
                                    <button type="button" id="theme-light" class="pg-um-themebtn no-popover" data-bs-theme-value="light" title="' . lang('Light') . '" aria-label="' . lang('Light') . '"><i class="bi bi-sun-fill" aria-hidden="true"></i></button>
                                    <button type="button" id="theme-dark"  class="pg-um-themebtn no-popover" data-bs-theme-value="dark"  title="' . lang('Dark') . '" aria-label="' . lang('Dark') . '"><i class="bi bi-moon-stars-fill" aria-hidden="true"></i></button>
                                    <button type="button" id="theme-auto"  class="pg-um-themebtn no-popover" data-bs-theme-value="auto"  title="' . lang('Auto') . '" aria-label="' . lang('Auto') . '"><i class="bi bi-circle-half" aria-hidden="true"></i></button>
                                </div>
                            </li>
                            <li class="pg-um-group">
                                <a class="my_account_link_url dropdown-item" href="' . h(get_page_type_url('my account')) . '"' . $output_parent_target . '>
                                    <i class="bi bi-person"></i><span>' . lang('My Account') . '</span>
                                </a>
                            </li>
                            ' . $output_start_page_link . '
                            <li class="pg-um-section-row"><span class="pg-um-section">' . lang('Help & Support') . '</span></li>
                            ' . $output_tour_link . '
                            ' . $output_help_link . '
                            ' . $output_software_distributor_link . '
                            ' . $output_feedback_link . '
                            <li class="accordion accordion-flush border-top" id="menu_accordion">
                                <div class="accordion-item bg-transparent">
                                    <div class="accordion-header" id="keyboard_shortcuts_accordion">
                                        <button class="accordion-button collapsed py-2 bg-transparent" type="button" data-bs-toggle="collapse" data-bs-target="#keyboard_shortcuts" aria-expanded="false" aria-controls="keyboard_shortcuts">
                                            <i class="bi bi-keyboard" aria-hidden="true"></i><span>' . lang('Keyboard Shortcuts') . '</span>
                                        </button>
                                    </div>
                                    <div id="keyboard_shortcuts" class="accordion-collapse collapse" aria-labelledby="keyboard_shortcuts_accordion" data-bs-parent="#menu_accordion">
                                        <div class="accordion-body">
                                            <div class="row">
                                                <div class="col-auto">
                                                    <div class="row">
                                                        <span class="col form-text ">' . lang('While on the Panel') . '</span>
                                                    </div>
                                                    <div class="row">
                                                        <span class="col-auto form-text">
                                                            <span class="badge bg-body-secondary text-body">ctrl</span>
                                                            <span>+</span>
                                                            <span class="badge bg-body-secondary text-body">D</span>
                                                        </span>
                                                        <span class="col form-text">' . lang('Toggle Menu') . '</span>
                                                    </div>
                                                    <div class="row">
                                                        <span class="col-auto form-text">
                                                            <span class="badge bg-body-secondary text-body">ctrl</span>
                                                            <span>+</span>
                                                            <span class="badge bg-body-secondary text-body">Q</span>
                                                        </span>
                                                        <span class="col form-text">' . lang('Toggle Notifications') . '</span>
                                                    </div>
                                                    <div class="row">
                                                        <span class="col-auto form-text">
                                                            <span class="badge bg-body-secondary text-body">ctrl</span>
                                                            <span>+</span>
                                                            <span class="badge bg-body-secondary text-body">S</span>
                                                        </span>
                                                        <span class="col form-text">' . lang('Submit Form/Save') . '</span>
                                                    </div>
                                                    <div class="row">
                                                        <span class="col-auto form-text">
                                                            <span class="badge bg-body-secondary text-body">ctrl</span>
                                                            <span>+</span>
                                                            <span class="badge bg-body-secondary text-body">K</span>
                                                        </span>
                                                        <span class="col form-text">' . lang('Show/Hide Search Box') . '</span>
                                                    </div>
                                                </div>
                                                <div class="col-auto">
                                                    <div class="row">
                                                        <span class="col form-text">' . lang('While on the Pages') . '</span>
                                                    </div>
                                                    <div class="row">
                                                        <span class="col-auto form-text">
                                                            <span class="badge bg-body-secondary text-body">ctrl</span>
                                                            <span>+</span>
                                                            <span class="badge bg-body-secondary text-body">D</span>
                                                            </span>
                                                        <span class="col form-text">' . lang('Toggle Fullscreen Mode') . '</span>
                                                    </div>
                                                    <div class="row">
                                                        <span class="col-auto form-text">
                                                            <span class="badge bg-body-secondary text-body">ctrl</span>
                                                            <span>+</span>
                                                            <span class="badge bg-body-secondary text-body">E</span>
                                                            </span>
                                                        <span class="col form-text">' . lang('Toggle Edit Mode') . '</span>
                                                    </div>
                                                    <div class="row">
                                                        <span class="col-auto form-text">
                                                            <span class="badge bg-body-secondary text-body">ctrl</span>
                                                            <span>+</span>
                                                            <span class="badge bg-body-secondary text-body">G</span>
                                                            </span>
                                                        <span class="col form-text">' . lang('Toggle Page or Style Designer') . '</span>
                                                    </div>
                                                    <div class="row">
                                                        <span class="col-auto form-text">
                                                            <span class="badge bg-body-secondary text-body">ctrl</span>
                                                            <span>+</span>
                                                            <span class="badge bg-body-secondary text-body">I</span>
                                                            </span>
                                                        <span class="col form-text">' . lang('Toggle Page Panel') . '</span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </li>
                            ' . $output_private_label_list . '
                            <li class="pg-um-logout">
                                <a class="logout_link_url dropdown-item text-danger" href="' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/logout.php"' . $output_parent_target . '><i class="bi bi-box-arrow-right"></i><span>' . lang('Logout') . '</span></a>
                            </li>
                            <li class="pg-um-meta">
                                <span class="pg-um-metaitem" title="' . lang('Version') . '">' . VERSION . '</span>
                                <span class="pg-um-metaitem" title="' . lang('Edition') . '">' . EDITION . '</span>
                                ' . $output_environment_information . '
                            </li>
                        </ul>
                    </li>
                </ul>
                ' . $output_home_button . '
                ' . $output_toolbar_toggle_button . '
                </div>
            </nav>
        </div>
        ' . $output_software_sidebar . $output_settings_modal . '
        <div class="modal fade" id="SearchBox" tabindex="-1" aria-labelledby="SearchBoxLabel" aria-hidden="true">
          <div class="modal-dialog modal-lg modal-dialog-scrollable modal-dialog-centered  ">
            <div class="modal-content shadow">
              <div class="modal-header border-0 px-3 py-2 gap-2 ">
                <span class="bi bi-search fs-5 text-muted flex-shrink-0"></span>
                <form class="search_form disable_shortcut d-flex align-items-center flex-grow-1 position-relative" role="search">
                    <label for="query" class="visually-hidden">' . lang('Search') . '</label>
                    <input type="text" id="query" class="query form-control border-0 shadow-none no-popover fs-5 px-1" name="query" placeholder="' . lang('Search...') . '" value="" autocomplete="off">
                    <button type="button" class="clear btn btn-sm btn-link text-muted no-popover d-none position-absolute end-0 bi bi-x-lg" title="' . lang('Reset') . '"></button>
                </form>
                <span class="text-muted small flex-shrink-0 d-none d-md-inline opacity-50 user-select-none">Ctrl+K</span>
                <button type="button" class="btn-close ms-1 flex-shrink-0" data-bs-dismiss="modal" aria-label="Close"></button>
              </div>
              <div class="modal-body px-3 py-3" style="min-height:200px;max-height:65vh;overflow-y:auto;">
                <div class="search_results_empty_box text-center py-5 text-muted">
                  <span class="bi bi-search d-block fs-1 mb-2 opacity-25"></span>
                  <span class="d-block">' . lang('Type for results') . '</span>
                </div>
                <div id="search_results"></div>
              </div>
            </div>
          </div>
        </div>


        <div class="software-content' . ($output_section_nav_slot !== '' ? ' pg-awaiting-rail' : '') . '">
        ' . $output_section_nav_slot . '
        ' . $output_breadcrumb . '
        ';

    return $output;
}

function output_footer($properties = false)
{
    $output_software_labeling = '';
    if (PRIVATE_LABEL != true) {
        $output_software_labeling =
            '<p class="mx-auto small mb-0 text-muted">' . lang('Pinegrap Content Management System') . '</p>' .
            '<p class="version_and_copyright mx-auto small mb-0 text-muted">v' . VERSION . ' ' . EDITION .
            ' - <i class="bi bi-c-circle" aria-hidden="true"></i> ' . date('Y', time()) . '</p>';
    }

    // ── Live chat launcher ───────────────────────────────────────────────
    // The experimental <chat-bubble-snippet> (Cloudflare AutoRAG) was
    // replaced by the native chat launcher. Chat must never break a page:
    // when the module file is missing, the master switch is off or the
    // schema is not ready, the output stays empty.
    $output_chat_launcher = pg_chat_launcher_html();

    $output = '
        <div id="footer" class="footer p-3 d-flex flex-wrap justify-content-center justify-content-md-between text-muted d-print-none">
            ' . $output_software_labeling . '
        </div>
        </div>
        </div>
        ' . $output_chat_launcher . '
        </body>
        </html>';

    return $output;
}

function output_footer_secure($properties = false)
{
    $output = '
        </div>
        </div>
        </body>
        </html>';

    return $output;
}

function output_menu($properties = false)
{
    // get user informations.
    global $user;
    $menu_items = array();

    $toolbar = '';
    if (isset($properties['toolbar'])) {
        $toolbar = $properties['toolbar'];
    }
    $output_parent_target = '';
    if ($toolbar == true) {
        $output_parent_target = ' target=\'_parent\'';
    }

    //WELCOME
    $menu_items[0]['id'] = 0;
    $menu_items[0]['href'] = 'welcome.php';
    $menu_items[0]['icon'] = 'bi-speedometer2';
    $menu_items[0]['color_class'] = 'dashboard-color';
    $menu_items[0]['title'] = 'Dashboard';
    $menu_items[0]['context'] = true;
    $menu_items[0]['data-bs-content'] = '<a href=\'' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/welcome.php\'' . $output_parent_target . ' class=\'btn btn-link link-body-emphasis text-start text-decoration-none text-truncate bi bi-speedometer2 bi-me-2\'>' . lang('Dashboard') . '</a>';
    if ($user['role'] < 3) {
        // Cache the log count for 60 seconds in the session. Previously this issued
        // a full-table SELECT COUNT(*) FROM log on every backend page render — the
        // value is purely a UI badge so a slightly stale number is acceptable.
        if (
            !isset($_SESSION['software']['cache']['log_count'])
            || !isset($_SESSION['software']['cache']['log_count_ts'])
            || (time() - (int) ($_SESSION['software']['cache']['log_count_ts'] ?? '')) > 60
        ) {
            $_SESSION['software']['cache']['log_count'] = (int) db_value('SELECT COUNT(*) FROM log');
            $_SESSION['software']['cache']['log_count_ts'] = time();
        }
        $log_counts = ($_SESSION['software']['cache']['log_count'] ?? '');
        $menu_items[0]['data-bs-content'] .= '<a href=\'' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_log.php\'' . $output_parent_target . ' class=\'btn btn-link link-body-emphasis text-start text-decoration-none text-truncate bi bi-bug bi-me-2\'>' . lang('Site Log') . ' (' . $log_counts . ')</a>';
    }

    // Widget controls, on the dashboard only. They used to be a gear beside the
    // clock, which put a settings surface in the middle of the content; they
    // belong to the screen, so they ride the menu entry that opens it. The
    // context popover is built with sanitize:false, which is what lets a form
    // live in it, and reset_widgets() is declared by welcome.php - the only page
    // this branch renders on. The attribute is quoted with ", so everything
    // inside has to use '.
    if ($user['role'] < 2 && basename((string) ($_SERVER['SCRIPT_NAME'] ?? '')) === 'welcome.php') {

        // Card style and panel backdrop, above the refresh controls.
        //
        // Site-wide settings, not personal ones: both live on the same dashboard
        // row as the widget order, so they belong beside those controls rather
        // than in Settings, where whoever changed one could not see what it did.
        // The reach is this branch's own -- role 1 or better -- and api.php
        // enforces the same on the way in.
        //
        // Read defensively. The columns arrive with 2026.4.4 and the panel
        // files can be ahead of the database for the length of one upgrade;
        // where they are missing the selects are simply not drawn, and the
        // dashboard stays on the flat theme it has always had.
        $dashboard_appearance = db_item('SELECT * FROM dashboard');

        if ((is_array($dashboard_appearance)) && (isset($dashboard_appearance['widget_theme']))) {

            $appearance_controls = array(
                array(
                    'field'   => 'widget_theme',
                    'label'   => lang('Card Style'),
                    'value'   => (string) $dashboard_appearance['widget_theme'],
                    'options' => array(
                        'flat'   => lang('Flat Tint'),
                        'neon'   => lang('Neon Outline'),
                        'glass'  => lang('Glass'),
                        'aurora' => lang('Aurora'))),
                array(
                    'field'   => 'panel_backdrop',
                    'label'   => lang('Panel Backdrop'),
                    'value'   => (string) ($dashboard_appearance['panel_backdrop'] ?? 'auto'),
                    'options' => array(
                        'auto'  => lang('Match Card Style'),
                        'none'  => lang('None'),
                        'mesh'  => lang('Mesh'),
                        'dusk'  => lang('Dusk'),
                        'ember' => lang('Ember'))));

            $menu_items[0]['data-bs-content'] .=
                  '<hr class=\'dropdown-divider my-1\'>'
                . '<div class=\'disable_shortcut pg-ctx-form\'>';

            foreach ($appearance_controls as $appearance_control) {

                $appearance_options = '';

                foreach ($appearance_control['options'] as $appearance_key => $appearance_label) {

                    $appearance_options .=
                          '<option value=\'' . $appearance_key . '\''
                        . (($appearance_control['value'] == $appearance_key) ? ' selected=\'selected\'' : '')
                        . '>' . h($appearance_label) . '</option>';
                }

                // The select's id is the column name, and the handler reads it
                // back off this.id. That is not shorthand -- passing the field
                // as a string would need double quotes inside an attribute the
                // popover already delimits with them.
                $menu_items[0]['data-bs-content'] .=
                      '<label for=\'' . $appearance_control['field'] . '\' class=\'form-label small text-muted mb-1\'>'
                    . h($appearance_control['label']) . '</label>'
                    . '<select id=\'' . $appearance_control['field'] . '\''
                    . ' class=\'form-select form-select-sm mb-2\''
                    . ' onchange=\'set_dashboard_appearance(this.id, this.value)\'>'
                    . $appearance_options
                    . '</select>';
            }

            $menu_items[0]['data-bs-content'] .= '</div>';
        }

        $widget_refresh_value = 60;
        if (isset($_SESSION['software']['welcome']['widget']['refresh'])) {
            $widget_refresh_value = (int) $_SESSION['software']['welcome']['widget']['refresh'];
        }
        $menu_items[0]['data-bs-content'] .=
              '<hr class=\'dropdown-divider my-1\'>'
            . '<form action=\'' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/welcome.php\' method=\'get\' class=\'disable_shortcut pg-ctx-form\'>'
            . '<label for=\'widget_refresh\' class=\'form-label small text-muted mb-1\'>' . h(lang('Refresh Time')) . '</label>'
            . '<div class=\'input-group input-group-sm mb-2\'>'
            . '<input type=\'number\' name=\'refresh\' id=\'widget_refresh\' min=\'10\' value=\'' . $widget_refresh_value . '\' class=\'form-control\'>'
            . '<span class=\'input-group-text\'>' . h(lang('seconds')) . '</span>'
            . '</div>'
            . '<button type=\'submit\' class=\'btn btn-sm btn-primary w-100 mb-1\'>' . h(lang('Update')) . '</button>'
            . '<button type=\'button\' onclick=\'reset_widgets()\' data-loading-content=\' \' class=\'btn btn-sm btn-secondary w-100\'>' . h(lang('Reset Widgets')) . '</button>'
            . '</form>';
    }

    if (($user['role'] < 3) || (no_acl_check($user['id']) == true)) {
        //FOLDERS
        $menu_items[1]['id'] = 1;
        $menu_items[1]['href'] = 'view_folders.php';
        $menu_items[1]['icon'] = 'bi-folder';
        $menu_items[1]['color_class'] = 'folders-color';
        $menu_items[1]['title'] = 'File Manager';
        $menu_items[1]['context'] = true;
        $menu_items[1]['data-bs-content'] = '<a href=\'' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_folders.php\'' . $output_parent_target . ' class=\'btn btn-link link-body-emphasis text-start text-decoration-none text-truncate bi bi-folder bi-me-2\'>' . lang('File Manager') . '</a>';
        $menu_items[1]['data-bs-content'] .= '<a href=\'' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/add_folder.php\'' . $output_parent_target . ' class=\'btn btn-link link-body-emphasis text-start text-decoration-none text-truncate bi bi-folder-plus bi-me-2\'>' . lang(array('string' => 'Create {var:1}', 'vars' => lang('Folder'))) . '</a>';
        // PAGES
        $menu_items[2]['id'] = 2;
        $menu_items[2]['href'] = 'view_pages.php';
        $menu_items[2]['icon'] = 'bi-window';
        $menu_items[2]['color_class'] = 'pages-color';
        $menu_items[2]['title'] = 'Pages';
        $menu_items[2]['context'] = true;
        $menu_items[2]['data-bs-content'] = '<a href=\'' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_pages.php\'' . $output_parent_target . ' class=\'btn btn-link link-body-emphasis text-start text-decoration-none text-truncate bi bi-window bi-me-2\'>' . lang('Pages') . '</a>';
        $menu_items[2]['data-bs-content'] .= '<a href=\'' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/add_page.php\'' . $output_parent_target . ' class=\'btn btn-link link-body-emphasis text-start text-decoration-none text-truncate bi bi-plus-lg bi-me-2\'>' . lang(array('string' => 'Create {var:1}', 'vars' => lang('Page'))) . '</a>';
        // PAGES > Search Index
        if ((SEARCH_TYPE == 'advanced') && (USER_ROLE != 3)) {
            $menu_items[2]['data-bs-content'] .= '<hr class=\'divider my-2\' />';
            $menu_items[2]['data-bs-content'] .= '<a  onclick=\'open_search_index_window();\' class=\'btn btn-link link-body-emphasis text-start text-decoration-none text-truncate bi bi-input-cursor bi-me-2\'>' . lang('Update Search Index') . '</a>';
        }
        // PAGES > Short Links
        $menu_items[2]['data-bs-content'] .= '<hr class=\'divider my-2\' />';
        $menu_items[2]['data-bs-content'] .= '<a href=\'' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_short_links.php\'' . $output_parent_target . ' class=\'btn btn-link link-body-emphasis text-start text-decoration-none text-truncate bi bi-link-45deg bi-me-2\'>' . lang('Short Links') . '</a>';
        $menu_items[2]['data-bs-content'] .= '<a href=\'' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/add_short_link.php\'' . $output_parent_target . ' class=\'btn btn-link link-body-emphasis text-start text-decoration-none text-truncate bi bi-plus-lg bi-me-2\'>' . lang(array('string' => 'Create {var:1}', 'vars' => lang('Short Link'))) . '</a>';
        // PAGES > Auto Dialogs
        $menu_items[2]['data-bs-content'] .= '<hr class=\'divider my-2\' />';
        $menu_items[2]['data-bs-content'] .= '<a href=\'' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_auto_dialogs.php\'' . $output_parent_target . ' class=\'btn btn-link link-body-emphasis text-start text-decoration-none text-truncate bi bi-circle-square bi-me-2\'>' . lang('Auto Dialogs') . '</a>';
        $menu_items[2]['data-bs-content'] .= '<a href=\'' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/add_auto_dialog.php\'' . $output_parent_target . ' class=\'btn btn-link link-body-emphasis text-start text-decoration-none text-truncate bi bi-plus-lg bi-me-2\'>' . lang(array('string' => 'Create {var:1}', 'vars' => lang('Auto Dialog'))) . '</a>';
        // PAGES > Comments
        $menu_items[2]['data-bs-content'] .= '<hr class=\'divider my-2\' />';
        $menu_items[2]['data-bs-content'] .= '<a href=\'' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_comments.php\'' . $output_parent_target . ' class=\'btn btn-link link-body-emphasis text-start text-decoration-none text-truncate bi bi-chat-square-quote bi-me-2\'>' . lang('Comments') . '</a>';
        // FILES
        $menu_items[3]['id'] = 3;
        // The files live in the file manager now: the Files entry opens its
        // view across every file, and the two actions under it open the same
        // screen with the upload window, or a new file, already in hand.
        // view_files.php, add_file.php and create_file.php are no longer
        // linked from here.
        $menu_items[3]['href'] = 'view_folders.php?view=files';
        $menu_items[3]['icon'] = 'bi-images';
        $menu_items[3]['color_class'] = 'files-color';
        $menu_items[3]['title'] = 'Files';
        $menu_items[3]['context'] = true;
        $menu_items[3]['data-bs-content'] = '<a href=\'' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_folders.php?view=files\'' . $output_parent_target . ' class=\'btn btn-link link-body-emphasis text-start text-decoration-none text-truncate bi bi-file-earmark-image bi-me-2\'>' . lang('Files') . '</a>';
        $menu_items[3]['data-bs-content'] .= '<a href=\'' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_folders.php?view=images\'' . $output_parent_target . ' class=\'btn btn-link link-body-emphasis text-start text-decoration-none text-truncate bi bi-images bi-me-2\'>' . lang('Pictures') . '</a>';
        $menu_items[3]['data-bs-content'] .= '<a href=\'' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_folders.php?view=files&upload=1\'' . $output_parent_target . ' class=\'btn btn-link link-body-emphasis text-start text-decoration-none text-truncate bi bi-upload bi-me-2\'>' . lang('Upload Files or Folders') . '</a>';
        $menu_items[3]['data-bs-content'] .= '<a href=\'' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_folders.php?view=files&create=file\'' . $output_parent_target . ' class=\'btn btn-link link-body-emphasis text-start text-decoration-none text-truncate bi bi-file-earmark-plus bi-me-2\'>' . lang('Create File') . '</a>';

    }


    if ((CALENDARS === true) && (($user['role'] < 3) || ($user['manage_calendars'] == true))) {
        // CALENDARS
        $menu_items[4]['id'] = 4;
        $menu_items[4]['href'] = 'view_calendars.php';
        $menu_items[4]['icon'] = '';
        $menu_items[4]['color_class'] = 'calendars-color';
        $menu_items[4]['svg'] = '<svg style=\'font-size:10px\' width=\'1.3rem\' height=\'1.3rem\' class=\'me-2 calendars-color\' data-name=\'calendar icon\' fill=\'currentcolor\' xmlns=\'http://www.w3.org/2000/svg\' viewBox=\'0 0 16 16\'><path d=\'M3.6.18c.27,0,.49.22.49.49v.49h7.82v-.49c0-.27.22-.49.49-.49s.49.22.49.49v.49h.98c1.08,0,1.96.88,1.96,1.96v10.76c0,1.08-.88,1.96-1.96,1.96H2.13c-1.08,0-1.96-.88-1.96-1.96V3.11c0-1.08.88-1.96,1.96-1.96h.98v-.49c0-.27.22-.49.49-.49M1.16,4.09v9.78c0,.54.44.98.98.98h11.73c.54,0,.98-.44.98-.98V4.09H1.16Z\'/><text class=\'b\' transform=\'translate(2.55 12.73)\'><tspan x=\'0\' y=\'0\'>' . date('d') . '</tspan></text></svg>';
        $menu_items[4]['title'] = 'Calendars';
        $menu_items[4]['context'] = true;
        $menu_items[4]['data-bs-content'] = '<a href=\'' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_calendars.php\'' . $output_parent_target . ' class=\'btn btn-link link-body-emphasis text-start text-decoration-none text-truncate\'><svg style=\'font-size:10px\' width=\'16px\' height=\'16px\' class=\'me-2\' data-name=\'calendar icon\' fill=\'currentcolor\' xmlns=\'http://www.w3.org/2000/svg\' viewBox=\'0 0 16 16\'><path d=\'M3.6.18c.27,0,.49.22.49.49v.49h7.82v-.49c0-.27.22-.49.49-.49s.49.22.49.49v.49h.98c1.08,0,1.96.88,1.96,1.96v10.76c0,1.08-.88,1.96-1.96,1.96H2.13c-1.08,0-1.96-.88-1.96-1.96V3.11c0-1.08.88-1.96,1.96-1.96h.98v-.49c0-.27.22-.49.49-.49M1.16,4.09v9.78c0,.54.44.98.98.98h11.73c.54,0,.98-.44.98-.98V4.09H1.16Z\'/><text class=\'b\' transform=\'translate(2.55 12.73)\'><tspan x=\'0\' y=\'0\'>' . date('d') . '</tspan></text></svg>' . lang('Calendars') . '</a>';
        $menu_items[4]['data-bs-content'] .= '<a href=\'' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/calendars.php?date=&calendar_id=&view=monthly&status=\'' . $output_parent_target . ' class=\'btn btn-link link-body-emphasis text-start text-decoration-none text-truncate bi bi-calendar2-range bi-me-2\'>' . lang('All Calendars') . '</a>';
        if ($user['role'] != 3) {
            $menu_items[4]['data-bs-content'] .= '<a href=\'' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/add_calendar.php\'' . $output_parent_target . ' class=\'btn btn-link link-body-emphasis text-start text-decoration-none text-truncate bi bi-calendar-plus bi-me-2\'>' . lang(array('string' => 'Create {var:1}', 'vars' => lang('Calendar'))) . '</a>';
            // CALENDARS > Events Locations
            $menu_items[4]['data-bs-content'] .= '<hr class=\'divider my-2\' />';
            $menu_items[4]['data-bs-content'] .= '<a href=\'' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_calendar_event_locations.php\'' . $output_parent_target . ' class=\'btn btn-link link-body-emphasis text-start text-decoration-none text-truncate bi bi bi-pin-map bi-me-2\'>' . lang('Event Locations') . '</a>';
            $menu_items[4]['data-bs-content'] .= '<a href=\'' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/add_calendar_event_location.php\'' . $output_parent_target . ' class=\'btn btn-link link-body-emphasis text-start text-decoration-none text-truncate bi bi-plus-lg bi-me-2\'>' . lang(array('string' => 'Create {var:1}', 'vars' => lang('Calendar Event Location'))) . '</a>';
        }
    }

    if ((FORMS === true) && (($user['role'] < 3) || ($user['manage_forms'] == true))) {
        // FORMS
        $menu_items[5]['id'] = 5;
        $menu_items[5]['href'] = 'view_submitted_forms.php';
        $menu_items[5]['icon'] = 'bi-ui-checks';
        $menu_items[5]['color_class'] = 'forms-color';
        $menu_items[5]['title'] = 'Forms';
        $menu_items[5]['context'] = true;
        $menu_items[5]['data-bs-content'] = '<a href=\'' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_submitted_forms.php\'' . $output_parent_target . ' class=\'btn btn-link link-body-emphasis text-start text-decoration-none text-truncate bi bi-ui-checks bi-me-2\'>' . lang('Forms') . '</a>';
    }

    if (($user['role'] < 3) || ($user['manage_visitors'] == true)) {
        // VISITORS
        $menu_items[6]['id'] = 6;
        $menu_items[6]['href'] = 'view_visitor_reports.php';
        $menu_items[6]['icon'] = 'bi-clipboard2-data';
        $menu_items[6]['color_class'] = 'statistics-color';
        $menu_items[6]['title'] = 'Visitors';
        $menu_items[6]['context'] = true;
        $menu_items[6]['data-bs-content'] = '<a href=\'' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_visitor_reports.php\'' . $output_parent_target . ' class=\'btn btn-link link-body-emphasis text-start text-decoration-none text-truncate bi bi-clipboard2-data bi-me-2\'>' . lang('Visitors') . '</a>';
        $menu_items[6]['data-bs-content'] .= '<a href=\'' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_visitor_report.php\'' . $output_parent_target . ' class=\'btn btn-link link-body-emphasis text-start text-decoration-none text-truncate bi bi-plus-lg bi-me-2\'>' . lang(array('string' => 'Create {var:1}', 'vars' => lang('Visitor Report'))) . '</a>';
        if (ECOMMERCE === true and USER_MANAGE_ECOMMERCE_REPORTS) {
            // ECOMMERCE > Order Reports
            $menu_items[6]['data-bs-content'] .= '<hr class=\'divider my-2\' />';
            $menu_items[6]['data-bs-content'] .= '<a href=\'' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_order_reports.php\'' . $output_parent_target . ' class=\'btn btn-link link-body-emphasis text-start text-decoration-none text-truncate bi-graph-up-arrow bi-me-2\'>' . lang('All Order Reports') . '</a>';
            $menu_items[6]['data-bs-content'] .= '<a href=\'' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_order_report.php\'' . $output_parent_target . ' class=\'btn btn-link link-body-emphasis text-start text-decoration-none text-truncate bi bi-plus-lg bi-me-2\'>' . lang(array('string' => 'Create {var:1}', 'vars' => lang('Order Report'))) . '</a>';
        }
        if (ECOMMERCE === true and USER_MANAGE_ECOMMERCE_REPORTS and ECOMMERCE_SHIPPING) {
            // ECOMMERCE > Shipping Reports
            $menu_items[6]['data-bs-content'] .= '<hr class=\'divider my-2\' />';
            $menu_items[6]['data-bs-content'] .= '<a href=\'' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_shipping_report.php\'' . $output_parent_target . ' class=\'btn btn-link link-body-emphasis text-start text-decoration-none text-truncate bi-graph-up bi-me-2\'>' . lang('Shipping Report') . '</a>';
        }

        // VISITORS > Analystics
        // if an external web stats link is supplied in the settings, then prepare link to web stats
        if ((defined('STATS_URL') == true) && (STATS_URL != '')) {
            $menu_items[6]['data-bs-content'] .= '<hr class=\'divider my-2\' />';
            $menu_items[6]['data-bs-content'] .= '<a href=\'' . STATS_URL . '\' target=\'_blank\' class=\'btn btn-link link-body-emphasis text-start text-decoration-none text-truncate bi bi-box-arrow-up-right bi-me-2\'>' . lang('Website Analytics') . '</a>';
        }
    }

    if ((($user['role'] < 3) || ($user['manage_contacts'] == true))) {
        // CONTACTS
        $menu_items[7]['id'] = 7;
        $menu_items[7]['href'] = 'view_contacts.php';
        $menu_items[7]['icon'] = 'bi-person-vcard';
        $menu_items[7]['color_class'] = 'contacts-color';
        $menu_items[7]['title'] = 'Contacts';
        $menu_items[7]['context'] = true;
        $menu_items[7]['data-bs-content'] = '<a href=\'' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_contacts.php\'' . $output_parent_target . ' class=\'btn btn-link link-body-emphasis text-start text-decoration-none text-truncate bi bi-person-vcard bi-me-2\'>' . lang('Contacts') . '</a>';
        $menu_items[7]['data-bs-content'] .= '<a href=\'' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/add_contact.php\'' . $output_parent_target . ' class=\'btn btn-link link-body-emphasis text-start text-decoration-none text-truncate bi bi-plus-lg bi-me-2\'>' . lang(array('string' => 'Create {var:1}', 'vars' => lang('Contact'))) . '</a>';
        // CONTACTS > Contact Groups
        $menu_items[7]['data-bs-content'] .= '<hr class=\'divider my-2\' />';
        $menu_items[7]['data-bs-content'] .= '<a href=\'' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_contact_groups.php\'' . $output_parent_target . ' class=\'btn btn-link link-body-emphasis text-start text-decoration-none text-truncate bi bi-person-rolodex bi-me-2\'>' . lang('All Contact Groups') . '</a>';
        $menu_items[7]['data-bs-content'] .= '<a href=\'' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/add_contact_group.php\'' . $output_parent_target . ' class=\'btn btn-link link-body-emphasis text-start text-decoration-none text-truncate bi bi-plus-lg bi-me-2\'>' . lang(array('string' => 'Create {var:1}', 'vars' => lang('Contact Group'))) . '</a>';
    }

    if ($user['role'] < 3) {
        // USERS
        $menu_items[8]['id'] = 8;
        $menu_items[8]['href'] = 'view_users.php';
        $menu_items[8]['icon'] = 'bi-people';
        $menu_items[8]['color_class'] = 'users-color';
        $menu_items[8]['title'] = 'Users';
        $menu_items[8]['context'] = true;
        $menu_items[8]['data-bs-content'] = '<a href=\'' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_users.php\'' . $output_parent_target . ' class=\'btn btn-link link-body-emphasis text-start text-decoration-none text-truncate bi bi-people bi-me-2\'>' . lang('Users') . '</a>';
        $menu_items[8]['data-bs-content'] .= '<a href=\'' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/add_user.php\'' . $output_parent_target . ' class=\'btn btn-link link-body-emphasis text-start text-decoration-none text-truncate bi bi-person-plus bi-me-2\'>' . lang(array('string' => 'Create {var:1}', 'vars' => lang('User'))) . '</a>';
        $menu_items[8]['data-bs-content'] .= '<hr class=\'divider my-2\' />';
        $menu_items[8]['data-bs-content'] .= '<a href=\'' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_sessions.php\'' . $output_parent_target . ' class=\'btn btn-link link-body-emphasis text-start text-decoration-none text-truncate bi bi-shield-lock bi-me-2\'>' . lang('Sessions') . '</a>';
    }

    if ((($user['role'] < 3) || ($user['manage_emails'] == true))) {
        // CAMPAIGNS
        $menu_items[9]['id'] = 9;
        $menu_items[9]['href'] = 'view_email_campaigns.php';
        $menu_items[9]['icon'] = 'bi-megaphone';
        $menu_items[9]['color_class'] = 'campaigns-color';
        $menu_items[9]['title'] = 'My Campaigns';
        $menu_items[9]['context'] = true;
        $menu_items[9]['data-bs-content'] = '<a href=\'' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_email_campaigns.php\'' . $output_parent_target . ' class=\'btn btn-link link-body-emphasis text-start text-decoration-none text-truncate bi bi-megaphone bi-me-2\'>' . lang('My Campaigns') . '</a>';
        $menu_items[9]['data-bs-content'] .= '<a href=\'' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/add_email_campaign.php\'' . $output_parent_target . ' class=\'btn btn-link link-body-emphasis text-start text-decoration-none text-truncate bi bi-plus-lg bi-me-2\'>' . lang(array('string' => 'Create {var:1}', 'vars' => lang('Campaign'))) . '</a>';
        $menu_items[9]['data-bs-content'] .= '<hr class=\'divider my-2\' />';
        $menu_items[9]['data-bs-content'] .= '<a href=\'' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_email_campaign_history.php\'' . $output_parent_target . ' class=\'btn btn-link link-body-emphasis text-start text-decoration-none text-truncate bi bi-envelope-dash bi-me-2\'>' . lang('My Campaign History') . '</a>';
        $menu_items[9]['data-bs-content'] .= '<hr class=\'divider my-2\' />';
        $menu_items[9]['data-bs-content'] .= '<a href=\'' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_email_campaign_profiles.php\'' . $output_parent_target . ' class=\'btn btn-link link-body-emphasis text-start text-decoration-none text-truncate bi bi-envelope-check bi-me-2\'>' . lang('My Campaign Profiles') . '</a>';
        $menu_items[9]['data-bs-content'] .= '<a href=\'' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/add_email_campaign_profile.php\'' . $output_parent_target . ' class=\'btn btn-link link-body-emphasis text-start text-decoration-none text-truncate bi bi-envelope-plus bi-me-2\'>' . lang(array('string' => 'Create {var:1}', 'vars' => lang('Campaign Profile'))) . '</a>';
    }

    // ECOMMERCE
    if (((ECOMMERCE === true) and (($user['role'] < 3) or USER_MANAGE_ECOMMERCE or USER_MANAGE_ECOMMERCE_REPORTS))) {

        if (USER_MANAGE_ECOMMERCE) {
            // ECOMMERCE > Orders
            $menu_items[10]['id'] = 10;
            $menu_items[10]['href'] = 'view_orders.php';
            $menu_items[10]['icon'] = 'bi-shop';
            $menu_items[10]['color_class'] = 'ecommerce-color';
            $menu_items[10]['title'] = 'All Orders';
            $menu_items[10]['context'] = true;
            $menu_items[10]['data-bs-content'] = '<a href=\'' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_orders.php\'' . $output_parent_target . ' class=\'btn btn-link link-body-emphasis text-start text-decoration-none text-truncate bi bi-cart4 bi-me-2\'>' . lang('All Orders') . '</a>';
            $menu_items[10]['data-bs-content'] .= '<hr class=\'divider my-2\' />';
            $menu_items[10]['data-bs-content'] .= '<a href=\'' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_orders.php?status=incomplete\'' . $output_parent_target . ' class=\'btn btn-link link-body-emphasis text-start text-decoration-none text-truncate bi bi-basket bi-me-2\'>' . lang('Shopping Carts') . '</a>';
            $menu_items[10]['data-bs-content'] .= '<a href=\'' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_orders.php?type=online\'' . $output_parent_target . ' class=\'btn btn-link link-body-emphasis text-start text-decoration-none text-truncate bi bi-cart-fill bi-me-2\'>' . lang('All Online Orders') . '</a>';
            $menu_items[10]['data-bs-content'] .= '<hr class=\'divider my-2\' />';
            $menu_items[10]['data-bs-content'] .= '<a href=\'' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_orders.php?status=any&type=local\'' . $output_parent_target . ' class=\'btn btn-link link-body-emphasis text-start text-decoration-none text-truncate bi bi-receipt bi-me-2\'>' . lang('Local Orders') . ' </a>';
            $menu_items[10]['data-bs-content'] .= '<a href=\'' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/add_order.php\'' . $output_parent_target . ' class=\'btn btn-link link-body-emphasis text-start text-decoration-none text-truncate bi bi-cart-plus bi-me-2\'>' . lang(['string' => 'Add {var:1}', 'vars' => [lang('Order')]]) . '</a>';

            if ((ECOMMERCE === true) and USER_MANAGE_ECOMMERCE_REPORTS) {
                // ECOMMERCE > Order Reports
                $menu_items[10]['data-bs-content'] .= '<hr class=\'divider my-2\' />';
                $menu_items[10]['data-bs-content'] .= '<a href=\'' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_order_reports.php\'' . $output_parent_target . ' class=\'btn btn-link link-body-emphasis text-start text-decoration-none text-truncate bi-graph-up-arrow bi-me-2\'>' . lang('All Order Reports') . '</a>';
                $menu_items[10]['data-bs-content'] .= '<a href=\'' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_order_report.php\'' . $output_parent_target . ' class=\'btn btn-link link-body-emphasis text-start text-decoration-none text-truncate bi bi-plus-lg bi-me-2\'>' . lang(array('string' => 'Create {var:1}', 'vars' => lang('Order Report'))) . '</a>';
            }
            if (USER_MANAGE_ECOMMERCE_REPORTS and ECOMMERCE_SHIPPING) {
                // ECOMMERCE > Shipping Reports
                $menu_items[10]['data-bs-content'] .= '<hr class=\'divider my-2\' />';
                $menu_items[10]['data-bs-content'] .= '<a href=\'' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_shipping_report.php\'' . $output_parent_target . ' class=\'btn btn-link link-body-emphasis text-start text-decoration-none text-truncate bi-graph-up bi-me-2\'>' . lang('Shipping Report') . '</a>';
            }
            if (defined('ENABLE_PARASUT') && ENABLE_PARASUT && defined('PARASUT_COMPANY_ID') && PARASUT_COMPANY_ID !== '') {
                // ECOMMERCE > Parasut E-Invoice Inbox
                $menu_items[10]['data-bs-content'] .= '<hr class=\'divider my-2\' />';
                $menu_items[10]['data-bs-content'] .= '<a href=\'' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_parasut_inbox.php\'' . $output_parent_target . ' class=\'btn btn-link link-body-emphasis text-start text-decoration-none text-truncate bi bi-inbox bi-me-2\'>' . lang('Parasut E-Invoice Inbox') . '</a>';
            }


            // ECOMMERCE > Products
            $menu_items[11]['id'] = 11;
            $menu_items[11]['href'] = 'view_products.php';
            $menu_items[11]['icon'] = 'bi-box2-heart';
            $menu_items[11]['color_class'] = 'ecommerce-color';
            $menu_items[11]['title'] = 'All Products';
            $menu_items[11]['context'] = true;
            $menu_items[11]['data-bs-content'] = '<a href=\'' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_products.php\'' . $output_parent_target . ' class=\'btn btn-link link-body-emphasis text-start text-decoration-none text-truncate bi bi-box2-heart bi-me-2\'>' . lang('All Products') . '</a>';
            $menu_items[11]['data-bs-content'] .= '<a href=\'' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/add_product.php\'' . $output_parent_target . ' class=\'btn btn-link link-body-emphasis text-start text-decoration-none text-truncate bi bi-plus-lg bi-me-2\'>' . lang(array('string' => 'Create {var:1}', 'vars' => lang('Product'))) . '</a>';
            $menu_items[11]['data-bs-content'] .= '<hr class=\'divider my-2\' />';
            $menu_items[11]['data-bs-content'] .= '<a href=\'' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_products.php?filter=all_products\'' . $output_parent_target . ' class=\'btn btn-link link-body-emphasis text-start text-decoration-none text-truncate bi bi-box2-heart bi-me-2\'>' . lang('All Products') . '</a>';
            $menu_items[11]['data-bs-content'] .= '<a href=\'' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_products.php?filter=out_of_stock_products\'' . $output_parent_target . ' class=\'btn btn-link link-body-emphasis text-start text-decoration-none text-truncate bi bi-clipboard2-x bi-me-2\'>' . lang('All Out of Stock Products') . '</a>';

            // ECOMMERCE > Barcode
            $menu_items[11]['data-bs-content'] .= '<hr class=\'divider my-2\' />';
            $menu_items[11]['data-bs-content'] .= '<a href=\'' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/barcode_increase_inventory.php\'' . $output_parent_target . ' class=\'btn btn-link link-body-emphasis text-start text-decoration-none text-truncate bi-node-plus bi-me-2\'>' . lang('Product Inventory Quantity Increase') . '</a>';
            $menu_items[11]['data-bs-content'] .= '<a href=\'' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/barcode_decrease_inventory.php\'' . $output_parent_target . ' class=\'btn btn-link link-body-emphasis text-start text-decoration-none text-truncate bi-node-minus bi-me-2\'>' . lang('Product Inventory Quantity Decrease') . '</a>';
            // ECOMMERCE > Product 
            $menu_items[11]['data-bs-content'] .= '<hr class=\'divider my-2\' />';
            $menu_items[11]['data-bs-content'] .= '<a href=\'' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_product_attributes.php\'' . $output_parent_target . ' class=\'btn btn-link link-body-emphasis text-start text-decoration-none text-truncate bi bi-card-checklist bi-me-2\'>' . lang('All Product Attributes') . '</a>';
            $menu_items[11]['data-bs-content'] .= '<a href=\'' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/add_product_attribute.php\'' . $output_parent_target . ' class=\'btn btn-link link-body-emphasis text-start text-decoration-none text-truncate bi bi-plus-lg bi-me-2\'>' . lang(array('string' => 'Create {var:1}', 'vars' => lang('Product Attribute'))) . '</a>';

            // ECOMMERCE > Products > Featured and New
            $menu_items[11]['data-bs-content'] .= '<hr class=\'divider my-2\' />';
            $menu_items[11]['data-bs-content'] .= '<a href=\'' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/edit_featured_and_new_items.php\'' . $output_parent_target . ' class=\'btn btn-link link-body-emphasis text-start text-decoration-none text-truncate bi bi-funnel bi-me-2\'>' . lang('Edit Featured & New Items') . '</a>';

            // ECOMMERCE > Product Groups
            $menu_items[12]['id'] = 12;
            $menu_items[12]['href'] = 'view_product_groups.php';
            $menu_items[12]['icon'] = 'bi-boxes';
            $menu_items[12]['color_class'] = 'ecommerce-color neon';
            // Named for what the screen is rather than for one of the things
            // in it. It stopped being a list of product groups when it became
            // the catalog explorer: the tree, the products inside each group,
            // the variant sets, the bin and the import / export all live here.
            //
            // "Manager" to match the File Manager above it, because the two are
            // the same screen with different tables behind them -- the tree,
            // the grid, the right button, F2, Ctrl+Z and the bin all behave the
            // same in both. The name is the cheapest way to say so.
            $menu_items[12]['title'] = 'Catalog Manager';
            $menu_items[12]['context'] = true;
            $menu_items[12]['data-bs-content'] = '<a href=\'' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_product_groups.php\'' . $output_parent_target . ' class=\'btn btn-link link-body-emphasis text-start text-decoration-none text-truncate bi bi-boxes bi-me-2\'>' . lang('Catalog Manager') . '</a>';
            $menu_items[12]['data-bs-content'] .= '<a href=\'' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/add_product_group.php\'' . $output_parent_target . ' class=\'btn btn-link link-body-emphasis text-start text-decoration-none text-truncate bi bi-plus-lg bi-me-2\'>' . lang(array('string' => 'Create {var:1}', 'vars' => lang('Product Group'))) . '</a>';
            // ECOMMERCE > > Featured and New
            $menu_items[12]['data-bs-content'] .= '<hr class=\'divider my-2\' />';
            $menu_items[12]['data-bs-content'] .= '<a href=\'' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/edit_featured_and_new_items.php\'' . $output_parent_target . ' class=\'btn btn-link link-body-emphasis text-start text-decoration-none text-truncate bi bi-funnel bi-me-2\'>' . lang('Edit Featured & New Items') . '</a>';

            // ECOMMERCE > Offers
            $menu_items[14]['id'] = 14;
            $menu_items[14]['href'] = 'view_offers.php';
            $menu_items[14]['icon'] = 'bi-percent';
            $menu_items[14]['color_class'] = 'ecommerce-color';
            $menu_items[14]['title'] = 'All Offers';
            $menu_items[14]['context'] = true;
            $menu_items[14]['data-bs-content'] = '<a href=\'' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_offers.php\'' . $output_parent_target . ' class=\'btn btn-link link-body-emphasis text-start text-decoration-none text-truncate bi bi-percent bi-me-2\'>' . lang('All Offers') . '</a>';
            $menu_items[14]['data-bs-content'] .= '<a href=\'' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/add_offer.php\'' . $output_parent_target . ' class=\'btn btn-link link-body-emphasis text-start text-decoration-none text-truncate bi bi-plus-lg bi-me-2\'>' . lang(array('string' => 'Create {var:1}', 'vars' => lang('Offer'))) . '</a>';
            // ECOMMERCE > Gift Cards
            $menu_items[14]['data-bs-content'] .= '<hr class=\'divider my-2\' />';
            $menu_items[14]['data-bs-content'] .= '<a href=\'' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_gift_cards.php\'' . $output_parent_target . ' class=\'btn btn-link link-body-emphasis text-start text-decoration-none text-truncate bi-wallet bi-me-2\'>' . lang('All Gift Cards') . '</a>';
            $menu_items[14]['data-bs-content'] .= '<a href=\'' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/add_gift_card.php\'' . $output_parent_target . ' class=\'btn btn-link link-body-emphasis text-start text-decoration-none text-truncate bi bi-plus-lg bi-me-2\'>' . lang(array('string' => 'Create {var:1}', 'vars' => lang('Gift Card'))) . '</a>';
            // ECOMMERCE > Gift Cards
            $menu_items[14]['data-bs-content'] .= '<hr class=\'divider my-2\' />';
            $menu_items[14]['data-bs-content'] .= '<a href=\'' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_key_codes.php\'' . $output_parent_target . ' class=\'btn btn-link link-body-emphasis text-start text-decoration-none text-truncate bi-key bi-me-2\'>' . lang('All Key Codes') . '</a>';
            $menu_items[14]['data-bs-content'] .= '<a href=\'' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/add_key_code.php\'' . $output_parent_target . ' class=\'btn btn-link link-body-emphasis text-start text-decoration-none text-truncate bi bi-plus-lg bi-me-2\'>' . lang(array('string' => 'Create {var:1}', 'vars' => lang('Key Code'))) . '</a>';
        }

        if (USER_MANAGE_ECOMMERCE and AFFILIATE_PROGRAM) {
            // ECOMMERCE > Commissions
            $menu_items[14]['data-bs-content'] .= '<hr class=\'divider my-2\' />';
            $menu_items[14]['data-bs-content'] .= '<a href=\'' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_commissions.php\'' . $output_parent_target . ' class=\'btn btn-link link-body-emphasis text-start text-decoration-none text-truncate bi bi-cash-coin bi-me-2\'>' . lang('All Commissions') . '</a>';
            // ECOMMERCE > Commissions Profiles
            $menu_items[14]['data-bs-content'] .= '<a href=\'' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_recurring_commission_profiles.php\'' . $output_parent_target . ' class=\'btn btn-link link-body-emphasis text-start text-decoration-none text-truncate bi-cash-stack bi-me-2\'>' . lang('All Commission Profiles') . '</a>';
        }


        // ECOMMERCE > Shipping
        $menu_items[15]['id'] = 15;
        $menu_items[15]['href'] = 'view_shipping_methods.php';
        $menu_items[15]['icon'] = 'bi-truck';
        $menu_items[15]['color_class'] = 'ecommerce-color';
        $menu_items[15]['title'] = 'All Shipping Methods';
        $menu_items[15]['context'] = true;

        if (USER_MANAGE_ECOMMERCE and ECOMMERCE_SHIPPING) {
            $menu_items[15]['data-bs-content'] = '<a href=\'' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_shipping_methods.php\'' . $output_parent_target . ' class=\'btn btn-link link-body-emphasis text-start text-decoration-none text-truncate bi bi-truck bi-me-2\'>' . lang('All Shipping Methods') . '</a>';
            $menu_items[15]['data-bs-content'] .= '<a href=\'' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/add_shipping_method.php\'' . $output_parent_target . ' class=\'btn btn-link link-body-emphasis text-start text-decoration-none text-truncate bi bi-plus-lg bi-me-2\'>' . lang(array('string' => 'Create {var:1}', 'vars' => lang('Shipping Method'))) . '</a>';
            // ECOMMERCE > Zones
            $menu_items[15]['data-bs-content'] .= '<hr class=\'divider my-2\' />';
            $menu_items[15]['data-bs-content'] .= '<a href=\'' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_zones.php\'' . $output_parent_target . ' class=\'btn btn-link link-body-emphasis text-start text-decoration-none text-truncate bi-map bi-me-2\'>' . lang('All Shipping Zones') . '</a>';
            $menu_items[15]['data-bs-content'] .= '<a href=\'' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/add_zone.php\'' . $output_parent_target . ' class=\'btn btn-link link-body-emphasis text-start text-decoration-none text-truncate bi bi-plus-lg bi-me-2\'>' . lang(array('string' => 'Create {var:1}', 'vars' => lang('Shipping Zone'))) . '</a>';
            // ECOMMERCE > Arrival Dates
            $menu_items[15]['data-bs-content'] .= '<hr class=\'divider my-2\' />';
            $menu_items[15]['data-bs-content'] .= '<a href=\'' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_arrival_dates.php\'' . $output_parent_target . ' class=\'btn btn-link link-body-emphasis text-start text-decoration-none text-truncate bi-calendar2-day bi-me-2\'>' . lang('All Shipping Arrival Dates') . '</a>';
            $menu_items[15]['data-bs-content'] .= '<a href=\'' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/add_arrival_date.php\'' . $output_parent_target . ' class=\'btn btn-link link-body-emphasis text-start text-decoration-none text-truncate bi bi-plus-lg bi-me-2\'>' . lang(array('string' => 'Create {var:1}', 'vars' => lang('Arrival Date'))) . '</a>';
            // ECOMMERCE > Shipping Addresses
            $menu_items[15]['data-bs-content'] .= '<hr class=\'divider my-2\' />';
            $menu_items[15]['data-bs-content'] .= '<a href=\'' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_verified_shipping_addresses.php\'' . $output_parent_target . ' class=\'btn btn-link link-body-emphasis text-start text-decoration-none text-truncate bi-mailbox bi-me-2\'>' . lang('All Verified Shipping Addresses') . '</a>';
            $menu_items[15]['data-bs-content'] .= '<a href=\'' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/add_verified_shipping_address.php\'' . $output_parent_target . ' class=\'btn btn-link link-body-emphasis text-start text-decoration-none text-truncate bi bi-plus-lg bi-me-2\'>' . lang(array('string' => 'Create {var:1}', 'vars' => lang('Verified Shipping Address'))) . '</a>';
            // ECOMMERCE > Containers
            $menu_items[15]['data-bs-content'] .= '<hr class=\'divider my-2\' />';
            $menu_items[15]['data-bs-content'] .= '<a href=\'' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_containers.php\'' . $output_parent_target . ' class=\'btn btn-link link-body-emphasis text-start text-decoration-none text-truncate bi-box-seam bi-me-2\'>' . lang('All Containers') . '</a>';
            $menu_items[15]['data-bs-content'] .= '<a href=\'' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/add_container.php\'' . $output_parent_target . ' class=\'btn btn-link link-body-emphasis text-start text-decoration-none text-truncate bi bi-plus-lg bi-me-2\'>' . lang(array('string' => 'Create {var:1}', 'vars' => lang('Container'))) . '</a>';
        }

        if (USER_MANAGE_ECOMMERCE) {
            // ECOMMERCE > Countries
            $menu_items[15]['data-bs-content'] .= '<hr class=\'divider my-2\' />';
            $menu_items[15]['data-bs-content'] .= '<a href=\'' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_countries.php\'' . $output_parent_target . ' class=\'btn btn-link link-body-emphasis text-start text-decoration-none text-truncate bi-globe-americas bi-me-2\'>' . lang('All Countries') . '</a>';
            $menu_items[15]['data-bs-content'] .= '<a href=\'' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/add_country.php\'' . $output_parent_target . ' class=\'btn btn-link link-body-emphasis text-start text-decoration-none text-truncate bi bi-plus-lg bi-me-2\'>' . lang(array('string' => 'Create {var:1}', 'vars' => lang('Country'))) . '</a>';
            // ECOMMERCE > States
            $menu_items[15]['data-bs-content'] .= '<hr class=\'divider my-2\' />';
            $menu_items[15]['data-bs-content'] .= '<a href=\'' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_states.php\'' . $output_parent_target . ' class=\'btn btn-link link-body-emphasis text-start text-decoration-none text-truncate bi-globe-europe-africa bi-me-2\'>' . lang('All States') . '</a>';
            $menu_items[15]['data-bs-content'] .= '<a href=\'' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/add_state.php\'' . $output_parent_target . ' class=\'btn btn-link link-body-emphasis text-start text-decoration-none text-truncate bi bi-plus-lg bi-me-2\'>' . lang(array('string' => 'Create {var:1}', 'vars' => lang('State'))) . '</a>';
            // ECOMMERCE > Tax
            $menu_items[15]['data-bs-content'] .= '<hr class=\'divider my-2\' />';
            $menu_items[15]['data-bs-content'] .= '<a href=\'' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_tax_zones.php\'' . $output_parent_target . ' class=\'btn btn-link link-body-emphasis text-start text-decoration-none text-truncate bi-file-text bi-me-2\'>' . lang('All Tax Zones') . '</a>';
            $menu_items[15]['data-bs-content'] .= '<a href=\'' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/add_tax_zone.php\'' . $output_parent_target . ' class=\'btn btn-link link-body-emphasis text-start text-decoration-none text-truncate bi bi-plus-lg bi-me-2\'>' . lang(array('string' => 'Create {var:1}', 'vars' => lang('Tax Zone'))) . '</a>';
            $menu_items[15]['data-bs-content'] .= '<hr class=\'divider my-2\' />';
        }

        if (USER_MANAGE_ECOMMERCE_REPORTS and ECOMMERCE_SHIPPING) {
            // ECOMMERCE > Shipping Reports

            $menu_items[15]['data-bs-content'] .= '<a href=\'' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_shipping_report.php\'' . $output_parent_target . ' class=\'btn btn-link link-body-emphasis text-start text-decoration-none text-truncate bi-graph-up bi-me-2\'>' . lang('Shipping Report') . '</a>';
            if (USER_MANAGE_ECOMMERCE) {
                // ECOMMERCE > Ship Date Adjustments
                $menu_items[15]['data-bs-content'] .= '<hr class=\'divider my-2\' />';
                $menu_items[15]['data-bs-content'] .= '<a href=\'' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_ship_date_adjustments.php\'' . $output_parent_target . ' class=\'btn btn-link link-body-emphasis text-start text-decoration-none text-truncate bi-calendar4-range bi-me-2\'>' . lang('All Ship Date Adjustments') . '</a>';
                $menu_items[15]['data-bs-content'] .= '<a href=\'' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/add_ship_date_adjustment.php\'' . $output_parent_target . ' class=\'btn btn-link link-body-emphasis text-start text-decoration-none text-truncate bi bi-plus-lg bi-me-2\'>' . lang(array('string' => 'Create {var:1}', 'vars' => lang('Ship Date Adjustment'))) . '</a>';
            }
        }
    }

    if ((ADS === true) && (($user['role'] < 3) || (count(get_items_user_can_edit('ad_regions', $user['id'])) > 0))) {
        // ADS
        $menu_items[16]['id'] = 16;
        $menu_items[16]['href'] = 'view_ads.php';
        $menu_items[16]['icon'] = 'bi-badge-ad';
        $menu_items[16]['color_class'] = 'ads-color';
        $menu_items[16]['title'] = 'Ads';
        $menu_items[16]['context'] = true;
        $menu_items[16]['data-bs-content'] = '<a href=\'' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_ads.php\'' . $output_parent_target . ' class=\'btn btn-link link-body-emphasis text-start text-decoration-none text-truncate bi bi-badge-ad bi-me-2\'>' . lang('Ads') . '</a>';
        $menu_items[16]['data-bs-content'] .= '<a href=\'' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/add_ad.php\'' . $output_parent_target . ' class=\'btn btn-link link-body-emphasis text-start text-decoration-none text-truncate bi bi-plus-lg bi-me-2\'>' . lang(array('string' => 'Create {var:1}', 'vars' => lang('Ad'))) . '</a>';
    }

    if (($user['role'] < 2)) {
        // DESIGN
        $menu_items[17]['id'] = 17;
        $menu_items[17]['href'] = 'view_styles.php';
        $menu_items[17]['icon'] = 'bi-palette';
        $menu_items[17]['color_class'] = 'design-color';
        $menu_items[17]['title'] = 'Page Styles';
        $menu_items[17]['context'] = true;
        $menu_items[17]['data-bs-content'] = '<a href=\'' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_styles.php\'' . $output_parent_target . ' class=\'btn btn-link link-body-emphasis text-start text-decoration-none text-truncate bi bi-palette bi-me-2\'>' . lang('Page Styles') . '</a>';
        $menu_items[17]['data-bs-content'] .= '<a href=\'' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/add_custom_style.php\'' . $output_parent_target . ' class=\'btn btn-link link-body-emphasis text-start text-decoration-none text-truncate bi bi-plus-lg bi-me-2\'>' . lang(array('string' => 'Create {var:1}', 'vars' => lang('Page Style'))) . '</a>';
        // DESIGN > Import Design
        $menu_items[17]['data-bs-content'] .= '<a href=\'' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/import_design.php\'' . $output_parent_target . ' class=\'btn btn-link link-body-emphasis text-start text-decoration-none text-truncate bi bi-cloud-arrow-up bi-me-2\'>' . lang('Import My Site') . '</a>';

        // DESIGN > Themes
        $menu_items[17]['data-bs-content'] .= '<hr class=\'divider my-2\' />';
        $menu_items[17]['data-bs-content'] .= '<a href=\'' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_themes.php\'' . $output_parent_target . ' class=\'btn btn-link link-body-emphasis text-start text-decoration-none text-truncate bi-palette2 bi-me-2\'>' . lang('All Themes') . '</a>';
        $menu_items[17]['data-bs-content'] .= '<a href=\'' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/add_theme_file.php\'' . $output_parent_target . ' class=\'btn btn-link link-body-emphasis text-start text-decoration-none text-truncate bi bi-plus-lg bi-me-2\'>' . lang(array('string' => 'Create {var:1}', 'vars' => lang('Theme'))) . '</a>';

        // DESIGN > Design File
        $menu_items[17]['data-bs-content'] .= '<hr class=\'divider my-2\' />';
        $menu_items[17]['data-bs-content'] .= '<a href=\'' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_design_files.php\'' . $output_parent_target . ' class=\'btn btn-link link-body-emphasis text-start text-decoration-none text-truncate bi-file-earmark-image bi-me-2\'>' . lang('All Design Files') . '</a>';
        $menu_items[17]['data-bs-content'] .= '<a href=\'' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/add_design_file.php\'' . $output_parent_target . ' class=\'btn btn-link link-body-emphasis text-start text-decoration-none text-truncate bi bi-plus-lg bi-me-2\'>' . lang('Upload Design Files') . '</a>';
        // DESIGN > Import Zip
        $menu_items[17]['data-bs-content'] .= '<a href=\'' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/import_zip.php\'' . $output_parent_target . ' class=\'btn btn-link link-body-emphasis text-start text-decoration-none text-truncate bi bi-file-earmark-arrow-up bi-me-2\'>' . lang('Import ZIP File') . '</a>';
        // if the user is an administrator and migration enabled
        if (($user['role'] < 1) && (defined('MIG') == true) && (MIG == true)) {
            // DESIGN > Migration
            $menu_items[17]['data-bs-content'] .= '<hr class=\'divider my-2\' />';
            $menu_items[17]['data-bs-content'] .= '<a href=\'' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/migration.php\'' . $output_parent_target . ' class=\'btn btn-link link-body-emphasis text-start text-decoration-none text-truncate bi bi-sign-merge-right bi-me-2\'>' . lang('Migration') . '</a>';
        }
        // DESIGN > Find And Replace
        $menu_items[17]['data-bs-content'] .= '<hr class=\'divider my-2\' />';
        $menu_items[17]['data-bs-content'] .= '<a href=\'' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/find_and_replace.php\'' . $output_parent_target . ' class=\'btn btn-link link-body-emphasis text-start text-decoration-none text-truncate bi bi-binoculars bi-me-2\'>' . lang('Find And Replace') . '</a>';
        // DESIGN > Mass Edit
        $menu_items[17]['data-bs-content'] .= '<hr class=\'divider my-2\' />';
        $menu_items[17]['data-bs-content'] .= '<a href=\'' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/mass_edit.php\'' . $output_parent_target . ' class=\'btn btn-link link-body-emphasis text-start text-decoration-none text-truncate bi bi-sliders2 bi-me-2\'>' . lang('Mass Edit') . '</a>';

        // VISUAL PAGE EDITOR — designs made with the visual editor. Its own
        // entry: the Page Styles list is the legacy HTML-template screen.
        $menu_items[21]['id'] = 21;
        $menu_items[21]['href'] = 'view_system_styles.php';
        $menu_items[21]['icon'] = 'bi-magic';
        $menu_items[21]['color_class'] = 'design-color';
        $menu_items[21]['title'] = 'Visual Page Editor';
        $menu_items[21]['context'] = true;
        $menu_items[21]['data-bs-content'] = '<a href=\'' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_system_styles.php\'' . $output_parent_target . ' class=\'btn btn-link link-body-emphasis text-start text-decoration-none text-truncate bi bi-magic bi-me-2\'>' . lang('Visual Page Editor') . '</a>';
        $menu_items[21]['data-bs-content'] .= '<a href=\'' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/add_system_style.php\'' . $output_parent_target . ' class=\'btn btn-link link-body-emphasis text-start text-decoration-none text-truncate bi bi-plus-lg bi-me-2\'>' . lang('Start Blank') . '</a>';
        $menu_items[21]['data-bs-content'] .= '<a href=\'' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/add_system_style.php?start=import\'' . $output_parent_target . ' class=\'btn btn-link link-body-emphasis text-start text-decoration-none text-truncate bi bi-file-earmark-zip bi-me-2\'>' . lang('Import HTML / ZIP') . '</a>';

        // DESIGN > Menu
        $menu_items[18]['id'] = 18;
        $menu_items[18]['href'] = 'view_menus.php';
        $menu_items[18]['icon'] = 'bi-menu-button';
        $menu_items[18]['color_class'] = 'design-color';
        $menu_items[18]['title'] = 'All Menus';
        $menu_items[18]['context'] = true;
        $menu_items[18]['data-bs-content'] = '<a href=\'' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_menus.php\'' . $output_parent_target . ' class=\'btn btn-link link-body-emphasis text-start text-decoration-none text-truncate bi bi-menu-button bi-me-2\'>' . lang('All Menus') . '</a>';
        $menu_items[18]['data-bs-content'] .= '<a href=\'' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/add_menu.php\'' . $output_parent_target . ' class=\'btn btn-link link-body-emphasis text-start text-decoration-none text-truncate bi bi-plus-lg bi-me-2\'>' . lang(array('string' => 'Create {var:1}', 'vars' => lang('Menu'))) . '</a>';

        // DESIGN > Regions
        $menu_items[19]['id'] = 19;
        $menu_items[19]['href'] = 'view_regions.php?filter=all_common_regions';
        $menu_items[19]['icon'] = 'bi-window-stack';
        $menu_items[19]['color_class'] = 'design-color';
        $menu_items[19]['title'] = 'All Regions';
        $menu_items[19]['context'] = true;
        // DESIGN > Common Regions
        $menu_items[19]['data-bs-content'] = '<a href=\'' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_regions.php?filter=all_common_regions\'' . $output_parent_target . ' class=\'btn btn-link link-body-emphasis text-start text-decoration-none text-truncate bi bi-layout-text-window-reverse bi-me-2\'>' . lang('All Common Regions') . '</a>';
        $menu_items[19]['data-bs-content'] .= '<a href=\'' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/add_common_region.php\'' . $output_parent_target . ' class=\'btn btn-link link-body-emphasis text-start text-decoration-none text-truncate bi bi-plus-lg bi-me-2\'>' . lang(array('string' => 'Create {var:1}', 'vars' => lang('Common Region'))) . '</a>';
        // DESIGN > Designer Regions
        $menu_items[19]['data-bs-content'] .= '<hr class=\'divider my-2\' />';
        $menu_items[19]['data-bs-content'] .= '<a href=\'' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_regions.php?filter=all_designer_regions\'' . $output_parent_target . ' class=\'btn btn-link link-body-emphasis text-start text-decoration-none text-truncate bi bi-window-dock bi-me-2\'>' . lang('All Designer Regions') . '</a>';
        $menu_items[19]['data-bs-content'] .= '<a href=\'' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/add_designer_region.php\'' . $output_parent_target . ' class=\'btn btn-link link-body-emphasis text-start text-decoration-none text-truncate bi bi-plus-lg bi-me-2\'>' . lang(array('string' => 'Create {var:1}', 'vars' => lang('Designer Region'))) . '</a>';
        // DESIGN > Login Regions
        $menu_items[19]['data-bs-content'] .= '<hr class=\'divider my-2\' />';
        $menu_items[19]['data-bs-content'] .= '<a href=\'' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_regions.php?filter=all_login_regions\'' . $output_parent_target . ' class=\'btn btn-link link-body-emphasis text-start text-decoration-none text-truncate bi bi-person-up bi-me-2\'>' . lang('All Login Regions') . '</a>';
        $menu_items[19]['data-bs-content'] .= '<a href=\'' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/add_login_region.php\'' . $output_parent_target . ' class=\'btn btn-link link-body-emphasis text-start text-decoration-none text-truncate bi bi-plus-lg bi-me-2\'>' . lang(array('string' => 'Create {var:1}', 'vars' => lang('Login Region'))) . '</a>';
        if (ADS === true) {
            // DESIGN > Ad Regions
            $menu_items[19]['data-bs-content'] .= '<hr class=\'divider my-2\' />';
            $menu_items[19]['data-bs-content'] .= '<a href=\'' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_regions.php?filter=all_ad_regions\'' . $output_parent_target . ' class=\'btn btn-link link-body-emphasis text-start text-decoration-none text-truncate bi bi-window-desktop bi-me-2\'>' . lang('All Ad Regions') . '</a>';
            $menu_items[19]['data-bs-content'] .= '<a href=\'' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/add_ad_region.php\'' . $output_parent_target . ' class=\'btn btn-link link-body-emphasis text-start text-decoration-none text-truncate bi bi-plus-lg bi-me-2\'>' . lang(array('string' => 'Create {var:1}', 'vars' => lang('Ad Region'))) . '</a>';
        }
        // if the user is an administrator and dynamic regions are enabled, then prepare to output dynamic regions link
        if (($user['role'] < 1) && ((defined('DYNAMIC_REGIONS') == true) && (DYNAMIC_REGIONS == true))) {
            // DESIGN > Dynamic Regions
            $menu_items[19]['data-bs-content'] .= '<hr class=\'divider my-2\' />';
            $menu_items[19]['data-bs-content'] .= '<a href=\'' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_regions.php?filter=all_dynamic_regions\'' . $output_parent_target . ' class=\'btn btn-link link-body-emphasis text-start text-decoration-none text-truncate bi bi-filetype-php bi-me-2\'>' . lang('All Dynamic Regions') . '</a>';
            $menu_items[19]['data-bs-content'] .= '<a href=\'' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/add_dynamic_region.php\'' . $output_parent_target . ' class=\'btn btn-link link-body-emphasis text-start text-decoration-none text-truncate bi bi-plus-lg bi-me-2\'>' . lang(array('string' => 'Create {var:1}', 'vars' => lang('Dynamic Region'))) . '</a>';
        }

    }

    // ERP. Slot 22: the numbers are what an operator pins in
    // user.selected_appmenu_items_array, so they are never reused and never
    // shuffled. Slot 20 is the empty one left behind by Site Settings.
    if (defined('ERP_ENABLED') && ERP_ENABLED && (($user['role'] < 3) || USER_MANAGE_ERP)) {

        $menu_items[22]['id'] = 22;
        $menu_items[22]['href'] = 'erp_dashboard.php';
        $menu_items[22]['icon'] = 'bi-receipt';
        $menu_items[22]['color_class'] = 'erp-color';
        $menu_items[22]['title'] = 'ERP';
        $menu_items[22]['context'] = true;
        $menu_items[22]['data-bs-content'] = '<a href=\'' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/erp_dashboard.php\'' . $output_parent_target . ' class=\'btn btn-link link-body-emphasis text-start text-decoration-none text-truncate bi bi-speedometer2 bi-me-2\'>' . lang('ERP Dashboard') . '</a>';
        $menu_items[22]['data-bs-content'] .= '<hr class=\'divider my-2\' />';
        $menu_items[22]['data-bs-content'] .= '<a href=\'' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/erp_accounts.php\'' . $output_parent_target . ' class=\'btn btn-link link-body-emphasis text-start text-decoration-none text-truncate bi bi-people bi-me-2\'>' . lang('Accounts') . '</a>';
        $menu_items[22]['data-bs-content'] .= '<a href=\'' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/erp_invoices.php\'' . $output_parent_target . ' class=\'btn btn-link link-body-emphasis text-start text-decoration-none text-truncate bi bi-receipt bi-me-2\'>' . lang('Invoices') . '</a>';
        $menu_items[22]['data-bs-content'] .= '<a href=\'' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/erp_waybills.php\'' . $output_parent_target . ' class=\'btn btn-link link-body-emphasis text-start text-decoration-none text-truncate bi bi-truck bi-me-2\'>' . lang('Delivery Notes') . '</a>';

        if (USER_MANAGE_ERP_CASH) {
            $menu_items[22]['data-bs-content'] .= '<hr class=\'divider my-2\' />';
            $menu_items[22]['data-bs-content'] .= '<a href=\'' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/erp_cash.php\'' . $output_parent_target . ' class=\'btn btn-link link-body-emphasis text-start text-decoration-none text-truncate bi bi-cash-stack bi-me-2\'>' . lang('Cash and Bank') . '</a>';
        }

        if (USER_MANAGE_ERP_SETTINGS) {
            $menu_items[22]['data-bs-content'] .= '<hr class=\'divider my-2\' />';
            $menu_items[22]['data-bs-content'] .= '<a href=\'' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/erp_settings.php\'' . $output_parent_target . ' class=\'btn btn-link link-body-emphasis text-start text-decoration-none text-truncate bi bi-sliders bi-me-2\'>' . lang('ERP Settings') . '</a>';
        }

    }

    // Site Settings has no menu item. It is a modal now, opened from the gear
    // beside the notifications in the header, so that a setting can be changed
    // without leaving the screen the operator is working on. Slot 20 is left
    // empty rather than renumbered: the items around it keep the positions the
    // operators know. What this panel used to carry -- the categories and the
    // neighbouring screens -- is the sidebar of the modal, drawn from the same
    // includes/settings/registry.php.
    // get the name of the PHP script that was just accessed in order to determine which tab should be highlighted
    $url_path_parts = explode('/', $_SERVER['SCRIPT_NAME']);
    $file_name = $url_path_parts[count($url_path_parts) - 1];
    $active_menu = '';
    switch ($file_name) {
        case 'welcome.php':
            $active_menu = 0;
            break;
        case 'view_folders.php':
            // The same screen answers for the Files entry when it is opened
            // on one of its views across every file.
            $active_menu = (isset($_GET['view']) && in_array($_GET['view'], array('files', 'images'), true)) ? 3 : 1;
            break;
        case 'add_folder.php':
        case 'edit_folder.php':
        case 'duplicate_folder.php':
            $active_menu = 1;
            break;
        case 'view_pages.php':
        case 'add_page.php':
        case 'edit_page.php':
        case 'edit_form_list_view.php':
        case 'edit_form_item_view.php':
        case 'update_search_index.php':
        case 'toolbar.php':
        case 'generate_layout.php':
        case 'view_short_links.php':
        case 'add_short_link.php':
        case 'edit_short_link.php':
        case 'view_auto_dialogs.php':
        case 'add_auto_dialog.php':
        case 'edit_auto_dialog.php':
        case 'view_comments.php':
        case 'edit_comment.php':
            $active_menu = 2;
            break;
        case 'view_files.php':
        case 'add_file.php':
        case 'edit_file.php':
        case 'image_editor_edit.php':
        case 'editor_select_image.php':
            $active_menu = 3;
            break;
        case 'calendars.php':
        case 'view_calendars.php':
        case 'add_calendar.php':
        case 'edit_calendar.php':
        case 'add_calendar_event.php':
        case 'edit_calendar_event.php':
        case 'view_calendar_event_locations.php':
        case 'add_calendar_event_location.php':
        case 'edit_calendar_event_location.php':
            $active_menu = 4;
            break;
        case 'view_forms.php':
        case 'view_submitted_forms.php':
        case 'add_submitted_form.php':
        case 'edit_submitted_form.php':
        case 'import_submitted_forms.php':
            $active_menu = 5;
            break;
        case 'view_visitor_reports.php':
        case 'view_visitor_report.php':
        case 'view_visitor.php':
            $active_menu = 6;
            break;
        case 'view_contacts.php':
        case 'add_contact.php':
        case 'edit_contact.php':
        case 'import_contacts.php':
        case 'view_contact_groups.php':
        case 'add_contact_group.php':
        case 'edit_contact_group.php':
        case 'organize_contacts.php':
            $active_menu = 7;
            break;
        case 'view_users.php':
        case 'add_user.php':
        case 'edit_user.php':
        case 'import_users.php':
            $active_menu = 8;
            break;
        case 'view_email_campaigns.php':
        case 'add_email_campaign.php':
        case 'edit_email_campaign.php':
        case 'view_email_campaign_history.php':
        case 'view_email_campaign_profiles.php':
        case 'add_email_campaign_profile.php':
        case 'edit_email_campaign_profile.php':
        case 'import_email_campaign_profiles.php':
            $active_menu = 9;
            break;
        case 'erp_dashboard.php':
        case 'erp_accounts.php':
        case 'add_erp_account.php':
        case 'edit_erp_account.php':
        case 'erp_invoices.php':
        case 'add_erp_invoice.php':
        case 'add_erp_return.php':
        case 'edit_erp_invoice.php':
        case 'erp_cash.php':
        case 'add_erp_till.php':
        case 'edit_erp_till.php':
        case 'add_erp_receipt.php':
        case 'add_erp_transfer.php':
        case 'erp_waybills.php':
        case 'erp_settings.php':
            $active_menu = 22;
            break;
        case 'view_orders.php':
        case 'view_order.php':
        case 'view_orders_for_contact.php':
        case 'view_order_reports.php':
        case 'view_order_report.php':
        case 'add_order.php':
        case 'view_parasut_inbox.php':
            $active_menu = 10;
            break;
        case 'view_products.php':
        case 'add_product.php':
        case 'edit_product.php':
        case 'import_products.php':
        case 'local_sale.php':
        case 'barcode_increase_inventory.php':
        case 'barcode_decrease_inventory.php':
        case 'edit_featured_and_new_items.php':
            $active_menu = 11;
            break;
        case 'view_product_groups.php':
        case 'add_product_group.php':
        case 'edit_product_group.php':
        case 'duplicate_product_group.php':
            $active_menu = 12;
            break;
        case 'view_product_attributes.php':
        case 'add_product_attribute.php':
        case 'edit_product_attribute.php':
            $active_menu = 13;
            break;

        case 'view_offers.php':
        case 'add_offer.php':
        case 'edit_offer.php':
        case 'view_key_codes.php':
        case 'add_key_code.php':
        case 'edit_key_code.php':
        case 'import_key_codes.php':
        case 'delete_key_codes.php':
        case 'view_gift_cards.php':
        case 'add_gift_card.php':
        case 'edit_gift_card.php':
        case 'view_commissions.php':
        case 'edit_commission.php':
        case 'view_recurring_commission_profiles.php':
        case 'edit_recurring_commission_profile.php':
            $active_menu = 14;
            break;
        case 'view_zones.php':
        case 'add_zone.php':
        case 'edit_zone.php':
        case 'view_shipping_methods.php':
        case 'add_shipping_method.php':
        case 'edit_shipping_method.php':
        case 'view_arrival_dates.php':
        case 'add_arrival_date.php':
        case 'edit_arrival_date.php':
        case 'view_verified_shipping_addresses.php':
        case 'add_verified_shipping_address.php':
        case 'edit_verified_shipping_address.php':
        case 'view_containers.php':
        case 'add_container.php':
        case 'edit_container.php':
        case 'view_shipping_report.php':
        case 'view_ship_date_adjustments.php':
        case 'add_ship_date_adjustment.php':
        case 'edit_ship_date_adjustment.php':
        case 'view_tax_zones.php':
        case 'add_tax_zone.php':
        case 'edit_tax_zone.php':
        case 'view_countries.php':
        case 'add_country.php':
        case 'edit_country.php':
        case 'view_states.php':
        case 'add_state.php':
        case 'edit_state.php':
            $active_menu = 15;
            break;
        case 'view_ads.php':
        case 'add_ad.php':
        case 'edit_ad.php':
            $active_menu = 16;
            break;
        case 'view_system_styles.php':
        case 'add_system_style.php':
        case 'edit_system_style.php':
            $active_menu = 21;
            break;
        case 'view_styles.php':
        case 'add_style.php':
        case 'view_system_style_source.php':
        case 'add_custom_style.php':
        case 'edit_custom_style.php':
        case 'page_designer.php':
        case 'view_themes.php':
        case 'add_theme_file.php':
        case 'edit_theme_file.php':
        case 'theme_designer.php':
        case 'edit_theme_css.php':
        case 'view_design_files.php':
        case 'add_design_file.php':
        case 'edit_design_file.php':
        case 'edit_javascript.php':
        case 'import_design.php':
        case 'import_zip.php':
        case 'migration.php':
        case 'find_and_replace.php':
        case 'mass_edit.php':
            $active_menu = 17;
            break;
        case 'view_menus.php':
        case 'add_menu.php':
        case 'edit_menu.php':
        case 'view_menu_items.php':
        case 'add_menu_item.php':
        case 'edit_menu_item.php':
            $active_menu = 18;
            break;
        case 'view_regions.php':
        case 'add_common_region.php':
        case 'edit_common_region.php':
        case 'edit_designer_region.php':
        case 'add_designer_region.php':
        case 'add_ad_region.php':
        case 'edit_ad_region.php':
        case 'add_dynamic_region.php':
        case 'edit_dynamic_region.php':
        case 'add_login_region.php':
        case 'edit_login_region.php':
            $active_menu = 19;
            break;

        case 'view_fields.php':
        case 'add_field.php':
        case 'edit_field.php':
        case 'preview_form.php':
            if (isset($_GET['product_id']) == true) {
                $active_menu = 5;
            } else {
                $active_menu = 2;
            }
            break;
            // The screens that used to sit under the Settings menu highlight
            // nothing: there is no menu item to highlight any more. They are
            // reached from the sidebar of the settings modal.

    }





    $output_pinned_menu_items = '';
    // get list of pinned links for this user.
    $query = "SELECT selected_appmenu_items_array " . "FROM user " . "WHERE user_username = '" . escape($_SESSION['sessionusername']) . "'";
    $result = mysqli_query(db::$con, $query) or output_error(lang('Query failed'));
    $order_string = mysqli_fetch_array($result)['selected_appmenu_items_array'];






    $output_all_menu_items = '';
    foreach ($menu_items as $key => $options) {

        //also add active class for full menu item that user viewed.
        $active_menu_class = '';
        if (isset($active_menu) && isset($options['id']) && $active_menu === $options['id']) {
            $active_menu_class = ' active';
        }

        $context_enabled = '';
        if (isset($options['context']) && $options['context'] != false) {
            $context_enabled = ' data-bs-toggle="context"';
        }

        $context_content = '';
        if (isset($options['data-bs-content']) && $options['data-bs-content'] != false) {
            $context_content = ' data-bs-content="' . $options['data-bs-content'] . '"';
        }

        $output_menu_item_icon = '';
        //if icon is set
        if (isset($options['icon']) && $options['icon'] != '') {
            $output_menu_item_icon = '<i class="me-2 bi ' . $options['color_class'] . ' ' . $options['icon'] . '"></i>';

        } else if (isset($options['svg']) && $options['svg'] != '') {
            //else check if svg exist
            $output_menu_item_icon = $options['svg'];
        } else {
            //else generate icon
            $output_menu_item_icon = '<svg style="font-size:10px" width="16px" height="16px" class="me-2 ' . $options['color_class'] . '" data-name="empty icon" fill="currentcolor" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16"><text transform="translate(5 12)"><tspan x="0" y="0">' . substr(lang($options['title']), 0, 1) . '</tspan></text><path d="M8,.55C3.89.55.55,3.89.55,8s3.34,7.45,7.45,7.45,7.45-3.34,7.45-7.45S12.11.55,8,.55ZM8,14.86c-3.79,0-6.86-3.07-6.86-6.86S4.21,1.14,8,1.14s6.86,3.07,6.86,6.86-3.07,6.86-6.86,6.86Z"/></svg>';

        }

        $output_all_menu_items .= '<a id="menu_item_' . $options['id'] . '" ' . $context_enabled . $context_content . ' class="list-group-item list-group-item-action border-0' . $active_menu_class . '" title="' . lang($options['title']) . '" href="' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/' . $options['href'] . '" ' . $output_parent_target . '>' . $output_menu_item_icon . '<span class="list-group-text">' . lang($options['title']) . '</span></a>';


    }
    // output full menu.
    return $output_all_menu_items;
}

// license_check()
// PHP 7.0 - 8.5 compatible helper to validate and display subscription/license status.
// Improvements:
//  - Robust input checks and fallbacks (cURL or file_get_contents).
//  - Safer JSON handling and session updates.
//  - Clear docblock and conservative use of language/features compatible with PHP 7.0+.
//  - Keeps original behavior: returns different outputs depending on $properties['output']
//    ('bar' returns HTML fragment for header bar, 'text' returns 'licensed'/'not_licensed',
//     otherwise it will block access and display a promotional/licensing page).
function license_check($properties = false)
{
    // Simple doc / usage:
    // license_check(); // default behavior - may exit() if not licensed
    // license_check(array('output'=>'bar')); // returns the account menu status block, or '' if no key
    // license_check(array('output'=>'badge')); // returns a colour word for the avatar dot, or '' if no key
    // license_check(array('output'=>'text')); // returns 'licensed' or 'not_licensed'
    global $user;

    // normalize properties
    $output_information = '';
    if (is_array($properties) && isset($properties['output'])) {
        $output_information = $properties['output'];
    }

    $output_notice_title_classes = '';
    $output_counter = '';
    $output_license_status_color_class = '';
    $output_extend_license_button = '';
    $output_check_license_button = '';
    $license_valid = false;

    // Use DateTime for date arithmetic, use today without time
    try {
        $today = new DateTime('today');
    } catch (\Exception $e) {
        // fallback: create from formatted string
        $today = new DateTime(date('Y-m-d'));
    }

    // Ensure SUBSCRIPTION_KEY constant exists and is not empty
    if (defined('SUBSCRIPTION_KEY') && trim(SUBSCRIPTION_KEY) !== '') {

        // initialize session array path
        if (!isset($_SESSION['software'])) {
            $_SESSION['software'] = array();
        }
        if (!isset($_SESSION['software']['settings'])) {
            $_SESSION['software']['settings'] = array();
        }
        if (!isset($_SESSION['software']['settings']['license'])) {
            $_SESSION['software']['settings']['license'] = array();
        }

        // Determine whether we should force-check the license (GET param) or re-check after 12 hours
        // Only manager-level and above (role <= 2) may trigger an immediate re-check;
        // this prevents lower-privilege users from hammering the license server on every request.
        $force_check = false;
        if (isset($_GET['check_license_again']) && $_GET['check_license_again']) {
            if (isset($user['role']) && (int) $user['role'] <= 2) {
                $force_check = true;

            }
        }

        $last_check = isset($_SESSION['software']['settings']['license']['last_check']) ? (int) ($_SESSION['software']['settings']['license']['last_check'] ?? '') : 0;
        $need_check = $force_check || (empty($_SESSION['software']['settings']['license']['status'])) || ((time() - $last_check) > 43200);

        if ($need_check) {
            // Clear previous license indicators (we'll set again below)
            $_SESSION['software']['settings']['license']['status'] = '';
            $_SESSION['software']['settings']['license']['error_code'] = '';
            $_SESSION['software']['settings']['license']['countdown'] = '';
            $_SESSION['software']['settings']['license']['expiration_date_formatted'] = '';

            // Prepare request payload
            $request = array(
                'hostname' => (defined('HOSTNAME_SETTING') ? HOSTNAME_SETTING : (isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '')),
                'url' => (defined('URL_SCHEME') && defined('HOSTNAME_SETTING') && defined('PATH') ? (URL_SCHEME . HOSTNAME_SETTING . PATH) : ''),
                'version' => (defined('VERSION') ? VERSION : ''),
                'edition' => (defined('EDITION') ? EDITION : ''),
                'uname' => function_exists('php_uname') ? php_uname() : '',
                'os' => defined('PHP_OS') ? PHP_OS : (function_exists('php_uname') ? php_uname('s') : ''),
                'web_server' => isset($_SERVER['SERVER_SOFTWARE']) ? $_SERVER['SERVER_SOFTWARE'] : '',
                'php_version' => function_exists('phpversion') ? phpversion() : '',
                // mysql_version - try safe call; db() helper used elsewhere in file, keep same approach
                'mysql_version' => function_exists('db') ? db("SELECT VERSION()") : '',
                'installer' => (defined('INSTALLER') ? INSTALLER : ''),
                'private_label' => (defined('PRIVATE_LABEL') ? PRIVATE_LABEL : false),
            );

            $data = encode_json($request);
            $subscription_key_clean = str_replace('-', '', SUBSCRIPTION_KEY);
            $api_url = 'https://www.kodpen.com/api2?API=59593DS72233483322T669223344&REQUEST=license&LICENSE=' . h($subscription_key_clean);

            $response = false;
            $curl_errno = 0;
            $curl_error = '';

            // Prefer cURL if available
            if (function_exists('curl_init')) {
                $ch = curl_init();
                // Identify this installation on outgoing requests. Sent with no
                // User-Agent, a request looks like an anonymous client to the receiving
                // server's firewall and gets rejected — which is how Pinegrap ended up
                // blocking its own licence and update checks.
                curl_setopt($ch, CURLOPT_USERAGENT, function_exists('pinegrap_user_agent') ? pinegrap_user_agent() : 'Pinegrap');
                curl_setopt($ch, CURLOPT_URL, $api_url);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                curl_setopt($ch, CURLOPT_TIMEOUT, 10);
                curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
                curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
                curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
                // send JSON POST
                curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
                curl_setopt($ch, CURLOPT_FORBID_REUSE, true);
                curl_setopt($ch, CURLOPT_POST, 1);
                curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                    'Content-Type: application/json',
                    'Content-Length: ' . strlen($data)
                ));
                // optional proxy
                if (defined('PROXY_ADDRESS') && PROXY_ADDRESS !== '') {
                    curl_setopt($ch, CURLOPT_HTTPPROXYTUNNEL, true);
                    curl_setopt($ch, CURLOPT_PROXYTYPE, CURLPROXY_HTTP);
                    curl_setopt($ch, CURLOPT_PROXY, PROXY_ADDRESS);
                }

                $response = @curl_exec($ch);
                $curl_errno = curl_errno($ch);
                $curl_error = curl_error($ch);
                curl_close($ch);
            } else {
                // Fallback: file_get_contents with stream context if allow_url_fopen is enabled
                $context = stream_context_create(array(
                    'http' => array(
                        'method' => 'POST',
                        'header' => "Content-Type: application/json\r\n" .
                            "Content-Length: " . strlen($data) . "\r\n",
                        'content' => $data,
                        'timeout' => 10
                    ),
                    'ssl' => array(
                        'verify_peer' => false,
                        'verify_peer_name' => false
                    )
                ));
                $response = @file_get_contents($api_url, false, $context);
            }

            // decode result safely
            $decoded = false;
            if ($response !== false && $response !== null && $response !== '') {
                $decoded = decode_json($response);
                if (!is_array($decoded)) {
                    $decoded = false;
                }
            }

            if ($decoded && isset($decoded['status'])) {
                $_SESSION['software']['settings']['license']['status'] = $decoded['status'];
            } else {
                // If no structured response, set a technical error message for display
                $_SESSION['software']['settings']['license']['status'] = '';
            }

            if ($decoded && isset($decoded['error_code'])) {
                $_SESSION['software']['settings']['license']['error_code'] = $decoded['error_code'];
            }

            // successful license verification path
            if (isset($_SESSION['software']['settings']['license']['status']) && ($_SESSION['software']['settings']['license']['status'] ?? '') === 'success') {
                // server returns end date in 'P_end_d' per original code - guard it
                if (isset($decoded['P_end_d']) && $decoded['P_end_d'] !== '') {
                    try {
                        $expiration_date = new DateTime($decoded['P_end_d']);
                    } catch (\Exception $e) {
                        // try alternate formats
                        $expiration_date = DateTime::createFromFormat('d-m-Y', $decoded['P_end_d']);
                        if ($expiration_date === false) {
                            // last fallback: try to parse using strtotime
                            $ts = strtotime($decoded['P_end_d']);
                            $expiration_date = $ts ? new DateTime(date('Y-m-d', $ts)) : null;
                        }
                    }
                    if ($expiration_date instanceof DateTime) {
                        $_SESSION['software']['settings']['license']['expiration_date_formatted'] = $expiration_date;
                        // compute days difference
                        $diff = $today->diff($expiration_date);
                        // %a gives total days; ensure format method exists (it does)
                        $countdown = (int) $diff->format('%a');
                        $_SESSION['software']['settings']['license']['countdown'] = $countdown;
                        $_SESSION['software']['settings']['license']['last_check'] = time();
                    }
                } else {
                    // If P_end_d not provided, set last_check to avoid continuous retries
                    $_SESSION['software']['settings']['license']['last_check'] = time();
                }
            } else {
                // mark last_check to avoid hammering remote if it failed repeatedly
                $_SESSION['software']['settings']['license']['last_check'] = time();
                // store diagnostic info for admin display
                $_SESSION['software']['settings']['license']['diag_errno'] = $curl_errno;
                $_SESSION['software']['settings']['license']['diag_error'] = $curl_error;
                $_SESSION['software']['settings']['license']['diag_response'] = is_string($response) ? substr($response, 0, 300) : '';
            }
        } // end need_check

        // Interpret the session license values and set output variables
        $status = isset($_SESSION['software']['settings']['license']['status']) ? ($_SESSION['software']['settings']['license']['status'] ?? '') : '';
        $error_code = isset($_SESSION['software']['settings']['license']['error_code']) ? ($_SESSION['software']['settings']['license']['error_code'] ?? '') : '';
        $countdown = isset($_SESSION['software']['settings']['license']['countdown']) ? (int) ($_SESSION['software']['settings']['license']['countdown'] ?? '') : 0;
        $expiration_dt = isset($_SESSION['software']['settings']['license']['expiration_date_formatted']) ? ($_SESSION['software']['settings']['license']['expiration_date_formatted'] ?? '') : null;
        $diag = array(
            'errno' => isset($_SESSION['software']['settings']['license']['diag_errno']) ? ($_SESSION['software']['settings']['license']['diag_errno'] ?? '') : '',
            'error' => isset($_SESSION['software']['settings']['license']['diag_error']) ? ($_SESSION['software']['settings']['license']['diag_error'] ?? '') : '',
            'response' => isset($_SESSION['software']['settings']['license']['diag_response']) ? ($_SESSION['software']['settings']['license']['diag_response'] ?? '') : '',
        );

        if ($status === 'success' && $expiration_dt instanceof DateTime && ($countdown >= 0) && ($today <= $expiration_dt)) {
            $output_notice_title = lang('Congratulations! Your license is valid.');
            $output_notice_title_classes .= ' rounded text-success';
            $output_counter = '<h6 class="text-center">' . lang(array('string' => '{var:1} days left', 'vars' => array($countdown))) . '</h6>';
            $progress_width = ($countdown / 365 * 100);
            if ($progress_width < 0) {
                $progress_width = 0;
            }
            if ($progress_width > 100) {
                $progress_width = 100;
            }
            $output_counter .=
                '<div class="m-2 row">
                    <div class="col p-0" style="padding-right:2px !important">
                        <div class="progress rounded-0 w-100" style="--bs-progress-height: .2rem;">
                            <div class="progress-bar" style="width: ' . h($progress_width) . '%;" aria-valuenow="' . h($countdown) . '" aria-valuemin="0" aria-valuemax="365"></div>
                        </div>
                    </div>
                </div>';

            $output_license_status_color_class = '';
            if ($countdown <= 60) {
                $output_license_status_color_class = ' text-warning';
                $output_extend_license_button = '<a href="https://www.kodpen.com/iletisim" class="dropdown-item text-truncate" target="_blank">' . lang('Extend License') . '</a>';
                if ($countdown <= 10) {
                    $output_notice_title_classes .= ' rounded text-danger';
                    $output_notice_title = lang('Your software license is about to expire, and if it is not renewed, you will be deprived of some features.');
                }
            }

            $license_valid = true;
        } else {
            // Not successful or expired
            if ($status === 'success' && $expiration_dt instanceof DateTime) {
                // expired
                $output_notice_title = lang('Your license has expired. You cannot benefit from some features.');
                $output_notice_title_classes .= ' rounded text-danger';
                $output_license_status_color_class = ' text-danger';
                $output_check_license_button = '<form method="get" action="" class="text-center disable_shortcut"><input type="hidden" name="check_license_again" value="1"/><button type="submit" value="refresh" class="dropdown-item text-truncate">' . lang('Check License') . '</button></form>';
                $output_extend_license_button = '<a href="https://www.kodpen.com/iletisim" class="dropdown-item text-truncate" target="_blank">' . lang('Buy License') . '</a>';
            } else {
                // invalid or technical error
                if ($error_code === 'no_match' || $error_code === 'no_key') {
                    $output_notice_title = lang('Your license is not valid. You cannot benefit from some features.');
                    $output_notice_title_classes .= ' rounded text-danger';
                    $output_license_status_color_class = ' text-danger';
                    $output_check_license_button = '<form method="get" action="" class="text-center disable_shortcut"><input type="hidden" name="check_license_again" value="1"/><button type="submit" value="refresh" class="dropdown-item text-truncate">' . lang('Check License') . '</button></form>';
                    $output_extend_license_button = '<a href="https://www.kodpen.com/iletisim" class="dropdown-item text-truncate" target="_blank">' . lang('Buy License') . '</a>';
                } else {
                    $output_notice_title = lang('License verification failed due to technical reason. You cannot benefit from some features.');
                    $output_notice_title_classes .= ' rounded text-danger';
                    $output_license_status_color_class = ' text-danger';
                    $output_check_license_button = '<form method="get" action="" class="text-center disable_shortcut"><input type="hidden" name="check_license_again" value="1"/><button type="submit" value="refresh" class="dropdown-item text-truncate">' . lang('Check License') . '</button></form>';
                }
            }
        }
    } else {
        // No subscription key provided
        $output_notice_title = lang('No license key provided in Site Settings. You cannot benefit from some features.');
        $output_notice_title_classes .= ' rounded text-danger';
    }

    // Output according to request
    if ($output_information === 'bar' || $output_information === 'badge') {
        if (defined('SUBSCRIPTION_KEY') && SUBSCRIPTION_KEY != '') {

            // ── Derive display state ─────────────────────────────────────────
            if ($license_valid) {
                if ($countdown <= 10) {
                    $bar_color = 'danger';
                } elseif ($countdown <= 60) {
                    $bar_color = 'warning';
                } else {
                    $bar_color = 'success';
                }
                $bar_status_text = lang('Active');
            } elseif ($status === 'success') {
                $bar_color = 'danger';
                $bar_status_text = lang('Expired');
            } else {
                $bar_color = 'secondary';
                $bar_status_text = ($error_code === 'no_match' || $error_code === 'no_key')
                    ? lang('Invalid')
                    : lang('Unavailable');
            }

            $bar_icon_cls = 'text-' . $bar_color;
            $bar_badge_cls = 'bg-' . $bar_color . ($bar_color === 'warning' ? ' text-dark' : '');

            // The avatar dot only needs the state, not the panel.
            if ($output_information === 'badge') {
                return $bar_color;
            }

            // ── Countdown / expiration block ─────────────────────────────────
            $bar_body = '';
            if ($license_valid && $expiration_dt instanceof DateTime) {
                $bar_progress = min(100, max(0, round($countdown / 365 * 100)));
                $bar_body = '
                <div class="px-3 pt-2 pb-1 text-center">
                    <span class="fw-bold lh-1 text-' . h($bar_color) . '" style="font-size:2.25rem">' . (int) $countdown . '</span>
                    <div class="text-muted small">' . lang('days remaining') . '</div>
                    <div class="text-muted mt-1" style="font-size:.7rem;letter-spacing:.02em">' . h($expiration_dt->format('d M Y')) . '</div>
                </div>
                <div class="px-3 pt-1 pb-2">
                    <div class="progress rounded-pill overflow-hidden" style="height:3px">
                        <div class="progress-bar bg-' . h($bar_color) . '" role="progressbar"
                             style="width:' . h($bar_progress) . '%"
                             aria-valuenow="' . (int) $countdown . '" aria-valuemin="0" aria-valuemax="365"></div>
                    </div>
                </div>';
            } elseif ($status === 'success' && $expiration_dt instanceof DateTime) {
                // expired — show the date it expired
                $bar_body = '
                <div class="px-3 py-2 text-center">
                    <div class="text-danger small">' . lang('Expired') . ': ' . h($expiration_dt->format('d M Y')) . '</div>
                </div>';
            } else {
                // technical error — show diagnostic for managers+
                $bar_diag_html = '';
                if (isset($user['role']) && (int) $user['role'] <= 2 && ($diag['errno'] || $diag['response'] || $diag['error'])) {
                    $diag_lines = array();
                    if ($diag['errno'])
                        $diag_lines[] = 'cURL errno: ' . (int) $diag['errno'];
                    if ($diag['error'])
                        $diag_lines[] = 'cURL error: ' . h($diag['error']);
                    if ($diag['response'])
                        $diag_lines[] = 'Response: ' . h($diag['response']);
                    $bar_diag_html = '
                    <details class="px-2 pb-1" style="font-size:.65rem">
                        <summary class="text-muted" style="cursor:pointer">Diagnostic</summary>
                        <pre class="text-start text-danger bg-body-secondary rounded p-1 mt-1 mb-0" style="white-space:pre-wrap;word-break:break-all;font-size:.65rem">' . implode("\n", $diag_lines) . '</pre>
                    </details>';
                }
                $bar_body = '
                <div class="px-3 pt-2 pb-1 text-center">
                    <small class="text-muted">' . h($output_notice_title) . '</small>
                </div>' . $bar_diag_html;
            }

            // ── Action buttons ───────────────────────────────────────────────
            $bar_actions_html = '';
            $bar_btn_check = '';
            $bar_btn_extend = '';

            if ($output_check_license_button) {
                $bar_btn_check = '
                <form method="get" action="" class="disable_shortcut">
                    <input type="hidden" name="check_license_again" value="1">
                    <button type="submit" class="btn btn-sm btn-outline-secondary w-100">
                        <i class="bi bi-arrow-clockwise me-1"></i>' . lang('Check License') . '
                    </button>
                </form>';
            }
            if ($output_extend_license_button) {
                $btn_label = ($status === 'success') ? lang('Extend License') : lang('Buy License');
                $bar_btn_extend = '
                <a href="https://www.kodpen.com/iletisim" target="_blank"
                   class="btn btn-sm btn-outline-primary w-100">
                    <i class="bi bi-gem me-1"></i>' . $btn_label . '
                </a>';
            }
            if ($bar_btn_check || $bar_btn_extend) {
                $bar_actions_html = '
                <div class="p-2 border-top d-grid gap-1">' . $bar_btn_check . $bar_btn_extend . '</div>';
            }

            // Short right hand figure on the summary row. The full date, the
            // progress bar and the action buttons stay in the collapsed panel.
            $bar_row_meta = '';
            if ($license_valid && $expiration_dt instanceof DateTime) {
                // Just the unit here. "610 kalan gun" cost the label the words
                // that say which state it is in, and the row already reads as a
                // countdown; the sentence is two lines below in the panel.
                $bar_row_meta = '<span class="pg-lic-meta">' . (int) $countdown . ' ' . lang('days') . '</span>';
            }

            return '
            <li class="pg-lic-item no-popover pg-lic-' . h($bar_color) . '">
                <button type="button" class="pg-lic-row" data-bs-toggle="collapse"
                        data-bs-target="#pg_license_panel" aria-expanded="false"
                        aria-controls="pg_license_panel"
                        title="' . lang('Premium features access status') . '">
                    <i class="bi bi-gem"></i>
                    <span class="pg-lic-label">Pinegrap Premium™ · ' . h($bar_status_text) . '</span>
                    ' . $bar_row_meta . '
                    <i class="bi bi-chevron-down pg-lic-chev"></i>
                </button>
                <div class="collapse" id="pg_license_panel">
                    <div class="pg-lic-panel">
                        ' . $bar_body . $bar_actions_html . '
                    </div>
                </div>
            </li>';
        }
        return '';
    } else if ($output_information === 'text') {
        return ($license_valid === true) ? 'licensed' : 'not_licensed';
    } else {
        // Default: block access if NOT licensed
        if ($license_valid !== true) {
            // show promotional/licensing page and exit
            print

                $output = output_header([
                    'title' => lang('Pinegrap Premium™'),
                    'extra classes' => 'setting',
                    'icon' => 'setting',
                    'heading' => lang('Pinegrap Premium™'),
                    'cancel' => true
                ]) . '
            <main class="container mb-5" style="min-height:calc(100vh - 175px)" id="content">
                <div class="col-12 col-md-8 offset-md-2">
                    <div class="card my-5 border-4">
                        <div class="card-body mb-0 p-4">
                        <div class=" text-center"><span class="material-icons text-light" style="font-size:5em;line-height:1em;">diamond</span></div>
                        <h6 class="text-success text-center">Pinegrap Premium™</h6>
                        <h5>' . lang('Advantages') . ':</h5>
                        <ul>
                            <li>' . lang('Simplify your remote server operations with API support.') . '</li>
                            <li>' . lang('Enrich website functions with custom coding support.') . '</li>
                            <li>' . lang('Keep stocks under control easily with barcode transactions without requiring additional software.') . '</li>
                            <li>' . lang('Sell locally with your barcode device.') . '</li>
                            <li>' . lang('Get website design and editing support, your website design will not fall behind the times.') . '</li>
                            <li>' . lang('Get the latest updates and security patches.') . '</li>
                            <li>' . lang('Take advantage of our 24/7 customer support service.') . '</li>
                        </ul>
                        <p class="alert alert-danger ">' . lang('The page you are trying to access requires a premium license. If you already have a premium license key, report the problem to us on our contact page.') . '</p>
                        <a href="https://www.kodpen.com/iletisim" target="_blank" class="btn-link link-secondary"><i class="material-icons me-2">support_agent</i>' . lang('Contact Us') . '</a>
                        </div>
                    </div>
                </div>
            </main>
            ' . output_footer();
            exit();
        }
    }
}



/**
 * Fetches the toolbar page row (page + folder_archived) with a per-request
 * static cache so toolbar.php and output_toolbar() don't both execute the
 * same query on the same request.
 */
function get_toolbar_page_row($page_id)
{
    static $cache = array();
    $key = (string) $page_id;
    if (isset($cache[$key])) {
        return $cache[$key];
    }
    $query =
        "SELECT
            page.page_id,
            page.page_name,
            page.page_folder,
            page.page_home,
            page.page_search,
            page.page_search_keywords,
            page.page_style,
            page.mobile_style_id,
            page.page_type,
            page.comments,
            page.comments_automatic_publish,
            page.comments_administrator_email_to_email_address,
            page.seo_score,
            page.sitemap,
            page.page_timestamp,
            page.page_user,
            folder.folder_archived
        FROM page
        LEFT JOIN folder ON page.page_folder = folder.folder_id
        WHERE page.page_id = '" . escape($page_id) . "'";
    $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
    $cache[$key] = mysqli_fetch_assoc($result);
    return $cache[$key];
}

/**
 * Whether this user may be told what a page is, and offered what to do with it.
 *
 * The two gates the toolbar dropdown applied: a plain user needs edit rights
 * somewhere at all, and on this page's own folder. Memoized because both
 * pg_page_facts() and pg_page_actions() ask, and the answer for a plain user
 * costs a walk of the whole folder tree.
 *
 * @param int   $page_folder
 * @param array $user
 * @return bool
 */
function pg_page_properties_visible($page_folder, $user)
{
    static $cache = array();

    if (!is_array($user) || empty($user) || !isset($user['role'])) {
        return false;
    }

    $key = $user['id'] . ':' . $page_folder;

    if (isset($cache[$key])) {
        return $cache[$key];
    }

    if (($user['role'] >= 3) && (no_acl_check($user['id']) != true)) {
        $cache[$key] = false;
    } elseif ($user['role'] == 3) {
        $cache[$key] = (check_folder_access_in_array($page_folder, get_folders_that_user_has_access_to($user['id'])) == true);
    } else {
        $cache[$key] = true;
    }

    return $cache[$key];
}

/**
 * Address an admin screen opened from the front end panel should return to.
 *
 * The panel lives on the page being looked at, so that is where every screen
 * opened from it comes back to. URL encoded, ready to sit in a query string.
 */
function pg_page_panel_send_to($send_to = null)
{
    if ($send_to === null) {
        $send_to = defined('REQUEST_URL') ? REQUEST_URL : (isset($_GET['send_to']) ? $_GET['send_to'] : '');
    }

    return urlencode($send_to);
}

/**
 * The descriptive facts about a page, as data rather than markup.
 *
 * These used to be assembled as dropdown items inside output_toolbar(). They
 * are drawn on the front end now, in the panel behind the SEO ring, where the
 * surrounding document belongs to whoever built the site: no Bootstrap, no
 * icon font, and every link has to carry its full path because the panel sits
 * on the visitor's page and not inside the toolbar's iframe.
 *
 * A fact carrying a label renders as a two column row; one without a label is
 * a sentence and spans the row. Returns an empty array when the caller may not
 * see this page's properties.
 *
 * @param int    $page_id
 * @param int    $style_id style the page is being rendered with
 * @param array  $user     row from validate_user() / initialize_user()
 * @param string $send_to  address the admin screens should return to
 * @return array list of label / text / url
 */
function pg_page_facts($page_id, $style_id, $user, $send_to = null)
{
    $facts = array();
    $row = get_toolbar_page_row($page_id);

    if (!is_array($row) || !pg_page_properties_visible($row['page_folder'], $user)) {
        return $facts;
    }

    $page_folder = $row['page_folder'];
    $send_to = pg_page_panel_send_to($send_to);
    $software = OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/';

    $page_id = $row['page_id'];
    $page_type = $row['page_type'];
    $access_control_type = get_access_control_type($page_folder);

    $facts[] = array('label' => lang('Access'), 'text' => get_access_control_type_name($access_control_type));

    if ($page_type != '') {
        $facts[] = array('label' => lang('Page Type'), 'text' => get_page_type_name($page_type));
    }

    // Most recent short link pointing at this page, if there is one. It opens
    // for editing rather than just being read out: the operator who looks it
    // up here is usually about to change or retire it.
    $short_link = db_item(
        "SELECT id, name
        FROM short_links
        WHERE
            (destination_type = 'page')
            AND (page_id = '" . escape($page_id) . "')
        ORDER BY last_modified_timestamp DESC
        LIMIT 1");

    if (is_array($short_link) && ($short_link['name'] != '')) {
        $facts[] = array(
            'label' => lang('Short Link'),
            'text'  => $short_link['name'],
            'url'   => $software . 'view_folders.php?view=short_links&edit=' . (int) $short_link['id']);
    }

    if (check_for_page_type_properties($page_type) == true) {
        $page_type_properties = get_page_type_properties($page_id, $page_type);

        // A catalog detail page carries a next page that is only walked to
        // when the visitor may order, so it is not a property of the page
        // itself unless ordering is turned on.
        //
        // NULL is "this page type has no next page at all"; an id that
        // resolves to nothing still gets a row, because a step whose
        // destination was never chosen is the case worth reporting.
        $next_page_id = NULL;

        if (
            (isset($page_type_properties['next_page_id']) == true)
            &&
            (
                ($page_type != 'catalog detail')
                || ($page_type_properties['allow_customer_to_add_product_to_order'] == 1)
            )
        ) {
            $next_page_id = $page_type_properties['next_page_id'];
        } elseif (isset($page_type_properties['add_button_next_page_id']) && ($page_type_properties['add_button_next_page_id'] != 0)) {
            $next_page_id = $page_type_properties['add_button_next_page_id'];
        }

        if ($next_page_id !== NULL) {
            $next_page_name = get_page_name($next_page_id);

            if ($next_page_name == '') {
                $facts[] = array('label' => lang('Next Page'), 'text' => '[' . lang('Not Set') . ']');
            } else {
                $facts[] = array('label' => lang('Next Page'), 'text' => $next_page_name, 'url' => OUTPUT_PATH . $next_page_name);
            }
        }

        if (isset($page_type_properties['skip_button_next_page_id']) && ($page_type_properties['skip_button_next_page_id'] != 0)) {
            $skip_page_name = get_page_name($page_type_properties['skip_button_next_page_id']);

            $facts[] = array('label' => lang('Skip Page'), 'text' => $skip_page_name, 'url' => OUTPUT_PATH . $skip_page_name);
        }
    }

    if ($row['page_search'] == 1) {
        $facts[] = array('label' => lang('Searchable'), 'text' => lang('Enabled'));

        if ($row['page_search_keywords'] != '') {
            $facts[] = array('label' => lang('Keyword'), 'text' => $row['page_search_keywords']);
        }
    }

    if ($row['comments'] == '1') {
        $facts[] = array(
            'text' => ($row['comments_automatic_publish'] == '1')
                ? lang('Comments automatically published')
                : lang('Comments require approval'));

        // Only worth a row when somebody is actually waiting. The line above
        // says approval is required; this one says whether that is a job.
        $pending = (int) db_value(
            "SELECT COUNT(id) FROM comments WHERE (page_id = '" . escape($page_id) . "') AND (published = '0')");

        if ($pending > 0) {
            $facts[] = array(
                'label' => lang('Comments'),
                'text'  => lang('Pending') . ': ' . $pending,
                'url'   => $software . 'view_comments.php');
        }

        if ($row['comments_administrator_email_to_email_address'] != '') {
            $facts[] = array('label' => lang('Moderator'), 'text' => $row['comments_administrator_email_to_email_address']);
        }
    }

    // A feed is only ever served to visitors, so a page behind a login has
    // none whatever its own settings say.
    $rss = NULL;

    if ($page_type == 'form list view') {
        $number_of_rss_fields = db_value(
            "SELECT COUNT(form_fields.rss_field)
            FROM form_list_view_pages
            LEFT JOIN form_fields ON form_fields.page_id = form_list_view_pages.custom_form_page_id
            WHERE
                (form_list_view_pages.page_id = '" . escape($page_id) . "')
                AND (form_list_view_pages.collection = 'a')
                AND (rss_field != '')");

        if ($number_of_rss_fields > 0) {
            $rss = ($access_control_type == 'public');
        }
    } elseif ($page_type == 'calendar view') {
        $rss = ($access_control_type == 'public');
    }

    if ($rss !== NULL) {
        $facts[] = array(
            'label' => 'RSS',
            'text'  => $rss ? lang('Enabled') : lang('Disabled because Page is not in a Public Folder.'));
    }

    $style = db_item("SELECT style_name, style_type FROM style WHERE style_id = '" . escape($style_id) . "'");

    if (is_array($style) && ($style['style_name'] != '')) {
        $fact = array('label' => lang('Page Style'), 'text' => $style['style_name']);

        // Only a designer may open the style, so only a designer gets a link.
        if ($user['role'] <= 1) {
            $fact['url'] = $software . 'edit_' . $style['style_type'] . '_style.php?id=' . urlencode($style_id) . '&send_to=' . $send_to;
        }

        $facts[] = $fact;
    }

    if ($row['sitemap'] == 1) {

        if (($access_control_type == 'public') && ($row['folder_archived'] == 0)) {
            $sitemap = lang('Included');
        } else {
            $message = ($access_control_type != 'public') ? lang('not in a Public Folder.') : lang('is archived');
            $sitemap = lang(array('string' => 'Excluded because Page {var:1}', 'vars' => array($message)));
        }

        $facts[] = array('label' => lang('Sitemap'), 'text' => $sitemap);
    }

    // How much this page is read, next to how well it scores: the SEO panel
    // answers "what is wrong here", and this answers "is it worth fixing".
    // Same summary table the dashboard and the Pages list read, so the three
    // cannot disagree; on an installation without the rollup there is no
    // number to give and the row is left out rather than guessed at.
    if (pg_visitor_rollup_ready()) {

        $views = (int) db_value(
            "SELECT SUM(views)
            FROM visitor_content_hourly
            WHERE
                (page_id = '" . escape($page_id) . "')
                AND (stat_date >= '" . date('Y-m-d', strtotime('-30 days')) . "')");

        if ($views > 0) {
            $facts[] = array(
                'label' => lang('Page Views'),
                'text'  => number_format($views) . ' · ' . lang('Last 30 days'));
        }
    }

    // Who touched it last. page_user is a user id and that user can be gone,
    // so the name is looked up rather than stored, and a missing one is said
    // out loud instead of leaving the row half written.
    if (((int) $row['page_timestamp']) > 0) {

        $editor = (string) db_value("SELECT user_username FROM user WHERE user_id = '" . escape($row['page_user']) . "'");

        $facts[] = array(
            'label' => lang('Last Modified'),
            'text'  => get_relative_time(array('timestamp' => (int) $row['page_timestamp'], 'format' => 'plain_text'))
                     . ' · ' . (($editor !== '') ? $editor : lang('Unknown')));
    }

    return $facts;
}

/**
 * What can be done to a page, as data rather than markup.
 *
 * The companion of pg_page_facts(): that one says what the page is, this one
 * what to do with it. All of these were icons in the toolbar, where a picture
 * had to stand in for a sentence because there was no room for one; in the
 * panel they are written out.
 *
 * Returned in groups, each with an optional heading. The first group acts on
 * the page just described above it and needs no heading of its own; the design
 * tools do, because they act on the style the page is wearing rather than on
 * the page. The mobile toggle sits with them: it decides whether the desktop
 * or the mobile style renders, which is the same question.
 *
 * Each action also names a Bootstrap Icon. The panel ignores it and writes the
 * label - that is the whole point of the panel - but the strip the toolbar can
 * be switched to (pg_page_action_bar) has room for one row and nothing else,
 * and an icon with a tooltip is what fits there. Naming it here keeps the two
 * views describing the same action the same way.
 *
 * @param int    $page_id
 * @param int    $style_id style the page is being rendered with
 * @param array  $user     row from validate_user() / initialize_user()
 * @param string $send_to  address the screen should return to
 * @return array list of label / actions, each action icon / label / url / class
 */
function pg_page_actions($page_id, $style_id, $user, $send_to = null)
{
    $groups = array();
    $row = get_toolbar_page_row($page_id);

    if (!is_array($row) || !pg_page_properties_visible($row['page_folder'], $user)) {
        return $groups;
    }

    $page_id = (int) $row['page_id'];
    $send_to = pg_page_panel_send_to($send_to);
    $software = OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/';

    // get_token_query_string_field() hands back an attribute-ready fragment
    // (&amp;). These urls are escaped where they are drawn, like every other
    // one here, so the separator goes in plain.
    $token = str_replace('&amp;', '&', get_token_query_string_field());

    // ---- the page itself -------------------------------------------------

    // Where the page is edited decides what the button is called. On a visual
    // design the destination is the editor, which carries the page's settings
    // in a panel of its own - calling that "page properties" would name the
    // smaller half of what it opens.
    $page = pg_page_edit_row($page_id);
    $edit_url = is_array($page) ? pg_page_edit_url($page) : ('edit_page.php?id=' . $page_id);
    $visual = is_array($page) && pg_page_is_visual_design($page);

    $actions = array(array(
        'icon'  => $visual ? 'pencil-square' : 'sliders',
        'label' => $visual ? lang('Edit Page') : lang('Edit Page Properties'),
        'url'   => $software . $edit_url . (strpos($edit_url, '?') === false ? '?' : '&') . 'send_to=' . $send_to));

    // Duplicating creates a page, which is a right of its own: edit rights on
    // a folder do not by themselves let a plain user add to it.
    if (($user['role'] < 3) || ($user['create_pages'] == TRUE)) {
        $actions[] = array(
            'icon'  => 'files',
            'label' => lang('Duplicate Page'),
            'url'   => $software . 'duplicate_page.php?id=' . $page_id . $token);
    }

    // Offered whether or not one exists already - a page is allowed more than
    // one - while the facts above show the most recent and open it for editing.
    // Both go to the File Manager's short link area rather than to the classic
    // add_short_link.php / edit_short_link.php pair, and both carry what they
    // are about so the window opens on it instead of on an empty destination.
    $actions[] = array(
        'icon'  => 'link-45deg',
        'label' => lang(array('string' => 'Create {var:1}', 'vars' => lang('Short Link'))),
        'url'   => $software . 'view_folders.php?view=short_links&create=short_link&page_id=' . $page_id);

    $groups[] = array('label' => '', 'actions' => $actions);

    // ---- the design it is wearing ----------------------------------------

    $actions = array();
    $style_type = (string) db_value("SELECT style_type FROM style WHERE style_id = '" . escape($style_id) . "'");

    if ($user['role'] < 2) {

        // Keeps the page_designer_button class wherever it is drawn: the
        // Ctrl+G handler clicks it by that name, and page_designer.js turns it
        // into "Close Page Designer" while the designer is open.
        if ($style_type == 'system') {
            $actions[] = array(
                'icon'  => 'easel',
                'label' => lang('Open Style Designer'),
                'url'   => $software . 'edit_system_style.php?id=' . urlencode($style_id) . '&send_to=' . $send_to,
                'class' => 'page_designer_button');
        } else {
            $actions[] = array(
                'icon'  => 'easel',
                'label' => lang('Open Page Designer'),
                'url'   => $software . 'page_designer.php?url=' . $send_to,
                'class' => 'page_designer_button');
        }

        $actions[] = array(
            'icon'  => 'palette',
            'label' => lang('Edit Page Style'),
            'url'   => $software . 'edit_' . $style_type . '_style.php?id=' . urlencode($style_id) . '&send_to=' . $send_to);
    }

    // The switch that turns theme preview on. While it is on the controls
    // themselves stand at the head of the panel (pg_page_preview_tools) and
    // there is nothing here to offer.
    if (($user['role'] < 3) && (isset($_SESSION['software']['preview_theme_id']) == FALSE)) {
        $actions[] = array(
            'icon'  => 'eye',
            'label' => lang('Preview Page Styles & Themes'),
            'url'   => $software . 'preview_theme.php?mode=preview&send_to=' . $send_to . $token);
    }

    if (MOBILE == true) {

        if ($_SESSION['software']['device_type'] == 'mobile') {
            $device_type = 'desktop';
            $device_icon = 'display';
            $device_label = lang('Enable Desktop Mode');
        } else {
            $device_type = 'mobile';
            $device_icon = 'phone';
            $device_label = lang('Enable Mobile Mode');
        }

        $actions[] = array(
            'icon'  => $device_icon,
            'label' => $device_label,
            'url'   => $software . 'update_device_type.php?device_type=' . $device_type . '&send_to=' . $send_to . $token);
    }

    // On a visual design the style IS the designer, so "Open Style Designer"
    // and "Edit Page Style" are the same screen under two names. As icons in
    // the toolbar that went unnoticed; written out, a list that offers one
    // destination twice reads as two different things. First name wins.
    $seen = array();

    foreach ($actions as $index => $action) {

        if (isset($seen[$action['url']])) {
            unset($actions[$index]);
            continue;
        }

        $seen[$action['url']] = true;
    }

    if ($actions) {
        $groups[] = array('label' => lang('Design'), 'actions' => array_values($actions));
    }

    return $groups;
}

/**
 * The style and theme pick lists, while a theme preview is running.
 *
 * Drawn only in that mode, which is why it used to be a dropdown the toolbar
 * forced open (`class="show dropdown-menu"`): there is no sense in a control
 * you have to find for a mode you are already in. In the panel it takes the
 * top, above the page's own description, for the same reason - while previewing
 * this is what the operator came for.
 *
 * The reading below is the toolbar's, moved: which options exist, which one is
 * marked [A]ctivated or [P]review, and which is selected all follow the same
 * rules. What changed is the surroundings - their ids are prefixed so they
 * cannot collide with the site's own markup, and "Default" is translated here
 * instead of by a class the front end has no script for.
 *
 * The selects navigate window.parent. This block is drawn both on the page,
 * where the parent of a browsing context is the context itself, and inside
 * the toolbar frame, where the site underneath is the thing a preview has to
 * reload to change anything at all.
 *
 * @return array html, css
 */
function pg_page_preview_tools($page_id, $style_id, $user, $send_to = null)
{
    $none = array('html' => '', 'css' => '');
    $row = get_toolbar_page_row($page_id);

    if (!is_array($row) || !pg_page_properties_visible($row['page_folder'], $user)) {
        return $none;
    }

    if (($user['role'] >= 3) || (isset($_SESSION['software']['preview_theme_id']) == FALSE)) {
        return $none;
    }

    $page_id = (int) $row['page_id'];
    $page_folder = $row['page_folder'];
    $send_to = pg_page_panel_send_to($send_to);
    $software = OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/';
    $token = str_replace('&amp;', '&', get_token_query_string_field());

    $device_type = $_SESSION['software']['device_type'];
    $preview_theme_id = $_SESSION['software']['preview_theme_id'];
    $activated_theme_id = db_value("SELECT id FROM files WHERE activated_" . $device_type . "_theme = '1'");
    $activated_style_id_for_device_type = ($device_type == 'desktop') ? $row['page_style'] : $row['mobile_style_id'];

    // Which style the operator last picked for this page, in this theme, on
    // this device. Long key, kept as it is: preview_style.php writes it.
    $session_style_key = 'theme_' . $preview_theme_id . '_page_' . $page_id . '_' . $device_type;
    $session_style = isset($_SESSION['software']['preview_style'][$session_style_key])
        ? $_SESSION['software']['preview_style'][$session_style_key]
        : NULL;

    // Styles offered for this device. Desktop takes everything that is not a
    // mobile-only layout; mobile takes custom styles and mobile layouts.
    if ($device_type == 'desktop') {
        $sql_where = "WHERE style_layout != 'one_column_mobile'";
    } elseif (MOBILE == true) {
        $sql_where = "WHERE (style_type = 'custom') OR (style_layout = 'one_column_mobile')";
    } else {
        $sql_where = "";
    }

    $styles = db_items("SELECT style_id AS id, style_name AS name FROM style $sql_where ORDER BY style_name ASC");

    // The default style this folder resolves to, for the first option's label.
    $default_label = function () use ($page_folder, $device_type) {
        $default_style_id = get_style($page_folder, $device_type);

        // A page with no mobile style of its own falls back to its desktop one.
        if (($device_type == 'mobile') && ($default_style_id == 0)) {
            $default_style_id = get_style($page_folder, 'desktop');
        }

        if (!$default_style_id) {
            return '';
        }

        $name = db_value("SELECT style_name FROM style WHERE style_id = '" . (int) $default_style_id . "'");

        return ($name != '') ? (': ' . $name) : '';
    };

    $options = '';

    if (($preview_theme_id == $activated_theme_id) || ($preview_theme_id == '')) {

        // Previewing the theme the site already runs, so the first option is
        // the style that is really in force and the marks read [A]ctivated.
        $first = (!$activated_style_id_for_device_type ? '[A] ' : '') . lang('Default') . $default_label();
        $options .= '<option value="">' . h($first) . '</option>';
        $marked_style_id = $activated_style_id_for_device_type;
        $mark = '[A] ';

    } else {

        // Previewing a different theme: the first option is what the page
        // wears outside the preview, and the marks read [P]review.
        if ($activated_style_id_for_device_type) {
            $first = '[A] ' . db_value("SELECT style_name FROM style WHERE style_id = '" . (int) $activated_style_id_for_device_type . "'");
        } else {
            $first = '[A] ' . lang('Default') . $default_label();
        }

        $options .= '<option value="">' . h($first) . '</option>';

        $marked_style_id = $preview_theme_id
            ? db_value(
                "SELECT style_id
                FROM preview_styles
                WHERE
                    (page_id = '" . escape($page_id) . "')
                    AND (theme_id = '" . escape($preview_theme_id) . "')
                    AND (device_type = '" . escape($device_type) . "')")
            : '';
        $mark = '[P] ';
    }

    foreach ($styles as $style) {

        // What the operator last picked wins; with nothing picked, the marked
        // style is the one already in force.
        $selected = ($session_style !== NULL)
            ? ($style['id'] == $session_style)
            : ($style['id'] == $marked_style_id);

        $options .=
            '<option value="' . (int) $style['id'] . '"' . ($selected ? ' selected="selected"' : '') . '>'
            . (($style['id'] == $marked_style_id) ? $mark : '') . h($style['name']) . '</option>';
    }

    $style_url = $software . 'preview_style.php?mode=preview&page_id=' . $page_id . '&send_to=' . $send_to . $token . '&style_id=';

    $set_title = (($preview_theme_id == $activated_theme_id) || ($preview_theme_id == ''))
        ? lang('Set Activated Page Style')
        : lang('Set Preview Page Style');

    // ---- themes ----------------------------------------------------------

    $themes = db_items(
        "SELECT id, name, activated_" . $device_type . "_theme AS activated
        FROM files
        WHERE (type = 'css') AND (design = '1') AND (theme = '1')
        ORDER BY name ASC");

    $theme_options = '<option value="">-' . h(lang('None')) . '-</option>';

    foreach ($themes as $theme) {
        $theme_options .=
            '<option value="' . (int) $theme['id'] . '"' . (($theme['id'] == $preview_theme_id) ? ' selected="selected"' : '') . '>'
            . (($theme['activated'] == 1) ? '[A] ' : '') . h($theme['name']) . '</option>';
    }

    $theme_url = $software . 'preview_theme.php?mode=preview&send_to=' . $send_to . $token . '&id=';

    // Only a designer edits a theme, and only a real one - the "none" theme
    // has no file behind it. A system theme opens the theme designer, anything
    // else its stylesheet.
    $edit_theme = '';

    if ($preview_theme_id && ($user['role'] < 2)) {

        $is_system_theme = ((int) db_value(
            "SELECT COUNT(id) FROM system_theme_css_rules WHERE file_id = '" . escape($preview_theme_id) . "' LIMIT 1")) > 0;

        $edit_theme_url = $is_system_theme
            ? ($software . 'theme_designer.php?id=' . urlencode($preview_theme_id) . '&send_to=' . $send_to
                . '&clear_theme_designer_session=true&page_to_preview_id=' . $page_id)
            : ($software . 'edit_theme_css.php?id=' . urlencode($preview_theme_id) . '&send_to=' . $send_to);

        $edit_theme = '<a class="pg_pi_btn pg_pi_btn_narrow" target="_parent" href="' . h($edit_theme_url) . '" title="' . h(lang('Edit Theme')) . '">&#9998;</a>';
    }

    $html =
        '<div class="pg_seo_tb_head">' . h(lang('Preview Page Styles & Themes')) . '</div>
        <div class="pg_pi_pick">
            <select class="pg_pi_select" id="pg_preview_style_id" aria-label="' . h(lang('Style')) . '"
                onchange="parent.location.href=' . h(json_encode($style_url, JSON_UNESCAPED_SLASHES)) . '+encodeURIComponent(this.value);">' . $options . '</select>
            <a class="pg_pi_btn pg_pi_btn_narrow" target="_parent" href="' . h($software . 'preview_style.php?mode=set&page_id=' . $page_id . '&send_to=' . $send_to . $token) . '"
                title="' . h($set_title) . '">&#10003;</a>
        </div>
        <div class="pg_pi_pick">
            <select class="pg_pi_select" id="pg_preview_theme_id" aria-label="' . h(lang('Theme')) . '"
                onchange="parent.location.href=' . h(json_encode($theme_url, JSON_UNESCAPED_SLASHES)) . '+encodeURIComponent(this.value);">' . $theme_options . '</select>
            ' . $edit_theme . '
        </div>
        <div class="pg_pi_actions">
            <a class="pg_pi_btn" target="_parent" href="' . h($software . 'preview_theme.php?mode=cancel&send_to=' . $send_to . $token) . '">'
                . h(lang('Close Preview')) . '</a>
        </div>';

    // A <select> is the element a site theme is most certain to have dressed
    // up - height, uppercase, letter spacing, its own arrow. The panel id puts
    // these rules above nearly all of that, and every property a theme
    // commonly sets is named rather than left to inherit: an unset one is the
    // theme's, and the first draft came out as two 60px tall boxes of
    // SHOUTING CAPITALS.
    $css = '
            .pg_pi_pick{ display: flex; align-items: stretch; gap: 6px; margin-top: 6px; }
            #software_seo_panel .pg_pi_select{
                flex: 1 1 auto;
                min-width: 0;
                width: auto;
                height: auto;
                min-height: 0;
                max-width: none;
                margin: 0;
                border: 1px solid var(--pg-sp-border);
                border-radius: 3px;
                background: var(--pg-sp-bg);
                color: var(--pg-sp-fg);
                padding: 4px 6px;
                font: inherit;
                font-size: 11px;
                line-height: 1.35;
                text-transform: none;
                letter-spacing: normal;
                box-shadow: none;
            }
            #software_seo_panel .pg_pi_btn_narrow{ flex: 0 0 auto; padding: 5px 9px; font-size: 13px; }
    ';

    return array('html' => $html, 'css' => $css);
}

/**
 * Page name, page facts and page actions, rendered for the panel on the front end.
 *
 * Self-contained on purpose, the same way pg_seo_page_toolbar() is: its own
 * class names, its own colours, inline SVG instead of an icon font. The markup
 * around it was written by whoever built the site and nothing about it can be
 * assumed.
 *
 * Returns the block and the rules it needs, so the caller can drop the CSS
 * into the <style> the toolbar already emits.
 *
 * @return array html, css
 */
function pg_page_info_block($page_id, $style_id, $user, $send_to = null)
{
    $row = get_toolbar_page_row($page_id);

    if (!is_array($row)) {
        return array('html' => '', 'css' => '');
    }

    $facts = pg_page_facts($page_id, $style_id, $user, $send_to);
    $groups = pg_page_actions($page_id, $style_id, $user, $send_to);
    $preview = pg_page_preview_tools($page_id, $style_id, $user, $send_to);

    // A house for the home page and a window for every other one, matching the
    // icons the toolbar puts next to the same name.
    if ($row['page_home'] == 'yes') {
        $icon = '<svg width="13" height="13" viewBox="0 0 16 16" fill="#198754" aria-hidden="true">
                    <path d="M8.354 1.146a.5.5 0 0 0-.708 0l-6 6A.5.5 0 0 0 1.5 7.5v7a.5.5 0 0 0 .5.5h4.5a.5.5 0 0 0 .5-.5v-4h2v4a.5.5 0 0 0 .5.5H14a.5.5 0 0 0 .5-.5v-7a.5.5 0 0 0-.146-.354L13 5.793V2.5a.5.5 0 0 0-.5-.5h-1a.5.5 0 0 0-.5.5v1.293z"/>
                </svg>';
        $icon_title = lang('Homepage');
    } else {
        $icon = '<svg width="13" height="13" viewBox="0 0 16 16" fill="#1976d2" aria-hidden="true">
                    <path d="M2.5 4a.5.5 0 1 0 0-1 .5.5 0 0 0 0 1M4 3.5a.5.5 0 1 1-1 0 .5.5 0 0 1 1 0m1 .5a.5.5 0 1 0 0-1 .5.5 0 0 0 0 1"/>
                    <path d="M2 2a2 2 0 0 0-2 2v8a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V4a2 2 0 0 0-2-2zm12 1a1 1 0 0 1 1 1v1H1V4a1 1 0 0 1 1-1zM1 12V6h14v6a1 1 0 0 1-1 1H2a1 1 0 0 1-1-1"/>
                </svg>';
        $icon_title = lang('Page');
    }

    // Archived was a suffix on the toolbar heading. It is the one entry here
    // that changes what the page does rather than describing it, so it stays
    // beside the name instead of dropping into the list below.
    $archived = ($row['folder_archived'] == '1')
        ? '<span class="pg_pi_tag">' . h(lang('ARCHIVED')) . '</span>'
        : '';

    $html =
        '<div class="pg_pi_name" title="' . h($icon_title) . '">'
            . $icon
            . '<span class="pg_pi_nametext">' . h($row['page_name']) . '</span>'
            . $archived .
        '</div>'
        . $preview['html'];

    if ($facts) {
        $html .= '<div class="pg_seo_tb_head">' . h(lang('Page Information')) . '</div>';

        foreach ($facts as $fact) {
            $text = isset($fact['text']) ? $fact['text'] : '';

            $value = (isset($fact['url']) && ($fact['url'] !== ''))
                ? '<a target="_parent" href="' . h($fact['url']) . '">' . h($text) . '</a>'
                : h($text);

            $label = (isset($fact['label']) && ($fact['label'] !== ''))
                ? '<span class="pg_pi_label">' . h($fact['label']) . '</span>'
                : '';

            $html .=
                '<div class="pg_pi_row">'
                    . $label
                    . '<span class="pg_pi_value">' . $value . '</span>' .
                '</div>';
        }
    }

    // Written out rather than drawn: these were icons in the toolbar because
    // a picture was all that fit there, and an icon is a word nobody taught
    // the operator. They sit under the facts, beside what they act on, not in
    // the row of SEO buttons at the foot of the panel.
    //
    // target="_parent" on every address this block writes, here and in the
    // facts above. The panel is drawn twice: on the page, where the parent of
    // the browsing context is the context itself and the attribute means
    // nothing, and inside the toolbar frame, where without it a whole admin
    // screen loads into a fifty-pixel strip - carrying its own toolbar in.
    // The header and the menu drawer have always answered this the same way.
    foreach ($groups as $group) {

        if ($group['label'] !== '') {
            $html .= '<div class="pg_seo_tb_head">' . h($group['label']) . '</div>';
        }

        $html .= '<div class="pg_pi_actions">';

        foreach ($group['actions'] as $action) {
            $class = 'pg_pi_btn' . (isset($action['class']) ? ' ' . $action['class'] : '');
            $html .= '<a class="' . h($class) . '" target="_parent" href="' . h($action['url']) . '">' . h($action['label']) . '</a>';
        }

        $html .= '</div>';
    }

    $css = '
            .pg_pi_name{
                display: flex;
                align-items: center;
                gap: 6px;
                padding: 2px 0 4px;
                font-size: 13px;
                font-weight: 600;
                color: var(--pg-sp-fg);
            }
            .pg_pi_name svg{ flex: 0 0 auto; }
            .pg_pi_nametext{ min-width: 0; overflow-wrap: anywhere; }
            .pg_pi_tag{
                flex: 0 0 auto;
                background: var(--pg-sp-faint);
                color: var(--pg-sp-bg);
                border-radius: 2px;
                padding: 1px 4px;
                font-size: 9px;
                font-weight: 700;
                letter-spacing: .04em;
            }
            .pg_pi_row{ display: flex; align-items: flex-start; gap: 6px; padding: 3px 0; border-bottom: 1px solid var(--pg-sp-line-soft); }
            .pg_pi_label{ flex: 0 0 96px; color: var(--pg-sp-muted); }
            .pg_pi_value{ flex: 1 1 auto; min-width: 0; overflow-wrap: anywhere; }
            .pg_pi_actions{ display: flex; flex-wrap: wrap; gap: 6px; margin-top: 8px; }
            /* Deliberately the same shape as the SEO buttons at the foot of
               the panel (pg_seo_tb_btn, seo.php): one panel, one kind of
               button. Its own class because the panel binds a fetch handler
               to that one. */
            #software_seo_panel .pg_pi_btn{
                flex: 1 1 auto;
                display: flex;
                align-items: center;
                justify-content: center;
                border: 1px solid var(--pg-sp-border);
                background: var(--pg-sp-bg);
                color: var(--pg-sp-fg);
                border-radius: 3px;
                padding: 5px 8px;
                font: inherit;
                font-size: 11px;
                line-height: 1.35;
                text-align: center;
                text-transform: none;
                letter-spacing: normal;
                text-decoration: none;
                cursor: pointer;
            }
            #software_seo_panel .pg_pi_btn:hover{ background: var(--pg-sp-hover); color: var(--pg-sp-fg); text-decoration: none; }
            /* The one element a site theme is certain to have an opinion
               about, so the link rules carry the panel id to outrank it. */
            #software_seo_panel .pg_pi_row a{ color: var(--pg-sp-link); text-decoration: none; }
            #software_seo_panel .pg_pi_row a:hover{ text-decoration: underline; }
    ' . $preview['css'];

    return array('html' => $html, 'css' => $css);
}

/**
 * The same page actions as one row of icons, for the toolbar's compact view.
 *
 * The panel writes every action out because an icon is a word nobody taught
 * the operator. That is right the first week and wrong the hundredth: once the
 * hand knows where each one is, a column three hundred pixels wide is spent on
 * something already known by heart. So the toolbar offers both and remembers
 * which was picked - the panel for reading, this for reaching.
 *
 * Only the actions. The facts, the SEO checklist and the score breakdown have
 * no short form and stay in the panel; the score itself rides on the button
 * that switches back to it, which is the one thing here that has to keep
 * saying something rather than doing something.
 *
 * Empty while a theme preview is running: those controls are two pick lists,
 * not buttons, and they are the reason the operator opened this at all. The
 * toolbar falls back to the panel for the length of the preview.
 *
 * @param int    $page_id
 * @param int    $style_id style the page is being rendered with
 * @param array  $user     row from validate_user() / initialize_user()
 * @param string $send_to  address the screens should return to
 * @param array  $score    value (int or null) and hex, from pg_seo_page_toolbar()
 * @return array html, css
 */
function pg_page_action_bar($page_id, $style_id, $user, $send_to = null, $score = null)
{
    $none = array('html' => '', 'css' => '');
    $groups = pg_page_actions($page_id, $style_id, $user, $send_to);

    if (!$groups) {
        return $none;
    }

    $preview = pg_page_preview_tools($page_id, $style_id, $user, $send_to);

    if ($preview['html'] !== '') {
        return $none;
    }

    $buttons = '';
    $first = true;

    foreach ($groups as $group) {

        // The group headings are what the panel uses to say these act on the
        // style rather than on the page. Here there is no room for a word, so
        // the same break is drawn as a rule between the clusters.
        if (!$first) {
            $buttons .= '<span class="pg-pbar-sep" aria-hidden="true"></span>';
        }

        $first = false;

        foreach ($group['actions'] as $action) {

            $icon = isset($action['icon']) ? $action['icon'] : 'dot';
            // no-popover: the panel screens turn every [title] into a
            // Bootstrap popover, which on a 30px icon button is a card where
            // a tooltip was meant. The name still has to be there for the
            // browser's own tip and for anyone not looking at the screen.
            $class = 'pg-pbar-btn no-popover' . (isset($action['class']) ? ' ' . $action['class'] : '');

            // The label is the accessible name as well as the tooltip: an icon
            // with neither is a button that cannot be described to anyone who
            // is not looking at it.
            $buttons .=
                '<a class="' . h($class) . '" target="_parent" href="' . h($action['url']) . '"
                    title="' . h($action['label']) . '" aria-label="' . h($action['label']) . '">
                    <i class="bi bi-' . h($icon) . '" aria-hidden="true"></i>
                </a>';
        }
    }

    $value = (is_array($score) && isset($score['value'])) ? $score['value'] : NULL;
    $hex = (is_array($score) && !empty($score['hex'])) ? $score['hex'] : '#adb5bd';

    $score_title = lang('SEO Score') . (($value === NULL) ? '' : ': ' . (int) $value . '/100');

    $buttons .=
        '<span class="pg-pbar-sep" aria-hidden="true"></span>
        <button type="button" class="pg-pbar-btn pg-pbar-score no-popover" data-pg-page-view="panel"
            title="' . h($score_title . ' — ' . lang('Page Panel')) . '" aria-label="' . h(lang('Page Panel')) . '">
            <span style="color:' . h($hex) . '">' . (($value === NULL) ? '&#8211;' : (int) $value) . '</span>
            <i class="bi bi-chevron-down" aria-hidden="true"></i>
        </button>';

    $html = '<div id="pg_page_bar"><div class="pg-pbar-box">' . $buttons . '</div></div>';

    // Bootstrap's own tokens rather than the panel's --pg-sp-* set. The panel
    // carries that set because it is also drawn over the visitor's page, where
    // there is no Bootstrap; this strip is only ever drawn in the toolbar, and
    // those are the variables the bar above it is painted with - so the two
    // read as one surface without a second mapping in between.
    $css = '
            #pg_page_bar{
                position: fixed;
                top: var(--pg-navbar-h, 50px);
                right: 12px;
                margin-top: -1px;
                z-index: 0;
                display: flex;
                justify-content: flex-end;
                max-width: calc(100vw - 24px);
                font-family: system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;
            }
            .pg-pbar-box{
                display: flex;
                align-items: center;
                gap: 2px;
                padding: 3px 5px;
                background: rgb(var(--bs-navbar-bg-rgb));
                border: 1px solid var(--bs-border-color);
                border-top: 0;
                border-bottom-left-radius: 6px;
                border-bottom-right-radius: 6px;
                overflow-x: auto;
            }
            #pg_page_bar .pg-pbar-btn{
                flex: 0 0 auto;
                display: flex;
                align-items: center;
                justify-content: center;
                width: 30px;
                height: 28px;
                border: 0;
                border-radius: 4px;
                background: transparent;
                color: var(--bs-body-color);
                padding: 0;
                font: inherit;
                font-size: 15px;
                line-height: 1;
                text-decoration: none;
                cursor: pointer;
            }
            #pg_page_bar .pg-pbar-btn:hover,
            #pg_page_bar .pg-pbar-btn:focus-visible{
                background: var(--bs-tertiary-bg);
                color: var(--bs-body-color);
                text-decoration: none;
            }
            #pg_page_bar .pg-pbar-btn i{ line-height: 1; }
            #pg_page_bar .pg-pbar-score{ width: auto; min-width: 30px; gap: 2px; padding: 0 5px 0 6px; font-size: 12px; font-weight: 700; }
            #pg_page_bar .pg-pbar-score i{ font-size: 10px; color: var(--bs-secondary-color); }
            .pg-pbar-sep{
                flex: 0 0 auto;
                width: 1px;
                align-self: stretch;
                margin: 2px 3px;
                background: var(--bs-border-color-translucent);
            }
    ';

    return array('html' => $html, 'css' => $css);
}

/**
 * The toolbar's own controls: one, and it opens the page panel.
 *
 * Everything this used to draw stands in that panel now, written out instead
 * of drawn as icons: what the page is (pg_page_facts), what can be done to it
 * and to the style it wears (pg_page_actions), and the style and theme pick
 * lists while a preview runs (pg_page_preview_tools).
 *
 * The panel is on the page, behind the SEO ring - but the ring is covered
 * while the bar is open, and a hand that has spent years reaching for this bar
 * keeps arriving here and finding nothing. So the same panel is drawn inside
 * the bar as well (toolbar.php), down its right-hand side, with no switch: the
 * bar is only on screen when somebody asked for it, and that is what they came
 * for.
 *
 * The bar itself keeps what belongs to the session rather than to this page:
 * the logo, the page name, search, notifications, the account menu. The
 * function stays because pg_page_shell() asks every screen for its navbar
 * controls and this one's answer is "none".
 */
function output_toolbar($toolbar)
{
    return '';
}

/**
 * SQL expression that folds every name for the home page into one group.
 *
 * The home page is not a fixed page. Any page can carry page_home = 'yes',
 * more than one can carry it at a time, and get_page.php picks between them
 * with ORDER BY RAND() — so the site root can serve a different page on each
 * request, by design.
 *
 * Visitor tracking stores whatever the visitor asked for, so the same page is
 * recorded under two different names: an empty landing_page_name when someone
 * opened the site root, and its own name when someone opened it directly or
 * followed a link to it. Reports then showed the same page twice, splitting
 * its traffic and pushing it down the ranking.
 *
 * Read from the database on every call rather than cached in config, because
 * the operator can change which page is home at any time and a stale answer
 * would merge the wrong rows — a subtler wrong than not merging at all.
 *
 * Returns an expression to GROUP BY, mapping every home alias to ''.
 */
function pg_home_page_group_expression($column = 'landing_page_name')
{
    static $expression = null;

    if ($expression !== null) {
        return str_replace('landing_page_name', $column, $expression);
    }

    $names = array();
    $result = @mysqli_query(db::$con, "SELECT page_name FROM page WHERE page_home = 'yes'");

    if ($result) {
        while ($row = @mysqli_fetch_assoc($result)) {
            if (trim($row['page_name']) !== '') {
                $names[] = "'" . escape(trim($row['page_name'])) . "'";
            }
        }
    }

    // Always-present aliases for the root, whatever the home page happens to
    // be. 'example.com/' is a legacy value some installs recorded.
    $aliases = array("''", "'/'", "'index.php'", "'example.com/'");
    $all = array_merge($aliases, $names);

    $expression = "CASE WHEN landing_page_name IN (" . implode(', ', $all) . ") THEN '' ELSE landing_page_name END";

    return str_replace('landing_page_name', $column, $expression);
}

/**
 * Resolve a site URL to a file on disk, or '' if it is not one of ours.
 *
 * Two roots, because a Pinegrap URL can mean two different places:
 * uploaded files are served from the site root but physically live in
 * data/files, while software assets sit where their URL says they do.
 */
function pg_asset_path($url)
{
    // Anything with a host is somebody else's server.
    if (preg_match('#^(?:[a-z][a-z0-9+.-]*:)?//#i', $url) || strpos($url, 'data:') === 0) {
        return '';
    }

    $path = $url;

    // Strip the install path so "/sub/styles.css" becomes "styles.css".
    if (defined('PATH') && PATH !== '' && PATH !== '/' && strpos($path, PATH) === 0) {
        $path = substr($path, strlen(PATH));
    }

    $path = ltrim($path, '/');

    if ($path === '' || strpos($path, '..') !== false) {
        return '';
    }

    $path = rawurldecode($path);

    // Uploaded files first: that is where a designer's own assets live.
    $candidate = FILE_DIRECTORY_PATH . '/' . $path;

    if (is_file($candidate)) {
        return $candidate;
    }

    // Then the web root, for software assets and anything dropped in by hand.
    $candidate = dirname(PG_FUNCTIONS_DIR) . '/' . $path;

    if (is_file($candidate)) {
        return $candidate;
    }

    return '';
}

/**
 * Append ?v=<last modified> to local asset URLs, at output time.
 *
 * The version never enters the stored markup. A designer writes
 * src="{path}avatar-1.png" and that is what stays in the database; the query
 * string is added to the response on its way out, so editing the file changes
 * the URL the browser sees without anyone editing the page.
 *
 * This is the only thing that reliably invalidates a CDN. A browser can be
 * told to reload, but Cloudflare caches .css, .js and images by default and
 * neither Ctrl+F5 nor DevTools "disable cache" reaches it — those bypass the
 * browser and the request still lands on the edge, which answers from its own
 * copy. Changing the URL changes the cache key, so there is nothing to purge.
 *
 * Matching is by FILE EXTENSION, not by tag. Rewriting every href would
 * rewrite page links too, and appending a version to /urun/xyz would be a
 * visible bug on every link in the site. An extension list cannot do that:
 * ordinary page URLs do not end in .css or .png.
 *
 * URLs that already carry a query string are left alone — an explicit ?v= is
 * someone's deliberate choice and overriding it would be rude.
 */
function pg_version_assets($html)
{
    if (!is_string($html) || $html === '') {
        return $html;
    }

    return preg_replace_callback(
        '#\b(src|href)(\s*=\s*)(["\'])([^"\'>]+?\.(?:css|js|mjs|png|jpe?g|gif|svg|webp|avif|ico|bmp|woff2?|ttf|otf|eot|mp4|webm|ogg|mp3))(\3)#i',
        'pg_version_assets_callback',
        $html
    );
}

function pg_version_assets_callback($match)
{
    static $versions = array();

    $url = $match[4];

    // Already versioned, or carrying its own parameters.
    if (strpos($url, '?') !== false) {
        return $match[0];
    }

    if (!array_key_exists($url, $versions)) {
        $file = pg_asset_path($url);
        $versions[$url] = $file !== '' ? @filemtime($file) : false;
    }

    if (!$versions[$url]) {
        return $match[0];
    }

    return $match[1] . $match[2] . $match[3] . $url . '?v=' . $versions[$url] . $match[5];
}
