<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - an application agent's report about itself: the model it runs
 * on, its plan's usage windows as it reads them (percent used, reset time),
 * its context (the tokens it uses; the window's size is typed on its form,
 * ws_agents.context_size), the project and the git
 * branch it works on, sent with its own key (POST /workspace/agent-report).
 * The site cannot check any of it: it is kept as the agent's report, the
 * last one only (ws_agents.report_model, report_at, and report_usage holding
 * windows, context, project and branch as one JSON object; an older report
 * stored the windows alone as a list, which still reads), and shown on the
 * Agents card with its age.
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

// The largest context window a report names, in tokens.
define('WS_AGENT_REPORT_CONTEXT_MAX', 10000000);

// The longest project name a report carries.
define('WS_AGENT_REPORT_PROJECT_MAX', 80);

// The longest branch name a report carries.
define('WS_AGENT_REPORT_BRANCH_MAX', 120);

// A report older than this is drawn muted on the card.
define('WS_AGENT_REPORT_STALE_AFTER', 86400);

// In an agent channel an agent is asked for its model once its last report
// is older than this many seconds (ws_agent_report_brief_line()).
define('WS_AGENT_REPORT_ASK_AFTER', 3600);

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
 * Checks a context as a report gives it: {used} or {used, size}, whole
 * numbers of tokens, used from 0 to WS_AGENT_REPORT_CONTEXT_MAX; a size, when
 * given, from 1 to WS_AGENT_REPORT_CONTEXT_MAX with used at most size. An
 * agent sends the tokens it uses; the window's size is typed on its form
 * (ws_agents.context_size), a reported size is kept for a harness that reads
 * its own. Pure but for lang().
 *
 * @param mixed $context
 * @return array ok, error, context (used, and size when given)
 */
function ws_agent_report_context_check($context)
{
    $keys = is_array($context) ? array_keys($context) : array();
    sort($keys);
    $used = $context['used'] ?? null;
    $size = $context['size'] ?? null;
    $sized = ($keys === array('size', 'used'));

    $ok = ($sized || ($keys === array('used'))) && is_int($used) && ($used >= 0) && ($used <= WS_AGENT_REPORT_CONTEXT_MAX)
        && (!$sized || (is_int($size) && ($size >= 1) && ($size <= WS_AGENT_REPORT_CONTEXT_MAX) && ($used <= $size)));

    if (!$ok) {
        return array('ok' => false, 'error' => lang('The context is {used}, or {used, size}: whole numbers of tokens, used at most size, at most 10,000,000.'), 'context' => null);
    }

    return array('ok' => true, 'error' => '', 'context' => $sized ? array('used' => $used, 'size' => $size) : array('used' => $used));
}

/**
 * An agent's context window size as typed on its form: empty is 0 (not
 * entered), spaces, dots and commas dropped, otherwise a whole number up to
 * WS_AGENT_REPORT_CONTEXT_MAX. Pure but for lang().
 *
 * @param mixed $raw
 * @return array ok, value (int), error
 */
function ws_agent_context_size_check($raw)
{
    $text = str_replace(array(' ', '.', ','), '', trim((string) $raw));

    if ($text === '') {
        return array('ok' => true, 'value' => 0, 'error' => '');
    }

    if ((preg_match('/^[0-9]{1,8}$/', $text) !== 1) || ((int) $text > WS_AGENT_REPORT_CONTEXT_MAX)) {
        return array('ok' => false, 'value' => 0, 'error' => lang('Give the context window as a whole number of tokens up to 10,000,000, or leave it empty.'));
    }

    return array('ok' => true, 'value' => (int) $text, 'error' => '');
}

/**
 * Is a value one printable line of UTF-8 text of at most $max characters?
 * Pure.
 *
 * @param string $text
 * @param int    $max
 * @return bool
 */
function ws_agent_report_line_ok($text, $max)
{
    return (preg_match('//u', $text) === 1) && (mb_strlen($text) <= $max) && (preg_match('/[\p{C}]/u', $text) !== 1);
}

