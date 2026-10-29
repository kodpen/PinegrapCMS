<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The key code form, shared by the create and the edit screens
 * (add_key_code.php, edit_key_code.php): the checks of a posted key code,
 * the values a save writes and the generated code. Both screens draw
 * includes/templates/edit_key_code.php, chosen with $screen. The screens
 * keep what differs: the create screen's quantity checks and INSERT loop;
 * the edit screen's not-found guard, record load, delete and UPDATE.
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
 * Whether a code is already in a table: 'key_codes' (leaving the record
 * being edited out) or 'offers'. Codes compare as the database compares
 * them, so case does not make a second code.
 *
 * @param string $table      'key_codes' or 'offers'
 * @param string $code
 * @param mixed  $except_id  the edited key code's id, null on create
 * @return bool
 */
function pg_key_code_form_taken($table, $code, $except_id = null)
{
    $query = ($table == 'offers') ? "SELECT id FROM offers WHERE code = ?" : "SELECT id FROM key_codes WHERE code = ?";
    $values = array((string) $code);

    if (($table != 'offers') && ($except_id !== null)) {
        $query .= " AND id != ?";
        $values[] = (string) $except_id;
    }

    $result = db_q($query . " LIMIT 1", $values) or output_error('Query failed.');

    return mysqli_num_rows($result) > 0;
}

/**
 * The checks a posted key code shares between create and edit: offer code
 * required and an offer found for it; the key code not used by another key
 * code and not an offer's own code, which checkout would apply as the offer
 * without ever reading the key code; a typed expiration date valid. Create
 * may leave the code empty for the generator; edit requires it first.
 *
 * @param liveform $liveform
 * @param mixed    $id     null on create, the edited key code's id on edit
 * @param callable $taken  lookup as pg_key_code_form_taken() (tests stub it)
 */
function pg_key_code_form_validate($liveform, $id = null, $taken = null)
{
    if ($taken === null) {
        $taken = 'pg_key_code_form_taken';
    }

    $liveform->validate_required_field('offer_code', lang('Offer Code is required.'));

    $code = (string) $liveform->get_field_value('code');

    if (!$liveform->check_field_error('code') && ($code !== '')) {
        if ($taken('key_codes', $code, $id)) {
            $liveform->mark_error('code', lang('Sorry, the key code that you entered is already in use, so please enter a different key code.'));
        } elseif ($taken('offers', $code, null)) {
            $liveform->mark_error('code', lang('This is an offer\'s own code. Checkout would apply that offer and never read the key code.'));
        }
    }

    if (
        !$liveform->check_field_error('offer_code')
        && !$taken('offers', (string) $liveform->get_field_value('offer_code'), null)
    ) {
        $liveform->mark_error('offer_code', lang('Sorry, we cannot find an offer for the offer code that you entered.'));
    }

    $expiration_date = (string) $liveform->get_field_value('expiration_date');

    if (($expiration_date !== '') && (validate_date($expiration_date) == false)) {
        $liveform->mark_error('expiration_date', lang('Please enter a valid expiration date.'));
    }
}

/**
 * The seven key code values a save writes, in INSERT/UPDATE order: code,
 * offer code, enabled, expiration date (as stored), notes, single use,
 * report. The screens add the user and the timestamp after them.
 *
 * @param liveform $liveform
 * @param string   $code  the code to write (generated or typed on create)
 * @return array
 */
function pg_key_code_form_values($liveform, $code)
{
    return array(
        (string) $code,
        (string) $liveform->get_field_value('offer_code'),
        (string) $liveform->get_field_value('enabled'),
        (string) prepare_form_data_for_input((string) $liveform->get_field_value('expiration_date'), 'date'),
        (string) $liveform->get_field_value('notes'),
        (string) $liveform->get_field_value('single_use'),
        (string) $liveform->get_field_value('report'),
    );
}

/**
 * A new random key code of ten characters (0-9, A-Z) that is neither
 * another key code nor an offer's own code; '' when 50 tries all clash.
 * A key code is a secret that unlocks a discount, so it comes from the
 * CSPRNG.
 *
 * @param callable $taken  lookup as pg_key_code_form_taken() (tests stub it)
 * @return string
 */
function pg_key_code_form_generate($taken = null)
{
    if ($taken === null) {
        $taken = 'pg_key_code_form_taken';
    }

    $characters = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';

    for ($attempt = 0; $attempt < 50; $attempt++) {
        $code = '';

        for ($i = 0; $i < 10; $i++) {
            $code .= $characters[random_int(0, 35)];
        }

        if (!$taken('key_codes', $code, null) && !$taken('offers', $code, null)) {
            return $code;
        }
    }

    return '';
}
