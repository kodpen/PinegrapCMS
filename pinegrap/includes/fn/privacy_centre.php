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

/* ---------------------------------------------------------------------------
   The retention rules card: switches, periods, the interval, the dry run and
   the yes (includes/fn/privacy_retention.php does the work)
   --------------------------------------------------------------------------- */

/**
 * The icon tile of each rule; a rule not named here gets the hourglass.
 * Pure.
 *
 * @return array key => icon, tone
 */
function pg_privacy_centre_rule_faces()
{
    return array(
        'contacts' => array('bi-person-dash', 'pg-tone-blue'),
        'forms'    => array('bi-ui-checks', 'pg-tone-teal'),
        'carts'    => array('bi-cart-x', 'pg-tone-amber'),
        'orders'   => array('bi-bag', 'pg-tone-violet'),
        'requests' => array('bi-envelope-check', 'pg-tone-rose'),
    );
}

/**
 * A rule's state in the words Site Settings › How long we keep data uses:
 * off, on and waiting for the dry run and the yes, or running at night.
 *
 * @param array  $rule      one rule of pg_privacy_rules_normalise()
 * @param int    $interval  days between two passes
 * @param string $last_run  the last finished pass as text, '' for none
 * @return array state (off | waiting | running), line, pill (tone, word) or null
 */
function pg_privacy_centre_rule_state(array $rule, $interval, $last_run)
{
    if (empty($rule['on']) || !isset($rule['period'])) {
        return array('state' => 'off', 'line' => lang('Off: kept until deleted'), 'pill' => null);
    }

    if (empty($rule['approved_at'])) {
        return array('state' => 'waiting', 'line' => lang('On, waiting for a dry run and your yes: kept until deleted'), 'pill' => array('pg-tone-amber', lang('Waiting for your yes')));
    }

    $interval = max(1, (int) $interval);
    $line = ($interval === 1) ? lang('Runs every night') : lang(array('string' => 'Runs at night every {var:1} days', 'vars' => array($interval)));
    $line .= ' · ' . (($last_run !== '') ? lang(array('string' => 'last run {var:1}', 'vars' => array($last_run))) : lang('not run yet'));

    return array('state' => 'running', 'line' => $line, 'pill' => array('pg-tone-teal', lang('Running')));
}

/**
 * Where a sample row of a dry run opens. Pure.
 *
 * @param string $key
 * @param int    $id
 * @return string '' when the rule's rows have no screen of their own
 */
function pg_privacy_centre_rule_sample_url($key, $id)
{
    $screens = array(
        'contacts' => 'edit_contact.php?id=',
        'forms'    => 'edit_submitted_form.php?id=',
        'carts'    => 'view_order.php?id=',
        'orders'   => 'view_order.php?id=',
        'requests' => 'privacy_centre.php?view=requests&request=',
    );

    return isset($screens[$key]) ? $screens[$key] . (int) $id : '';
}

/**
 * A dry run as effect lines: how many rows reach the period, what the first
 * of them would lose by erase entry, and who would be left alone and why.
 * Pure but for lang().
 *
 * @param array $dry     pg_privacy_rule_dry_run()
 * @param array $skips   reason => label (pg_privacy_rules_labels()['skips'])
 * @param array $entries erase entry => label (pg_privacy_erase_labels())
 * @return array list of kind (go | keep | info), text, line
 */
function pg_privacy_centre_dry_run_effects(array $dry, array $skips, array $entries)
{
    $counts = isset($dry['counts']) && is_array($dry['counts']) ? $dry['counts'] : pg_privacy_rule_counts();
    $total = (int) ($dry['total'] ?? 0);
    $checked = (int) ($counts['checked'] ?? 0);
    $number = function ($value) {
        return pg_format_number((int) $value, 0);
    };

    if ($total === 0) {
        return array(array('kind' => 'info', 'text' => lang('Nothing reaches the period now.'), 'line' => ''));
    }

    $effects = array(array(
        'kind' => 'info',
        'text' => lang(array('string' => '{var:1} records reach the period', 'vars' => array($number($total)))),
        'line' => ($checked < $total) ? lang(array('string' => 'The first {var:1} were checked; the lines below count them.', 'vars' => array($number($checked)))) : '',
    ));

    foreach (array('deleted' => 'deleted', 'anonymised' => 'anonymised') as $side => $word) {
        foreach ((array) ($counts[$side] ?? array()) as $entry => $rows) {
            $label = isset($entries[$entry]) ? $entries[$entry] : $entry;
            $effects[] = array(
                'kind' => 'go',
                'text' => ($word === 'deleted')
                    ? lang(array('string' => '{var:1}: {var:2} deleted', 'vars' => array($label, $number($rows))))
                    : lang(array('string' => '{var:1}: {var:2} anonymised', 'vars' => array($label, $number($rows)))),
                'line' => '',
            );
        }
    }

    foreach ((array) ($counts['skipped'] ?? array()) as $reason => $rows) {
        $effects[] = array(
            'kind' => 'keep',
            'text' => lang(array('string' => '{var:1}: {var:2} left alone', 'vars' => array(isset($skips[$reason]) ? $skips[$reason] : $reason, $number($rows)))),
            'line' => '',
        );
    }

    if (($checked > 0) && ((int) ($counts['changed'] ?? 0) === 0) && empty($counts['skipped'])) {
        $effects[] = array('kind' => 'info', 'text' => lang('None of them would change.'), 'line' => '');
    }

    return $effects;
}

