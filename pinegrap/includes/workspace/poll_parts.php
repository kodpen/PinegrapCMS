<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - the parts an option of a poll can carry beside its label: a
 * line of description, a picture (a file already posted in the channel), a
 * few links, the "Recommended" mark, and the "Other" row that takes the
 * voter's own words (ws_poll_votes.other_text).
 *
 * A poll made before the columns existed has none of these: every column
 * defaults to empty, so it draws, counts and closes as it always did.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

// The most links one option carries.
define('WS_POLL_LINKS_MAX', 3);

/**
 * Do the option parts' columns exist? Files land before the upgrade runs.
 *
 * @return bool
 */
function ws_poll_parts_ready()
{
    return function_exists('pg_schema_has') && pg_schema_has('ws_poll_options', 'other') && pg_schema_has('ws_poll_votes', 'other_text');
}

/**
 * An option's links, cleaned: a list of {url, label}, http or https only,
 * at most WS_POLL_LINKS_MAX. A label left out is the address. Pure.
 *
 * @param mixed $links
 * @return array ok, error, links
 */
function ws_poll_links_clean($links)
{
    if (($links === null) || ($links === '') || ($links === array())) {
        return array('ok' => true, 'error' => '', 'links' => array());
    }

    if (!is_array($links)) {
        return array('ok' => false, 'error' => lang('The links of an option must be a list.'), 'links' => array());
    }

    $out = array();

    foreach ($links as $link) {
        if (is_string($link)) {
            $link = array('url' => $link);
        }

        $url = is_array($link) ? trim((string) ($link['url'] ?? '')) : '';
        $label = is_array($link) ? trim(preg_replace('/\s+/u', ' ', (string) ($link['label'] ?? ''))) : '';

        if ($url === '') {
            continue;
        }

        if ((preg_match('#^https?://[^\s/]+#i', $url) !== 1) || (mb_strlen($url) > 500)) {
            return array('ok' => false, 'error' => lang('A link of an option must be an http or https address.'), 'links' => array());
        }

        $out[] = array('url' => $url, 'label' => mb_substr(($label !== '') ? $label : $url, 0, 100));
    }

    if (count($out) > WS_POLL_LINKS_MAX) {
        return array('ok' => false, 'error' => lang(array('string' => 'An option can have at most {var:1} links.', 'vars' => WS_POLL_LINKS_MAX)), 'links' => array());
    }

    return array('ok' => true, 'error' => '', 'links' => $out);
}

/**
 * One option as the poll keeps it, from a text or an object of label,
 * description, file_id, links, recommended, other (and id when changed).
 * The "Other" row takes the label "Other" when none is given. Pure.
 *
 * @param mixed $item
 * @return array ok, error, part (id, label, description, file_id, links, recommended, other)
 */
function ws_poll_option_part($item)
{
    if (!is_array($item)) {
        $item = array('label' => is_scalar($item) ? (string) $item : '');
    }

    $other = !empty($item['other']);
    $label = mb_substr(trim(preg_replace('/\s+/u', ' ', is_scalar($item['label'] ?? '') ? (string) ($item['label'] ?? '') : '')), 0, 255);

    if ($other && ($label === '')) {
        $label = lang('Other');
    }

    $links = ws_poll_links_clean($item['links'] ?? array());

    if (!$links['ok']) {
        return array('ok' => false, 'error' => $links['error'], 'part' => null);
    }

    $description = is_scalar($item['description'] ?? '') ? (string) ($item['description'] ?? '') : '';

    return array('ok' => true, 'error' => '', 'part' => array(
        'id'          => max(0, (int) ($item['id'] ?? 0)),
        'label'       => $label,
        'description' => mb_substr(trim(preg_replace('/\s+/u', ' ', $description)), 0, 500),
        'file_id'     => max(0, (int) ($item['file_id'] ?? 0)),
        'links'       => $links['links'],
        'recommended' => !empty($item['recommended']),
        'other'       => $other,
    ));
}

