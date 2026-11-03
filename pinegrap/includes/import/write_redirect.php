<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The redirects of a content import (includes/fn/importers.php): an item
 * made from something the old site showed at an address of its own (a
 * WordPress post or page, a Shopify product) gets a 301 short link from that
 * path to what it became, so links and search results that name the old
 * address keep working once the domain points here.
 *
 * - The link goes through the short link rules (includes/fn/short_link_redirects.php):
 *   the name rule, a name no page, file or link holds, and the loop checks.
 *   A name in use is skipped and named in the report; nothing is replaced.
 * - A page or product is the link's destination itself, so it follows the
 *   page or product when it is renamed; a record is sent to its address.
 * - An incomplete record (a draft) gets none: it is not on the site.
 * - Each link is an import_items row of kind redirect, written right after
 *   its item, so undo (newest first) removes it before what it leads to.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

if (!function_exists('pg_short_link_name_from_address')) {
    require_once(PG_FUNCTIONS_DIR . '/includes/fn/short_link_redirects.php');
}

/**
 * The address the old site showed an item at, '' when it had none worth a
 * redirect: a WordPress post or page by its permalink (a ?p= address is the
 * home page's path), a Shopify product at /products/<handle>. The
 * WooCommerce products export carries no address. Pure.
 *
 * @param string $import_kind the reader
 * @param array  $item
 * @return string
 */
function pg_import_redirect_old_address($import_kind, $item)
{
    $kind = (string) ($item['kind'] ?? '');
    $address = '';

    if (($import_kind === 'wordpress') && in_array($kind, array('record', 'page'), true)) {
        $address = trim((string) ($item['data']['link'] ?? ''));
    } elseif (($import_kind === 'shopify') && ($kind === 'product')) {
        $key = (string) ($item['source_key'] ?? '');
        $address = (strpos($key, 'shopify:product:') === 0) ? '/products/' . substr($key, strlen('shopify:product:')) : '';
    }

    return (pg_import_redirect_name($address) === '') ? '' : $address;
}

/**
 * The short link name an old address becomes: its path, decoded, in the
 * stored form, the old site's host dropped. Pure.
 *
 * @param string $address
 * @return string
 */
function pg_import_redirect_name($address)
{
    return pg_short_link_name_from_address((string) $address, '/');
}

/**
 * A record's address on the page that shows it, from the site's root. Pure.
 *
 * @param string $page_name
 * @param string $reference_code
 * @param string $address_name
 * @param bool   $pretty
 * @return string
 */
function pg_import_redirect_record_url($page_name, $reference_code, $address_name, $pretty)
{
    $url = '/' . encode_url_path((string) $page_name);
    $address_name = trim((string) $address_name);

    if ($pretty && ($address_name !== '')) {
        return $url . '/' . encode_url_path($address_name);
    }

    return $url . '?r=' . rawurlencode((string) $reference_code);
}

/**
 * The short link columns that lead to what an item became, or a reason
 * string when nothing can lead there.
 *
 * @param string $kind record, page, product
 * @param int    $id
 * @return array|string
 */
