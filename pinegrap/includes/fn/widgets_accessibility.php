<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The accessibility statement (system widget 'accessibility_statement' and
 * the printable view of view_accessibility.php?statement=1): the statement
 * the EU Accessibility Act and EN 301 549 ask for, in the W3C statement
 * structure. Its fixed texts come from Site Settings › Visitors and privacy ›
 * Accessibility Statement (config_text 'a11y_statement', JSON); the
 * conformance claim and the known limitations come from the nightly
 * accessibility report (includes/fn/a11y.php).
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
 * The settings card's fields with their length limits, in card order.
 *
 * @return array<string,int> field => maximum length in characters
 */
function pg_a11y_statement_fields()
{
    return array(
        'organisation' => 200,
        'scope'        => 1000,
        'contact'      => 500,
        'feedback'     => 2000,
        'enforcement'  => 2000,
        'date'         => 10,
    );
}

/**
 * The stored settings made safe: every field a trimmed string within its
 * limit, the date a real Y-m-d date or ''. Pure.
 *
 * @param mixed $input decoded JSON or the posted fields
 * @return array<string,string>
 */
function pg_a11y_statement_clean($input)
{
    $clean = array();

    foreach (pg_a11y_statement_fields() as $field => $max) {
        $value = (is_array($input) && isset($input[$field]) && is_scalar($input[$field])) ? (string) $input[$field] : '';
        $value = trim(str_replace("\r\n", "\n", $value));

        if (function_exists('mb_substr')) {
            $value = mb_substr($value, 0, $max, 'UTF-8');
        } else {
            $value = substr($value, 0, $max);
        }

        $clean[$field] = $value;
    }

    if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $clean['date'], $m) || !checkdate((int) $m[2], (int) $m[3], (int) $m[1])) {
        $clean['date'] = '';
    }

    return $clean;
}

/**
 * The saved settings.
 *
 * @return array<string,string>
 */
function pg_a11y_statement_settings()
{
    $stored = json_decode(pg_config_text('a11y_statement'), true);

    return pg_a11y_statement_clean(is_array($stored) ? $stored : array());
}

/**
 * The conformance the statement claims, from the report's open findings:
 * 'unchecked' until a full pass has finished (no claim is made), 'full' with
 * no finding, 'none' when error findings stand on at least half the pages
 * checked, else 'partial'. Pure.
 *
 * @param array $summary     pg_a11y_report_summary(): code => pages, occurrences
 * @param array $rules       pg_a11y_rules(): code => severity …
 * @param int   $error_pages Pages with at least one error finding.
 * @param int   $pages       Pages the report covers.
 * @param int   $last_run    Time the last full pass ended, 0 for never.
 * @return string
 */
function pg_a11y_conformance($summary, $rules, $error_pages, $pages, $last_run)
{
    if (!$last_run) {
        return 'unchecked';
    }

    $found = false;

    foreach ((array) $summary as $code => $row) {
        if (isset($rules[$code]) && !empty($row['pages'])) {
            $found = true;
        }
    }

    if (!$found) {
        return 'full';
    }

    if (($error_pages > 0) && ($pages > 0) && (($error_pages * 2) >= $pages)) {
        return 'none';
    }

    return 'partial';
}

/**
 * The claim in a sentence of the statement.
 *
 * @param string $verdict pg_a11y_conformance()
 * @return string plain text
 */
function pg_a11y_conformance_text($verdict)
{
    switch ($verdict) {
        case 'full':
            return lang('Fully conformant: the content fully conforms to the standard without any exceptions.');
        case 'partial':
            return lang('Partially conformant: some parts of the content do not fully conform to the standard.');
        case 'none':
            return lang('Non-conformant: the content does not conform to the standard.');
    }

    return lang('Not yet assessed: the first full accessibility check has not finished.');
}

