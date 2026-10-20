<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * What the site tells AI crawlers: the AI section of robots.txt (a
 * Content-Signal line and a Disallow group per crawler that is not allowed)
 * and llms.txt, the short guide to the site that AI tools read.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// A module of functions.php. Both files are written by router.php's
// dispatchers (get_robots.txt.php, get_llms.txt.php) from the settings of the
// AI Crawlers card (Site Settings, SEO; 2026.11.0, 11.0.21).
//
// One question has one answer here. Training crawlers follow the card's own
// "Allow AI training" switch; assistants that fetch a page for a person
// (ChatGPT-User and the like) and AI search indexers follow the two firewall
// switches that let them through (waf_ai_bots()), so robots.txt never
// invites a crawler the firewall turns away, or tells one to leave that the
// firewall lets in. An allowed crawler gets no group of its own: it reads the
// "User-agent: *" group like every other crawler, so the pages taken out of
// the search engines stay out for it too.
if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

/**
 * The AI crawlers robots.txt speaks to, by what they do, as robots.txt names
 * them.
 *
 * train:  crawlers whose pages go into training AI models, and the two
 *         tokens Google and Apple read for that use of what their search
 *         crawlers fetch (Google-Extended, Applebot-Extended), which crawl
 *         nothing themselves. The firewall turns the real crawlers among them
 *         away as bad bots (waf_bad_bots()).
 * agent:  assistants that fetch one page when a person asks about it; the
 *         firewall switch waf_allow_ai_fetchers.
 * search: AI search indexers; the firewall switch waf_allow_ai_search.
 *
 * tests/seo_ai_crawlers_test.php holds the agent and search lists to
 * waf_ai_bots() and the training crawlers to waf_bad_bots().
 *
 * @return array category => list of user-agent tokens
 */
function pg_ai_crawler_catalog()
{
    return array(
        'train'  => array('GPTBot', 'ClaudeBot', 'Google-Extended', 'Applebot-Extended', 'CCBot', 'meta-externalagent', 'Amazonbot', 'Bytespider'),
        'agent'  => array('ChatGPT-User', 'Claude-User', 'Perplexity-User'),
        'search' => array('OAI-SearchBot', 'Claude-SearchBot'),
    );
}

/**
 * The settings from a config row.
 *
 * Before the 11.0.21 step the card's columns are missing: everything of this
 * module is off then and both files are what they were. The two firewall
 * switches read as on when their columns are missing, as the firewall card
 * shows them (includes/settings/prep.php).
 *
 * @param array|null $row the config row
 * @return array ready, rules, training, agents, search, llms (bool), llms_text (string)
 */
function pg_ai_crawler_settings_from_row($row)
{
    $row = is_array($row) ? $row : array();
    $ready = array_key_exists('robots_ai_rules', $row);

    return array(
        'ready'     => $ready,
        'rules'     => $ready && !empty($row['robots_ai_rules']),
        'training'  => $ready && !empty($row['robots_ai_training']),
        'agents'    => array_key_exists('waf_allow_ai_fetchers', $row) ? !empty($row['waf_allow_ai_fetchers']) : true,
        'search'    => array_key_exists('waf_allow_ai_search', $row) ? !empty($row['waf_allow_ai_search']) : true,
        'llms'      => $ready && !empty($row['llms_txt']),
        'llms_text' => ($ready && isset($row['additional_llms_content'])) ? (string) $row['additional_llms_content'] : '',
    );
}

/**
 * The settings of this site. On the router's path the firewall has already
 * read the config row (waf_config()); that row is used rather than a second
 * query.
 *
 * @return array as pg_ai_crawler_settings_from_row()
 */
function pg_ai_crawler_settings()
{
    $row = function_exists('waf_config') ? waf_config() : false;

    if (!is_array($row)) {
        $row = db_item("SELECT * FROM config LIMIT 1");
    }

    return pg_ai_crawler_settings_from_row($row);
}

