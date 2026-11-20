<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The Privacy centre's screen and downloads (privacy_centre.php): the
 * personal data inventory as rows with the columns the Turkish data
 * controllers' registry (VERBİS) asks for, the transfers to outside
 * services, the destruction records of the audit trail, and the same as an
 * Excel workbook and a PDF.
 *
 * The rows are built by pure functions from the catalogue
 * (pg_privacy_inventory()), its live counts and the transfers
 * (includes/fn/privacy_inventory.php); labels pass through a translate
 * callable, lang() on the screen. Legal grounds, retentions and measures are
 * suggested defaults, not legal advice.
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
 * The columns of the inventory sheet, in order: key => English heading. The
 * VERBİS columns first (category, purpose, legal ground, data subject group,
 * recipients, retention, transfer abroad, measures), then the live counts.
 * Pure.
 *
 * @return array key => heading
 */
function pg_privacy_centre_columns()
{
    return array(
        'table'      => 'Table',
        'categories' => 'Data category',
        'purpose'    => 'Purpose',
        'ground'     => 'Legal ground',
        'subjects'   => 'Data subject group',
        'recipients' => 'Recipients',
        'retention'  => 'Retention',
        'abroad'     => 'Transfer abroad',
        'measures'   => 'Measures',
        'records'    => 'Records',
        'people'     => 'People',
    );
}

/**
 * The technical and organisational measures the software itself takes, one
 * sentence for every line. A suggested text; the shop adds its own. Pure.
 *
 * @return string English source text
 */
function pg_privacy_centre_measures()
{
    return 'Panel access by role, hashed passwords, two-step verification, an audit trail of changes, erasure on request';
}

/**
 * The inventory as rows, one per catalogue line, keyed as
 * pg_privacy_centre_columns(). Pure.
 *
 * Recipients are the switched-on services receiving personal data whose data
 * categories meet the line's; a transfer abroad names those of them that sit
 * abroad, or says No. A count not known (null) stays null.
 *
 * @param array    $inventory  table => line (pg_privacy_inventory())
 * @param array    $counts     table => records, people (pg_privacy_inventory_counts())
 * @param array    $transfers  id => transfer (pg_privacy_transfers())
 * @param callable $translate  English source text => shown text
 * @return array list of rows
 */
function pg_privacy_centre_rows(array $inventory, array $counts, array $transfers, $translate)
{
    $labels = function ($keys, array $known) use ($translate) {
        $out = array();

        foreach ((array) $keys as $key) {
            $out[] = isset($known[$key]) ? call_user_func($translate, $known[$key]) : (string) $key;
        }

        return implode(', ', $out);
    };

    $categories = pg_privacy_inventory_categories();
    $subjects = pg_privacy_inventory_subjects();
    $grounds = pg_privacy_inventory_grounds();
    $retentions = pg_privacy_inventory_retentions();
    $measures = call_user_func($translate, pg_privacy_centre_measures());
    $rows = array();

    foreach ($inventory as $table => $line) {
        $recipients = array();
        $abroad = array();

        foreach ($transfers as $transfer) {
            if (empty($transfer['personal']) || !array_intersect((array) $line['categories'], (array) $transfer['data'])) {
                continue;
            }

            $recipients[] = (string) $transfer['name'];

            if (!empty($transfer['abroad'])) {
                $abroad[] = (string) $transfer['name'] . ' (' . (string) $transfer['country'] . ')';
            }
        }

        $rows[] = array(
            'table'      => (string) $table,
            'categories' => $labels($line['categories'], $categories),
            'purpose'    => call_user_func($translate, (string) $line['purpose']),
            'ground'     => $labels($line['ground'], $grounds),
            'subjects'   => $labels($line['subjects'], $subjects),
            'recipients' => implode(', ', $recipients),
            'retention'  => $labels($line['retention'], $retentions),
            'abroad'     => $abroad ? implode(', ', $abroad) : call_user_func($translate, 'No'),
            'measures'   => $measures,
            'records'    => isset($counts[$table]['records']) ? (int) $counts[$table]['records'] : null,
            'people'     => isset($counts[$table]['people']) ? (int) $counts[$table]['people'] : null,
        );
    }

    return $rows;
}

/**
 * The transfers as rows for the screen and the PDF: service, country, the
 * data it receives, abroad or not, and whether its state could be told.
 * Services receiving no personal data are left out. Pure.
 *
 * @param array    $transfers  id => transfer (pg_privacy_transfers())
 * @param callable $translate
 * @return array list of name, country, data, abroad (bool), unknown (bool)
 */