/**
 * The known limitations, one row per check with findings, errors first and
 * then by the number of pages. Pure.
 *
 * @param array $summary pg_a11y_report_summary()
 * @param array $rules   pg_a11y_rules()
 * @return array list of array(code, criterion, level, severity, pages)
 */
function pg_a11y_statement_limits($summary, $rules)
{
    $order = array('error' => 0, 'warning' => 1, 'notice' => 2);
    $limits = array();

    foreach ((array) $summary as $code => $row) {
        if (isset($rules[$code]) && !empty($row['pages'])) {
            $severity = $rules[$code]['severity'];
            $limits[] = array(
                'code'      => $code,
                'criterion' => $rules[$code]['criterion'],
                'level'     => $rules[$code]['level'],
                'severity'  => $severity,
                'pages'     => (int) $row['pages'],
                'rank'      => isset($order[$severity]) ? $order[$severity] : 3,
            );
        }
    }

    usort($limits, function ($a, $b) {
        if ($a['rank'] !== $b['rank']) {
            return $a['rank'] - $b['rank'];
        }

        if ($a['pages'] !== $b['pages']) {
            return $b['pages'] - $a['pages'];
        }

        return strcmp($a['code'], $b['code']);
    });

    foreach ($limits as $i => $limit) {
        unset($limits[$i]['rank']);
    }

    return $limits;
}

/**
 * What the report holds for the statement.
 *
 * @return array summary, error_pages, pages, last_run
 */
