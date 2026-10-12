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

// The stylesheet the sitemap names for browsers (pg_sitemap_add_stylesheet()):
// sitemap.xml?stylesheet=css, so it is served wherever the sitemap is.
if (isset($_GET['stylesheet']) && ($_GET['stylesheet'] === 'css')) {
    header('Content-Type: text/css; charset=utf-8');
    header('Cache-Control: public, max-age=86400');
    header_remove('Pragma');
    header_remove('Expires');

    echo pg_sitemap_stylesheet_css(array(
        'title'    => lang('XML Sitemap'),
        'note'     => lang('Search engines read this file as it is; the look is only for reading it in a browser.'),
        'count'    => lang('Addresses:'),
        'lastmod'  => lang('Last modified:'),
        'priority' => lang('Priority:'),
    ));
    exit();
}

require_once(dirname(__FILE__) . '/get_sitemap_info.php');

$sitemap_info = get_sitemap_info();

header('Content-type: text/xml');
echo pg_sitemap_add_stylesheet($sitemap_info['content'], PATH . 'sitemap.xml?stylesheet=css&v=' . rawurlencode(VERSION));