function pg_privacy_centre_transfer_rows(array $transfers, $translate)
{
    $categories = pg_privacy_inventory_categories();
    $rows = array();

    foreach ($transfers as $transfer) {
        if (empty($transfer['personal'])) {
            continue;
        }

        $data = array();

        foreach ((array) $transfer['data'] as $key) {
            $data[] = isset($categories[$key]) ? call_user_func($translate, $categories[$key]) : (string) $key;
        }

        $rows[] = array(
            'name'    => (string) $transfer['name'],
            'country' => ($transfer['country'] === '*') ? call_user_func($translate, 'Several countries') : (string) $transfer['country'],
            'data'    => implode(', ', $data),
            'abroad'  => !empty($transfer['abroad']),
            'unknown' => !empty($transfer['unknown']),
        );
    }

    usort($rows, function ($a, $b) {
        return ($a['abroad'] === $b['abroad']) ? strcasecmp($a['name'], $b['name']) : ($a['abroad'] ? -1 : 1);
    });

    return $rows;
}

/**
 * The records an erasure or a retention rule's run took, from its audit
 * line's after_json: the counts under deleted and anonymised, added up
 * (a rule's counts may be nested a level deeper). Pure.
 *
 * @param string|null $after_json
 * @return array deleted => int, anonymised => int
 */
function pg_privacy_centre_record_counts($after_json)
{
    $after = is_string($after_json) ? json_decode($after_json, true) : null;
    $sum = function ($value) use (&$sum) {
        if (is_int($value) || is_float($value) || (is_string($value) && ctype_digit($value))) {
            return max(0, (int) $value);
        }

        if (!is_array($value)) {
            return 0;
        }

        $total = 0;

        foreach ($value as $inner) {
            $total += $sum($inner);
        }

        return $total;
    };

    return array(
        'deleted'    => is_array($after) && isset($after['deleted']) ? $sum($after['deleted']) : 0,
        'anonymised' => is_array($after) && isset($after['anonymised']) ? $sum($after['anonymised']) : 0,
    );
}

/**
 * The destruction records of the last years: the erasures
 * (action erase, entity person) and the retention rules' runs (entity
 * privacy_rule), newest first, at most $limit. No person is named in them.
 *
 * @param int $years
 * @param int $limit
 * @return array list of created_at, username, kind (erase|rule), label, deleted, anonymised
 */
function pg_privacy_centre_destruction_records($years = 3, $limit = 500)
{
    if (!pg_schema_has('audit_log')) {
        return array();
    }

    $since = time() - ((int) $years * 365 * 86400);
    $result = db_q(
        "SELECT created_at, username, entity_type, label, after_json FROM audit_log"
        . " WHERE ((entity_type = 'person' AND action = 'erase') OR entity_type = 'privacy_rule')"
        . " AND created_at >= " . (int) $since
        . " ORDER BY created_at DESC LIMIT " . (int) $limit
    );

    $records = array();

    while ($result && ($row = mysqli_fetch_assoc($result))) {
        $records[] = array(
            'created_at' => (int) $row['created_at'],
            'username'   => (string) $row['username'],
            'kind'       => ($row['entity_type'] === 'privacy_rule') ? 'rule' : 'erase',
            'label'      => ($row['entity_type'] === 'privacy_rule') ? (string) $row['label'] : '',
        ) + pg_privacy_centre_record_counts($row['after_json']);
    }

    return $records;
}

/**
 * The download's file name: personal-data-inventory-<date>.<extension>. Pure.
 *
 * @param string   $extension xlsx or pdf
 * @param int|null $time
 * @return string
 */
function pg_privacy_centre_filename($extension, $time = null)
{
    return 'personal-data-inventory-' . date('Y-m-d', ($time === null) ? time() : (int) $time) . '.' . preg_replace('/[^a-z]/', '', strtolower((string) $extension));
}

/**
 * The inventory rows as the Excel sheet's heading and rows, translated.
 * Pure.
 *
 * @param array    $rows      pg_privacy_centre_rows()
 * @param callable $translate
 * @return array headings => list, rows => list of lists
 */
function pg_privacy_centre_sheet(array $rows, $translate)
{
    $headings = array();

    foreach (pg_privacy_centre_columns() as $heading) {
        $headings[] = call_user_func($translate, $heading);
    }

    $out = array();

    foreach ($rows as $row) {
        $out[] = array_values($row);
    }

    return array('headings' => $headings, 'rows' => $out);
}

/**
 * The PDF's HTML: the inventory, the transfers and the destruction records,
 * with the two sentences the screen says once (no legal advice; older
 * backups). Pure.
 *
 * @param array    $rows       pg_privacy_centre_rows()
 * @param array    $transfers  pg_privacy_centre_transfer_rows()
 * @param array    $records    pg_privacy_centre_destruction_records()
 * @param array    $meta       site, prepared_by, generated_at (unix time)
 * @param callable $translate  lang() on the screen
 * @return string
 */
