<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Site Settings > Accounts and sign-in as folded rows
 * (includes/settings/fold.php): the rows' order, names and icons, and each
 * part's one line of state with its pill. The pg_security_fold_<part>()
 * lines are pure but for lang(); includes/settings/security.php reads the
 * facts they take.
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
 * The rows' keys in reading order. A key is its card's id without pgset-.
 *
 * @return string[]
 */
function pg_security_fold_order()
{
    return array('session', 'throttle', 'mfa', 'device', 'signin', 'captcha', 'membership', 'audit', 'deletion', 'encryption');
}

/**
 * A row's name, icon and tone.
 *
 * @param string $key one of pg_security_fold_order()
 * @return array name, icon, tone
 */
function pg_security_fold_face($key)
{
    switch ($key) {
        case 'session':
            return array('name' => lang('Passwords and sessions'), 'icon' => 'bi-key', 'tone' => 'pg-tone-violet');
        case 'throttle':
            return array('name' => lang('Sign-in attempts'), 'icon' => 'bi-speedometer2', 'tone' => 'pg-tone-amber');
        case 'mfa':
            return array('name' => lang('Two-step verification'), 'icon' => 'bi-shield-check', 'tone' => 'pg-tone-teal');
        case 'device':
            return array('name' => lang('Devices'), 'icon' => 'bi-laptop', 'tone' => 'pg-tone-system');
        case 'signin':
            return array('name' => lang('Sign in with Google, Microsoft and Apple'), 'icon' => 'bi-person-badge', 'tone' => 'pg-tone-blue');
        case 'captcha':
            return array('name' => lang('Forms and spam'), 'icon' => 'bi-envelope-exclamation', 'tone' => 'pg-tone-rose');
        case 'membership':
            return array('name' => lang('Registration and membership'), 'icon' => 'bi-person-vcard', 'tone' => 'pg-tone-blue');
        case 'audit':
            return array('name' => lang('Audit trail'), 'icon' => 'bi-clock-history', 'tone' => '');
        case 'deletion':
            return array('name' => lang('Deleting many at once'), 'icon' => 'bi-trash3', 'tone' => 'pg-tone-rose');
        default:
            return array('name' => lang('Encryption key'), 'icon' => 'bi-lock', 'tone' => '');
    }
}

/**
 * The On or Off pill of a part one switch turns on.
 *
 * @param bool $on
 * @return array words, tone class
 */
function pg_security_fold_switch_pill($on)
{
    return $on ? array(lang('On'), 'pg-tone-teal') : array(lang('Off'), 'pg-tone-gray');
}

/**
 * The Passwords and sessions row's line.
 *
 * @param bool $strong   Require a strong password
 * @param bool $remember Remember me
 * @return array line, pill, attention
 */
function pg_security_fold_session($strong, $remember)
{
    $line = ($strong ? lang('Strong passwords required') : lang('Any password accepted'))
        . ' · ' . ($remember ? lang('Remember me offered') : lang('no Remember me'));

    return array('line' => $line, 'pill' => null, 'attention' => false);
}

/**
 * The Sign-in attempts row's line: how many failures lock out, and for how
 * long.
 *
 * @param bool $ready    the throttle's columns exist
 * @param bool $on       Limit failed sign-ins
 * @param int  $attempts failed attempts
 * @param int  $minutes  counted over, in minutes
 * @param int  $lockout  locked out for, in minutes
 * @return array line, pill, attention
 */
function pg_security_fold_throttle($ready, $on, $attempts, $minutes, $lockout)
{
    if (!$ready) {
        return array('line' => lang('Waits for the database upgrade'), 'pill' => null, 'attention' => false);
    }

    $line = $on
        ? lang(array('string' => '{var:1} failures in {var:2} minutes lock out for {var:3} minutes', 'vars' => array((int) $attempts, (int) $minutes, (int) $lockout)))
        : lang('Failed sign-ins are not limited');

    return array('line' => $line, 'pill' => pg_security_fold_switch_pill($on), 'attention' => false);
}

/**
 * The Two-step verification row's line: who must use it, and a passkey
 * alone.
 *
 * @param bool $available the site can offer it (tables, key, openssl)
 * @param int  $role      mfa_required_role: 99 nobody, 0 to 3 a role and up
 * @param bool $passkey   Sign in with a passkey alone
 * @return array line, pill, attention
 */
function pg_security_fold_mfa($available, $role, $passkey)
{
    if (!$available) {
        return array('line' => lang('Needs an encryption key'), 'pill' => null, 'attention' => true);
    }

    switch ((int) $role) {
        case 0:
            $line = lang('Required for administrators');
            break;
        case 1:
            $line = lang('Required for administrators and designers');
            break;
        case 2:
            $line = lang('Required for administrators, designers and managers');
            break;
        case 3:
            $line = lang('Required for everyone who signs in');
            break;
        default:
            $line = lang('Not required');
    }

    if ($passkey) {
        $line .= ' · ' . lang('passkey sign-in on');
    }

    return array('line' => $line, 'pill' => null, 'attention' => false);
}

