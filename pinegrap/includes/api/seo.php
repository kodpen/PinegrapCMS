<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// The SEO record, in the one shape every endpoint reports it in.
//
// page, products and product_groups carry the same score columns and share the
// seo_issue table, so this is written once here rather than three times in
// three resources.
//
// Nothing here calculates anything. The meta half of the record is written when
// a record is saved and by the nightly score job; the structure and link halves
// by the nightly analysis job, which renders each page and reads its markup.
// A read endpoint that recalculated would turn one listing into a site-wide
// re-scoring, so it reports what those passes left behind - including how long
// ago each half was examined, which is the part a client needs in order to know
// whether its own change has been taken into account yet.

if (!defined('PG_API_ENTRY')) {
	exit;
}

// The score engine lives in its own file rather than functions.php because the
// site does not need it on every request. Loaded on demand, once.
function api_seo_library() {

	static $loaded = false;

	if ($loaded) {

		return;

	}

	$directory = defined('PG_FUNCTIONS_DIR') ? PG_FUNCTIONS_DIR : dirname(dirname(dirname(__FILE__)));

	require_once($directory . '/seo.php');

	$loaded = true;

}

// page.seo_depth arrives with the same upgrade as the structure columns but in
// a statement of its own, so an installation interrupted between the two has
// one and not the other. Probed on its own for that reason, once per request.
function api_seo_depth_ready() {

	static $ready = null;

	if ($ready === null) {

		$ready = (api_row("SHOW COLUMNS FROM page LIKE 'seo\\_depth'") !== null);

	}

	return $ready;

}

// The score columns for a record table, as a SELECT fragment ending in a comma.
//
// Columns that arrive with an upgrade are named only when they are there:
// selecting one that does not exist is a failed query, and these endpoints have
// to keep working on an installation that has not been upgraded yet. The
// substitutes keep every consumer below free of "is this column here" checks.
function api_seo_columns($prefix = '', $with_depth = false) {

	api_seo_library();

	$sql = $prefix . "seo_score, " . $prefix . "seo_analysis, " . $prefix . "seo_analysis_current,";

	$sql .= pg_seo_schema_ready()
		? " " . $prefix . "seo_flags, " . $prefix . "seo_checked_at, " . $prefix . "seo_meta_score,"
		: " '0' AS seo_flags, '0' AS seo_checked_at, NULL AS seo_meta_score,";

	$sql .= pg_seo_structure_schema_ready()
		? " " . $prefix . "seo_struct_score, " . $prefix . "seo_struct_current, " . $prefix . "seo_struct_checked_at,"
		: " NULL AS seo_struct_score, '0' AS seo_struct_current, '0' AS seo_struct_checked_at,";

	$sql .= pg_seo_link_schema_ready()
		? " " . $prefix . "seo_link_score, " . $prefix . "seo_speed_score,"
		: " NULL AS seo_link_score, NULL AS seo_speed_score,";

	if ($with_depth) {

		$sql .= api_seo_depth_ready() ? " " . $prefix . "seo_depth," : " NULL AS seo_depth,";

	}

	return $sql;

}

// Which failed checks the flag bitmask is holding, as stable codes. The codes
// are the client's contract and stay English; the translated sentences belong
// to the panel screens.
function api_seo_flag_codes($flags) {

	api_seo_library();

	$flags = (int)$flags;

	$out = array();

	if ($flags === 0) {

		return $out;

	}

	foreach (pg_seo_flag_defs() as $name => $bit) {

		if (($flags & $bit) === $bit) {

			$out[] = $name;

		}

	}

	return $out;

}