/**
 * The forms a per-form period may be given to: each form with submissions,
 * and each form a period is already stored for, with its name.
 *
 * @param array $pages page id => years stored for the forms rule
 * @return array list of id, name, submissions
 */
function pg_privacy_centre_rule_forms(array $pages)
{
    $forms = array();

    if (pg_schema_has('forms', 'page_id')) {
        foreach ((array) db_items("SELECT f.page_id, COUNT(*) AS submissions FROM forms f WHERE f.page_id > 0 GROUP BY f.page_id") as $row) {
            $forms[(int) $row['page_id']] = (int) $row['submissions'];
        }
    }

    foreach (array_keys($pages) as $page) {
        if (!isset($forms[(int) $page])) {
            $forms[(int) $page] = 0;
        }
    }

    if (!$forms) {
        return array();
    }

    $names = array();

    foreach ((array) db_items("SELECT p.page_id, p.page_name, c.form_name FROM page p LEFT JOIN custom_form_pages c ON c.page_id = p.page_id WHERE p.page_id IN ('" . implode("', '", array_map('intval', array_keys($forms))) . "')") as $row) {
        $names[(int) $row['page_id']] = ((string) $row['form_name'] !== '') ? (string) $row['form_name'] : (string) $row['page_name'];
    }

    $list = array();

    foreach ($forms as $page => $submissions) {
        $list[] = array('id' => $page, 'name' => isset($names[$page]) ? $names[$page] : '#' . $page, 'submissions' => $submissions);
    }

    usort($list, function ($a, $b) {
        return strcasecmp($a['name'], $b['name']);
    });

    return $list;
}

/**
 * The rules card of the Privacy centre (id privacy-rules): a switch row a
 * rule with its period, the forms' own periods folded under it, the
 * interval and Save in one form; a rule switched on has Dry run, whose
 * result stands under the form with the yes. Only the role that may change
 * the rules gets fields that save and the yes.
 *
 * @param array $card rules (pg_privacy_rules()), state (pg_privacy_rules_state()),
 *                    labels (pg_privacy_rules_labels()), forms
 *                    (pg_privacy_centre_rule_forms()), may_change (bool),
 *                    dry (key, result of pg_privacy_rule_dry_run(), or null),
 *                    entries (pg_privacy_erase_labels())
 * @return string
 */
