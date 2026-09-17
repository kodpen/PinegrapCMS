<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// The OpenAPI description, generated from the route table.
//
// It is generated rather than written because a hand-kept description drifts
// from the code within weeks, and a description that lies is worse than none:
// the integrator trusts it and the endpoint does something else. Everything
// here comes out of api_schema(), so a document that is wrong means the router
// is wrong too, and both are fixed in one place.
//
// The document is what feeds the panel's test screen and what an outside
// developer loads into their own tooling to generate a client.

if (!defined('PG_API_ENTRY') && !defined('PG_API_PANEL')) {
	exit;
}

function api_openapi_base_url() {

	$scheme = defined('URL_SCHEME') ? URL_SCHEME : 'https://';

	$host = defined('HOSTNAME_SETTING') ? HOSTNAME_SETTING : '';

	$path = (defined('OUTPUT_PATH') ? OUTPUT_PATH : '') . (defined('OUTPUT_SOFTWARE_DIRECTORY') ? OUTPUT_SOFTWARE_DIRECTORY : '');

	return $scheme . $host . $path . '/integration.php';

}

function api_openapi_build() {

	$paths = array();

	foreach (api_schema() as $route) {

		$path = $route['path'];

		if (!isset($paths[$path])) {

			$paths[$path] = array();

		}

		$operation = array(
			'operationId' => str_replace('.', '_', $route['id']),
			'summary'     => $route['summary'],
			'tags'        => array(api_openapi_tag($route['id'])),
			'security'    => array(array('applicationKey' => array())),
			'responses'   => api_openapi_responses($route)
		);

		if (isset($route['description'])) {

			$operation['description'] = $route['description'];

		}

		if ($route['scope'] !== '') {

			$operation['description'] = (isset($operation['description']) ? $operation['description'] . ' ' : '')
				. 'Requires the ' . $route['scope'] . ' scope.';

		}

		$parameters = array();

		$body_properties = array();

		$body_required = array();

		foreach ($route['params'] as $param) {

			$where = isset($param['in']) ? $param['in'] : 'query';

			if ($where === 'body') {

				$body_properties[$param['name']] = api_openapi_type($param);

				if (!empty($param['required'])) {

					$body_required[] = $param['name'];

				}

				continue;

			}

			$entry = array(
				'name'     => $param['name'],
				'in'       => ($where === 'path') ? 'path' : 'query',
				'required' => ($where === 'path') ? true : !empty($param['required']),
				'schema'   => api_openapi_type($param)
			);

			if (isset($param['description'])) {

				$entry['description'] = $param['description'];

			}

			$parameters[] = $entry;

		}

		if (!empty($parameters)) {

			$operation['parameters'] = $parameters;

		}

		if (!empty($body_properties)) {

			$body_schema = array('type' => 'object', 'properties' => $body_properties);

			if (!empty($body_required)) {

				$body_schema['required'] = $body_required;

			}

			$operation['requestBody'] = array(
				'required' => !empty($body_required),
				'content'  => array('application/json' => array('schema' => $body_schema))
			);

		}

		// A write may carry an idempotency key. Declared here rather than in the
		// route table because it is true of every write and repeating it on each
		// one is how the two lists start to disagree.
		if (in_array($route['method'], array('POST', 'PUT', 'PATCH', 'DELETE'), true)) {

			if (!isset($operation['parameters'])) {

				$operation['parameters'] = array();

			}

			$operation['parameters'][] = array(
				'name'        => 'Idempotency-Key',
				'in'          => 'header',
				'required'    => false,
				'description' => 'A value of your choosing, up to 64 characters. Repeating a request with the same key returns the first answer instead of applying the change again.',
				'schema'      => array('type' => 'string', 'maxLength' => 64)
			);

		}

		$paths[$path][strtolower($route['method'])] = $operation;

	}

	return array(
		'openapi' => '3.0.3',
		'info' => array(
			'title'       => (defined('TITLE') ? TITLE : 'Pinegrap') . ' API',
			'version'     => (string)api_version(),
			'description' => 'Read and write products, stock, orders and customers. '
				. 'Money is always a whole number of minor units - 1999 is 19.99. '
				. 'Times are ISO-8601 in UTC. Listings are cursor paged: follow page.next_cursor until it is null.'
		),
		'servers' => array(array('url' => api_openapi_base_url())),
		'components' => array(
			'securitySchemes' => array(
				'applicationKey' => array(
					'type'        => 'http',
					'scheme'      => 'basic',
					'description' => 'HTTP Basic. The user name is the application key, the password is its secret.'
				)
			)
		),
		'security' => array(array('applicationKey' => array())),
		'paths' => $paths
	);

}

function api_openapi_tag($route_id) {

	$parts = explode('.', $route_id);

	return ucfirst($parts[0]);

}

function api_openapi_type($param) {

	$type = isset($param['type']) ? $param['type'] : 'string';

	switch ($type) {

		case 'int':

			$schema = array('type' => 'integer');

			if (isset($param['min'])) {

				$schema['minimum'] = $param['min'];

			}

			if (isset($param['max'])) {

				$schema['maximum'] = $param['max'];

			}

			if (isset($param['default'])) {

				$schema['default'] = $param['default'];

			}

			return $schema;

		case 'money':

			return array('type' => 'integer', 'description' => 'Whole number of minor units. 1999 is 19.99.');

		case 'decimal':

			return array('type' => 'number');

		case 'bool':

			return array('type' => 'boolean');

		case 'enum':

			return array('type' => 'string', 'enum' => $param['values']);

		case 'datetime':

			return array('type' => 'string', 'description' => 'ISO-8601, YYYY-MM-DD, or a unix timestamp.');

		case 'list':

			return array('type' => 'array', 'items' => array('type' => 'object'));

		default:

			$schema = array('type' => 'string');

			if (isset($param['max_length'])) {

				$schema['maxLength'] = $param['max_length'];

			}

			return $schema;

	}

}

// The failure shapes are the same everywhere, so they are described once and
// attached to every operation. An integrator reading any endpoint sees the whole
// error contract without having to find the one page that documents it.
function api_openapi_responses($route) {

	$error = array(
		'type' => 'object',
		'properties' => array(
			'error' => array(
				'type' => 'object',
				'properties' => array(
					'code'       => array('type' => 'string'),
					'message'    => array('type' => 'string'),
					'field'      => array('type' => 'string'),
					'request_id' => array('type' => 'string')
				)
			)
		)
	);

	$error_content = array('application/json' => array('schema' => $error));

	return array(
		'200' => array('description' => 'Success'),
		'401' => array('description' => 'The key or the secret is wrong, or none was sent.', 'content' => $error_content),
		'403' => array('description' => 'The application is not allowed to do this.', 'content' => $error_content),
		'404' => array('description' => 'No such record.', 'content' => $error_content),
		'422' => array('description' => 'A parameter is missing or the wrong shape.', 'content' => $error_content),
		'429' => array('description' => 'Rate limit reached. Retry-After says how long to wait.', 'content' => $error_content)
	);

}

function api_openapi_document($params) {

	api_ok(api_openapi_build());

}