// Everything wrong with the record, from both halves of it.
//
// The meta half is the checks array inside seo_analysis, written whenever the
// record is scored. The structure and link halves are rows in seo_issue,
// written by the analysis job. The panel's own detail screen merges exactly
// these two sources; a client asking "what should I fix" gets the same list.
//
// Speed checks are left out. They describe how the page is delivered rather
// than what is written on it, and nothing a client can write here moves them.
function api_seo_issues($type, $id, $analysis_json) {

	api_seo_library();

	$out = array();

	$analysis = json_decode((string)$analysis_json, true);

	if (is_array($analysis) && !empty($analysis['checks'])) {

		foreach ($analysis['checks'] as $check) {

			if (!isset($check['c']) || !isset($check['s']) || $check['s'] === 'ok') {

				continue;

			}

			if (strpos((string)$check['c'], 'speed_') === 0) {

				continue;

			}

			$out[] = array(
				'source'   => 'meta',
				'code'     => $check['c'],
				'severity' => ($check['s'] === 'fail') ? 'error' : 'warning',
				'label'    => pg_seo_check_label($check),
				'weight'   => isset($check['w']) ? (int)$check['w'] : 0,
				'earned'   => isset($check['e']) ? (int)$check['e'] : 0
			);

		}

	}

	if (pg_seo_structure_schema_ready()) {

		foreach (pg_seo_load_issues($type, (int)$id) as $issue) {

			$code = (string)$issue['code'];

			// Link findings are told apart by their code rather than by the
			// scope column, which records which pass wrote the row and not what
			// the row is about.
			$source = ((strpos($code, 'link_') === 0) || ($code === 'orphan_page')) ? 'links' : 'structure';

			$entry = array(
				'source'      => $source,
				'code'        => $code,
				'severity'    => $issue['severity'],
				'label'       => pg_seo_issue_label($code, $issue['occurrences'], $issue['detail']),
				'occurrences' => (int)$issue['occurrences'],
				'detail'      => $issue['detail']
			);

			// Where the markup came from: the record itself, a common region, a
			// style. Empty for a finding the analyser could not attribute.
			if (isset($issue['source']) && ($issue['source'] !== '')) {

				$entry['layer'] = $issue['source'];

			}

			$out[] = $entry;

		}

	}

	return $out;

}

// $type is the entity type the findings are filed under: page, product or
// product_group.
function api_seo_block($type, $id, $row, $with_issues = false) {

	api_seo_library();

	$scored = pg_seo_row_scored($row);

	$block = array(
		'score'  => $scored ? (int)$row['seo_score'] : null,
		'scores' => array(
			'meta'      => ($row['seo_meta_score'] === null) ? null : (int)$row['seo_meta_score'],
			'structure' => ($row['seo_struct_score'] === null) ? null : (int)$row['seo_struct_score'],
			'links'     => ($row['seo_link_score'] === null) ? null : (int)$row['seo_link_score'],
			'speed'     => ($row['seo_speed_score'] === null) ? null : (int)$row['seo_speed_score']
		),
		'scored_at'   => api_time($row['seo_checked_at']),
		'analyzed_at' => api_time($row['seo_struct_checked_at']),

		// Which half is waiting for a recalculation. A write refreshes the meta
		// half at once; the structure half belongs to the nightly job, so a
		// client that has just fixed a heading sees stale.structure true until
		// that job runs and knows not to write the same fix again.
		'stale' => array(
			'meta'      => ((int)$row['seo_analysis_current'] === 0),
			'structure' => (pg_seo_structure_schema_ready() && ((int)$row['seo_struct_current'] === 0))
		),
		'flags' => api_seo_flag_codes($row['seo_flags'])
	);

	// Only pages carry it: how many clicks from the home page the record is.
	if (array_key_exists('seo_depth', $row)) {

		$block['depth'] = ($row['seo_depth'] === null) ? null : (int)$row['seo_depth'];

	}

	if ($with_issues) {

		$block['issues'] = api_seo_issues($type, $id, $row['seo_analysis']);

	}

	return $block;

}

/**
 * What api_seo_block() returns, for the OpenAPI document.
 *
 * Declared beside the function that builds it so the two are read together;
 * tools/check_api_schema.php compares the two key lists.
 *
 * @return array
 */
function api_seo_schema()
{
    return array(
        'score'  => 'integer?',
        'scores' => array(
            'meta'      => 'integer?',
            'structure' => 'integer?',
            'links'     => 'integer?',
            'speed'     => 'integer?'
        ),
        'scored_at'   => 'string?',
        'analyzed_at' => 'string?',
        'stale' => array(
            'meta'      => 'boolean',
            'structure' => 'boolean'
        ),
        'flags'  => 'string[]',
        'depth'  => 'integer?',
        'issues' => array(array(
            'code'     => 'string',
            'severity' => 'string',
            'message'  => 'string'
        ))
    );
}