function pg_privacy_centre_rules_card(array $card)
{
    $rules = $card['rules'];
    $labels = $card['labels'];
    $may = !empty($card['may_change']);
    $off = $may ? '' : ' disabled';
    $faces = pg_privacy_centre_rule_faces();
    $catalogue = pg_privacy_rules_catalogue();
    $interval_limits = pg_privacy_rules_interval_limits();
    $rows = '';

    foreach ($catalogue as $key => $spec) {
        $rule = $rules[$key];
        $id = 'privacy_rule_' . $key;
        $name = isset($labels['rules'][$key]) ? $labels['rules'][$key] : $key;
        $face = isset($faces[$key]) ? $faces[$key] : array('bi-hourglass-split', 'pg-tone-gray');
        $last = (int) ($card['state'][$key]['last_run_at'] ?? 0);
        $state = pg_privacy_centre_rule_state($rule, $rules['interval'], ($last > 0) ? get_absolute_time(array('timestamp' => $last, 'format' => 'plain_text')) : '');
        $unit = ($spec['unit'] === 'days') ? lang('days') : lang('years');

        $rows .= '<div class="pg-set-switch-row" id="' . $id . '">'
            . '<span class="pg-icon-tile pg-icon-tile-lg ' . h($face[1]) . '" aria-hidden="true"><i class="bi ' . h($face[0]) . '"></i></span>'
            . '<span class="pg-set-fold-text"><label class="pg-set-fold-name" for="' . $id . '_on">' . h($name) . '</label><span class="pg-set-fold-line" id="' . $id . '_line">' . h($state['line']) . '</span></span>'
            . ($state['pill'] ? '<span class="pg-pill ' . h($state['pill'][0]) . '">' . h($state['pill'][1]) . '</span>' : '')
            . (($state['state'] !== 'off') ? '<a class="pg-button pg-button-quiet pg-button-sm" href="privacy_centre.php?dry_run=' . h($key) . '#privacy_rules_dry_run" aria-label="' . h(lang('Dry run') . ': ' . $name) . '">' . h(lang('Dry run')) . '</a>' : '')
            . '<span class="pg-input pg-input-sm" style="width:8.5rem"><input type="text" inputmode="numeric" name="rules[' . h($key) . '][period]" id="' . $id . '_period" value="' . h(($rule['period'] === null) ? '' : (string) $rule['period']) . '"'
            . (($spec['default'] === null) ? ' placeholder="' . h(lang('Never')) . '"' : '')
            . ' aria-label="' . h(lang('Period') . ': ' . $name) . '" aria-describedby="' . $id . '_bounds"' . $off . '><span class="pg-input-end">' . h($unit) . '</span></span>'
            . '<span class="visually-hidden" id="' . $id . '_bounds">' . h(lang(array('string' => '{var:1} to {var:2}', 'vars' => array($spec['min'], $spec['max'])))) . ' ' . h($unit) . '</span>'
            . '<input type="checkbox" class="pg-switch" name="rules[' . h($key) . '][on]" value="1" id="' . $id . '_on" aria-describedby="' . $id . '_line"' . ($rule['on'] ? ' checked' : '') . $off . '>'
            . '</div>';
    }

    // The forms' own periods: empty follows the rule, 0 keeps the form's
    // submissions.
    $forms = '';

    foreach ($card['forms'] as $form) {
        $field = 'privacy_rule_form_' . (int) $form['id'];
        $years = isset($rules['forms']['pages'][$form['id']]) ? (string) $rules['forms']['pages'][$form['id']] : '';

        $forms .= '<div class="pg-card-inner-row">'
            . '<div class="flex-grow-1 min-width-0"><label class="pg-list-row-title" for="' . $field . '">' . h($form['name']) . '</label>'
            . '<div class="pg-text-meta">' . h(lang(array('string' => '{var:1} submissions', 'vars' => array(pg_format_number((int) $form['submissions'], 0))))) . '</div></div>'
            . '<span class="pg-input pg-input-sm" style="width:8.5rem"><input type="text" inputmode="numeric" name="rules[forms][pages][' . (int) $form['id'] . ']" id="' . $field . '" value="' . h($years) . '"' . $off . '><span class="pg-input-end">' . h(lang('years')) . '</span></span>'
            . '</div>';
    }

    $forms = ($forms !== '')
        ? '<details class="mt-5"><summary class="pg-field-label">' . h(lang('Each form\'s own period')) . '</summary>'
            . '<div class="pg-field-help mb-3">' . h(lang('Empty: the rule\'s period. 0: this form\'s submissions are kept.')) . '</div>'
            . '<div class="pg-card-inner">' . $forms . '</div></details>'
        : '';

    $html = '
            <div class="card mb-7" id="privacy-rules">
                <h2 class="card-header bg-reset border-0 pg-title-section">' . h(lang('Retention rules')) . '</h2>
                <div class="card-body">
                    <p class="pg-card-sub">' . h(lang('Each rule is off until you switch it on. Its first run is a dry run: you see what it would remove, and it runs only after your yes.')) . '</p>
                    <form method="post" action="privacy_centre.php">' . get_token_field() . '
                        <input type="hidden" name="action" value="rules_save">
                        <div class="pg-set-fold">' . $rows . '</div>
                        <div class="pg-field-help">' . h(lang('Never removed by a rule: invoices, accounting records, order documents, and anyone with an open order or return, a gift card or store credit balance, a booking ahead or a panel role.')) . '</div>
                        ' . $forms . '
                        <div class="mt-5">
                            <label class="pg-field-label" for="privacy_rules_interval">' . h(lang('Run the rules every')) . '</label>
                            <span class="pg-input" style="width:8.5rem"><input type="text" inputmode="numeric" name="interval" id="privacy_rules_interval" value="' . (int) $rules['interval'] . '" aria-describedby="privacy_rules_interval_help"' . $off . '><span class="pg-input-end">' . h(lang('days')) . '</span></span>
                            <div class="pg-field-help" id="privacy_rules_interval_help">' . h(lang(array('string' => 'At night, between midnight and six; at most every {var:1} days.', 'vars' => array($interval_limits['max'])))) . '</div>
                        </div>'
        . ($may
            ? '
                        <div class="d-flex align-items-center flex-wrap gap-3 mt-5">
                            <button type="submit" class="pg-button pg-button-quiet">' . h(lang('Save')) . '</button>
                            <span class="pg-text-meta">' . h(lang('Switching a rule or changing its period asks for a new dry run and your yes.')) . '</span>
                        </div>'
            : '
                        <div class="pg-text-meta mt-5">' . h(lang('Your role reads the rules; the role that saves Site Settings changes them.')) . '</div>') . '
                    </form>' . pg_privacy_centre_dry_run_card($card) . '
                </div>
            </div>';

    return $html;
}

/**
 * A rule's dry run under the rules form: its effects, a few rows to look
 * at, and the yes while the rule waits for it.
 *
 * @param array $card see pg_privacy_centre_rules_card()
 * @return string '' when no dry run was asked
 */
function pg_privacy_centre_dry_run_card(array $card)
{
    if (empty($card['dry']) || !isset($card['rules'][$card['dry']['key']])) {
        return '';
    }

    $key = $card['dry']['key'];
    $result = $card['dry']['result'];
    $name = isset($card['labels']['rules'][$key]) ? $card['labels']['rules'][$key] : $key;
    $rule = $card['rules'][$key];
    $effects = '';
    $icons = array('go' => 'bi-x-circle-fill', 'keep' => 'bi-check-circle-fill', 'info' => 'bi-info-circle-fill');

    foreach (pg_privacy_centre_dry_run_effects($result, $card['labels']['skips'], $card['entries']) as $effect) {
        $effects .= '<li class="pg-effect pg-effect-' . $effect['kind'] . '"><i class="bi ' . $icons[$effect['kind']] . '" aria-hidden="true"></i>'
            . '<div>' . h($effect['text']) . (($effect['line'] !== '') ? '<span class="pg-effect-line">' . h($effect['line']) . '</span>' : '') . '</div></li>';
    }

    $sample = array();

    foreach ((array) ($result['sample'] ?? array()) as $id) {
        $url = pg_privacy_centre_rule_sample_url($key, $id);
        $sample[] = ($url !== '') ? '<a href="' . h($url) . '">#' . (int) $id . '</a>' : '#' . (int) $id;
    }

    $waiting = !empty($rule['on']) && ($rule['period'] !== null) && empty($rule['approved_at']);
    $yes = ($waiting && !empty($card['may_change']))
        ? '<form method="post" action="privacy_centre.php" class="d-inline">' . get_token_field()
            . '<input type="hidden" name="action" value="rules_approve"><input type="hidden" name="rule" value="' . h($key) . '">'
            . '<button type="submit" class="pg-button pg-button-danger">' . h(lang('Yes, run this rule')) . '</button></form>'
        : '';

    return '
                    <section class="mt-6 pt-5 border-top" id="privacy_rules_dry_run" aria-labelledby="privacy_rules_dry_run_title">
                        <div class="pg-title-section" id="privacy_rules_dry_run_title">' . h(lang('Dry run') . ': ' . $name) . '</div>
                        <p class="pg-card-sub">' . h(lang('Nothing was changed. This is what the rule would do if it ran now.')) . '</p>
                        <ul class="pg-card-inner pg-effects">' . $effects . '</ul>'
        . ($sample ? '
                        <p class="pg-text-meta mt-3">' . h(lang('Rows to look at:')) . ' ' . implode(', ', $sample) . '</p>' : '') . '
                        <div class="d-flex justify-content-end flex-wrap gap-3 mt-5">
                            <a class="pg-button pg-button-quiet" href="privacy_centre.php#privacy-rules">' . h(lang('Close')) . '</a>' . $yes . '
                        </div>
                    </section>';
}
