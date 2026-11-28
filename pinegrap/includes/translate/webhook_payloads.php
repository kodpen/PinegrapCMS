<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// What each translation event (translate_webhook_events()) carries in data,
// in the shape of the core's api_webhook_event_payloads(): its fields, each
// with a type and a meaning, and one sample. The fields are the keys
// pg_tr_job_announce_finished() writes; tests/webhook_event_payloads_test.php
// reads them and fails when the two part.

if (!defined('PG_API_ENTRY') && !defined('PG_API_PANEL') && !defined('PG_INIT_LOADED')) {
	exit;
}

function translate_webhook_event_payloads()
{
	return array(
		'translation.update_finished' => array(
			'fields' => array(
				'id'                 => array('integer', 'The update (GET /translations/updates/{id}).'),
				'language'           => array('string', 'The target language code.'),
				'scope'              => array('string', 'What it covered: all, page:<id>, group:<catalog, forms, pictures or ui>, or one item a save opened it for, such as product:<id>.'),
				'engine'             => array('string', 'The engine it ran on, such as google, ai or claude.'),
				'status'             => array('string', 'done, failed or cancelled.'),
				'total'              => array('integer', 'The texts it held.'),
				'translated'         => array('integer', 'The texts translated.'),
				'failed'             => array('integer', 'The texts the engine failed on; their reasons are read with GET /translations/updates/{id}.'),
				'error'              => array('string', 'Why the update failed as a whole, empty for none.'),
				'fallback_update_id' => array('integer?', 'The update opened for the failed texts on the language\'s fallback engine, null for none.')
			),
			'sample' => array('id' => 318, 'language' => 'de', 'scope' => 'page:12', 'engine' => 'google', 'status' => 'done', 'total' => 42, 'translated' => 40, 'failed' => 2, 'error' => '', 'fallback_update_id' => 319)
		)
	);
}