/**
 * Is a value a git branch name the report takes: at most 120 of a ref's
 * plain characters (letters, digits, . _ / -), no "..", no "//", not
 * starting with / or -, not ending with / or a dot. Pure.
 *
 * @param string $branch
 * @return bool
 */
function ws_agent_report_branch_ok($branch)
{
    return (strlen($branch) <= WS_AGENT_REPORT_BRANCH_MAX)
        && (preg_match('#^[A-Za-z0-9._/-]+$#', $branch) === 1)
        && (strpos($branch, '..') === false) && (strpos($branch, '//') === false)
        && (strpos('/-', $branch[0]) === false) && (strpos('/.', substr($branch, -1)) === false);
}

/**
 * Checks a report before it is written: a model name of at most 80
 * characters; at most four windows, each exactly {name, percent,
 * resets_at} with a name of the list given once, a whole percent 0-100 and a
 * reset time (unix seconds, already parsed); a context
 * (ws_agent_report_context_check()); a project of one printable line of at
 * most 80 characters; a branch (ws_agent_report_branch_ok()). Anything else
 * refuses the whole report. A report needs one of them. Pure.
 *
 * @param mixed $model
 * @param mixed $windows list of window objects, null for none
 * @param array $fields  context, project, branch; a field left out or null is none
 * @return array ok, error (a sentence), field, values (model, windows, context, project, branch, usage: the stored JSON)
 */