/**
 * The Devices row's line.
 *
 * @param bool $on     Limit devices per account
 * @param int  $max    Max devices per account
 * @param bool $strict Refuse new sign-ins when the limit is full
 * @return array line, pill, attention
 */
function pg_security_fold_device($on, $max, $strict)
{
    if (!$on) {
        return array('line' => lang('Any number of devices'), 'pill' => pg_security_fold_switch_pill(false), 'attention' => false);
    }

    $max = max(1, (int) $max);
    $line = ($max === 1) ? lang('At most 1 device per account') : lang(array('string' => 'At most {var:1} devices per account', 'vars' => $max));
    $line .= ' · ' . ($strict ? lang('new sign-ins refused when full') : lang('the oldest can be signed out'));

    return array('line' => $line, 'pill' => pg_security_fold_switch_pill(true), 'attention' => false);
}

/**
 * The sign-in providers row's line: the buttons shown, and those switched on
 * that wait for their setup.
 *
 * @param string[] $shown  names of the buttons visitors see
 * @param string[] $hidden names switched on but hidden
 * @return array line, pill, attention
 */
function pg_security_fold_signin(array $shown, array $hidden)
{
    $line = $shown
        ? lang(array('string' => 'Shown: {var:1}', 'vars' => implode(', ', $shown)))
        : lang('No sign-in button shown');

    if ($hidden) {
        $line .= ' · ' . lang(array('string' => 'waiting for setup: {var:1}', 'vars' => implode(', ', $hidden)));
    }

    return array('line' => $line, 'pill' => null, 'attention' => (bool) $hidden);
}

/**
 * The Forms and spam row's line.
 *
 * @param bool $captcha       CAPTCHA
 * @param bool $stopforumspam Check visitors against StopForumSpam
 * @param int  $blocked       entries in Blocked e-mail addresses
 * @return array line, pill, attention
 */
function pg_security_fold_forms($captcha, $stopforumspam, $blocked)
{
    $line = $captcha ? lang('CAPTCHA on') : lang('No CAPTCHA');

    if ($stopforumspam) {
        $line .= ' · ' . lang('StopForumSpam checked');
    }

    $blocked = (int) $blocked;

    if ($blocked === 1) {
        $line .= ' · ' . lang('1 address blocked');
    } elseif ($blocked > 1) {
        $line .= ' · ' . lang(array('string' => '{var:1} addresses blocked', 'vars' => $blocked));
    }

    return array('line' => $line, 'pill' => pg_security_fold_switch_pill($captcha), 'attention' => false);
}

/**
 * The Registration and membership row's line: the expiration warning.
 *
 * @param bool $warning Send an expiration warning to members
 * @param int  $days    days before expiration
 * @return array line, pill, attention
 */
function pg_security_fold_membership($warning, $days)
{
    $days = (int) $days;
    $line = $warning
        ? lang(array('string' => 'Expiration warning sent {var:1} day{suffix:1} before', 'vars' => $days, 'suffix' => array(($days === 1) ? '' : 's')))
        : lang('No expiration warning');

    return array('line' => $line, 'pill' => null, 'attention' => false);
}

/**
 * The Audit trail row's line.
 *
 * @param int $years Keep lines for; 0 keeps every line
 * @return array line, pill, attention
 */
function pg_security_fold_audit($years)
{
    $years = (int) $years;

    if ($years <= 0) {
        $line = lang('Every line kept');
    } else {
        $line = lang(array('string' => 'Lines kept {var:1} year{suffix:1}', 'vars' => $years, 'suffix' => array(($years === 1) ? '' : 's')));
    }

    return array('line' => $line, 'pill' => null, 'attention' => false);
}

/**
 * The Deleting many at once row's line.
 *
 * @param bool $on mass deletion
 * @return array line, pill, attention
 */
function pg_security_fold_deletion($on)
{
    return array(
        'line'      => $on ? lang('Everything a filter matches can be deleted') : lang('One at a time'),
        'pill'      => pg_security_fold_switch_pill($on),
        'attention' => false,
    );
}

/**
 * The Encryption key row's line.
 *
 * @param bool $has_key config.php holds a key
 * @param bool $openssl the openssl extension is loaded
 * @return array line, pill, attention
 */
function pg_security_fold_encryption($has_key, $openssl)
{
    if (!$openssl) {
        return array('line' => lang('OpenSSL is disabled'), 'pill' => null, 'attention' => false);
    }

    return $has_key
        ? array('line' => lang('A key is set in config.php'), 'pill' => null, 'attention' => false)
        : array('line' => lang('No key yet'), 'pill' => null, 'attention' => true);
}
