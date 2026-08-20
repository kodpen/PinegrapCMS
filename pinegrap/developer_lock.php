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
 *              2016–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

include('init.php');
$user = validate_user();
validate_area_access($user, 'manager');
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
              WHERE user_username = '" . mysqli_real_escape_string(db::$con, $SUser) . "'";
    $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
    $row = mysqli_fetch_assoc($result);
    $firstname_or_username = trim($row['first_name']) ?: $row['user_username'];

    // build pin input fields in rows of 4
    $pin_inputs = '';
    for ($i = 1; $i <= $pin_length; $i++) {
        if (($i - 1) % 4 === 0) {
            $pin_inputs .= '<div class="d-flex justify-content-center gap-2 mb-2">';
        }

        $pin_inputs .= '<input type="password"
                            class="text-center pin-input border border-primary rounded"
                            id="pin' . $i . '"
                            maxlength="1"
                            size="1"
                            pattern="[0-9]*"
                            inputmode="numeric"
                            autocomplete="new-password"
                            style="min-width: 40px; height: 50px; font-size: 1.5rem;" />';

        if ($i % 4 === 0 || $i === $pin_length) {
            $pin_inputs .= '</div>';
        }
    }

    print 
    pg_page_shell(array(
        'title'=> lang('Developer Lock'),
        'extra classes'=>'setting',
        'icon'=>'setting',
        'heading'=> lang('Developer Lock'),
        'cancel'=>array(
            'enable'=>true,
            'title'=>lang('Cancel')
        ),
        'breadcrumb' => array(
            array('label' => lang('Developer Lock')),
        ),
    )) . '
            <div class="row">
            <div class="col-12">
                ' . $liveform->output_errors() . '
                ' . $liveform->get_warnings() . '
                ' . $liveform->output_notices() . '
                <div class="row mb-2 flex-wrap">
                    <div class="col-12 text-center text-md-start">
<h2 class="d-inline-block" 
                            data-bs-content="' . lang('This warning page indicates that the page has been locked by a developer. Enter the correct pin code or contact your developer to access the locked page.') . '" 
                            title="' . lang('Developer Lock') . '">' 
                            . lang(array('string'=>'Enter Pin Code To Unlock Page ({var:1})','vars'=>$page_name)) . '
                        </h2>
                    </div>
                </div>
                <form name="form" action="developer_lock.php" method="post" autocomplete="off">
                    ' . get_token_field() . '
                    <div class="row">
                        <div class="col-12 col-md-6 mx-auto">
                            <div class="card my-4">
                                <div class="card-header bg-transparent border-0  text-center">
                                    <label for="pin1" class="form-label">' . lang('PIN CODE') . '</label>
                                </div>
                                <div class="card-body">
                                    
                                            ' . $pin_inputs . '
                                            <input type="password" id="pin" name="pin" style="display:none"/>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </main>
    <script>
// Input filter: allows only numeric input
(function($) {
  $.fn.inputFilter = function(inputFilter) {
    return this.on("input keydown keyup mousedown mouseup select contextmenu drop", function() {
      if (inputFilter(this.value)) {
        this.oldValue = this.value;
        this.oldSelectionStart = this.selectionStart;
        this.oldSelectionEnd = this.selectionEnd;
      } else if (this.hasOwnProperty("oldValue")) {
        this.value = this.oldValue;
        this.setSelectionRange(this.oldSelectionStart, this.oldSelectionEnd);
      }
    });
  };
})(jQuery);

// Apply numeric-only filter to all PIN inputs
$(".pin-input").inputFilter(function(value) {
  return /^\\d*$/.test(value);
});

// Handle input events for PIN fields
$(".pin-input").on("input", function() {
  // Get index of current input
  const index = $(".pin-input").index(this);

  // Focus next input if available
  const nextInput = $(".pin-input").eq(index + 1);
  if ($(this).val() && nextInput.length) {
    nextInput.focus();
  }

  // Combine all input values into hidden #pin field
  let pin = "";
  $(".pin-input").each(function() {
    pin += $(this).val();
  });
  $("#pin").val(pin);

  // Auto-submit form when all digits are entered
  if (pin.length === $(".pin-input").length) {
    $("form").submit();
  }
});

// Focus first input on page load
$(document).ready(function() {
  $(".pin-input").first().focus();
});
</script>
    
    
    ';
    $liveform->remove_form();

// else the form has been submitted
} else {
    validate_token_field();
    $liveform_settings = new liveform('developer_lock');
    $pin = str_replace("'", '', $_POST['pin']);

    if (DEVELOPER_PIN == $pin) {
        $hash = md5($pin);
        $query = "UPDATE user
                  SET user_devpasspin = '" . mysqli_real_escape_string(db::$con, $hash) . "'
                  WHERE user_username = '" . mysqli_real_escape_string(db::$con, $SUser) . "'";
        mysqli_query(db::$con, $query) or output_error('Query failed.');

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