function pg_privacy_centre_pdf_html(array $rows, array $transfers, array $records, array $meta, $translate)
{
    $t = function ($text) use ($translate) {
        return h(call_user_func($translate, $text));
    };
    $count = function ($value) {
        return ($value === null) ? '–' : (string) (int) $value;
    };

    $html = '<!DOCTYPE html><html><head><meta charset="utf-8"><title>' . $t('Personal data inventory') . '</title><style>'
        . '@page{margin:14mm 12mm}'
        . 'body{font-family:"DejaVu Sans",sans-serif;font-size:7.5pt;color:#222}'
        . 'h1{font-size:14pt;margin:0 0 4pt}'
        . 'h2{font-size:10pt;margin:12pt 0 4pt;border-bottom:1px solid #999;padding-bottom:2pt}'
        . 'p{margin:0 0 4pt}'
        . '.muted{color:#666}'
        . 'table{width:100%;border-collapse:collapse;margin:0 0 6pt}'
        . 'th,td{border:1px solid #ccc;padding:2pt 3pt;text-align:left;vertical-align:top}'
        . 'th{background:#f0f0f0}'
        . '.num{text-align:right;white-space:nowrap}'
        . '</style></head><body>';

    $html .= '<h1>' . $t('Personal data inventory') . '</h1>';
    $html .= '<p class="muted">' . h(call_user_func($translate, array(
        'string' => 'Prepared on {var:1} by {var:2} for {var:3}.',
        'vars'   => array(
            date('Y-m-d H:i', (int) ($meta['generated_at'] ?? time())),
            (string) ($meta['prepared_by'] ?? ''),
            (string) ($meta['site'] ?? ''),
        ),
    ))) . '</p>';
    $html .= '<p class="muted">' . $t('The legal grounds, retentions and measures are suggested defaults, not legal advice. Check them for your own notice and your VERBİS record.') . '</p>';

    // The inventory, without the measures column: it is the same sentence on
    // every line, so it is said once below the table.
    $columns = pg_privacy_centre_columns();
    unset($columns['measures']);

    $html .= '<h2>' . $t('Personal data') . '</h2><table><tr>';

    foreach ($columns as $key => $heading) {
        $html .= '<th' . (in_array($key, array('records', 'people'), true) ? ' class="num"' : '') . '>' . $t($heading) . '</th>';
    }

    $html .= '</tr>';

    foreach ($rows as $row) {
        $html .= '<tr>';

        foreach (array_keys($columns) as $key) {
            $html .= in_array($key, array('records', 'people'), true)
                ? '<td class="num">' . $count($row[$key]) . '</td>'
                : '<td>' . h((string) $row[$key]) . '</td>';
        }

        $html .= '</tr>';
    }

    $html .= '</table>';
    $html .= '<p><strong>' . $t('Measures') . ':</strong> ' . $t(pg_privacy_centre_measures()) . '</p>';

    $html .= '<h2>' . $t('Transfers to outside services') . '</h2>';

    if ($transfers) {
        $html .= '<table><tr><th>' . $t('Service') . '</th><th>' . $t('Country') . '</th><th>' . $t('Data it receives') . '</th><th>' . $t('Transfer abroad') . '</th></tr>';

        foreach ($transfers as $transfer) {
            $html .= '<tr><td>' . h($transfer['name']) . ($transfer['unknown'] ? ' <span class="muted">(' . $t('may be in use') . ')</span>' : '') . '</td>'
                . '<td>' . h($transfer['country']) . '</td>'
                . '<td>' . h($transfer['data']) . '</td>'
                . '<td>' . $t($transfer['abroad'] ? 'Yes' : 'No') . '</td></tr>';
        }

        $html .= '</table>';
    } else {
        $html .= '<p class="muted">' . $t('No outside service receives personal data.') . '</p>';
    }

    $html .= '<h2>' . $t('Destruction records') . '</h2>';

    if ($records) {
        $html .= '<table><tr><th>' . $t('Date') . '</th><th>' . $t('Kind') . '</th><th>' . $t('By') . '</th><th class="num">' . $t('Deleted') . '</th><th class="num">' . $t('Anonymised') . '</th></tr>';

        foreach ($records as $record) {
            $kind = ($record['kind'] === 'rule') ? call_user_func($translate, 'Retention rule') . (($record['label'] !== '') ? ': ' . $record['label'] : '') : call_user_func($translate, 'One person erased on request');

            $html .= '<tr><td>' . h(date('Y-m-d H:i', (int) $record['created_at'])) . '</td>'
                . '<td>' . h($kind) . '</td>'
                . '<td>' . h($record['username']) . '</td>'
                . '<td class="num">' . (int) $record['deleted'] . '</td>'
                . '<td class="num">' . (int) $record['anonymised'] . '</td></tr>';
        }

        $html .= '</table>';
    } else {
        $html .= '<p class="muted">' . $t('No personal data has been erased in the last three years.') . '</p>';
    }

    $html .= '<p class="muted" style="margin-top:10pt">' . $t('Erased data stays in backups made before the erasure until those backups are removed.') . '</p>';

    return $html . '</body></html>';
}
