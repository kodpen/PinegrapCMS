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
validate_contacts_access($user);

// get all contact groups
$query =
    "SELECT
       id,
       name
    FROM contact_groups
    ORDER BY name";
$result = mysqli_query(db::$con, $query) or output_error('Query failed.');

$output_add_to_contact_groups = '';
$output_remove_from_contact_groups = '';

// loop through all contact groups
while ($row = mysqli_fetch_assoc($result)) {
    $id = $row['id'];
    $name = $row['name'];
    
    // if user has access to contact group, then include this contact group
    if (validate_contact_group_access($user, $id)) {
        // get number of contacts in contact group
        $number_of_contacts = get_number_of_contacts($id);
        
        $output_add_to_contact_groups .= '
        <div class="form-check">
            <input type="checkbox" name="add_to_contact_groups" id="add_to_contact_group_' . $id . '" value="' . $id . '" class="checkbox form-check-input multiselect-checkbox" />
            <label class="form-check-label" for="add_to_contact_group_' . $id . '">' . h($name) . ' (' . number_format($number_of_contacts) . ')</label>    
        </div>';
        $output_remove_from_contact_groups .= '
        <div class="form-check">
        <input type="checkbox" name="remove_from_contact_groups" id="remove_from_contact_group_' . $id . '" value="' . $id . '" class="checkbox form-check-input multiselect-checkbox" />
            <label class="form-check-label" for="remove_from_contact_group_' . $id . '">' . h($name) . ' (' . number_format($number_of_contacts) . ')</label>    
        </div>';
    }
}

print
output_header_secure(array('title'=>lang('Organize Contacts'),'icon'=>'contact')) . '
<script type="text/javascript">
    function select_groups()
    {
        opener.document.form.add_to_contact_groups.value = "";
        
        // loop through all add to contact group checkboxes
        for (i = 0; i < document.form.add_to_contact_groups.length; i++) {
            // if contact group checkbox is checked, then add contact group to hidden form field on opener
            if (document.form.add_to_contact_groups[i].checked == true) {
                // if there is already contact groups in the list of contact groups, then add a comma first
                if (opener.document.form.add_to_contact_groups.value) {
                    opener.document.form.add_to_contact_groups.value += ",";
                }
                
                opener.document.form.add_to_contact_groups.value += document.form.add_to_contact_groups[i].value;
            }
        }
        
        opener.document.form.remove_from_contact_groups.value = "";
        
        // loop through all remove from contact group checkboxes
        for (i = 0; i < document.form.remove_from_contact_groups.length; i++) {
            // if contact group checkbox is checked, then add contact group to hidden form field on opener
            if (document.form.remove_from_contact_groups[i].checked == true) {
                // if there is already contact groups in the list of contact groups, then add a comma first
                if (opener.document.form.remove_from_contact_groups.value) {
                    opener.document.form.remove_from_contact_groups.value += ",";
                }
                
                opener.document.form.remove_from_contact_groups.value += document.form.remove_from_contact_groups[i].value;
            }
        }
        
        // submit opener form and close this popup window
        opener.document.form.submit();
        window.close();
    }
</script>
<nav id="header" class="navbar sticky-top rounded-0 navbar-expand border-bottom shadow-sm bg-body">
    <div class="container-fluid">
        <span class="navbar-text me-auto">' . lang('Organize Contacts') . '</span>
        <ul class="navbar-nav ms-auto">
            <li class="nav-item dropdown no-popover"  title="' . lang('Software Theme') . '">
                <button class="nav-link nav-link-sm position-relative dropdown-toggle dropdown-menu-right d-none" data-bs-toggle="dropdown" id="bd-theme" type="button"><span class="bi bi-circle-half"></span></button>
                <ul aria-labelledby="bd-theme" class="dropdown-menu shadow dropdown-menu-end p-1 bg-body backdrop mt-nav-link-sm border-dropdown-menu" data-bs-popper="static" style="--bs-dropdown-min-width: 8rem;">
                    <li><button class="dropdown-item dropdown-item-sm rounded p-0 my-1 d-flex align-items-center" data-bs-theme-value="light" type="button"><i class="bi bi-sun-fill m-2"></i>' . lang('Light') . '</button></li>
                    <li><button class="dropdown-item dropdown-item-sm rounded p-0 my-1 d-flex align-items-center active" data-bs-theme-value="dark" type="button"><i class="bi bi-moon-stars-fill m-2"></i>' . lang('Dark') . '</button></li>
                    <li><button class="dropdown-item dropdown-item-sm rounded p-0 my-1 d-flex align-items-center" data-bs-theme-value="auto" type="button"><i class="bi bi-circle-half m-2"></i>' . lang('Auto') . '</button></li>
                </ul>
            </li>
            <li class="nav-item">
                <button title="' . lang('Close') . '" type="button" class="nav-link nav-link-sm position-relative no-popover" onclick="window.close()" aria-label="Close">
                    <span class="bi bi-x-lg"></span>
                </button>
            </li>
        </ul>
    </div>
</nav>

<main id="content" class="container">
	<form name="form">
		<div class="row">
            <div class="col-xs-12  col-sm-12 col-md-8 col-lg-6 card-group">
                <div class="card mt-1 mb-2 multiselect-checkbox-container">
                    <div class="card-header bg-reset border-0">' . lang(array('string'=>'Add to Contact Groups') ) . '</div>
                    <div class="card-header border-0 bg-reset">
                        <div class="form-check form-switch">
                            <input id="multiselect-checkbox-checker-0" class="form-check-input multiselect-checkbox-checker" title="' . lang(array('string'=>'Select/Deselect All') ) . '" type="checkbox">
                            <label for="multiselect-checkbox-checker-0" class="form-check-label">' . lang('Select All') . '</label>
                        </div>
                    </div>
                    <div class="card-body overflow-auto" style="max-height:300px">
                        ' . $output_add_to_contact_groups . '
                    </div>
                </div>
            </div>
            <div class="col-xs-12  col-sm-12 col-md-8 col-lg-6 card-group">
                <div class="card mt-1 mb-2 multiselect-checkbox-container">
                    <div class="card-header bg-reset  border-0">' . lang(array('string'=>'Remove from Contact Groups') ) . '</div>
                    <div class="card-header border-0 bg-reset">
                        <div class="form-check form-switch">
                            <input id="multiselect-checkbox-checker-1" class="form-check-input multiselect-checkbox-checker" title="' . lang(array('string'=>'Select/Deselect All') ) . '" type="checkbox">
                            <label for="multiselect-checkbox-checker-1" class="form-check-label">' . lang('Select All') . '</label>
                        </div>
                    </div>
                    <div class="card-body overflow-auto" style="max-height:300px">
                        ' . $output_remove_from_contact_groups . '
                    </div>
                </div>
            </div>
        </div>
		<nav class="buttons navigation text-center position-sticky" style="bottom:.5rem;" aria-label="data edit buttons ">
			<div class="container">
				<div class=" btn-group btn-group-sm flex-wrap justify-content-center mb-0">
					<button type="button" name="submit_save" value="Organize Contact(s)" class="btn mb-1 mt-1  btn-primary submit-primary" onclick="select_groups()"><span class="material-icons me-2">edit</span>' . lang(array('string'=>'Organize Contact(s)') ) . '</button>
				</div>
			</div>
		</nav>
    </form>
</main>' . 
output_footer_secure();

?>