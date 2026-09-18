<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Lightweight JSON endpoint used by the style designer's "Select from
 * software" context menu option. Returns CSS / JS / JSON files from the files
 * table so the assets panel can link to existing server-side files.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */
include('init.php');
$user = validate_user();

header('Content-Type: application/json; charset=utf-8');

$filter = isset($_GET['filter']) ? strtolower(trim($_GET['filter'])) : '';

if ($filter === 'css') {
    $where_types = "'css'";
} elseif ($filter === 'js') {
    $where_types = "'js','json'";
} else {
    $where_types = "'css','js','json'";
}

$rows = db_items(
    "SELECT name, type FROM files
     WHERE type IN ($where_types) AND name != ''
     ORDER BY name ASC
     LIMIT 1000"
);

$result = array();
foreach ($rows as $row) {
    $result[] = array(
        'name' => $row['name'],
        'type' => $row['type'],
        'url'  => OUTPUT_PATH . $row['name'],
    );
}

echo encode_json($result);
exit();
