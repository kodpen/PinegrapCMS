<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// GET /security-scan: the Security Scan's findings and its last run, read
// only (includes/fn/security_scan_section.php). It answers "is the site at
// risk?" for the operator's monitoring or a connected assistant; Quarantine,
// Ignore and Scan now stay a person's clicks in the panel.

if (!defined('PG_API_ENTRY') && !defined('PG_API_PANEL')) {

	exit;

}

function api_security_scan_read($params) {

	$ready = pg_security_finding_ready();

	$state = isset($params['state']) ? (string)$params['state'] : '';

	api_ok(api_security_scan_answer($ready ? pg_security_section_rows() : array(), pg_security_scan_stored_state(), $ready, $state));

}

// The findings and the run state as the answer. Apart from the handler so a
// test reaches it without a database. $rows are the section's rows (gone
// ones left out); $only is a state key, '' for every listed state. The
// counts and the risk always cover every row, whatever $only lists.
function api_security_scan_answer(array $rows, array $state, $ready, $only) {

	$rows = pg_security_section_sort($rows);

	$counts = pg_security_section_open_counts($rows);

	$open = array();

	$worst = null;

	foreach ($counts as $severity => $count) {

		$open[pg_security_severity_key($severity)] = (int)$count;

		if ($worst === null && $count > 0) {

			$worst = pg_security_severity_key($severity);

		}

	}

	$quarantined = 0;

	$ignored = 0;

	$findings = array();

	foreach ($rows as $row) {

		$row_state = (int)$row['state'];

		if ($row_state === PG_SECURITY_STATE_QUARANTINED) {

			$quarantined++;

		} elseif ($row_state === PG_SECURITY_STATE_IGNORED) {

			$ignored++;

		}

		if ($only !== '' && pg_security_state_key($row_state) !== $only) {

			continue;

		}

		$findings[] = api_security_finding_present($row);

	}

	$running = (int)$state['started'] > 0;

	return array(
		'ready'       => (bool)$ready,
		'scanned'     => (int)$state['last_finished'] > 0,
		'at_risk'     => ($counts[PG_SECURITY_SEVERITY_CRITICAL] + $counts[PG_SECURITY_SEVERITY_HIGH]) > 0,
		'worst'       => $worst,
		'open'        => $open,
		'quarantined' => $quarantined,
		'ignored'     => $ignored,
		'scan'        => array(
			'running'          => $running,
			'phase'            => $running ? (string)$state['phase'] : null,
			'progress'         => $running ? pg_security_scan_progress($state) : null,
			'started_at'       => $running ? api_time($state['started']) : null,
			'last_finished_at' => api_time($state['last_finished']),
			'last_files'       => (int)$state['last_files'],
			'last_records'     => (int)$state['last_records']
		),
		'findings'    => $findings
	);

}

// One finding as the API shows it: keys to branch on, and the reason and
// what to do in the site language, as the panel's row reads them.
function api_security_finding_present(array $row) {

	$text = pg_security_rule_text($row['kind'], $row['detail']);

	$detail = pg_security_section_detail($row['kind'], $row['detail']);

	$note = (string)$row['note'];

	return array(
		'id'            => (int)$row['id'],
		'kind'          => (string)$row['kind'],
		'severity'      => pg_security_severity_key($row['severity']),
		'state'         => pg_security_state_key($row['state']),
		'place'         => (string)$row['place'],
		'reason'        => $text['reason'],
		'what_to_do'    => $text['advice'],
		'detail'        => ($detail === '') ? null : $detail,
		'note'          => ($note === '') ? null : $note,
		'quarantinable' => pg_security_quarantinable($row['kind']),
		'first_seen'    => api_time($row['first_seen']),
		'last_seen'     => api_time($row['last_seen']),
		'acted_at'      => api_time($row['acted_at'])
	);

}

// What api_security_scan_read() answers with, declared for the OpenAPI document.
function api_security_scan_schema() {

	return array(
		'ready'       => 'boolean',
		'scanned'     => 'boolean',
		'at_risk'     => 'boolean',
		'worst'       => 'string?',
		'open'        => array(
			'critical' => 'integer',
			'high'     => 'integer',
			'medium'   => 'integer',
			'low'      => 'integer'
		),
		'quarantined' => 'integer',
		'ignored'     => 'integer',
		'scan'        => array(
			'running'          => 'boolean',
			'phase'            => 'string?',
			'progress'         => 'string?',
			'started_at'       => 'datetime?',
			'last_finished_at' => 'datetime?',
			'last_files'       => 'integer',
			'last_records'     => 'integer'
		),
		'findings'    => array(array(
			'id'            => 'integer',
			'kind'          => 'string',
			'severity'      => 'string',
			'state'         => 'string',
			'place'         => 'string',
			'reason'        => 'string',
			'what_to_do'    => 'string',
			'detail'        => 'string?',
			'note'          => 'string?',
			'quarantinable' => 'boolean',
			'first_seen'    => 'datetime?',
			'last_seen'     => 'datetime?',
			'acted_at'      => 'datetime?'
		))
	);

}
