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
 *              2016–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

include('init.php');

$custom_form_fields = array();

// Get folder id for custom form page in order to verify that user has edit access to custom form.
$folder_id = db_value("SELECT page_folder FROM page WHERE page_id = '" . escape($_GET['page_id']) . "'");

// If custom form page exists and user has edit access to custom form, then continue to get fields.
if (($folder_id) && (check_edit_access($folder_id) == true)) {
    $custom_form_fields = db_items(
        "SELECT
            id,
            name,
            type
        FROM form_fields
        WHERE
            (page_id = '" . escape($_GET['page_id']) . "')
            AND (type != 'information')
            AND (type != 'file upload')
            AND (name != '')
        ORDER BY sort_order ASC");
}

echo encode_json($custom_form_fields);
?>