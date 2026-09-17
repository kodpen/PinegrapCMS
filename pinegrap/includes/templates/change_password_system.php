<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// This file is a view partial. It is required from a page that has already run
// init.php and it depends on the constants and variables that bootstrap sets up.
// Requested directly over the web it runs with none of them and dies on the first
// constant it touches -- which is exactly how it turned up in the error log.
if (!defined('PG_INIT_LOADED')) {
    http_response_code(403);
    exit;
}
?>
<?=$messages?> 
<form <?=$attributes?>>
	<table style="margin-bottom: 1em">
		<tr>
			<td>
				<label for="email_address">Email*:</label>
			</td>
			<td>
				<input type="email" name="email_address" id="email_address" autocomplete="off" class="software_input_text">
			</td>
		</tr>
		<?php if (!empty($password_not_set)): ?> 
		<tr>
			<td colspan="2" style="padding-bottom: 1em"><?=lang('Your account signs in with Google and has no password yet. Set one below; afterwards you can sign in either way.')?></td>
		</tr>
		<?php else: ?> 
		<tr>
			<td>
				<label for="current_password">Current Password*:</label>
			</td>
			<td>
				<input type="password" name="current_password" id="current_password" class="software_input_password">
			</td>
		</tr>
		<?php endif ?>
		<?php if ($strong_password_help): ?> 
		<tr>
			<td colspan="2"> <?=$strong_password_help?> </td>
		</tr>
		<?php endif ?> 
		<tr>
			<td>
				<label for="new_password">New Password*:</label>
			</td>
			<td>
				<input type="password" name="new_password" id="new_password" class="software_input_password">
			</td>
		</tr>
		<tr>
			<td>
				<label for="new_password_verify">Confirm New Password*:</label>
			</td>
			<td>
				<input type="password" name="new_password_verify" id="new_password_verify" class="software_input_password">
			</td>
		</tr>
		<?php if (PASSWORD_HINT): ?> 
		<tr>
			<td>
				<label for="password_hint">Password Hint:</label>
			</td>
			<td>
				<input type="text" name="password_hint" id="password_hint" class="software_input_text">
			</td>
		</tr>
		<?php endif ?>
	</table>
	<div>
		<input type="submit" name="submit" value="Change Password" class="software_input_submit_primary submit_button"> <?php if ($my_account_url): ?> &nbsp; <a href="
			<?=h($my_account_url)?>" class="software_button_secondary cancel_button">Cancel </a> <?php endif ?>
	</div>
	<!-- Required hidden fields (do not remove) --> <?=$system?>
</form>