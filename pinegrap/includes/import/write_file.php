<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The file writer of a content import: an item of kind file (a WordPress
 * attachment) is fetched from the old site into the file library, in the
 * mapping's file_folder. A body that named the same address before shares
 * the file (includes/import/media.php).
 *
 * The item's data: url, caption.
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

/**
 * Fetches one file item.
 *
 * @param array $item
 * @param array $import pg_import_get()
 * @return array{result:string,id:int,reason:string}
 */
function pg_import_write_file($item, $import)
{
    $data = (array) ($item['data'] ?? array());
    $caption = trim(preg_replace('/\s+/u', ' ', strip_tags((string) ($data['caption'] ?? ''))));

    $related = pg_import_related_ids($import);
    $stored = pg_import_media_fetch($import, $related, (string) ($data['url'] ?? ''), array('description' => $caption));

    if (!$stored['ok']) {
        return array('result' => 'failed', 'id' => 0, 'reason' => $stored['reason']);
    }

    // A post written before its featured image's attachment gets it now.
    require_once(PG_FUNCTIONS_DIR . '/includes/import/write_record.php');
    pg_import_record_attach_picture($import, $related, (string) ($item['source_key'] ?? ''), $stored['id']);

    return array('result' => 'made', 'id' => $stored['id'], 'reason' => '');
}

/**
 * Removes one fetched file through the files screen's delete path. A file
 * listed twice (an attachment and a body's picture) is gone at the second
 * call already.
 *
 * @param int $file_id
 */
function pg_import_unwrite_file($file_id)
{
    $file = db_item("SELECT id, name FROM files WHERE id = '" . (int) $file_id . "'");

    if (!is_array($file)) {
        return;
    }

    require_once(PG_FUNCTIONS_DIR . '/includes/explorer/recycle_bin.php');

    pg_delete_file_record($file);
}
