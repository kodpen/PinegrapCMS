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

// get additional robots.txt content
$query = "SELECT additional_robots_content FROM config";
$result = mysqli_query(db::$con, $query) or output_error('Query failed.');
$row = mysqli_fetch_assoc($result);
$additional_robots_content = $row['additional_robots_content'];

// Pages the operator has taken out of the search engines, under every
// language directory as well.
$disallow_rules = pg_build_robots_disallow_rules();

if (function_exists('pg_tr_robots_rules')) {
    $disallow_rules = pg_tr_robots_rules($disallow_rules);
}

// The AI section (Site Settings, SEO, AI Crawlers; includes/fn/ai_crawlers.php)
// and the file around it. With the section off, or before its 2026.11.0 step,
// the file is exactly what it was without it.
$content = pg_robots_txt_content(
    $additional_robots_content,
    $disallow_rules,
    URL_SCHEME . HOSTNAME_SETTING . PATH . 'sitemap.xml',
    pg_ai_crawler_settings());

header('Content-type: text/plain');
print $content;
?>