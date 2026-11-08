<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - the notes lock on the My Notes screen: the texts of
 * assets/js/workspace_note_lock.js (the toolbar's Lock and its dialogs, Lock
 * and Unlock in the menus, the lock pane with its forgotten-PIN code). The
 * store and its actions are note_lock.php.
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
 * The texts of the notes lock on the screen, merged into ws_js_strings()'s
 * set (screen.php); keys start with nlock_.
 *
 * @return array key => text
 */
function ws_note_lock_js_strings()
{
    return array(
        'nlock_lock'          => lang('Lock'),
        'nlock_unlock'        => lang('Unlock'),
        'nlock_now'           => lang('Lock now'),
        'nlock_set_up'        => lang('Lock your notes with a PIN'),
        'nlock_set_up_ok'     => lang('Set the PIN'),
        'nlock_set_up_done'   => lang('Your notes PIN is set. Lock a note or a group from its menu.'),
        'nlock_intro'         => lang('A locked note or group opens only with this PIN, and is never shared. If you forget it, a code by e-mail lets you set a new one.'),
        'nlock_pin'           => lang('PIN'),
        'nlock_pin_again'     => lang('PIN again'),
        'nlock_pin_current'   => lang('Current PIN'),
        'nlock_pin_new'       => lang('New PIN'),
        'nlock_pin_shape'     => lang('A PIN is 4 to 8 digits.'),
        'nlock_again_after'   => lang('Ask for the PIN again after'),
        'nlock_change_pin'    => lang('Change PIN'),
        'nlock_change_time'   => lang('Change the time'),
        'nlock_pin_changed'   => lang('Your notes PIN is changed.'),
        'nlock_time_changed'  => lang('The time is changed.'),
        'nlock_off'           => lang('Turn off'),
        'nlock_off_says'      => lang('Every note and group is unlocked and the PIN is removed.'),
        'nlock_off_done'      => lang('The notes lock is off.'),
        'nlock_locked_done'   => ws_js_template('“{var:1}” is locked.', 1),
        'nlock_unlocked_done' => ws_js_template('“{var:1}” is unlocked.', 1),
        'nlock_shares_ask'    => ws_js_template('“{var:1}” is shared ({var:2}). Locking it takes these shares back.', 2),
        'nlock_unlock_ask'    => ws_js_template('Type your PIN to unlock “{var:1}”.', 1),
        'nlock_mark_closed'   => lang('Locked'),
        'nlock_mark_open'     => lang('Locked, open for now'),
        'nlock_locked_note'   => lang('Locked note'),
        'nlock_pane_note'     => lang('This note is locked. Type your PIN to open it.'),
        'nlock_pane_list'     => lang('Type your PIN to see the notes in your locked groups.'),
        'nlock_open'          => lang('Open'),
        'nlock_forgot'        => lang('Forgot your PIN?'),
        'nlock_wait'          => ws_js_template('Too many wrong PINs. Try again in {var:1} min, or use a code by e-mail.', 1),
        'nlock_code_sent'     => ws_js_template('We sent a code to {var:1}. It works for 10 minutes.', 1),
        'nlock_code'          => lang('Code from the e-mail'),
        'nlock_new_pin_save'  => lang('Set the new PIN'),
        'nlock_back_to_pin'   => lang('Back to the PIN'),
        'nlock_unlock_list'   => lang('Open with your PIN'),
    );
}
