<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The File management card: the room the files take as a ring (images,
 * documents, other, and the disk's free space where the host tells it) and
 * the last uploads. Scoped by widget 18's condition
 * (pg_dashboard_files_where()), so a person never sees a file they could
 * not open from the files screen.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_DASHBOARD_V2')) {
    exit;
}

/**
 * The group a file type counts under in the ring.
 *
 * @param string $type the stored extension
 * @return string image, document or other
 */
function pg_dashboard_v2_file_group($type)
{
    $type = strtolower((string) $type);

    if (in_array($type, array('jpg', 'jpeg', 'png', 'gif', 'bmp', 'tiff', 'tif', 'webp', 'svg', 'avif', 'ico'), true)) {
        return 'image';
    }

    if (in_array($type, array('pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'odt', 'ods', 'odp', 'rtf', 'txt', 'csv'), true)) {
        return 'document';
    }

    return 'other';
}

/**
 * The ring's parts from bytes per stored type and the free space.
 *
 * @param array      $sizes rows of type and bytes
 * @param float|null $free  free bytes, null when unknown
 * @return array used (bytes), parts: key => bytes (image, document, other,
 *               and free when known)
 */
function pg_dashboard_v2_file_parts($sizes, $free)
{
    $parts = array('image' => 0, 'document' => 0, 'other' => 0);

    foreach ((array) $sizes as $row) {
        $parts[pg_dashboard_v2_file_group($row['type'] ?? '')] += max(0, (float) ($row['bytes'] ?? 0));
    }

    $used = array_sum($parts);

    if (($free !== null) && ($free >= 0)) {
        $parts['free'] = (float) $free;
    }

    return array('used' => $used, 'parts' => $parts);
}

function pg_dashboard_v2_card_files($user, $period, $compare)
{
    if (((int) $user['role'] >= 3) && !no_acl_check($user['id'])) {
        return array('status' => 'denied');
    }

    require_once(PG_FUNCTIONS_DIR . '/includes/dashboard/widgets.php');
    require_once(PG_FUNCTIONS_DIR . '/includes/dashboard/widgets/widget_18.php');

    $where = pg_dashboard_files_where($user);

    if ($where === null) {
        return array('status' => 'success', 'data' => array('empty' => lang('No files yet.')));
    }

    $sizes = db_items(
        "SELECT LOWER(files.type) AS type, SUM(files.size) AS bytes
         FROM files
         LEFT JOIN folder ON files.folder = folder.folder_id
         WHERE " . $where . "
         GROUP BY LOWER(files.type)");

    // Many hosts switch disk_free_space() off; the ring then shows the
    // files alone.
    $free = function_exists('disk_free_space') ? @disk_free_space(FILE_DIRECTORY_PATH) : false;
    $ring = pg_dashboard_v2_file_parts($sizes, ($free === false) ? null : (float) $free);

    $names = array(
        'image'    => lang('Images'),
        'document' => lang('Documents'),
        'other'    => lang('Other'),
        'free'     => lang('Free'),
    );

    $parts = array();

    foreach ($ring['parts'] as $key => $bytes) {
        $parts[] = array(
            'key'   => $key,
            'label' => $names[$key],
            'value' => $bytes,
            'note'  => convert_bytes_to_string($bytes, 'auto'),
        );
    }

    $rows = array();

    foreach (db_items(
        "SELECT files.id, files.name, files.type, files.size, files.timestamp
         FROM files
         LEFT JOIN folder ON files.folder = folder.folder_id
         WHERE " . $where . "
         ORDER BY files.id DESC
         LIMIT 3") as $file) {
        $rows[] = array(
            'type' => strtoupper(mb_substr((string) $file['type'], 0, 4)),
            'text' => (string) $file['name'],
            'note' => convert_bytes_to_string($file['size'], 'auto') . ' · ' . get_relative_time(array('timestamp' => (int) $file['timestamp'], 'format' => 'plain_text')),
            'url'  => 'edit_file.php?id=' . (int) $file['id'],
        );
    }

    $total = $ring['used'] + ($ring['parts']['free'] ?? 0);

    return array('status' => 'success', 'data' => array(
        'used'       => convert_bytes_to_string($ring['used'], 'auto'),
        'used_note'  => isset($ring['parts']['free']) && ($total > 0)
            ? lang(array('string' => '{var:1}% full', 'vars' => pg_format_number(($ring['used'] / $total) * 100, 0)))
            : lang('in files'),
        'ring_label' => lang('Storage used by files'),
        'parts'      => $parts,
        'rows_title' => lang('Last uploads'),
        'rows'       => $rows,
        'empty'      => (empty($rows) && ($ring['used'] <= 0)) ? lang('No files yet.') : '',
        'url'        => 'view_folders.php?view=files',
        'link'       => lang('Go to files'),
    ));
}