function ws_agent_report_check($model, $windows, $fields = array())
{
    $fail = function ($error, $field) {
        return array('ok' => false, 'error' => $error, 'field' => $field, 'values' => null);
    };

    if (($model !== null) && !is_string($model)) {
        return $fail(lang('The model is a name of up to 80 characters.'), 'model');
    }

    $model = trim((string) $model);

    // Text from outside: printable, one line.
    if (!ws_agent_report_line_ok($model, WS_AGENT_REPORT_MODEL_MAX)) {
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

    $context = null;

    if (isset($fields['context'])) {
        $checked = ws_agent_report_context_check($fields['context']);

        if (!$checked['ok']) {
            return $fail($checked['error'], 'context');
        }

        $context = $checked['context'];
    }

    $project = $fields['project'] ?? null;

    if (($project !== null) && !is_string($project)) {
        return $fail(lang('The project is a name of up to 80 characters.'), 'project');
    }

    $project = trim((string) $project);

    if (!ws_agent_report_line_ok($project, WS_AGENT_REPORT_PROJECT_MAX)) {
        return $fail(lang('The project is a name of up to 80 characters.'), 'project');
    }

    $branch = $fields['branch'] ?? null;

    if (($branch !== null) && !is_string($branch)) {
        return $fail(lang('The branch is a git branch name of up to 120 characters.'), 'branch');
    }

    $branch = trim((string) $branch);

    if (($branch !== '') && !ws_agent_report_branch_ok($branch)) {
        return $fail(lang('The branch is a git branch name of up to 120 characters.'), 'branch');
    }

    if (($model === '') && !$out && ($context === null) && ($project === '') && ($branch === '')) {
        return $fail(lang('Report a model, a usage window, the context, the project or the branch.'), 'model');
    }

    // Kept in the list's order, whatever order they came in.
    $ordered = array_values(array_filter(array_map(function ($name) use ($out) {
        return $out[$name] ?? null;
    }, array_keys($names))));

    $stored = '';

    if ($ordered || ($context !== null) || ($project !== '') || ($branch !== '')) {
        $stored = json_encode(array('windows' => $ordered, 'context' => $context, 'project' => $project, 'branch' => $branch), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    return array('ok' => true, 'error' => '', 'field' => '', 'values' => array(
        'model'   => $model,
        'windows' => $ordered,
        'context' => $context,
        'project' => $project,
        'branch'  => $branch,
        'usage'   => (string) $stored,
    ));
}

/**
 * A stored report read back through the same check: the object of windows,
 * context, project and branch, or the list of windows an older report
 * stored. A value that does not read gives nothing. Pure.
 *
 * @param string $usage ws_agents.report_usage
 * @return array windows (name, percent, resets_at), context (used, size)|null, project, branch
 */
function ws_agent_report_read($usage)
{
    $none = array('windows' => array(), 'context' => null, 'project' => '', 'branch' => '');
    $stored = json_decode((string) $usage, true);

    if (!is_array($stored)) {
        return $none;
    }

    $fields = array();

    if (($stored !== array()) && (array_values($stored) === $stored)) {
        // An older report: the windows alone, as a list.
        $windows = $stored;
    } else {
        if (array_diff(array_keys($stored), array('windows', 'context', 'project', 'branch'))) {
            return $none;
        }

        $windows = $stored['windows'] ?? array();
        $fields = $stored;
    }

    $check = ws_agent_report_check('x', $windows, $fields);

    if (!$check['ok']) {
        return $none;
    }

    return array(
        'windows' => $check['values']['windows'],
        'context' => $check['values']['context'],
        'project' => $check['values']['project'],
        'branch'  => $check['values']['branch'],
    );
}

/**
 * The windows of a stored report (ws_agent_report_read()). Pure.
 *
 * @param string $usage ws_agents.report_usage
 * @return array[] name, percent, resets_at
 */
function ws_agent_report_windows($usage)
{
    return ws_agent_report_read($usage)['windows'];
}

/**
 * A stored report with its context replaced, the rest kept: an answer
 * names its context beside its model. Pure.
 *
 * @param string $usage   ws_agents.report_usage
 * @param array  $context ws_agent_report_context_check()'s context
 * @return string the JSON to store
 */
function ws_agent_report_usage_with_context($usage, $context)
{
    $read = ws_agent_report_read($usage);
    $read['context'] = isset($context['size']) ? array('used' => (int) $context['used'], 'size' => (int) $context['size']) : array('used' => (int) $context['used']);

    return (string) json_encode($read, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

/**
 * A number of tokens in short: 950, 412k, 1.5M. Pure.
 *
 * @param int $tokens
 * @return string
 */
function ws_agent_report_tokens($tokens)
{
    $tokens = (int) $tokens;

    if ($tokens < 1000) {
        return (string) $tokens;
    }

    $thousands = (int) round($tokens / 1000);

    if ($thousands < 1000) {
        return $thousands . 'k';
    }

    $millions = round($tokens / 1000000, 1);

    return rtrim(rtrim(number_format($millions, 1, '.', ''), '0'), '.') . 'M';
}

/**
 * An agent's reported context: tokens used, the window's size (the one typed
 * on its form, ws_agents.context_size, else the one it reported, else none),
 * the percent used and its words. Without a size the words are the used
 * tokens alone and size and percent are null; used above the size is drawn
 * as full. Null when its last report names no context. Pure but for lang().
 *
 * @param array $agent ws_agent_shape() or the raw row: report_usage, context_size
 * @return array|null used, size, percent, words
 */
function ws_agent_report_context($agent)
{
    $context = ws_agent_report_read($agent['report_usage'] ?? '')['context'];

    if ($context === null) {
        return null;
    }

    $used = (int) $context['used'];
    $form = (int) ($agent['context_size'] ?? 0);
    $size = ($form > 0) ? $form : (int) ($context['size'] ?? 0);

    if ($size <= 0) {
        return array(
            'used'    => $used,
            'size'    => null,
            'percent' => null,
            'words'   => lang(array('string' => 'Context {var:1} tokens', 'vars' => ws_agent_report_tokens($used))),
        );
    }

    $percent = min(100, (int) round($used * 100 / $size));

    return array(
        'used'    => $used,
        'size'    => $size,
        'percent' => $percent,
        'words'   => lang(array('string' => 'Context {var:1} of {var:2} tokens ({var:3}%)', 'vars' => array(ws_agent_report_tokens($used), ws_agent_report_tokens($size), $percent))),
    );
}

/**
 * Whether the schema step that added ws_agents.context_size has run.
 *
 * @return bool
 */
function ws_agent_context_size_ready()
{
    static $ready = null;

    if ($ready === null) {
        $ready = ws_agents_ready() && pg_schema_has('ws_agents', 'context_size');
    }

    return $ready;
}

/**
 * Stores an application agent's context window size, 0 for not entered.
 *
 * @param int $agent_id
 * @param int $size
 * @return void
 */
function ws_agent_context_size_save($agent_id, $size)
{
    if (((int) $agent_id <= 0) || !ws_agent_context_size_ready()) {
        return;
    }

    db_q("UPDATE ws_agents SET context_size = ? WHERE id = ? AND kind = 'app'", array(max(0, (int) $size), (int) $agent_id));
}

/**
 * An agent's reported project, null when its last report names none. Pure.
 *
 * @param array $agent ws_agent_shape() or the raw row: report_usage
 * @return string|null
 */
function ws_agent_report_project($agent)
{
    $project = ws_agent_report_read($agent['report_usage'] ?? '')['project'];

    return ($project === '') ? null : $project;
}

/**
 * An agent's reported git branch, null when its last report names none.
 * Pure.
 *
 * @param array $agent ws_agent_shape() or the raw row: report_usage
 * @return string|null
 */
function ws_agent_report_branch($agent)
{
    $branch = ws_agent_report_read($agent['report_usage'] ?? '')['branch'];

    return ($branch === '') ? null : $branch;
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
 * A model named on its own (an answer's model field), checked by the
 * report's rule. Pure but for lang().
 *
 * @param mixed $model
 * @return array ok, error, model
 */
function ws_agent_report_model_check($model)
{
    if (is_string($model) && (trim($model) === '')) {
        return array('ok' => false, 'error' => lang('The model is a name of up to 80 characters.'), 'model' => '');
    }

    $check = ws_agent_report_check($model, null);

    return $check['ok'] ? array('ok' => true, 'error' => '', 'model' => $check['values']['model'])
        : array('ok' => false, 'error' => $check['error'], 'model' => '');
}

/**
 * Writes a model named with an answer: the model and the report's time,
 * the usage windows of the last report kept.
 *
 * @param int    $agent_id
 * @param string $model ws_agent_report_model_check()'s model
 * @param int    $now
 */
function ws_agent_report_model_save($agent_id, $model, $now)
{
    db("UPDATE ws_agents SET report_model = '" . e((string) $model) . "', report_at = '" . (int) $now . "'
        WHERE id = '" . (int) $agent_id . "'");
}

/**
 * Writes a context named with an answer: the context and the report's time,
 * the rest of the last report kept.
 *
 * @param int   $agent_id
 * @param array $context ws_agent_report_context_check()'s context
 * @param int   $now
 */
function ws_agent_report_context_save($agent_id, $context, $now)
{
    $row = db_item("SELECT report_usage FROM ws_agents WHERE id = '" . (int) $agent_id . "'");

    if (!$row) {
        return;
    }

    db("UPDATE ws_agents SET report_usage = '" . e(ws_agent_report_usage_with_context($row['report_usage'] ?? '', $context)) . "', report_at = '" . (int) $now . "'
        WHERE id = '" . (int) $agent_id . "'");
}

/**
 * The brief's line that asks an application agent in an agent channel for
 * its model: when it never reported or its last report is older than
 * WS_AGENT_REPORT_ASK_AFTER. '' when the report is fresh, for another kind
 * of agent or outside an agent channel. English, as the brief is. Pure.
 *
 * @param array $agent         the raw ws_agents row: kind, report_at
 * @param bool  $agent_channel the request was asked in an agent channel
 * @param int   $now
 * @return string
 */
function ws_agent_report_brief_line($agent, $agent_channel, $now)
{
    if (!$agent_channel || (($agent['kind'] ?? '') !== 'app')) {
        return '';
    }

    $at = (int) ($agent['report_at'] ?? 0);

    if (($at > 0) && (((int) $now - $at) < WS_AGENT_REPORT_ASK_AFTER)) {
        return '';
    }

    return 'Your model: the site has no report of it from the last hour. Add "model": "<the model you run on>" and "context": {"used": <the tokens you use>} to this answer (or report them with POST /workspace/agent-report); never guess your context window\'s size.';
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