/**
 * The rules a poll's options keep together: blank and repeated labels left
 * out (compared folded), two to ten options of which at least two are not
 * "Other", at most one "Other" row and at most one recommended. Pure.
 *
 * @param array $items texts or objects, as ws_poll_option_part() takes them
 * @return array ok, error, field, parts
 */
function ws_poll_parts_list($items)
{
    $fail = function ($error) {
        return array('ok' => false, 'error' => $error, 'field' => 'options', 'parts' => array());
    };

    $parts = array();
    $seen = array();
    $others = 0;
    $recommended = 0;

    foreach ((array) $items as $item) {
        $made = ws_poll_option_part($item);

        if (!$made['ok']) {
            return $fail($made['error']);
        }

        $part = $made['part'];
        $key = ws_fold($part['label']);

        if (($part['label'] === '') || isset($seen[$key])) {
            continue;
        }

        $seen[$key] = true;
        $others += $part['other'] ? 1 : 0;
        $recommended += $part['recommended'] ? 1 : 0;
        $parts[] = $part;
    }

    if ((count($parts) - $others) < 2) {
        return $fail(lang('A poll needs at least two different options.'));
    }

    if (count($parts) > 10) {
        return $fail(lang('A poll can have at most ten options.'));
    }

    if ($others > 1) {
        return $fail(lang('A poll can have one "Other" option.'));
    }

    if ($recommended > 1) {
        return $fail(lang('Only one option can be recommended.'));
    }

    return array('ok' => true, 'error' => '', 'field' => 'options', 'parts' => $parts);
}

/**
 * Does an option carry anything beyond its label? Pure.
 *
 * @param array $part ws_poll_option_part()'s part
 * @return bool
 */
function ws_poll_part_has_extras($part)
{
    return ((string) $part['description'] !== '') || ((int) $part['file_id'] > 0) || !empty($part['links'])
        || !empty($part['recommended']) || !empty($part['other']);
}

/**
 * Can these options be kept in this channel? Their parts need the upgrade,
 * and a picture has to be a picture posted in the channel: a reader sees a
 * workspace file only through a message that carries it (get_file.php).
 *
 * @param int   $channel_id
 * @param array $parts ws_poll_parts_list()'s parts
 * @return array ok, error, field
 */
