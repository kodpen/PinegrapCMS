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
$user = validate_user();



// Get page info via the per-request cached helper. output_header() will
// trigger output_toolbar() further down, which reuses the same cached row
// instead of issuing a duplicate page+folder JOIN query.
$row = get_toolbar_page_row($_GET['page_id']);

$page_id = $row['page_id'];
$page_name = $row['page_name'];
$page_folder = $row['page_folder'];
$page_home = $row['page_home'];
$page_search = $row['page_search'];
$page_search_keywords = $row['page_search_keywords'];
$page_style = $row['page_style'];
$mobile_style_id = $row['mobile_style_id'];
$page_type = $row['page_type'];
$comments = $row['comments'];
$comments_automatic_publish = $row['comments_automatic_publish'];
$comments_administrator_email_to_email_address = $row['comments_administrator_email_to_email_address'];
$seo_score = $row['seo_score'];
$sitemap = $row['sitemap'];
$folder_archived = $row['folder_archived'];

if ($page_home == 'yes') {
    $page_name = '<span class="bi bi-house text-success ms-2" title="' . lang('Homepage') . '"> ' . $page_name . '</span>';
} else {
    $page_name = '<span class="bi bi-window pages-color ms-2" title="' . lang('Page') . '"> ' . $page_name . '</span>';
}

print pg_page_shell(
    array(
        'toolbar' => true,
        'title' => lang('Toolbar'),
        'extra classes' => 'page toolbar',
        'icon' => 'page',
        'heading' => $page_name,
        'auto_main' => false,
    )
) . '
        <script>
            var loaded = false;
            $(window).on("load", function() {
                loaded = true;
            });

            jQuery("body.toolbar main").dblclick(function(event){ 
                parent.document.getElementById(\'software_fullscreen_toggle\').click();
            }); 
        </script>';
?>