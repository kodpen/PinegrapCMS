<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The page behind the link a double opt-in form mails
 * (includes/fn/form_subscription.php). Opening the link changes nothing: mail
 * scanners and link previews open links on their own. The page shows a button,
 * and only its POST, with the form token and the same signed values, adds the
 * contact to the group.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

include('init.php');

// A personal answer: never kept by a cache, never indexed, and the signed
// address is not handed to another site as a referrer.
header('Cache-Control: no-store, private');
header('X-Robots-Tag: noindex, nofollow');
header('Referrer-Policy: no-referrer');

$pg_fs_post = (isset($_SERVER['REQUEST_METHOD']) && ($_SERVER['REQUEST_METHOD'] === 'POST'));

if ($pg_fs_post) {
    validate_token_field();
}

$pg_fs_link = pg_fs_parse($pg_fs_post ? $_POST : $_GET);
$pg_fs_state = 'invalid';
$pg_fs_email = '';

// The link is checked against the contact's address as it is now and a group
// that still exists; a deleted contact or group leaves nothing to confirm.
if (($pg_fs_link !== null) && (pg_fs_group($pg_fs_link['g']) !== null)) {
    $pg_fs_email = pg_fs_contact_email($pg_fs_link['c']);
    $pg_fs_expected = pg_fs_token($pg_fs_link['c'], $pg_fs_link['g'], $pg_fs_email, $pg_fs_link['t'], pg_fs_key());
    $pg_fs_state = pg_fs_link_state(
        $pg_fs_link,
        $pg_fs_expected,
        time(),
        ($pg_fs_expected !== '') && pg_fs_is_member($pg_fs_link['c'], $pg_fs_link['g']));
}

if ($pg_fs_post && ($pg_fs_state === 'confirm')) {
    $pg_fs_state = pg_fs_confirm($pg_fs_link['c'], $pg_fs_link['g'], $pg_fs_email) ? 'confirmed' : 'failed';
}

switch ($pg_fs_state) {
    case 'confirm':
        $pg_fs_message = lang('Press the button below to confirm your subscription.');
        break;

    case 'confirmed':
        $pg_fs_message = lang('Your subscription is confirmed. Thank you.');
        break;

    case 'member':
        $pg_fs_message = lang('You are already subscribed.');
        break;

    case 'expired':
        $pg_fs_message = lang('This link has expired. Submit the form again to get a new one.');
        break;

    case 'failed':
        $pg_fs_message = lang('Your confirmation could not be saved. Please try again.');
        break;

    default:
        $pg_fs_message = lang('This link cannot be used. Open the whole link from the e-mail, or submit the form again.');
}

$pg_fs_content =
    '<div style="max-width:520px;margin:2em auto;padding:1.5em;border:1px solid #dadce0;border-radius:8px;font-family:Roboto,Arial,sans-serif">'
    . '<h2 style="margin-top:0">' . h(lang('Confirm your subscription')) . '</h2>'
    . '<p>' . h($pg_fs_message) . '</p>';

if (($pg_fs_state === 'confirm') || ($pg_fs_state === 'failed')) {
    $pg_fs_content .=
        '<form method="post" action="' . h(OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/confirm_subscription.php') . '" style="margin-top:1.25em">'
        . get_token_field()
        . '<input type="hidden" name="c" value="' . h((string) $pg_fs_link['c']) . '"/>'
        . '<input type="hidden" name="g" value="' . h((string) $pg_fs_link['g']) . '"/>'
        . '<input type="hidden" name="t" value="' . h((string) $pg_fs_link['t']) . '"/>'
        . '<input type="hidden" name="s" value="' . h($pg_fs_link['s']) . '"/>'
        . '<button type="submit" class="software_input_submit_primary">' . h(lang('Confirm my subscription')) . '</button>'
        . '</form>';
}

$pg_fs_content .= '</div>';

print output_header_secure(array('title' => lang('Confirm your subscription'))) . $pg_fs_content . output_footer_secure();