/**
 * The lines of a robots.txt text with their comments taken off and trimmed.
 *
 * @param string $text
 * @return array
 */
function pg_robots_record_lines($text)
{
    $lines = array();

    foreach (preg_split('/\r\n|\r|\n/', (string) $text) as $line) {
        $hash = strpos($line, '#');

        if ($hash !== false) {
            $line = substr($line, 0, $hash);
        }

        $lines[] = trim($line);
    }

    return $lines;
}

/**
 * The user agents a robots.txt text has a group for, lower-cased.
 *
 * @param string $text
 * @return array
 */
function pg_robots_named_agents($text)
{
    $agents = array();

    foreach (pg_robots_record_lines($text) as $line) {
        if (preg_match('/^user-agent\s*:\s*(\S+)/i', $line, $match)) {
            $agents[] = mb_strtolower($match[1]);
        }
    }

    return array_values(array_unique($agents));
}

/**
 * Whether a robots.txt text already says how its content may be used.
 *
 * @param string $text
 * @return bool
 */
function pg_robots_has_content_signal($text)
{
    foreach (pg_robots_record_lines($text) as $line) {
        if (preg_match('/^content-signal\s*:/i', $line)) {
            return true;
        }
    }

    return false;
}

/**
 * Whether the catch-all group of a robots.txt text closes the whole site
 * ("Disallow: /", what Block All Search Engines writes).
 *
 * A group is one or more User-agent lines and the rules under them; blank
 * lines and comments do not end it, the next User-agent line after a rule
 * does (RFC 9309).
 *
 * @param string $text
 * @return bool
 */
function pg_robots_catch_all_closed($text)
{
    $agents = array();
    $reading_agents = false;

    foreach (pg_robots_record_lines($text) as $line) {
        if ($line === '') {
            continue;
        }

        if (preg_match('/^user-agent\s*:\s*(\S+)/i', $line, $match)) {
            if (!$reading_agents) {
                $agents = array();
            }

            $agents[] = $match[1];
            $reading_agents = true;
            continue;
        }

        $reading_agents = false;

        if (in_array('*', $agents, true) && preg_match('#^disallow\s*:\s*/$#i', $line)) {
            return true;
        }
    }

    return false;
}

/**
 * The Content-Signal line (contentsignals.org): search, ai-input, ai-train.
 *
 * ai-input is what assistants and AI search do with a page, so it says yes
 * while the firewall lets either of them in; ai-train follows the card's
 * training switch. A site whose catch-all group closes everything says no to
 * all three, search included.
 *
 * @param array $settings as pg_ai_crawler_settings_from_row()
 * @param bool  $closed   pg_robots_catch_all_closed() of the operator's text
 * @return string
 */
function pg_ai_content_signal_line($settings, $closed)
{
    if ($closed) {
        return 'Content-Signal: search=no, ai-input=no, ai-train=no';
    }

    return 'Content-Signal: search=yes'
        . ', ai-input=' . ((!empty($settings['agents']) || !empty($settings['search'])) ? 'yes' : 'no')
        . ', ai-train=' . (!empty($settings['training']) ? 'yes' : 'no');
}

/**
 * The AI crawlers robots.txt tells to stay away, in catalog order. A crawler
 * the operator's own text already has a group for is left to that text: two
 * groups for one crawler are read as one (RFC 9309), and ours would undo
 * what the operator wrote for it.
 *
 * @param array  $settings      as pg_ai_crawler_settings_from_row()
 * @param string $operator_text the Additional robots.txt Content field
 * @return array user-agent tokens
 */
