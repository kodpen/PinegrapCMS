<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The Wix reader of the content import framework (includes/fn/
 * importers.php): a collection's CSV from Wix's CMS export, read by the
 * shared collection reader (includes/import/collection.php) with its
 * mapping step.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/import/collection.php');

/**
 * @param string $file the uploaded export
 * @return array
 */
function pg_import_wix_mapping($file = '')
{
    return pg_import_collection_mapping((string) $file, 'wix');
}

/**
 * @param string $file
 * @return array the cursor
 */
function pg_import_wix_open($file)
{
    return pg_import_collection_open((string) $file, 'wix');
}

/**
 * @param array $cursor
 * @return array|null
 */
function pg_import_wix_next(&$cursor)
{
    return pg_import_collection_next($cursor);
}

/**
 * @param array $item
 * @return array{title:string,detail:string}
 */
function pg_import_wix_plan($item)
{
    return pg_import_collection_plan($item);
}
