<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The finding model of the Security Scan (2026.11.0, 11.0.277): what a
 * finding is (a rule's kind, a place, a severity and a reason), its
 * fingerprint, its states and the moves between them, and how a scan's
 * sighting is merged into the stored row. Everything but the readiness
 * probe is pure: the scan, the screen and the writes come later and only
 * call these.
 *
 * The scan only reads: a finding is never acted on by itself. Quarantine,
 * Restore and Ignore are a person's clicks; a scan may only open a finding,
 * update it, or mark it gone.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

// Severity: higher is worse, so idx_state (state, severity) lists the worst
// open findings first.
define('PG_SECURITY_SEVERITY_LOW', 1);
define('PG_SECURITY_SEVERITY_MEDIUM', 2);
define('PG_SECURITY_SEVERITY_HIGH', 3);
define('PG_SECURITY_SEVERITY_CRITICAL', 4);

// State.
define('PG_SECURITY_STATE_OPEN', 0);
define('PG_SECURITY_STATE_QUARANTINED', 1);
define('PG_SECURITY_STATE_IGNORED', 2);
define('PG_SECURITY_STATE_GONE', 3);

// A gone finding is removed this long after it was last seen.
define('PG_SECURITY_GONE_KEEP', 90 * 86400);

/**
 * Does the database carry the findings table (2026.11.0, 11.0.277)?
 *
 * @return bool
 */
function pg_security_finding_ready()
{
    static $ready = null;

    if ($ready === null) {
        $ready = pg_schema_has('security_finding', 'fingerprint');
    }

    return $ready;
}

/**
 * The severities by their key, worst last.
 *
 * @return array<string, int>
 */
function pg_security_severities()
{
    return array(
        'low' => PG_SECURITY_SEVERITY_LOW,
        'medium' => PG_SECURITY_SEVERITY_MEDIUM,
        'high' => PG_SECURITY_SEVERITY_HIGH,
        'critical' => PG_SECURITY_SEVERITY_CRITICAL,
    );
}

/**
 * The key of a severity ('low' ... 'critical'), '' for an unknown value.
 *
 * @param mixed $severity
 * @return string
 */
function pg_security_severity_key($severity)
{
    $key = array_search((int) $severity, pg_security_severities(), true);

    return ($key === false) ? '' : $key;
}

/**
 * The states by their key.
 *
 * @return array<string, int>
 */
function pg_security_states()
{
    return array(
        'open' => PG_SECURITY_STATE_OPEN,
        'quarantined' => PG_SECURITY_STATE_QUARANTINED,
        'ignored' => PG_SECURITY_STATE_IGNORED,
        'gone' => PG_SECURITY_STATE_GONE,
    );
}

/**
 * The key of a state ('open' ... 'gone'), '' for an unknown value.
 *
 * @param mixed $state
 * @return string
 */
function pg_security_state_key($state)
{
    $key = array_search((int) $state, pg_security_states(), true);

    return ($key === false) ? '' : $key;
}

/**
 * Is this a rule's kind: lower-case ASCII letters, digits and underscores,
 * starting with a letter, at most 40 characters (the column)?
 *
 * @param mixed $kind
 * @return bool
 */
function pg_security_kind_valid($kind)
{
    return is_string($kind) && (preg_match('/^[a-z][a-z0-9_]{0,39}$/', $kind) === 1);
}

/**
 * The place of a file: relative to the software folder, forward slashes, no
 * leading "./" or "/", no empty or "." segments. '' when nothing is left,
 * when a ".." segment is in it or when it is longer than the column's 500
 * characters (a truncated path would name another file).
 *
 * @param mixed $path
 * @return string
 */
function pg_security_place_path($path)
{
    if (!is_string($path) || ($path === '') || (strpos($path, "\0") !== false)) {
        return '';
    }

    $parts = array();

    foreach (explode('/', str_replace('\\', '/', $path)) as $part) {
        if (($part === '') || ($part === '.')) {
            continue;
        }

        if ($part === '..') {
            return '';
        }

        $parts[] = $part;
    }

    $place = implode('/', $parts);

    if (($place === '') || (mb_strlen($place, 'UTF-8') > 500)) {
        return '';
    }

    return $place;
}

/**
 * The place of a stored record or an account: "<type>:<id>", for example
 * "page:12" or "user:3". A finding about an account keeps the user id only.
 * '' for a type that is not lower-case ASCII or an id below 1.
 *
 * @param mixed $type
 * @param mixed $id
 * @return string
 */
function pg_security_place_record($type, $id)
{
    if (!is_string($type) || (preg_match('/^[a-z][a-z0-9_]{0,39}$/', $type) !== 1)) {
        return '';
    }

    if (!is_numeric($id) || ((int) $id < 1) || ((string) (int) $id !== (string) $id)) {
        return '';
    }

    return $type . ':' . (int) $id;
}

/**
 * The fingerprint of a finding: sha1 of its kind and place, so the same rule
 * at the same place is one row however often it is found.
 *
 * @param string $kind
 * @param string $place
 * @return string 40 hex characters
 */
function pg_security_fingerprint($kind, $place)
{
    return sha1((string) $kind . "\n" . (string) $place);
}

/**
 * A scan's sighting as the columns it brings, or null when it is not a
 * finding (unknown kind, empty place, unknown severity). The detail is the
 * reason in words, cut to what a TEXT column holds.
 *
 * @param mixed $kind
 * @param mixed $place A place from pg_security_place_path() or pg_security_place_record()
 * @param mixed $severity
 * @param mixed $detail
 * @return array|null
 */
function pg_security_sighting($kind, $place, $severity, $detail = '')
{
    if (!pg_security_kind_valid($kind) || !is_string($place) || ($place === '') || (mb_strlen($place, 'UTF-8') > 500)) {
        return null;
    }

    if (pg_security_severity_key($severity) === '') {
        return null;
    }

    $detail = is_string($detail) ? $detail : '';

    if (strlen($detail) > 65535) {
        $detail = mb_strcut($detail, 0, 65535, 'UTF-8');
    }

    return array(
        'kind' => $kind,
        'place' => $place,
        'fingerprint' => pg_security_fingerprint($kind, $place),
        'severity' => (int) $severity,
        'detail' => $detail,
    );
}

/**
 * The row to write for a sighting: a new open finding when nothing is stored
 * under its fingerprint, otherwise the stored row seen again. A gone finding
 * found again opens again, and so does a quarantined one found back at its
 * place (the file came back); an ignored one stays ignored. A person's note,
 * acted_by and acted_at are kept.
 *
 * @param array|null $stored The stored row, or null
 * @param array $sighting From pg_security_sighting()
 * @param int $now
 * @return array
 */
function pg_security_finding_merge($stored, array $sighting, $now)
{
    $now = (int) $now;

    if (!is_array($stored)) {
        return array_merge($sighting, array(
            'state' => PG_SECURITY_STATE_OPEN,
            'note' => '',
            'acted_by' => 0,
            'first_seen' => $now,
            'last_seen' => $now,
            'acted_at' => 0,
        ));
    }

    $row = array_merge($stored, $sighting);
    $row['last_seen'] = $now;
    $state = isset($stored['state']) ? (int) $stored['state'] : PG_SECURITY_STATE_OPEN;
    $row['state'] = ($state === PG_SECURITY_STATE_IGNORED) ? PG_SECURITY_STATE_IGNORED : PG_SECURITY_STATE_OPEN;

    return $row;
}

/**
 * The state of a stored finding a full scan did not see. Open and ignored
 * become gone; a quarantined one stays quarantined (its file was moved away
 * by the quarantine, not fixed); a gone one stays gone.
 *
 * @param mixed $state
 * @return int
 */
function pg_security_state_unseen($state)
{
    $state = (int) $state;

    if ($state === PG_SECURITY_STATE_QUARANTINED) {
        return PG_SECURITY_STATE_QUARANTINED;
    }

    return PG_SECURITY_STATE_GONE;
}

/**
 * The state a person's action leads to from this state, or null when the
 * action is not allowed there. Quarantine and Ignore from open; Restore from
 * quarantined; Reopen from ignored.
 *
 * @param mixed $state
 * @param string $action quarantine, restore, ignore, reopen
 * @return int|null
 */
function pg_security_state_after($state, $action)
{
    $moves = array(
        PG_SECURITY_STATE_OPEN => array('quarantine' => PG_SECURITY_STATE_QUARANTINED, 'ignore' => PG_SECURITY_STATE_IGNORED),
        PG_SECURITY_STATE_QUARANTINED => array('restore' => PG_SECURITY_STATE_OPEN),
        PG_SECURITY_STATE_IGNORED => array('reopen' => PG_SECURITY_STATE_OPEN),
    );
    $state = (int) $state;

    return isset($moves[$state][$action]) ? $moves[$state][$action] : null;
}

/**
 * The note given with Ignore, as the column keeps it: one line, no control
 * characters, at most 255 characters.
 *
 * @param mixed $note
 * @return string
 */
function pg_security_note_clean($note)
{
    if (!is_string($note)) {
        return '';
    }

    $note = preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $note);

    if (!is_string($note)) {
        return '';
    }

    return mb_substr(trim(preg_replace('/ {2,}/', ' ', $note)), 0, 255, 'UTF-8');
}

/**
 * Is this stored finding due for removal: gone and last seen 90 days ago or
 * more?
 *
 * @param array $row
 * @param int $now
 * @return bool
 */
function pg_security_finding_expired(array $row, $now)
{
    if (!isset($row['state']) || ((int) $row['state'] !== PG_SECURITY_STATE_GONE)) {
        return false;
    }

    $last_seen = isset($row['last_seen']) ? (int) $row['last_seen'] : 0;

    return ((int) $now - $last_seen) >= PG_SECURITY_GONE_KEEP;
}
