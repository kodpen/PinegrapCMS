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

// get additional robots.txt content
$query = "SELECT additional_robots_content FROM config";
$result = mysqli_query(db::$con, $query) or output_error('Query failed.');
$row = mysqli_fetch_assoc($result);
$additional_robots_content = $row['additional_robots_content'];

// Pages the operator has taken out of the search engines.
$disallow_rules = pg_build_robots_disallow_rules();

$own_group = '';

// A site with no such page gets the file exactly as it was built before this
// existed, down to the byte.
if (count($disallow_rules) > 0) {
    // A rule belongs to the "User-agent" line above it, so where these go is
    // the whole question. When the operator's own text already declares a
    // catch-all group, they go inside it and the file keeps one group.
    $merged_robots_content = pg_merge_robots_rules_into_catch_all($additional_robots_content, $disallow_rules);

    if ($merged_robots_content !== FALSE) {
        $additional_robots_content = $merged_robots_content;

    // Otherwise open a group of our own, before the operator's text rather than
    // after it: appended, the rules would attach to whatever group that text
    // happens to end with, and a trailing "User-agent: Googlebot" block would
    // silently narrow every one of them to Googlebot alone.
    } else {
        $own_group =
            'User-agent: *' . "\r\n" .
            implode("\r\n", $disallow_rules) . "\r\n" .
            "\r\n";
    }
}

// do not translate Sitemap text. its functional string.
$content =
    $own_group .
    'Sitemap: ' . URL_SCHEME . HOSTNAME_SETTING . PATH . 'sitemap.xml';

// if there is additional robots content then add it to the content
if ($additional_robots_content != '') {
    $content .=
        "\r\n" .
        "\r\n" .
        $additional_robots_content;
}

header('Content-type: text/plain');
print $content;
?>