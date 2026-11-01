<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The address of the retired full-page image editor, kept for links and
 * bookmarks that still name it: it opens the file's folder in the File
 * Manager with the image editor on the file (#pg-image-edit=, read by the
 * loader of pg_image_editor_includes()).
 *
 *   image_editor_edit.php?file_name=<name>
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

include('init.php');

validate_user();

pg_request_single_values(array('file_name'));

$image_editor_name = isset($_GET['file_name']) ? (string) $_GET['file_name'] : '';
$image_editor_folder = ($image_editor_name !== '') ? (int) db_value("SELECT folder FROM files WHERE name = '" . e($image_editor_name) . "' LIMIT 1") : 0;

go(PATH . SOFTWARE_DIRECTORY . '/view_folders.php' . (($image_editor_folder > 0) ? '?folder=' . $image_editor_folder : '')
    . (($image_editor_name !== '') ? '#pg-image-edit=' . rawurlencode($image_editor_name) : ''));
