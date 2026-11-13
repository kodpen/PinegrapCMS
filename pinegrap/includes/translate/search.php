<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Front-end translation - the site search on a page served in another
 * language: the visual pages whose translated texts hold the typed words.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

/**
 * The site search on a page served in another language: the visual pages
 * whose translated texts (body, title, description) contain the words, best
 * first. What the visitor may see is the caller's to check.
 *
 * @param string $query
 * @param int    $limit
 * @return array of array(page_id, name, folder_id, title, excerpt)
 */
function pg_tr_search_pages_find($query, $limit = 100)
{
    $language = pg_tr_language();
    $query = trim((string) $query);

    if (($language === '') || ($query === '')) {
        return array();
    }

    $like = "'%" . e(escape_like($query)) . "%'";
    $needle = mb_strtolower($query, 'UTF-8');

    $rows = db_items("SELECT u.owner_id AS page_id, u.owner_type, t.text
                      FROM translations t
                      INNER JOIN translation_uses u ON u.string_id = t.string_id
                      WHERE t.language = '" . e($language) . "'
                        AND u.owner_type IN ('page', 'page_seo')
                        AND t.text LIKE $like
                      LIMIT 2000");

    $scores = array();
    $texts = array();

    foreach ((array) $rows as $row) {
        $pid = (int) $row['page_id'];
        $plain = html_entity_decode(strip_tags((string) $row['text']), ENT_QUOTES, 'UTF-8');
        $hits = max(1, mb_substr_count(mb_strtolower($plain, 'UTF-8'), $needle));

        // A word in the title or the description counts for more, as in the
        // source search.
        if ($row['owner_type'] === 'page_seo') {
            $hits *= 3;
        }

        $scores[$pid] = (isset($scores[$pid]) ? $scores[$pid] : 0) + $hits;

        if (!isset($texts[$pid]) && ($row['owner_type'] === 'page')) {
            $texts[$pid] = $plain;
        }
    }

    if (!$scores) {
        return array();
    }

    arsort($scores);

    $binned = function_exists('pg_designer_not_binned_sql') ? pg_designer_not_binned_sql('page.page_folder') : '';
    $pages = db_items("SELECT page.page_id, page.page_name, page.page_folder, page.page_title, page.page_meta_description
                       FROM page
                       LEFT JOIN folder ON page.page_folder = folder.folder_id
                       WHERE page.page_search = '1' AND folder.folder_archived = '0'
                         AND page.page_id IN (" . implode(',', array_map('intval', array_keys($scores))) . ")$binned", 'page_id');

    $out = array();

    foreach ($scores as $pid => $score) {
        if (!isset($pages[$pid])) {
            continue;
        }

        $page = $pages[$pid];
        $description = pg_tr_render_text((string) $page['page_meta_description'], 'text', 'seo');
        $excerpt = ($description !== (string) $page['page_meta_description']) ? $description : '';

        if (($excerpt === '') && isset($texts[$pid])) {
            $excerpt = function_exists('_pg_sw_snippet') ? _pg_sw_snippet($texts[$pid], $query) : mb_substr($texts[$pid], 0, 200);
        }

        $out[] = array(
            'page_id'   => (int) $pid,
            'name'      => (string) $page['page_name'],
            'folder_id' => (int) $page['page_folder'],
            'title'     => (string) $page['page_title'],
            'excerpt'   => $excerpt,
        );

        if (count($out) >= $limit) {
            break;
        }
    }

    return $out;
}
