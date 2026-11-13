<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - an application agent's report about itself: the model it runs
 * on and its plan's usage windows as it reads them (percent used, reset
 * time), sent with its own key (POST /workspace/agent-report). The site
 * cannot check either value: they are kept as the agent's report, the last
 * one only (ws_agents.report_model, report_usage, report_at), and shown on
 * the Agents card with their age.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

// The longest model name a report carries.
define('WS_AGENT_REPORT_MODEL_MAX', 80);

// The most usage windows one report carries.
define('WS_AGENT_REPORT_WINDOWS_MAX', 4);

// A report older than this is drawn muted on the card.
define('WS_AGENT_REPORT_STALE_AFTER', 86400);

/**
 * The usage windows a report may name, key => English label, in the order
 * the card shows them. Pure.
 *
 * @return string[]
 */
function ws_agent_report_window_list()
{
    return array(
        'five_hour'    => '5-hour',
        'daily'        => 'Daily',
        'weekly'       => 'Weekly',
        'weekly_model' => 'Weekly, this model',
        'monthly'      => 'Monthly',
    );
}

/**
 * The window labels translated.
 *
 * @return string[] key => label
 */
function ws_agent_report_window_labels()
{
    // Each label written out, so the language check finds every key.
    return array(
        'five_hour'    => lang('5-hour'),
        'daily'        => lang('Daily'),
        'weekly'       => lang('Weekly'),
        'weekly_model' => lang('Weekly, this model'),
        'monthly'      => lang('Monthly'),
    );
}

/**
 * Checks a report before it is written: a model name of at most 80
 * characters and at most four windows, each exactly {name, percent,
 * resets_at} with a name of the list given once, a whole percent 0-100 and a
 * reset time (unix seconds, already parsed). Anything else refuses the whole
 * report. A report needs a model or a window. Pure.
 *
 * @param mixed $model
 * @param mixed $windows list of window objects, null for none
 * @return array ok, error (a sentence), field, values (model, windows, usage: the stored JSON)
 */
function ws_agent_report_check($model, $windows)
{
    $fail = function ($error, $field) {
        return array('ok' => false, 'error' => $error, 'field' => $field, 'values' => null);
    };

    if (($model !== null) && !is_string($model)) {
        return $fail(lang('The model is a name of up to 80 characters.'), 'model');
    }

    $model = trim((string) $model);

    // Text from outside: printable, one line.
    if ((mb_strlen($model) > WS_AGENT_REPORT_MODEL_MAX) || (preg_match('/[\p{C}]/u', $model) === 1) || (preg_match('//u', $model) !== 1)) {
        return $fail(lang('The model is a name of up to 80 characters.'), 'model');
    }

    if ($windows === null) {
        $windows = array();
    }

    if (!is_array($windows) || (count($windows) > WS_AGENT_REPORT_WINDOWS_MAX) || (array_values($windows) !== $windows)) {
        return $fail(lang('Give at most four usage windows.'), 'windows');
    }

    $names = ws_agent_report_window_list();
    $out = array();

    foreach ($windows as $window) {
        $keys = is_array($window) ? array_keys($window) : array();
        sort($keys);

        if ($keys !== array('name', 'percent', 'resets_at')) {
            return $fail(lang('Each usage window is {name, percent, resets_at}.'), 'windows');
        }

        $name = $window['name'];

        if (!is_string($name) || !isset($names[$name]) || isset($out[$name])) {
            return $fail(lang('Name each usage window once, from the list.'), 'windows');
        }

        $percent = $window['percent'];

        if (!is_int($percent) || ($percent < 0) || ($percent > 100)) {
            return $fail(lang('A usage window\'s percent is a whole number from 0 to 100.'), 'windows');
        }

        $resets = $window['resets_at'];

        if (!is_int($resets) || ($resets <= 0)) {
            return $fail(lang('A usage window needs its reset time.'), 'windows');
        }

        $out[$name] = array('name' => $name, 'percent' => $percent, 'resets_at' => $resets);
    }

    if (($model === '') && !$out) {
        return $fail(lang('Report a model or a usage window.'), 'model');
    }

    // Kept in the list's order, whatever order they came in.
    $ordered = array_values(array_filter(array_map(function ($name) use ($out) {
        return $out[$name] ?? null;
    }, array_keys($names))));

    return array('ok' => true, 'error' => '', 'field' => '', 'values' => array(
        'model'   => $model,
        'windows' => $ordered,
        'usage'   => $ordered ? json_encode($ordered) : '',
    ));
}

/**
 * The windows of a stored report, read back as the check wrote them; a
 * value that does not read so gives no windows. Pure.
 *
 * @param string $usage ws_agents.report_usage
 * @return array[] name, percent, resets_at
 */
function ws_agent_report_windows($usage)
{
    $list = json_decode((string) $usage, true);

    if (!is_array($list)) {
        return array();
    }

    $check = ws_agent_report_check('x', $list);

    return $check['ok'] ? $check['values']['windows'] : array();
}

/**
 * Is the report columns' schema there? The 2026.11.0 step 11.0.195 adds
 * them; files can land before it runs.
 *
 * @return bool
 */
function ws_agent_report_ready()
{
    return ws_agents_ready() && pg_schema_has('ws_agents', 'report_at');
}

/**
 * Writes a checked report on the agent's own row: the last one only.
 *
 * @param int   $agent_id
 * @param array $values ws_agent_report_check() values
 * @param int   $now
 */
function ws_agent_report_save($agent_id, $values, $now)
{
    db("UPDATE ws_agents SET report_model = '" . e((string) $values['model']) . "', report_usage = '" . e((string) $values['usage']) . "', report_at = '" . (int) $now . "'
        WHERE id = '" . (int) $agent_id . "'");
}

/**
 * An application agent's last report as the Agents card shows it: the
 * model, each window's percent and reset time, and the report's age; stale
 * once older than a day. Nothing for another kind (a model assistant's
 * model is on its form) or before a first report. Pure but for lang() and
 * date().
 *
 * @param array $agent ws_agent_shape()
 * @param int   $now
 * @return array text ('' for none), stale
 */
function ws_agent_report_words($agent, $now)
{
    $at = (int) ($agent['report_at'] ?? 0);

    if ((($agent['kind'] ?? '') !== 'app') || ($at <= 0)) {
        return array('text' => '', 'stale' => false);
    }

    $labels = ws_agent_report_window_labels();
    $parts = array();
    $model = (string) ($agent['report_model'] ?? '');

    if ($model !== '') {
        $parts[] = $model;
    }

    foreach (ws_agent_report_windows($agent['report_usage'] ?? '') as $window) {
        $parts[] = lang(array('string' => '{var:1} {var:2}%, resets {var:3}', 'vars' => array($labels[$window['name']], $window['percent'], date('d.m H:i', $window['resets_at']))));
    }

    $parts[] = lang(array('string' => 'reported {var:1}', 'vars' => ws_seen_ago_words($at, $now)));

    return array('text' => implode(' · ', $parts), 'stale' => ((int) $now - $at) >= WS_AGENT_REPORT_STALE_AFTER);
}