function pg_ai_robots_blocked_agents($settings, $operator_text)
{
    $catalog = pg_ai_crawler_catalog();
    $blocked = array();

    if (empty($settings['training'])) {
        $blocked = array_merge($blocked, $catalog['train']);
    }

    if (empty($settings['agents'])) {
        $blocked = array_merge($blocked, $catalog['agent']);
    }

    if (empty($settings['search'])) {
        $blocked = array_merge($blocked, $catalog['search']);
    }

    $named = pg_robots_named_agents($operator_text);
    $agents = array();

    foreach ($blocked as $agent) {
        if (!in_array(mb_strtolower($agent), $named, true)) {
            $agents[] = $agent;
        }
    }

    return $agents;
}

/**
 * The whole robots.txt.
 *
 * Without the AI section ($ai null, or its switch off) this is the file
 * get_robots.txt.php has always written, down to the byte: the noindex rules
 * in the operator's catch-all group, or in a group of our own before the
 * Sitemap line, then the operator's text.
 *
 * With it, the Content-Signal line joins the catch-all group the same way
 * (unless the operator's text has one already), and the AI crawlers not
 * allowed get a "Disallow: /" group each, at the top of the file. Not after
 * the operator's text: a text that ends on a User-agent line would take the
 * first of them into its own group and close the site to that crawler.
 *
 * @param string     $additional     the Additional robots.txt Content field
 * @param array      $disallow_rules "Disallow: ..." lines (pg_build_robots_disallow_rules())
 * @param string     $sitemap_url
 * @param array|null $ai             as pg_ai_crawler_settings_from_row()
 * @return string
 */
function pg_robots_txt_content($additional, $disallow_rules, $sitemap_url, $ai = null)
{
    $additional = (string) $additional;
    $catch_all_lines = array_values((array) $disallow_rules);
    $preamble = '';
    $ai_groups = '';

    if (is_array($ai) && !empty($ai['rules'])) {

        if (!pg_robots_has_content_signal($additional)) {
            $signal = pg_ai_content_signal_line($ai, pg_robots_catch_all_closed($additional));

            array_unshift($catch_all_lines, $signal);

            $preamble =
                '# Content-Signal says how the content of this site may be used: search' . "\r\n" .
                '# (a search index with links and short excerpts), ai-input (answers an AI' . "\r\n" .
                '# tool gives from a page) and ai-train (training or fine-tuning AI models).' . "\r\n" .
                ((strpos($signal, 'ai-train=no') !== false)
                    ? '# ai-train=no expressly reserves the rights in this content under' . "\r\n" .
                      '# Article 4 of Directive (EU) 2019/790.' . "\r\n"
                    : '') .
                "\r\n";
        }

        foreach (pg_ai_robots_blocked_agents($ai, $additional) as $agent) {
            $ai_groups .=
                'User-agent: ' . $agent . "\r\n" .
                'Disallow: /' . "\r\n" .
                "\r\n";
        }
    }

    $own_group = '';

    // A site with nothing for the catch-all group gets the file exactly as it
    // was built before any of this existed.
    if (count($catch_all_lines) > 0) {
        // A rule belongs to the "User-agent" line above it, so where these go is
        // the whole question. When the operator's own text already declares a
        // catch-all group, they go inside it and the file keeps one group.
        $merged = pg_merge_robots_rules_into_catch_all($additional, $catch_all_lines);

        if ($merged !== FALSE) {
            $additional = $merged;

        // Otherwise open a group of our own, before the operator's text rather
        // than after it: appended, the rules would attach to whatever group that
        // text happens to end with, and a trailing "User-agent: Googlebot" block
        // would silently narrow every one of them to Googlebot alone.
        } else {
            $has_rule = false;

            foreach ($catch_all_lines as $line) {
                if (preg_match('/^(allow|disallow)\s*:/i', $line)) {
                    $has_rule = true;
                    break;
                }
            }

            // A group that only carries the Content-Signal line says so with
            // "Allow: /", which is what it means anyway.
            if (!$has_rule) {
                $catch_all_lines[] = 'Allow: /';
            }

            $own_group =
                'User-agent: *' . "\r\n" .
                implode("\r\n", $catch_all_lines) . "\r\n" .
                "\r\n";
        }
    }

    // Do not translate the Sitemap text: it is a functional string.
    $content =
        $preamble .
        $ai_groups .
        $own_group .
        'Sitemap: ' . $sitemap_url;

    if ($additional != '') {
        $content .=
            "\r\n" .
            "\r\n" .
            $additional;
    }

    return $content;
}