function pg_a11y_statement_data()
{
    require_once(PG_FUNCTIONS_DIR . '/includes/fn/a11y.php');

    $runs = function_exists('pg_cron_health_last_runs') ? pg_cron_health_last_runs() : array();

    return array(
        'summary'     => pg_a11y_report_summary(),
        'error_pages' => (int) db_value(
            "SELECT COUNT(DISTINCT entity_id) FROM seo_issue
            WHERE (entity_type = 'page') AND (LEFT(code, 5) = 'a11y_') AND (severity = 'error')"),
        'pages'       => count(pg_a11y_pages()),
        'last_run'    => isset($runs['seo_a11y_job']) ? (int) $runs['seo_a11y_job'] : 0,
    );
}

/**
 * The statement's tokens and its limitation rows. Pure but for lang() and
 * the report's labels.
 *
 * @param array  $settings pg_a11y_statement_clean()
 * @param array  $data     pg_a11y_statement_data()
 * @param array  $rules    pg_a11y_rules()
 * @param string $site     The site's host name, used when no organisation is set.
 * @return array array('tokens' => array, 'rows' => array, 'verdict' => string)
 */
function pg_a11y_statement_parts($settings, $data, $rules, $site)
{
    $verdict = pg_a11y_conformance($data['summary'], $rules, $data['error_pages'], $data['pages'], $data['last_run']);
    $criteria = pg_a11y_criteria();
    $rows = array();

    foreach (pg_a11y_statement_limits($data['summary'], $rules) as $limit) {
        $rows[] = array(
            '__limit_label'     => pg_a11y_issue_label($limit['code']),
            '__limit_criterion' => $limit['criterion'] . (isset($criteria[$limit['criterion']]) ? ' ' . $criteria[$limit['criterion']] : ''),
            '__limit_level'     => $limit['level'],
            '__limit_pages'     => (string) $limit['pages'],
        );
    }

    $organisation = ($settings['organisation'] !== '') ? $settings['organisation'] : $site;

    return array(
        'verdict' => $verdict,
        'rows'    => $rows,
        'tokens'  => array(
            '__a11y_organisation' => $organisation,
            '__a11y_scope'        => ($settings['scope'] !== '') ? $settings['scope'] : $site,
            '__a11y_standard'     => 'WCAG 2.2 AA, EN 301 549',
            '__a11y_conformance'  => pg_a11y_conformance_text($verdict),
            '__a11y_contact'      => $settings['contact'],
            '__a11y_feedback'     => $settings['feedback'],
            '__a11y_enforcement'  => $settings['enforcement'],
            '__a11y_date'         => ($settings['date'] !== '') ? $settings['date'] : date('Y-m-d'),
            '__a11y_last_check'   => $data['last_run'] ? date('Y-m-d', $data['last_run']) : '',
            '__a11y_pages'        => (string) (int) $data['pages'],
            '__limit_total'       => (string) count($rows),
        ),
    );
}

/**
 * The flags a widget tree and the print view show sections by.
 *
 * @param array $parts pg_a11y_statement_parts()
 * @return array<string,bool>
 */
function pg_a11y_statement_flags($parts)
{
    $t = $parts['tokens'];

    return array(
        'has_limitations' => (bool) $parts['rows'],
        'no_limitations'  => !$parts['rows'],
        'has_contact'     => $t['__a11y_contact'] !== '',
        'has_feedback'    => $t['__a11y_feedback'] !== '',
        'has_enforcement' => $t['__a11y_enforcement'] !== '',
        'has_last_check'  => $t['__a11y_last_check'] !== '',
        'a11y_assessed'   => $parts['verdict'] !== 'unchecked',
    );
}

/**
 * The statement as a printable HTML document (view_accessibility.php
 * ?statement=1), in the W3C statement structure. Every value is escaped.
 *
 * @param array $parts pg_a11y_statement_parts()
 * @return string
 */
function pg_a11y_statement_html($parts)
{
    $t = $parts['tokens'];
    $flags = pg_a11y_statement_flags($parts);
    $p = function ($text) {
        return '<p>' . nl2br(h($text), false) . '</p>';
    };

    $html = '<!DOCTYPE html><html lang="' . h(lang(array('info' => ''))) . '"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">'
        . '<title>' . h(lang('Accessibility Statement')) . '</title><style>body{font-family:system-ui,sans-serif;line-height:1.5;color:#212529;max-width:48rem;margin:2rem auto;padding:0 1rem}'
        . 'h1{font-size:1.75rem}h2{font-size:1.25rem;margin-top:1.75rem}table{border-collapse:collapse;width:100%}th,td{border:1px solid #adb5bd;padding:.25rem .5rem;text-align:left}</style></head><body><main>'
        . '<h1>' . h(lang(array('string' => 'Accessibility Statement for {var:1}', 'vars' => array($t['__a11y_organisation'])))) . '</h1>'
        . $p(lang(array('string' => '{var:1} is committed to ensuring digital accessibility for people with disabilities. This statement applies to: {var:2}.', 'vars' => array($t['__a11y_organisation'], $t['__a11y_scope']))))
        . '<h2>' . h(lang('Conformance status')) . '</h2>'
        . $p(lang(array('string' => 'Standard: {var:1}.', 'vars' => array($t['__a11y_standard'])))) . $p($t['__a11y_conformance'])
        . '<h2>' . h(lang('Known limitations')) . '</h2>';

    if ($flags['has_limitations']) {
        $html .= '<table><thead><tr><th scope="col">' . h(lang('Problem')) . '</th><th scope="col">' . h(lang('Criterion')) . '</th><th scope="col">' . h(lang('Level')) . '</th><th scope="col">' . h(lang('Pages')) . '</th></tr></thead><tbody>';

        foreach ($parts['rows'] as $row) {
            $html .= '<tr><td>' . h($row['__limit_label']) . '</td><td>' . h($row['__limit_criterion']) . '</td><td>' . h($row['__limit_level']) . '</td><td>' . h($row['__limit_pages']) . '</td></tr>';
        }

        $html .= '</tbody></table>';
    } else {
        $html .= $p($flags['a11y_assessed'] ? lang('The automatic check found no known limitations.') : lang('Known limitations are listed once the first full check has finished.'));
    }

    $html .= '<h2>' . h(lang('Feedback')) . '</h2>'
        . $p(lang('We welcome your feedback on the accessibility of this site. Please let us know if you meet accessibility barriers.'))
        . ($flags['has_contact'] ? $p($t['__a11y_contact']) : '')
        . ($flags['has_feedback'] ? $p($t['__a11y_feedback']) : '')
        . '<h2>' . h(lang('Assessment approach')) . '</h2>'
        . $p(lang(array('string' => 'Self-evaluation with an automatic check of {var:1} published pages every night. An automatic check covers only part of each criterion; the rest is reviewed by people.', 'vars' => array($t['__a11y_pages']))))
        . ($flags['has_enforcement'] ? '<h2>' . h(lang('Enforcement procedure')) . '</h2>' . $p($t['__a11y_enforcement']) : '')
        . '<h2>' . h(lang('Date')) . '</h2>'
        . $p(lang(array('string' => 'This statement was prepared on {var:1}.', 'vars' => array($t['__a11y_date']))))
        . ($flags['has_last_check'] ? $p(lang(array('string' => 'Last automatic check: {var:1}.', 'vars' => array($t['__a11y_last_check'])))) : '');

    return $html . '</main></body></html>';
}

// Render an 'accessibility_statement' system widget.
//
// loop_area: one row per known limitation. Row tokens: ^^__limit_label^^,
// ^^__limit_criterion^^ (number and name), ^^__limit_level^^ (A, AA),
// ^^__limit_pages^^. Tokens: ^^__a11y_organisation^^, ^^__a11y_scope^^,
// ^^__a11y_standard^^, ^^__a11y_conformance^^, ^^__a11y_contact^^,
// ^^__a11y_feedback^^, ^^__a11y_enforcement^^, ^^__a11y_date^^,
// ^^__a11y_last_check^^, ^^__a11y_pages^^, ^^__limit_total^^. Flags: see
// pg_a11y_statement_flags(). No Messages block.
// $parts: for the tests, the statement to draw in place of the one read.
function _render_system_widget_accessibility_statement($tree_json, $widget_id, $cfg = array(), $mode = 'preview', $parts = null)
{
    static $placement = 0;
    $widget_id = (int) $widget_id;
    if ($tree_json === '' || $tree_json === null) return '';
    $tree = json_decode($tree_json, true);
    if (!is_array($tree)) return '';

    if ($parts === null) {
        require_once(PG_FUNCTIONS_DIR . '/includes/fn/a11y.php');
        $parts = pg_a11y_statement_parts(pg_a11y_statement_settings(), pg_a11y_statement_data(), pg_a11y_rules(), HOSTNAME_SETTING);
    }

    _eo_apply_visibility_bindings($tree, pg_a11y_statement_flags($parts));
    _pg_inject_messages_node($tree, 'accessibility_statement');

    $placed = ++$placement;
    $again  = pg_sw_widget_drawn_before($widget_id) ? $placed : 0;
    $split = _split_widget_tree($tree);
    $loop_rendered = '';
    if ($split['loop_children'] !== null) {
        $row_tree = array('type' => 'root', 'props' => array(), 'children' => $split['loop_children']);
        foreach ($parts['rows'] as $i => $row) {
            $html = '';
            foreach ($row_tree['children'] as $child) $html .= _render_tree_node($child, 0, 0);
            $html = pg_sw_sweep_tokens(_pg_sw_fill_tokens(trim($html), pg_sw_escape_token_values($row)));
            $loop_rendered .= pg_sw_uniquify_loop_row_ids($html, $widget_id, $i + 1, $again);
        }
    }

    $static = trim(_render_tree_node($split['static_tree'], 0, 0));
    $static = pg_sw_uniquify_row_ids(pg_sw_sweep_tokens(_pg_sw_fill_tokens($static, pg_sw_escape_token_values($parts['tokens']))), $widget_id, 1000 + $placed);
    return str_replace('<!--pg-loop-slot-->', $loop_rendered, $static);
}
