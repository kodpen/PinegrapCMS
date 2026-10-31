<?php
/**
 * PineGrap - Enterprise Website Platform
 *
 * Originally developed as LiveSite by Camelback Web Architects.
 * Since 2017, maintained and evolved by Erdal Güral (Kodpen) under the name PineGrap.
 * The final LiveSite update (2019) has been integrated into PineGrap.
 * LiveSite remains available as a separate downloadable legacy version.
 *
 * @author      Camelback Web Architects
 *              Erdal Güral (Kodpen)
 * @link        https://livesite.com
 *              https://kodpen.com
 * @copyright   2001–2019 Camelback Consulting, Inc.
 *              2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

include('init.php');
$user = validate_user();
validate_area_access($user, 'manager');

// The PIN is an optional config constant; without it there is nothing to unlock against.
if (!defined('DEVELOPER_PIN')) {
    output_error(lang('Developer PIN is not configured.'));
}

$page_name = initialize_developer_security();
include_once('liveform.class.php');

$liveform = new liveform('developer_lock');
$SUser = $_SESSION['sessionusername'];
$pin_length = strlen(DEVELOPER_PIN);

// if the form has not been submitted
if (!$_POST) {
    $query = "SELECT user_id, user_username, contacts.first_name
              FROM user
              LEFT JOIN contacts ON user.user_contact = contacts.id
              WHERE user_username = ?";
    $result = db_q($query, array((string) $SUser)) or output_error('Query failed.');
    $row = mysqli_fetch_assoc($result);
    $firstname_or_username = trim((string) $row['first_name']) ?: $row['user_username'];

    // One masked slot per PIN digit: Bootstrap's OTP component draws them
    // over a single real input (data-bs-otp starts it), which is what is
    // posted. A long PIN's slots shrink with the card (.pg-otp-fluid).
    $pin_inputs = '<div class="otp otp-lg otp-connected pg-otp-fluid justify-content-center" data-bs-otp="" data-bs-mask="true">
            <input type="text" id="pin" name="pin" class="otp-input" maxlength="' . (int) $pin_length . '" inputmode="numeric" autocomplete="off" spellcheck="false" autofocus="autofocus" aria-describedby="pin_hint"/>
        </div>';

    print 
    pg_page_shell(array(
        'title'=> lang('Developer Lock'),
        'extra classes'=>'setting',
        'icon'=>'setting',
        'heading'=> lang('Developer Lock'),
        'heading_description' => lang('This warning page indicates that the page has been locked by a developer. Enter the correct pin code or contact your developer to access the locked page.'),
        'cancel'=>array(
            'enable'=>true,
            'title'=>lang('Cancel')
        ),
        'breadcrumb' => array(
            array('label' => lang('Developer Lock')),
        ),
    )) . '
<main id="content" class="container-fluid">
            <div class="row">
            <div class="col-12">
                ' . $liveform->output_errors() . '
                ' . $liveform->get_warnings() . '
                ' . $liveform->output_notices() . '
                
                <form name="form" action="developer_lock.php" method="post" autocomplete="off">
                    ' . get_token_field() . '
                    <div class="row">
                        <div class="col-12 md:col-6 mx-auto">
                            <div class="card my-7">
                                <div class="card-header bg-transparent border-0  text-center">
                                    <label for="pin" class="form-label">' . lang('PIN CODE') . '</label>
                                </div>
                                <div class="card-body">
                                    ' . $pin_inputs . '
                                    <div id="pin_hint" class="form-text text-center mt-3">' . lang('The page opens as soon as every digit is entered.') . '</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </main>
    <style>
    @layer custom {
        .pg-otp-fluid .otp-slots, .pg-otp-fluid .otp-slot { min-width: 0; }
    }
    </style>
    <script>
    (function () {
        // Sends the form once every slot holds a digit (#pin_hint says so
        // in advance). The OTP component
        // takes over typing (beforeinput) and paste, so no native "input"
        // follows a keystroke: the value is read after each keyup and paste.
        var pin = document.getElementById("pin");
        var sent = false;
        function check() {
            if (!sent && pin.value.length === ' . (int) $pin_length . ' && /^[0-9]+$/.test(pin.value)) {
                sent = true;
                pin.form.submit();
            }
        }
        ["keyup", "input", "change"].forEach(function (type) {
            pin.addEventListener(type, check);
        });
        pin.addEventListener("paste", function () {
            window.setTimeout(check, 0);
        });
    })();
    </script>
    ';
    $liveform->remove_form();

// else the form has been submitted
} else {
    validate_token_field();
    $liveform_settings = new liveform('developer_lock');
    $pin = str_replace("'", '', $_POST['pin']);

    if (hash_equals((string) DEVELOPER_PIN, (string) $pin)) {
        $hash = md5($pin);
        $query = "UPDATE user
                  SET user_devpasspin = ?
                  WHERE user_username = ?";
        db_q($query, array($hash, (string) $SUser)) or output_error('Query failed.');

        log_activity(lang(array(
            'string'=>'Unlock Pin correct for access to {var:1}. Page and other developer locked pages accessible for user',
            'vars'=>$page_name
        )), $_SESSION['sessionusername']);

        header('Location: ' . URL_SCHEME . $_SERVER['HTTP_HOST'] . PATH . SOFTWARE_DIRECTORY . '/' . $page_name);
        exit;

    } else {
        $liveform_settings->mark_error('error', lang('Unlock pin is not correct'));
        log_activity(lang(array(
            'string'=>'Unlock Pin is not correct for access to {var:1}',
            'vars'=>$page_name
        )), $_SESSION['sessionusername']);

        header('Location: ' . URL_SCHEME . $_SERVER['HTTP_HOST'] . PATH . SOFTWARE_DIRECTORY . '/developer_lock.php');
        exit;
    }
}
?>