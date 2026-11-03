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

validate_token_field();

// Each is one value; one sent as an array (?page_id[]=x) counts as not given:
// it went into the session key and the queries as "Array".
pg_request_single_values(array('mode', 'page_id', 'style_id'));

// The page as a number: the page's panel reads the picked style back under
// the key built from its integer id.
$preview_page_id = (int) ($_GET['page_id'] ?? 0);

// The style picked for this page, in the previewed theme, on this device.
// Without a theme preview open there is nothing to pick for.
$preview_style_key = (isset($_SESSION['software']['preview_theme_id']) && is_scalar($_SESSION['software']['preview_theme_id']) && ($preview_page_id > 0))
    ? 'theme_' . $_SESSION['software']['preview_theme_id'] . '_page_' . $preview_page_id . '_' . ($_SESSION['software']['device_type'] ?? '')
    : '';

// Do different things depending on mode.
switch (($_GET['mode'] ?? '')) {
    // If user has selected a style from the pick list, then update current style.
    case 'preview':
        if ($preview_style_key !== '') {
            $_SESSION['software']['preview_style'][$preview_style_key] = (int) ($_GET['style_id'] ?? 0);
        }

        break;
    
    // If user clicked the set button, then save style in database.
    case 'set':
        // If the page id, theme id, and device type are known,
        // and the user has selected an option from the pick list,
        // then continue to update style.
        if (
            ($preview_style_key !== '')
            && !empty($_SESSION['software']['device_type'])
            && (isset($_SESSION['software']['preview_style'][$preview_style_key]) == true)
        ) {
            // If the user is currently previewing the activated theme or the "none" theme,
            // then update the activated style for the page.
            if (
                ($_SESSION['software']['preview_theme_id'] == db_value("SELECT id FROM files WHERE activated_" . sql_device_type($_SESSION['software']['device_type'] ?? '') . "_theme = '1'"))
                || ($_SESSION['software']['preview_theme_id'] == '')
            ) {

                if ($_SESSION['software']['device_type'] == 'desktop') {
                    $sql_style_id_column = "page_style";
                } else {
                    $sql_style_id_column = "mobile_style_id";
                }

                db(
                    "UPDATE page
                    SET
                        $sql_style_id_column = '" . escape($_SESSION['software']['preview_style'][$preview_style_key]) . "',
                        page_user = '" . USER_ID . "',
                        page_timestamp = UNIX_TIMESTAMP()
                    WHERE page_id = '" . $preview_page_id . "'");

                // If a style was selected (not default/inherit), then get style name.
                if ($_SESSION['software']['preview_style'][$preview_style_key]) {
                    $log_style_name = db_value("SELECT style_name FROM style WHERE style_id = '" . escape($_SESSION['software']['preview_style'][$preview_style_key]) . "'");

                } else {
                    $log_style_name = 'Default (inherit)';
                }

                log_activity(lang(array('string' => 'activated {var:1} page style ({var:2}) was set for page ({var:3})', 'vars' => array($_SESSION['software']['device_type'], $log_style_name, get_page_name($preview_page_id)))), $_SESSION['sessionusername']);

            // Otherwise the user is not previewing the activated theme or "none" theme,
            // so update the preview style for the page.
            } else {

                // Delete existing record.
                db(
                    "DELETE FROM preview_styles
                    WHERE
                        (page_id = '" . $preview_page_id . "')
                        AND (theme_id = '" . escape($_SESSION['software']['preview_theme_id']) . "')
                        AND (device_type = '" . escape($_SESSION['software']['device_type']) . "')");

                // If the user has selected a style, then add record for preview style.
                if ($_SESSION['software']['preview_style'][$preview_style_key]) {
                    db(
                        "INSERT INTO preview_styles (
                            page_id,
                            theme_id,
                            style_id,
                            device_type)
                        VALUES (
                            '" . $preview_page_id . "',
                            '" . escape($_SESSION['software']['preview_theme_id']) . "',
                            '" . escape($_SESSION['software']['preview_style'][$preview_style_key]) . "',
                            '" . escape($_SESSION['software']['device_type']) . "')");
                }

                // If a style was selected (i.e not the activated style), then get style name.
                if ($_SESSION['software']['preview_style'][$preview_style_key]) {
                    $log_style_name = db_value("SELECT style_name FROM style WHERE style_id = '" . escape($_SESSION['software']['preview_style'][$preview_style_key]) . "'");

                } else {
                    $log_style_name = '[Activated]';
                }

                $log_theme_name = db_value("SELECT name FROM files WHERE id = '" . escape($_SESSION['software']['preview_theme_id']) . "'");

                log_activity(lang(array('string' => 'preview {var:1} page style ({var:2}) was set for page ({var:3}) and theme ({var:4})', 'vars' => array($_SESSION['software']['device_type'], $log_style_name, get_page_name($preview_page_id), $log_theme_name))), $_SESSION['sessionusername']);
            }
        }
        
        break;
}

go(($_GET['send_to'] ?? ''));
?>