function pg_import_redirect_destination($kind, $id)
{
    $id = (int) $id;

    if ($kind === 'page') {
        return array('destination_type' => 'page', 'page_id' => $id);
    }

    if ($kind === 'product') {
        $page_id = (int) db_value("SELECT page_id FROM page WHERE page_type = 'catalog detail' ORDER BY page_id LIMIT 1");

        if ($page_id <= 0) {
            return lang('The site has no catalog detail page to show the product on.');
        }

        return array('destination_type' => 'product', 'product_id' => $id, 'page_id' => $page_id);
    }

    if ($kind === 'record') {
        $record = db_item("SELECT page_id, reference_code, address_name, complete FROM forms WHERE id = '" . $id . "'");

        if (!is_array($record) || ((int) $record['complete'] !== 1)) {
            return '';
        }

        $page_name = (string) db_value("SELECT page.page_name
            FROM form_item_view_pages
            JOIN page ON page.page_id = form_item_view_pages.page_id
            WHERE form_item_view_pages.custom_form_page_id = '" . (int) $record['page_id'] . "' AND form_item_view_pages.collection = 'a'
            ORDER BY form_item_view_pages.page_id LIMIT 1");

        if ($page_name === '') {
            return lang('The form has no page that shows its records.');
        }

        return array('destination_type' => 'url', 'url' => pg_import_redirect_record_url($page_name, $record['reference_code'], $record['address_name'], check_if_pretty_urls_are_enabled($record['page_id'])));
    }

    return '';
}

/**
 * Adds the redirect of an item just made. Null when the item has no old
 * address (nothing to count), else the redirect's own answer.
 *
 * @param array $import
 * @param array $item
 * @param int   $item_id what the item's writer made
 * @return array{result:string,id:int,reason:string}|null
 */
function pg_import_redirect_after($import, $item, $item_id)
{
    $address = pg_import_redirect_old_address((string) $import['kind'], $item);

    if ($address === '') {
        return null;
    }

    if (!function_exists('pg_short_link_redirects_ready') || !pg_short_link_redirects_ready()) {
        return array('result' => 'failed', 'id' => 0, 'reason' => lang('The database is not up to date.'));
    }

    $destination = pg_import_redirect_destination((string) $item['kind'], $item_id);

    if ($destination === '') {
        return null;
    }

    if (is_string($destination)) {
        return array('result' => 'skipped', 'id' => 0, 'reason' => $destination);
    }

    $answer = pg_import_redirect_add($import, pg_import_redirect_name($address), $destination);

    if ($answer['result'] === 'made') {
        db("INSERT IGNORE INTO import_items SET
            import_id = '" . (int) $import['id'] . "',
            kind = 'redirect',
            item_id = '" . (int) $answer['id'] . "',
            source_key = '" . e(mb_substr('redirect:' . (string) $item['source_key'], 0, 191)) . "'");
    }

    return $answer;
}

/**
 * One short link from $name to $destination, by the short link rules.
 *
 * @param array  $import
 * @param string $name        pg_import_redirect_name()
 * @param array  $destination pg_import_redirect_destination()
 * @return array{result:string,id:int,reason:string}
 */
function pg_import_redirect_add($import, $name, $destination)
{
    $problem = pg_short_link_name_problem($name);

    if (($problem === '') && pg_short_link_is_pattern($name)) {
        $problem = lang('The old address holds a *, which would make the redirect a pattern.');
    }

    if (($problem === '') && ($destination['destination_type'] === 'url')) {
        $problem = pg_short_link_url_problem($destination['url']);
    }

    if ($problem !== '') {
        return array('result' => 'skipped', 'id' => 0, 'reason' => $problem);
    }

    if (check_name_availability(array('name' => $name)) == false) {
        return array('result' => 'skipped', 'id' => 0, 'reason' => lang(array('string' => 'The old address /{var:1} is in use on this site already, so no redirect was added.', 'vars' => array($name))));
    }

    $link = array('id' => 0, 'name' => $name, 'url' => '') + $destination;
    $checked = pg_short_link_redirect_columns($link, 301, 0, null);

    if ($checked['problem'] !== null) {
        return array('result' => 'skipped', 'id' => 0, 'reason' => (string) $checked['problem']['message']);
    }

    $user_id = (int) ($import['user_id'] ?? 0);
    $columns = array(
        "name = '" . e($name) . "'",
        "destination_type = '" . e($destination['destination_type']) . "'",
        "page_id = '" . (int) ($destination['page_id'] ?? 0) . "'",
        "product_id = '" . (int) ($destination['product_id'] ?? 0) . "'",
        "url = '" . e((string) ($destination['url'] ?? '')) . "'",
        "created_user_id = '" . $user_id . "'",
        "created_timestamp = UNIX_TIMESTAMP()",
        "last_modified_user_id = '" . $user_id . "'",
        "last_modified_timestamp = UNIX_TIMESTAMP()",
    );

    if (pg_short_link_modes_ready()) {
        $columns[] = "link_mode = 'permanent'";
    }

    foreach ($checked['columns'] as $column => $value) {
        $columns[] = $column . " = '" . (int) $value . "'";
    }

    db("INSERT INTO short_links SET " . implode(', ', $columns));
    $id = (int) mysqli_insert_id(db::$con);

    if ($id <= 0) {
        return array('result' => 'failed', 'id' => 0, 'reason' => lang('The redirect could not be saved.'));
    }

    return array('result' => 'made', 'id' => $id, 'reason' => '');
}

/**
 * A redirect item a reader hands over itself: data from (the old address)
 * and to (an address on this site).
 *
 * @param array $item
 * @param array $import
 * @return array{result:string,id:int,reason:string}
 */
function pg_import_write_redirect($item, $import)
{
    if (!function_exists('pg_short_link_redirects_ready') || !pg_short_link_redirects_ready()) {
        return array('result' => 'failed', 'id' => 0, 'reason' => lang('The database is not up to date.'));
    }

    $data = (array) ($item['data'] ?? array());

    return pg_import_redirect_add($import, pg_import_redirect_name((string) ($data['from'] ?? '')), array('destination_type' => 'url', 'url' => trim((string) ($data['to'] ?? ''))));
}

/**
 * Removes an imported redirect: the short link, as its screen deletes one.
 *
 * @param int $link_id
 * @return true
 */
function pg_import_unwrite_redirect($link_id)
{
    db("DELETE FROM short_links WHERE id = '" . (int) $link_id . "'");

    return true;
}

/**
 * The redirects an import made, as view_short_links.php exports links, for
 * pg_short_link_csv().
 *
 * @param int $import_id
 * @return array[]
 */
function pg_import_redirect_links($import_id)
{
    return (array) db_items("SELECT
            short_links.name,
            short_links.destination_type,
            short_links.url,
            short_links.tracking_code,
            short_links.page_id,
            short_links.redirect_code,
            page.page_name,
            products.address_name AS product_address_name
        FROM import_items
        JOIN short_links ON short_links.id = import_items.item_id
        LEFT JOIN page ON short_links.page_id = page.page_id
        LEFT JOIN products ON short_links.product_id = products.id
        WHERE import_items.import_id = '" . (int) $import_id . "' AND import_items.kind = 'redirect'
        ORDER BY short_links.name");
}
