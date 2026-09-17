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
	<?php if (!$screen): ?>
	<table>
		<tr>
			<td><label for="email">Email:</label></td>
			<td><input name="email" id="email" type="email" size="30" class="software_input_text"></td>
		</tr>
		<tr>
			<td>&nbsp;</td>
			<td>
				<input type="submit" name="submit" value="Send Email" class="software_input_submit_primary submit_button" style="margin-top: 1em">
			</td>
		</tr>
	</table>
	<?php elseif ($screen == 'password_hint'): ?>
	<p>Your Password Hint is: <strong><?=h($password_hint)?></strong></p>
	<p>Do you remember your password now?</p>
	<div>
		<a href="<?=h($send_to)?>" class="software_button_primary">Yes, I Remember</a>
		&nbsp;
		<input type="submit" value="No, I Forget" class="software_input_submit_primary">
	</div>
	<?php endif ?>
	<!-- Required hidden fields (do not remove) -->
	<?=$system?>
</form>