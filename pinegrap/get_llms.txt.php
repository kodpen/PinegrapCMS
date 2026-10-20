<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * /llms.txt: a short guide to the site for AI tools (llmstxt.org), built from
 * the site's name and description, the operator's own text and the pages the
 * sitemap lists (includes/fn/ai_crawlers.php). router.php sends the address
 * here only while the site publishes it (Site Settings, SEO, AI Crawlers).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

include('init.php');

$llms_settings = pg_ai_crawler_settings();

// Asked for at this script's own address while the site does not publish the
// file: there is nothing here.
if (!$llms_settings['llms']) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    exit();
}

// The name AI tools know the site by: the organization, else the browser
// title, else the host name.
$llms_site_name = trim((string) ORGANIZATION_NAME);

if ($llms_site_name === '') {
    $llms_site_name = trim((string) TITLE);
}

if ($llms_site_name === '') {
    $llms_site_name = HOSTNAME_SETTING;
}

$content = pg_llms_txt_content(
    array(
        'name'        => $llms_site_name,
        'description' => META_DESCRIPTION,
    ),
    pg_llms_txt_pages(),
    $llms_settings['llms_text'],
    URL_SCHEME . HOSTNAME_SETTING . PATH . 'sitemap.xml',
    lang('Pages'));

header('Content-Type: text/plain; charset=utf-8');

// A guide for AI tools, not a page of the site to be listed in search results.
header('X-Robots-Tag: noindex');

echo $content;