function ws_poll_parts_store_check($channel_id, $parts)
{
    $extras = false;

    foreach ((array) $parts as $part) {
        $extras = $extras || ws_poll_part_has_extras($part);
    }

    if (!$extras) {
        return array('ok' => true, 'error' => '', 'field' => '');
    }

    if (!ws_poll_parts_ready()) {
        return array('ok' => false, 'error' => lang('The database has to be updated first.'), 'field' => 'options');
    }

    foreach ((array) $parts as $part) {
        if ((int) $part['file_id'] <= 0) {
            continue;
        }

        $name = (string) db_value("SELECT file_name FROM ws_messages
            WHERE channel_id = '" . (int) $channel_id . "' AND file_id = '" . (int) $part['file_id'] . "' AND deleted_at = 0 LIMIT 1");

        if (($name === '') || (ws_file_kind($name) !== 'image')) {
            return array('ok' => false, 'error' => lang('The picture of an option must be a picture posted in this channel.'), 'field' => 'options');
        }
    }

    return array('ok' => true, 'error' => '', 'field' => '');
}

/**
 * The SET list that writes an option's parts, '' before the upgrade.
 *
 * @param array $part
 * @return string
 */
function ws_poll_part_sql($part)
{
    if (!ws_poll_parts_ready()) {
        return '';
    }

    return ", description = '" . e((string) $part['description']) . "', file_id = '" . (int) $part['file_id'] . "'"
        . ", links = " . (empty($part['links']) ? 'NULL' : "'" . e(json_encode(array_values($part['links']), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)) . "'")
        . ", recommended = '" . (empty($part['recommended']) ? 0 : 1) . "', other = '" . (empty($part['other']) ? 0 : 1) . "'";
}

/**
 * The stored parts of a poll's options, by id, as ws_poll_option_part()
 * gives them; plain labels before the upgrade.
 *
 * @param int $poll_id
 * @return array id => part
 */
function ws_poll_parts_stored($poll_id)
{
    $ready = ws_poll_parts_ready();
    $out = array();

    foreach ((array) db_items("SELECT * FROM ws_poll_options WHERE poll_id = '" . (int) $poll_id . "' ORDER BY sort, id") as $row) {
        $out[(int) $row['id']] = ws_poll_part_of_row($row, $ready);
    }

    return $out;
}

/**
 * An option row as a part. Pure.
 *
 * @param array $row   ws_poll_options row
 * @param bool  $ready the parts' columns exist
 * @return array
 */
function ws_poll_part_of_row($row, $ready = true)
{
    $links = $ready ? json_decode((string) ($row['links'] ?? ''), true) : null;

    return array(
        'id'          => (int) $row['id'],
        'label'       => (string) $row['label'],
        'description' => $ready ? (string) ($row['description'] ?? '') : '',
        'file_id'     => $ready ? (int) ($row['file_id'] ?? 0) : 0,
        'links'       => is_array($links) ? $links : array(),
        'recommended' => $ready && ((int) ($row['recommended'] ?? 0) === 1),
        'other'       => $ready && ((int) ($row['other'] ?? 0) === 1),
    );
}

/**
 * The voter's own words on the "Other" row, cleaned; '' when blank. Pure.
 *
 * @param mixed $text
 * @return string
 */
function ws_poll_other_text($text)
{
    return is_scalar($text) ? mb_substr(trim(preg_replace('/\s+/u', ' ', (string) $text)), 0, 255) : '';
}

/**
 * Is a choice that holds the "Other" row answered in words? Pure.
 *
 * @param int[]  $chosen   option ids
 * @param int    $other_id the poll's "Other" row, 0 for none
 * @param string $text     ws_poll_other_text()
 * @return array ok, error, field
 */
function ws_poll_other_check($chosen, $other_id, $text)
{
    if (((int) $other_id > 0) && in_array((int) $other_id, array_map('intval', (array) $chosen), true) && ((string) $text === '')) {
        return array('ok' => false, 'error' => lang('Write your answer for "Other".'), 'field' => 'other_text');
    }

    return array('ok' => true, 'error' => '', 'field' => '');
}

/**
 * The poll's "Other" row, 0 for none or before the upgrade.
 *
 * @param int $poll_id
 * @return int
 */
function ws_poll_other_id($poll_id)
{
    return ws_poll_parts_ready() ? (int) db_value("SELECT id FROM ws_poll_options WHERE poll_id = '" . (int) $poll_id . "' AND other = 1 LIMIT 1") : 0;
}

/**
 * The pictures of a set of options, as a reader is shown them: the file's
 * name, address and whether it is an image, by file id.
 *
 * @param int[] $file_ids
 * @return array file id => {file_id, name, url, image}
 */
function ws_poll_pictures_map($file_ids)
{
    $file_ids = array_values(array_unique(array_filter(array_map('intval', (array) $file_ids))));
    $out = array();

    if (empty($file_ids)) {
        return $out;
    }

    foreach ((array) db_items("SELECT f.id, f.name, m.file_name FROM files f
        JOIN ws_messages m ON m.file_id = f.id AND m.deleted_at = 0
        WHERE f.id IN (" . implode(',', $file_ids) . ")") as $row) {
        if (isset($out[(int) $row['id']]) || ((string) $row['name'] === '')) {
            continue;
        }

        $out[(int) $row['id']] = array(
            'file_id' => (int) $row['id'],
            'name'    => (string) $row['file_name'],
            'url'     => PATH . encode_url_path((string) $row['name']),
            'image'   => (ws_file_kind($row['file_name']) === 'image'),
        );
    }

    return $out;
}
