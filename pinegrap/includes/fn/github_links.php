<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Repository links of a Workspace channel: a GitHub repository linked to a
 * channel through a GitHub account connected for the channel_links purpose
 * (includes/connect/purposes/channel_links.php). Saving a link adds a
 * webhook to the repository that points at the site and is signed with a
 * secret of the link's own; the repository's events then come in as lines of
 * the channel, written as the "GitHub" application.
 *
 * This file holds the rules that need no database: the repository name, the
 * events a link can follow and the GitHub hook events they need, the hook
 * GitHub is asked to create, the X-Hub-Signature-256 check, the delivery id,
 * the lines, and a written line read back with a day's counts of them. The lines keep the formats the repository's own CI wrote
 * before ([ci] green|red #<n> <branch> <sha7> ... <run url> <title>,
 * [ready] #<n> <branch> <sha7> <title>), so wake rules on those prefixes
 * keep working.
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
 * The events a link can follow, keyed by the name stored with the link,
 * with the GitHub hook events each one needs.
 *
 * @return array key => array(label, hooks)
 */
function pg_github_link_events()
{
    return array(
        'ci'           => array('label' => lang('CI results'), 'hooks' => array('workflow_run')),
        'pull_request' => array('label' => lang('Pull requests opened, merged or closed'), 'hooks' => array('pull_request')),
        'label'        => array('label' => lang('Labels put on a pull request'), 'hooks' => array('pull_request')),
    );
}

/**
 * Keeps the known link events, in catalogue order, without repeats.
 *
 * @param array|string $events a list, or a comma separated text
 * @return array
 */
function pg_github_link_events_clean($events)
{
    if (is_string($events)) {
        $events = explode(',', $events);
    }

    if (!is_array($events)) {
        return array();
    }

    $wanted = array();

    foreach ($events as $event) {
        if (is_string($event)) {
            $wanted[trim($event)] = true;
        }
    }

    $clean = array();

    foreach (array_keys(pg_github_link_events()) as $key) {
        if (isset($wanted[$key])) {
            $clean[] = $key;
        }
    }

    return $clean;
}

/**
 * The GitHub hook events a link's events need, sorted, without repeats.
 *
 * @param array $events link events
 * @return array
 */
function pg_github_hook_events($events)
{
    $catalogue = pg_github_link_events();
    $hooks = array();

    foreach (pg_github_link_events_clean($events) as $event) {
        foreach ($catalogue[$event]['hooks'] as $hook) {
            $hooks[$hook] = true;
        }
    }

    $hooks = array_keys($hooks);
    sort($hooks);

    return $hooks;
}

/**
 * Reads a repository as owner/name. A repository address
 * (https://github.com/owner/name, with .git or a trailing path) is accepted
 * too, since that is what people copy. GitHub's own rules: an owner is 1-39
 * letters, digits or single hyphens, not starting or ending with one; a
 * repository name is 1-100 letters, digits, '.', '-' or '_', never '.' or
 * '..'.
 *
 * @param string $text
 * @return string owner/name, or '' when it is not a repository
 */
function pg_github_repo_parse($text)
{
    $text = trim((string) $text);

    if (preg_match('~^(?:https?://)?(?:www\.)?github\.com/([^/?#]+)/([^/?#]+)~i', $text, $match)) {
        $text = $match[1] . '/' . $match[2];
    }

    $text = preg_replace('~\.git$~i', '', $text);

    if (!preg_match('~^([A-Za-z0-9](?:[A-Za-z0-9]|-(?=[A-Za-z0-9])){0,38})/([A-Za-z0-9._-]{1,100})$~', $text, $match)) {
        return '';
    }

    if (($match[2] === '.') || ($match[2] === '..')) {
        return '';
    }

    return $match[1] . '/' . $match[2];
}

/**
 * The body of POST /repos/{owner}/{repo}/hooks for a link.
 *
 * @param string $url    the site's receiver address for the link
 * @param string $secret the link's signing secret
 * @param array  $events link events
 * @return array
 */
function pg_github_hook_request($url, $secret, $events)
{
    return array(
        'name'   => 'web',
        'active' => true,
        'events' => pg_github_hook_events($events),
        'config' => array(
            'url'          => (string) $url,
            'content_type' => 'json',
            'secret'       => (string) $secret,
            'insecure_ssl' => '0',
        ),
    );
}

/**
 * Whether a delivery carries the link's signature: X-Hub-Signature-256 is
 * "sha256=" and the hex HMAC-SHA256 of the raw body with the hook's secret.
 * Compared in constant time; an empty secret never matches.
 *
 * @param string $secret
 * @param string $body   the raw request body, as received
 * @param string $header the X-Hub-Signature-256 value
 * @return bool
 */
function pg_github_signature_valid($secret, $body, $header)
{
    $secret = (string) $secret;
    $header = strtolower(trim((string) $header));

    if (($secret === '') || (strpos($header, 'sha256=') !== 0)) {
        return false;
    }

    return hash_equals('sha256=' . hash_hmac('sha256', (string) $body, $secret), $header);
}

/**
 * Reads the X-GitHub-Delivery id (a GUID) that makes a delivery idempotent.
 *
 * @param string $header
 * @return string the id in lower case, or '' when it is not one
 */
function pg_github_delivery_id($header)
{
    $header = strtolower(trim((string) $header));

    return preg_match('~^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$~', $header) ? $header : '';
}

/**
 * Reads a label name a link follows as the prefix it writes: lower case,
 * letters, digits, '.', '-' and '_' only, at most 40 characters.
 *
 * @param string $label
 * @return string '' when nothing is left
 */
function pg_github_label_prefix($label)
{
    $label = strtolower(trim((string) $label));
    $label = preg_replace('~[^a-z0-9._-]+~', '-', $label);

    return substr(trim($label, '-'), 0, 40);
}

/**
 * One line of free text from a payload: no line breaks or control
 * characters, runs of white space folded, at most $limit characters.
 *
 * @param mixed $text
 * @param int   $limit
 * @return string
 */
function pg_github_line_text($text, $limit = 200)
{
    if (!is_string($text) && !is_int($text)) {
        return '';
    }

    $text = preg_replace('~[\x00-\x1F\x7F\s]+~u', ' ', (string) $text);

    return trim(mb_substr(trim((string) $text), 0, $limit));
}

/**
 * The channel line for one delivery, or '' when the link does not follow it.
 *
 *   workflow_run completed  [ci] green|red #<n> <branch> <sha7> <workflow>=<conclusion> <run url> <title>
 *                           (#<n> left out for a run that belongs to no pull request;
 *                           a cancelled or skipped run writes nothing)
 *   pull_request labeled    [<label>] #<n> <branch> <sha7> <title>, for a label the link follows
 *   pull_request opened, reopened, closed
 *                           [pr] opened|reopened|merged|closed #<n> <branch> <sha7> <url> <title>
 *
 * @param string $hook    the X-GitHub-Event value
 * @param array  $payload the decoded body
 * @param array  $link    events (list), labels (list of label names)
 * @return string
 */
function pg_github_line($hook, $payload, $link)
{
    if (!is_array($payload)) {
        return '';
    }

    $events = pg_github_link_events_clean($link['events'] ?? array());
    $action = (string) ($payload['action'] ?? '');

    if (($hook === 'workflow_run') && in_array('ci', $events, true)) {
        $run = $payload['workflow_run'] ?? null;

        if (($action !== 'completed') || !is_array($run)) {
            return '';
        }

        $conclusion = (string) ($run['conclusion'] ?? '');

        if (in_array($conclusion, array('success', 'neutral'), true)) {
            $state = 'green';
        } elseif (in_array($conclusion, array('failure', 'timed_out', 'action_required', 'startup_failure', 'stale'), true)) {
            $state = 'red';
        } else {
            return '';
        }

        $number = 0;

        if (!empty($run['pull_requests'][0]['number'])) {
            $number = (int) $run['pull_requests'][0]['number'];
        }

        $parts = array('[ci]', $state);

        if ($number > 0) {
            $parts[] = '#' . $number;
        }

        $parts[] = pg_github_line_text($run['head_branch'] ?? '', 100);
        $parts[] = substr(pg_github_line_text($run['head_sha'] ?? '', 40), 0, 7);
        $parts[] = pg_github_label_prefix($run['name'] ?? 'ci') . '=' . $conclusion;
        $parts[] = pg_github_line_text($run['html_url'] ?? '', 300);
        $parts[] = pg_github_line_text($run['display_title'] ?? '');

        return pg_github_line_join($parts);
    }

    if ($hook !== 'pull_request') {
        return '';
    }

    $pull = $payload['pull_request'] ?? null;

    if (!is_array($pull) || empty($pull['number'])) {
        return '';
    }

    $number = '#' . (int) $pull['number'];
    $branch = pg_github_line_text($pull['head']['ref'] ?? '', 100);
    $sha = substr(pg_github_line_text($pull['head']['sha'] ?? '', 40), 0, 7);
    $title = pg_github_line_text($pull['title'] ?? '');

    if (($action === 'labeled') && in_array('label', $events, true)) {
        $label = pg_github_label_prefix($payload['label']['name'] ?? '');
        $followed = array_map('pg_github_label_prefix', (array) ($link['labels'] ?? array()));

        if (($label === '') || !in_array($label, $followed, true)) {
            return '';
        }

        return pg_github_line_join(array('[' . $label . ']', $number, $branch, $sha, $title));
    }

    if (in_array('pull_request', $events, true) && in_array($action, array('opened', 'reopened', 'closed'), true)) {
        if ($action === 'closed') {
            $action = empty($pull['merged']) ? 'closed' : 'merged';
        }

        return pg_github_line_join(array('[pr]', $action, $number, $branch, $sha, pg_github_line_text($pull['html_url'] ?? '', 300), $title));
    }

    return '';
}

/**
 * Joins the parts of a line with single spaces, leaving empty ones out.
 *
 * @param array $parts
 * @return string
 */
function pg_github_line_join($parts)
{
    return implode(' ', array_values(array_filter($parts, function ($part) {
        return $part !== '';
    })));
}

/**
 * Reads a line pg_github_line() wrote back into its parts, or null when the
 * text is not one of its shapes (a person's message, a typed look-alike).
 * Empty parts were left out when the line was written, so the branch and
 * the commit are told apart by the commit's seven hex digits, and the check
 * and the address by their own form. A label named "ci" or "pr" writes
 * "[ci] #<n> …", told from a CI or pull request line by the missing state.
 * Pure.
 *
 * @param string $text
 * @return array|null kind (ci|pr|label), state (green|red; opened|reopened|
 *                    merged|closed; the label), number (int|null), branch,
 *                    sha, check (<workflow>=<conclusion>, ci only), url, title
 */
function pg_github_line_read($text)
{
    if (!is_string($text) || !preg_match('~^\[([a-z0-9._-]{1,40})\] (.+)$~s', trim($text), $match)) {
        return null;
    }

    $prefix = $match[1];
    $words = explode(' ', $match[2]);
    $kind = 'label';
    $state = $prefix;

    if (($prefix === 'ci') && in_array($words[0], array('green', 'red'), true)) {
        $kind = 'ci';
    } elseif (($prefix === 'pr') && in_array($words[0], array('opened', 'reopened', 'merged', 'closed'), true)) {
        $kind = 'pr';
    }

    if ($kind !== 'label') {
        $state = array_shift($words);
    }

    $number = null;

    if (isset($words[0]) && preg_match('~^#([1-9][0-9]{0,9})$~', $words[0], $found)) {
        $number = (int) $found[1];
        array_shift($words);
    } elseif ($kind !== 'ci') {
        // A pull request and a label line always carry the number.
        return null;
    }

    $read = array('kind' => $kind, 'state' => $state, 'number' => $number, 'branch' => '', 'sha' => '', 'check' => '', 'url' => '', 'title' => '');

    if ($kind === 'ci') {
        // The check is the first of the next three words that reads as one,
        // tried from the furthest, so a branch with "=" in it stays a branch.
        $at = -1;

        for ($i = min(2, count($words) - 1); $i >= 0; $i--) {
            if (preg_match('~^[a-z0-9._-]{0,40}=(success|neutral|failure|timed_out|action_required|startup_failure|stale)$~', $words[$i])) {
                $at = $i;
                break;
            }
        }

        if ($at < 0) {
            return null;
        }

        $read['check'] = $words[$at];
        $before = array_slice($words, 0, $at);
        $words = array_slice($words, $at + 1);
    } elseif ($kind === 'pr') {
        $at = -1;

        for ($i = 0; ($i < 3) && ($i < count($words)); $i++) {
            if (pg_github_line_is_url($words[$i])) {
                $at = $i;
                break;
            }
        }

        $before = array_slice($words, 0, ($at >= 0) ? $at : min(2, count($words)));
        $words = array_slice($words, count($before));
    } else {
        $before = array_slice($words, 0, min(2, count($words)));
        $words = array_slice($words, count($before));
    }

    // Branch and commit: the commit is the last of them when it has the
    // shape of seven hex digits; whatever else stays goes back to the title.
    if ($before && preg_match('~^[0-9a-f]{7}$~', (string) end($before))) {
        $read['sha'] = array_pop($before);
    }

    if ($before) {
        $read['branch'] = array_shift($before);
    }

    $words = array_merge($before, $words);

    if (($kind === 'ci') || ($kind === 'pr')) {
        if ($words && pg_github_line_is_url($words[0])) {
            $read['url'] = array_shift($words);
        }
    }

    $read['title'] = implode(' ', $words);

    return $read;
}

/**
 * Whether a word of a line is a web address a line carries.
 *
 * @param string $word
 * @return bool
 */
function pg_github_line_is_url($word)
{
    return (bool) preg_match('~^https?://[^\s]+$~i', (string) $word);
}

/**
 * The address of a repository's pull request.
 *
 * @param string $repository owner/name
 * @param int    $number
 * @return string '' when either is missing
 */
function pg_github_pull_url($repository, $number)
{
    $repository = pg_github_repo_parse($repository);

    return (($repository !== '') && ((int) $number > 0)) ? 'https://github.com/' . $repository . '/pull/' . (int) $number : '';
}

/**
 * A day's numbers from the lines the GitHub application wrote into a
 * channel: pull requests opened (a reopened one counts as opened), merged
 * and closed, checks green and red, and the newest lines read, each with
 * its address (a label line, or a check without one, links to its pull
 * request). Text that does not read as a line counts nothing. Pure.
 *
 * @param array[] $rows       id, body, created_at; newest first
 * @param string  $repository owner/name, for the pull request addresses
 * @param int     $shown      how many lines to give
 * @return array pull_requests (opened, merged, closed), checks (green, red),
 *               lines (message_id, kind, state, number, branch, title, url, created_at)
 */
function pg_github_day_counts($rows, $repository, $shown)
{
    $out = array(
        'pull_requests' => array('opened' => 0, 'merged' => 0, 'closed' => 0),
        'checks'        => array('green' => 0, 'red' => 0),
        'lines'         => array(),
    );

    foreach ((array) $rows as $row) {
        $read = pg_github_line_read($row['body'] ?? '');

        if ($read === null) {
            continue;
        }

        if ($read['kind'] === 'pr') {
            $out['pull_requests'][($read['state'] === 'reopened') ? 'opened' : $read['state']]++;
        } elseif ($read['kind'] === 'ci') {
            $out['checks'][$read['state']]++;
        }

        if (count($out['lines']) < (int) $shown) {
            $out['lines'][] = array(
                'message_id' => (int) ($row['id'] ?? 0),
                'kind'       => $read['kind'],
                'state'      => $read['state'],
                'number'     => $read['number'],
                'branch'     => $read['branch'],
                'title'      => $read['title'],
                'url'        => ($read['url'] !== '') ? $read['url'] : pg_github_pull_url($repository, (int) $read['number']),
                'created_at' => (int) ($row['created_at'] ?? 0),
            );
        }
    }

    return $out;
}

/**
 * The label names a link follows, from a comma separated text: each folded
 * as pg_github_label_prefix() writes it, without repeats, at most 10.
 *
 * @param array|string $labels
 * @return array
 */
function pg_github_labels_clean($labels)
{
    if (is_string($labels)) {
        $labels = explode(',', $labels);
    }

    $clean = array();

    foreach ((array) $labels as $label) {
        $label = is_string($label) ? pg_github_label_prefix($label) : '';

        if (($label !== '') && !in_array($label, $clean, true)) {
            $clean[] = $label;
        }

        if (count($clean) >= 10) {
            break;
        }
    }

    return $clean;
}

/**
 * The receiver address a link's hook points at.
 *
 * @param string $base    the site's software address, no trailing slash
 * @param int    $link_id
 * @return string
 */
function pg_github_hook_url($base, $link_id)
{
    return rtrim((string) $base, '/') . '/github_webhook.php?link=' . (int) $link_id;
}

/**
 * What a refused GitHub hook call means, for the link's card.
 *
 * @param int   $status the HTTP status, 0 when GitHub was not reached
 * @param mixed $data   the decoded answer
 * @param string $error the transport's or the connection's own words
 * @return string
 */
function pg_github_api_problem($status, $data, $error = '')
{
    $status = (int) $status;
    $message = (is_array($data) && isset($data['message']) && is_string($data['message'])) ? pg_github_line_text($data['message'], 150) : '';

    if ($status === 0) {
        return ($error !== '') ? pg_github_line_text($error, 200) : lang('GitHub could not be reached.');
    }

    if ($status === 401) {
        return lang('GitHub no longer accepts the connected account. Connect it again under Settings › Connections.');
    }

    if (($status === 403) || ($status === 404)) {
        return lang('The repository was not found, or the connected account may not manage its webhooks.');
    }

    if ($status === 422) {
        return lang('GitHub refused the webhook:') . ' ' . (($message !== '') ? $message : lang('Validation Failed'));
    }

    return lang(array('string' => 'GitHub answered with status {var:1}.', 'vars' => $status)) . (($message !== '') ? ' ' . $message : '');
}
