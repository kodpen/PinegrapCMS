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

			// Declared only where the handler stops for it. A module writes its
			// own routes through the seam, and one that has not put the stop in
			// must not advertise a rehearsal that would write.
			if (!empty($route['dry_run'])) {

				$operation['parameters'][] = array(
					'name'        => 'X-Dry-Run',
					'in'          => 'header',
					'required'    => false,
					'description' => 'true runs every check this endpoint makes and then answers instead of writing. The answer is {"dry_run": true, "would": "created", "resource": "product"}; a refusal is the real refusal, with the same code and the same field.',
					'schema'      => array('type' => 'string', 'enum' => array('true', 'false'))
				);

			}

		}

		$paths[$path][strtolower($route['method'])] = $operation;

		// A route that answers to a second verb is listed under that verb too.
		//
		// A generated client, or a test suite built from this document, reads
		// nothing but what is written here. Leaving the alias out is what turns a
		// documented fallback into a test that fails: a default IIS install
		// answers DELETE itself before PHP is reached, the API accepts POST for
		// exactly that reason, and a document that only names DELETE sends the
		// caller down the road that does not arrive.
		if (!empty($route['also_accepts'])) {

			foreach ($route['also_accepts'] as $spare) {

				$alias = $operation;

				$alias['operationId'] = $operation['operationId'] . '_' . strtolower($spare);

				$alias['summary'] = $operation['summary'] . ' (' . strtoupper($spare) . ')';

				$paths[$path][strtolower($spare)] = $alias;

			}

		}

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
			'schemas' => api_openapi_components(),
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
/**
 * A declared field map, compiled into JSON Schema.
 *
 * Every resource file says what it returns as one line per field, beside the
 * function that builds it: 'price' => 'integer', 'seo' => 'Seo',
 * 'group_ids' => 'integer[]'. The declaration is read and changed in the same
 * place as the code it describes, which is the only arrangement that survives
 * a year of edits.
 *
 * The spelling:
 *   string | integer | number | boolean | object   a JSON type
 *   Product                                        another declared object ($ref)
 *   integer[] | Product[]                          a list of those
 *   string?                                        may be null
 *   array('a' => 'integer', ...)                   an object written inline
 *   array(array('a' => 'integer'))                 a list of those
 *
 * The question mark matters more than it looks: a generated client that maps a
 * nullable column to a non-nullable field breaks on the first order placed
 * without a phone number.
 *
 * @param mixed $spec
 * @return array
 */
function api_openapi_type_spec($spec)
{
    if (is_array($spec)) {

        // A list with one member describes an array of that member.
        if (isset($spec[0])) {

            return array('type' => 'array', 'items' => api_openapi_type_spec($spec[0]));

        }

        $properties = array();

        foreach ($spec as $name => $field) {

            $properties[$name] = api_openapi_type_spec($field);

        }

        return array('type' => 'object', 'properties' => $properties);

    }

    $spec = trim((string) $spec);

    $nullable = false;

    if (substr($spec, -1) === '?') {

        $nullable = true;

        $spec = substr($spec, 0, -1);

    }

    if (substr($spec, -2) === '[]') {

        $schema = array('type' => 'array', 'items' => api_openapi_type_spec(substr($spec, 0, -2)));

    } elseif ($spec !== '' && ctype_upper(substr($spec, 0, 1))) {

        $schema = array('$ref' => '#/components/schemas/' . $spec);

    } else {

        $schema = array('type' => ($spec === '') ? 'string' : $spec);

    }

    if ($nullable) {

        // In 3.0 a $ref cannot carry a sibling keyword, so a nullable reference
        // is written as a one-member allOf with the flag outside it.
        $schema = isset($schema['$ref'])
            ? array('nullable' => true, 'allOf' => array($schema))
            : array_merge($schema, array('nullable' => true));

    }

    return $schema;
}

/**
 * The objects the API returns, by the name routes refer to them with.
 *
 * Each entry names the function that declares that object's fields. The
 * functions live beside the presenters that build the same shape, and
 * tools/check_api_schema.php is what notices when one of the two is changed
 * without the other.
 *
 * @return array name => declaring function
 */
function api_openapi_objects()
{
    $objects = array(
        'Meta'         => 'api_meta_schema',
        'Product'      => 'api_product_schema',
        'ProductGroup' => 'api_product_group_schema',
        'Order'        => 'api_order_schema',
        'Customer'     => 'api_customer_schema',
        'Page'         => 'api_page_schema',
        'File'         => 'api_file_schema',
        'Offer'        => 'api_offer_schema',
        'Webhook'      => 'api_webhook_schema',
        'Inventory'    => 'api_inventory_schema',
        'InventoryBatch' => 'api_inventory_batch_schema',
        'PriceBatch'     => 'api_price_batch_schema',
        'Seo'          => 'api_seo_schema',
        'Form'           => 'api_form_schema',
        'FormField'      => 'api_form_field_schema',
        'FormSubmission' => 'api_form_submission_schema',
        'SeoIssue'       => 'api_seo_issue_schema',
        'SystemStatus'   => 'api_system_status_schema',
        'SalesReport'    => 'api_sales_report_schema',
    );

    // A module declares the objects its own endpoints answer with, the same way
    // and under its own names.
    require_once(dirname(__FILE__) . '/modules.php');

    return $objects + api_module_contributions('openapi_objects');
}

/**
 * components.schemas, built from those declarations.
 *
 * @return array
 */
function api_openapi_components()
{
    $schemas = array();

    foreach (api_openapi_objects() as $name => $declare) {

        if (!function_exists($declare)) {

            continue;

        }

        $schemas[$name] = api_openapi_type_spec(call_user_func($declare));

    }

    // The envelope every listing arrives in. Its shape is the paging contract:
    // follow page.next_cursor until it is null.
    $schemas['Paging'] = api_openapi_type_spec(array(
        'limit'       => 'integer',
        'has_more'    => 'boolean',
        'next_cursor' => 'string?',
        'total'       => 'integer?'
    ));

    $schemas['Error'] = api_openapi_type_spec(array(
        'error' => array(
            'code'       => 'string',
            'message'    => 'string',
            'field'      => 'string?',
            'request_id' => 'string'
        )
    ));

    return $schemas;
}

/**
 * What one route answers with, as the 200 content.
 *
 * A route declares it as 'returns' => 'Product' for the object itself, or
 * array('list' => 'Product') for the listing envelope around it. A route that
 * returns something with no shape worth naming - the OpenAPI document, the
 * console's own HTML - declares nothing and keeps the bare description it had.
 *
 * @param array $route
 * @return array|null
 */
function api_openapi_success_schema($route)
{
    if (!isset($route['returns'])) {

        return null;

    }

    $returns = $route['returns'];

    if (is_array($returns) && isset($returns['list'])) {

        return api_openapi_type_spec(array(
            'data' => $returns['list'] . '[]',
            'page' => 'Paging'
        ));

    }

    return api_openapi_type_spec($returns);
}

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

	$success = array('description' => 'Success');

	$schema = api_openapi_success_schema($route);

	if ($schema !== null) {

		$success['content'] = array('application/json' => array('schema' => $schema));

	}

	return array(
		'200' => $success,
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