/**
 * Text for one line of llms.txt: the tags and entities of a title or a
 * description taken out, one line, at most $max characters.
 *
 * @param string $text
 * @param int    $max 0 for no limit
 * @return string
 */
function pg_llms_txt_line($text, $max = 0)
{
    $text = html_entity_decode(strip_tags((string) $text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $text = trim(preg_replace('/\s+/u', ' ', $text));

    if (($max > 0) && (mb_strlen($text) > $max)) {
        $text = rtrim(mb_substr($text, 0, $max - 1)) . '…';
    }

    return $text;
}

/**
 * The pages llms.txt lists, in order: the home page first, then the pages
 * visited most over the last days, then the ones changed most recently.
 *
 * @param array $pages rows with id, home ('yes'/'no') and timestamp
 * @param array $views page id => views
 * @param int   $limit
 * @return array the rows kept
 */
function pg_llms_txt_rank_pages($pages, $views, $limit)
{
    $home = null;
    $others = array();

    foreach ($pages as $page) {
        if ($page['home'] === 'yes') {
            // One home page, at the site root; another page marked as home is
            // the same address and is not listed again.
            if ($home === null) {
                $home = $page;
            }

            continue;
        }

        $others[] = $page;
    }

    usort($others, function ($a, $b) use ($views) {
        $views_a = isset($views[$a['id']]) ? (int) $views[$a['id']] : 0;
        $views_b = isset($views[$b['id']]) ? (int) $views[$b['id']] : 0;

        if ($views_a !== $views_b) {
            return ($views_a > $views_b) ? -1 : 1;
        }

        if ((int) $a['timestamp'] !== (int) $b['timestamp']) {
            return ((int) $a['timestamp'] > (int) $b['timestamp']) ? -1 : 1;
        }

        return ((int) $a['id'] < (int) $b['id']) ? -1 : 1;
    });

    $ranked = ($home !== null) ? array_merge(array($home), $others) : $others;

    return array_slice($ranked, 0, max(0, (int) $limit));
}

/**
 * The whole llms.txt (llmstxt.org): the site's name, its description, the
 * operator's own text, the pages, and the sitemap as the one optional link.
 *
 * "Optional" is the section name the format gives the links an agent may
 * skip, so it is not translated.
 *
 * @param array  $site          name, description
 * @param array  $pages         title, url, description per page
 * @param string $extra         the operator's text, Markdown
 * @param string $sitemap_url
 * @param string $pages_heading the heading of the page list
 * @return string
 */
function pg_llms_txt_content($site, $pages, $extra, $sitemap_url, $pages_heading)
{
    $lines = array('# ' . pg_llms_txt_line($site['name'], 200), '');

    $description = pg_llms_txt_line($site['description'], 500);

    if ($description !== '') {
        $lines[] = '> ' . $description;
        $lines[] = '';
    }

    $extra = trim(str_replace(array("\r\n", "\r"), "\n", (string) $extra));

    if ($extra !== '') {
        $lines[] = $extra;
        $lines[] = '';
    }

    if (count($pages) > 0) {
        $lines[] = '## ' . pg_llms_txt_line($pages_heading, 100);
        $lines[] = '';

        foreach ($pages as $page) {
            // Brackets would end the link text early.
            $title = str_replace(array('[', ']'), array('\\[', '\\]'), pg_llms_txt_line($page['title'], 150));
            $page_description = pg_llms_txt_line($page['description'], 300);

            $lines[] = '- [' . $title . '](' . $page['url'] . ')' . (($page_description !== '') ? ': ' . $page_description : '');
        }

        $lines[] = '';
    }

    $lines[] = '## Optional';
    $lines[] = '';
    $lines[] = '- [sitemap.xml](' . $sitemap_url . ')';

    return implode("\n", $lines) . "\n";
}

/**
 * Views per page over the last $days days, from the visitor rollup. Empty
 * where the rollup is not there.
 *
 * @param int $days
 * @return array page id => views
 */
function pg_llms_txt_page_views($days)
{
    if (!function_exists('pg_visitor_rollup_ready') || !pg_visitor_rollup_ready()) {
        return array();
    }

    $result = @mysqli_query(
        db::$con,
        "SELECT page_id, SUM(views) AS views
         FROM visitor_content_hourly
         WHERE stat_date >= '" . e(date('Y-m-d', time() - (max(1, (int) $days) * 86400))) . "'
         GROUP BY page_id"
    );

    $views = array();

    if ($result) {
        while ($row = @mysqli_fetch_assoc($result)) {
            $views[(int) $row['page_id']] = (int) $row['views'];
        }
    }

    return $views;
}

/**
 * The pages llms.txt lists: those the sitemap lists that a reader of the site
 * would start from, at most $limit of them (pg_llms_txt_rank_pages()). The
 * cart, the order forms and the search results are left out; they are steps
 * of a visit, not content.
 *
 * @param int $limit
 * @return array title, url, description per page
 */
function pg_llms_txt_pages($limit = 50)
{
    $sql_noindex = pg_page_noindex_ready() ? "AND (page.noindex = '0')" : "";

    // The folders the sitemap reads its pages from (get_sitemap_info()): not
    // archived, not in the recycle bin.
    $rows = db_items(
        "SELECT
            page.page_id,
            page.page_name,
            page.page_folder,
            page.page_home,
            page.page_title,
            page.page_meta_description,
            page.page_timestamp
        FROM page
        INNER JOIN folder ON folder.folder_id = page.page_folder
        WHERE
            (page.sitemap = '1')
            $sql_noindex
            AND (folder.folder_archived = '0')
            AND (page.page_type IN ('standard', 'folder view', 'photo gallery', 'custom form', 'form list view', 'form view directory', 'calendar view', 'catalog'))
            " . pg_designer_not_binned_sql('page.page_folder'));

    if (!is_array($rows)) {
        return array();
    }

    // A page behind a login is not listed, as in the sitemap; each folder is
    // asked once.
    $public_folders = array();
    $pages = array();

    foreach ($rows as $row) {
        $folder_id = (int) $row['page_folder'];

        if (!isset($public_folders[$folder_id])) {
            $public_folders[$folder_id] = (get_access_control_type($folder_id) == 'public');
        }

        if (!$public_folders[$folder_id]) {
            continue;
        }

        $pages[] = array(
            'id'          => (int) $row['page_id'],
            'home'        => (string) $row['page_home'],
            'timestamp'   => (int) $row['page_timestamp'],
            'name'        => (string) $row['page_name'],
            'title'       => (string) $row['page_title'],
            'description' => (string) $row['page_meta_description'],
        );
    }

    $site_url = URL_SCHEME . HOSTNAME_SETTING . PATH;
    $list = array();

    foreach (pg_llms_txt_rank_pages($pages, pg_llms_txt_page_views(30), $limit) as $page) {
        $title = pg_llms_txt_line($page['title']);

        // A page without a title of its own is shown with the site's browser
        // title (get_page_content()); for the home page that is the name it
        // goes by, for any other page its own name says more.
        if (($title === '') && ($page['home'] === 'yes') && defined('TITLE')) {
            $title = pg_llms_txt_line(TITLE);
        }

        $list[] = array(
            'title'       => ($title !== '') ? $title : $page['name'],
            'url'         => ($page['home'] === 'yes') ? $site_url : $site_url . encode_url_path($page['name']),
            'description' => $page['description'],
        );
    }

    return $list;
}
