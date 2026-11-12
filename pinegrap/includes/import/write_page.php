<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The page writer of a content import: an item of kind page (a WordPress
 * page) becomes a draft page of a visual design. Its pictures are fetched
 * into the file library first (includes/import/media.php), then the body goes
 * through the designer's HTML importer (pg_designer_import_single_html()),
 * so lazy picture sources, framework files and the element mapping follow
 * the designer's own import rules.
 *
 * The item's data: body (HTML), title, slug, excerpt. The mapping's
 * page_folder is the folder the page is published to once it leaves the
 * drafts; the design is the one that folder takes, else the home page's.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/import/media.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/classic_style_design.php');

/**
 * The page's name: its slug, else its title, made safe for an address. Pure.
 *
 * @param array $item
 * @return string
 */
function pg_import_page_name($item)
{
    $data = (array) ($item['data'] ?? array());

    foreach (array(rawurldecode((string) ($data['slug'] ?? '')), (string) ($item['title'] ?? '')) as $candidate) {
        $name = strtolower(trim(preg_replace('/[^A-Za-z0-9_-]+/', '-', pg_ascii_file_name($candidate)), '-'));

        if ($name !== '') {
            return mb_substr($name, 0, 90);
        }
    }

    return 'page';
}

/**
 * The visual design an imported page belongs to: the one its folder takes,
 * else the home page's, else the newest. 0 when the site has none.
 *
 * @param int $folder_id
 * @return int
 */
function pg_import_page_style($folder_id)
{
    $folders = db_items("SELECT folder_id, folder_parent, folder_style, folder_level FROM folder");
    $styles = pg_classic_style_folder_styles(is_array($folders) ? $folders : array());
    $candidates = array(
        (int) ($styles[(int) $folder_id] ?? 0),
        (int) db_value("SELECT page_style FROM page WHERE page_home = 'yes' LIMIT 1"),
    );

    foreach ($candidates as $style_id) {
        if (($style_id > 0) && (int) db_value("SELECT COUNT(*) FROM style WHERE style_id = '" . $style_id . "' AND style_layout = 'visual_designer'") > 0) {
            return $style_id;
        }
    }

    return (int) db_value("SELECT style_id FROM style WHERE style_layout = 'visual_designer' ORDER BY style_id DESC LIMIT 1");
}

/**
 * Writes one page item as a draft page.
 *
 * @param array $item
 * @param array $import pg_import_get()
 * @return array{result:string,id:int,reason:string}
 */
function pg_import_write_page($item, $import)
{
    $fail = function ($reason) {
        return array('result' => 'failed', 'id' => 0, 'reason' => $reason);
    };

    $owner = db_item("SELECT user_id AS id, user_role AS role, user_username AS username FROM user WHERE user_id = '" . (int) $import['user_id'] . "'");

    // A page's tree is the design's markup: only a designer or an
    // administrator writes one (pg_designer_is_full()).
    if (!is_array($owner) || ((int) $owner['role'] > 1)) {
        return $fail(lang('Only a designer or an administrator can import pages.'));
    }

    $user = array('id' => (int) $owner['id'], 'role' => (int) $owner['role'], 'username' => (string) $owner['username']);

    $folder = (int) ($import['mapping']['page_folder'] ?? 0);
    $style_id = pg_import_page_style($folder);

    if ($style_id <= 0) {
        return $fail(lang('The site has no design to place the page in.'));
    }

    $data = (array) ($item['data'] ?? array());
    $title = trim((string) ($item['title'] ?? ''));
    $media = pg_import_media_localize($import, pg_import_related_ids($import), (string) ($data['body'] ?? ''));
    $name = get_unique_name(array('name' => pg_import_page_name($item), 'type' => 'page'));

    require_once(PG_FUNCTIONS_DIR . '/includes/designer_import.php');

    $document = '<!DOCTYPE html><html><head><title>' . h($title) . '</title></head><body>' . $media['html'] . '</body></html>';
    $imported = pg_designer_import_single_html($document, $name . '.html', $user);
    $page = $imported['pages'][0] ?? null;

    if (!is_array($page) || !is_array($page['tree'] ?? null)) {
        return $fail(lang('The page body could not be read as HTML.'));
    }

    $tree = $page['tree'];
    $seq = 0;
    pg_di_assign_ids($tree, $seq);

    $excerpt = trim(preg_replace('/\s+/u', ' ', strip_tags((string) ($data['excerpt'] ?? ''))));

    // The save writes its log lines in the name of the person who started
    // the import, also when the general job runs it.
    $had_name = isset($_SESSION) && array_key_exists('sessionusername', $_SESSION);
    $old_name = $had_name ? $_SESSION['sessionusername'] : null;
    $_SESSION['sessionusername'] = $user['username'];

    $saved = pg_designer_save_page($style_id, array(
        'page_id'               => 0,
        'page_name'             => $name,
        'tree_json'             => pg_designer_tree_encode($tree),
        'page_folder'           => $folder,
        'page_title'            => mb_substr($title, 0, 255),
        'page_meta_description' => mb_substr($excerpt, 0, 255),
        'page_search'           => 1,
        'page_sitemap'          => 1,
        'page_home'             => 0,
        'page_draft'            => 1,
        'revision_source'       => 'import',
    ), $user);

    if ($had_name) {
        $_SESSION['sessionusername'] = $old_name;
    } else {
        unset($_SESSION['sessionusername']);
    }

    if (empty($saved['ok']) || ((int) ($saved['page_id'] ?? 0) <= 0)) {
        return $fail(implode(' ', (array) ($saved['errors'] ?? array())) ?: lang('The page could not be saved.'));
    }

    return array('result' => 'made', 'id' => (int) $saved['page_id'], 'reason' => pg_import_media_notes_line($media['notes']));
}

/**
 * Removes one imported page through the pages screen's delete path.
 *
 * @param int $page_id
 */
function pg_import_unwrite_page($page_id)
{
    require_once(PG_FUNCTIONS_DIR . '/includes/explorer/recycle_bin.php');

    // Undo is the importing screen's, which only staff open; the folder
    // right was checked when the page was made.
    pg_delete_page_record((int) $page_id, array('id' => 0, 'role' => 0, 'delete_pages' => true));
}
