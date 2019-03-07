<?php //-->
/**
 * This file is part of a Custom Project.
 * (c) 2017-2019 Acme Inc.
 *
 * Copyright and license information can be found at LICENSE.txt
 * distributed with this package.
 */

use Cradle\Package\System\Schema;
use Cradle\Package\System\Exception;

use Cradle\Package\System\Model\Validator;

/**
 * Render admin page
 *
 * @param Request $request
 * @param Response $response
 */
$this->on('admin-render-page', function ($request, $response) {
    //path
    $path = $request->getPath('string');
    if (strpos($path, '?') !== false) {
        $path = substr($path, 0, strpos($path, '?'));
    }

    $response->addMeta('path', $path);

    $content = $this->package('cradlephp/cradle-system')->template(
        '_page',
        [
            'page' => $response->getPage(),
            'results' => $response->getResults(),
            'content' => $response->getContent(),
            'i18n' => $request->getSession('i18n')
        ],
        [
            'head',
            'foot',
            'side'
        ],
        __DIR__ . '/template',
        __DIR__ . '/template'
    );

    $response->setContent($content);
});

/**
 * Updates a pipeline item
 *
 * @param Request $request
 * @param Response $response
 */
$this->on('system-model-pipeline-update', function ($request, $response) {
    //----------------------------//
    // 1. Get Data
    $data = [];
    if ($request->hasStage()) {
        $data = $request->getStage();
    }

    if (!isset($data['schema'])) {
        throw Exception::forNoSchema();
    }

    $schema = Schema::i($data['schema']);

    if (isset($data['id'])) {
        $data[$schema->getPrimaryFieldName()] = $data['id'];
    }

    //
    // FIX: For import or in any part of the system
    // if primary is set but doesn't have a value.
    //
    if (isset($data[$schema->getPrimaryFieldName()])
        && empty($data[$schema->getPrimaryFieldName()])
    ) {
        // remove the field instead
        unset($data[$schema->getPrimaryFieldName()]);
    }

    //----------------------------//
    // 2. Validate Data
    $errors = Validator::i($schema)
        ->getUpdateErrors($data);

    //if there are errors
    if (!empty($errors)) {
        return $response
            ->setError(true, 'Invalid Parameters')
            ->set('json', 'validation', $errors);
    }

    // if this is for the previous column update only
    // we have nothing to update against a specific
    // column, so we have to go back from here
    if (isset($data['previous_column']) && $data['previous_column']) {
        return [];
    }

    //----------------------------//
    // 3. Prepare Data
    // nothing to prepare or format
    //----------------------------//
    // 4. Process Data
    $table = $schema->getName();

    $database = Schema::i('webhook')
        ->model()
        ->service('sql')
        ->getResource();

    $updated = $schema->getUpdatedFieldName();

    if ($updated) {
        $data[$updated] = date('Y-m-d H:i:s');
    }

    // we will be using moved, since
    // 'order' is used for sorting
    // if there is moved, the the user
    // attempts to update ordering
    if (isset($data['moved']) && isset($data['fields'], $data['filters'])) {
        $query = $database
            ->getUpdateQuery($table)
            ->where($data['filters']);

        // update the update field if any
        if ($updated) {
            $data['fields'][$updated] = sprintf("'%s'", $data[$updated]);
        }

        // add fields to be updated
        foreach ($data['fields'] as $field => $value) {
            $query->set($field, $value);
        }

        // we need to assign as we might end from here
        $database->query($query);
    }

    // if this is for the previous column update only
    // we have nothing to update against a specific
    // column, so we have to go back from here
    $results = [];
    if (!isset($data['previous_column']) || !$data['previous_column']) {
        $results = $this->method('system-model-update', $request, $response);

        // if there's an error
        if ($response->isError()) {
            return;
        }
    }

    // return response format
    $response->setError(false)->setResults($results);
});

/**
 * Prepare Report Chart
 *
 * @param Request $request
 * @param Response $response
 */
$this->on('report-chart-prepare', function ($request, $response) {
    //----------------------------//
    // 1. Get Data
    $data = $request->getStage();

    // is the identifier given?
    if ((!isset($data['report_id']) || isset($data['report_slug']))
        || (isset($data['report_id']) && empty($data['report_id']))
        || (isset($data['report_slug']) && empty($data['report_slug']))
    ) {
        return 'Invalid report identifier';
    }

    $request->setStage('schema', 'report');
    $this->trigger('system-model-detail', $request, $response);

    // are there report?
    if ($response->isError()) {
        return 'No report found';
    }

    // now we need to prep data from this details
    $report = $response->getResults();

    // first we prepare the request
    $payload = $this->makePayload();

    // clean the request first,
    // we will only entertain limitations
    // from the filter parameters provided
    $payload['request']->removeStage('filter');
    $payload['request']->removeStage('start');
    $payload['request']->removeStage('range');
    $payload['request']->removeStage('group');
    $payload['request']->removeStage('order');

    $payload['request']->setStage($report['report_filter_parameters']);
    $payload['request']->setStage('schema', $report['report_schema']);

    // first determine the type of chart
    // if it's line or bar, we have to deal with x and y axes
    if ($report['report_chart'] == 'line'
        || $report['report_chart'] == 'bar'
        || $report['report_chart'] == 'area'
    ) {
        foreach ($report['report_meta'] as $key => $axis) {
            // if it's not x and y axis, ignore
            if ($key !== 'x_axis' && $key !== 'y_axis') {
                continue;
            }

            // deal with the x-axis first
            switch ($report['report_meta'][$key]['key']) {
                // if it's sum of the column
                case 'column-sum':
                    $column = sprintf('%s_%s', $report['report_schema'], $report['report_meta'][$key]['value']);
                    $payload['request']->setStage('sum', $column);
                    break;

                // if it's count of the column
                case 'column-count':
                    $column = sprintf('%s_%s', $report['report_schema'], $report['report_meta'][$key]['value']);
                    $payload['request']->setStage('count', $column);
                    break;

                case 'column-value':
                    $column = sprintf('%s_%s', $report['report_schema'], $report['report_meta'][$key]['value']);
                    $payload['request']->setStage('group', $column);
                    break;

                case 'year':
                    $column = sprintf('DATE_FORMAT(%s, "%Y")', $report['report_meta'][$key]['value']);
                    $payload['request']->setStage('group', $column);
                    break;

                case 'month':
                    $column = sprintf('DATE_FORMAT(%s, "%m")', $report['report_meta'][$key]['value']);
                    $payload['request']->setStage('group', $column);
                    break;

                case 'year-month':
                    $column = sprintf('DATE_FORMAT(%s, "%Y/%m")', $report['report_meta'][$key]['value']);
                    $payload['request']->setStage('group', $column);
                    break;

                default:
                    break;
            }
        }
    }

    // then we'll now pull the data to be included in the report
    $this->trigger('system-model-search', $payload['request'], $payload['response']);

    // if no data to process, then let us not proceed
    if (!$payload['response']->getResults('total')) {
        return [];
    }

    //----------------------------//
    // 2. Process Data
    $results = $payload['response']->getResults('rows');

    $payload = $this->makePayload();
    $payload['request']->setStage('results', $results);
    $payload['request']->setStage('report', $report);

    switch ($report['report_chart']) {
        case 'line':
        case 'area':
            $this->trigger('line-chart-prepare', $payload['request'], $payload['response']);
            break;

        case 'bar':
            $this->trigger('bar-chart-prepare', $payload['request'], $payload['response']);
            break;

        case 'pie':
        case 'doughnut':
            $this->trigger('circular-chart-prepare', $payload['request'], $payload['response']);
            break;

        default:
            break;
    }

    $dataset = $payload['response']->getResults('dataset');

    //----------------------------//
    // 3. Prepare Results
    // since we have everything we need, let's not prep the data
    $data = [
        'chart_type' => $report['report_chart'],
        'dataset' => $dataset,
        'chart_height' => isset($report['meta']['chart_height']) ? $report['meta']['chart_height']: 400,
        'chart_width' => isset($report['meta']['chart_width']) ? isset($report['meta']['chart_width']): 900,
    ];

    if (isset($report['report_meta']['chart_label'])) {
        $data['chart_label'] = $report['report_meta']['chart_label'];
    }

    if ($payload['response']->getResults('options')) {
        $data['options'] = $payload['response']->getResults('options');
    }

    if ($payload['response']->getResults('labels')
        && ($report['report_chart'] == 'pie'
            || $report['report_chart'] == 'doughnut'
    )) {
        $data['chart_label'] = json_encode($payload['response']->getResults('labels'));
    }

    // first prepare from the given report
    // if user wants a horizontal bar
    if ($report['report_chart'] == 'bar'
        && isset($report['report_meta']['bar_type'])
        && $report['report_meta']['bar_type'] == 'horizontal'
    ) {
        $data['chart_type'] = 'horizontalBar';
    }

    // if the user wants an area,
    if ($report['chart_type'] == 'area'
        || ($report['chart_type'] == 'line'
            && isset($report['report_meta']['line_type'])
            && $report['report_meta']['line_type'] == 'area')
    ) {
        $data['chart_type'] = 'line';
        $data['fill'] = 'origin';
    }

    // if the user wants a stepped line chart
    if ($report['chart_type'] == 'line'
        && isset($report['report_meta']['line_type'])
        && $report['report_meta']['line_type'] == 'stepped'
    ) {
        $data['stepped'] = true;
    }

    if ($report['report_chart'] == 'doughnut'
        && isset($report['report_meta']['doughnut_type'])
        && $report['report_meta']['doughnut_type'] == 'half'
    ) {
        $data['circle'] = 'half';
    }

    //----------------------------//
    // 4. Send Results
    $response->setResults($data);
});

/**
 * Prepare Line Chart
 *
 * @param Request $request
 * @param Response $response
 */
$this->on('line-chart-prepare', function ($request, $response) {
    //----------------------------//
    // 1. Get Data
    $data = $request->getStage();
    $results = $data['results'];
    $report = $data['report'];
    $dataset = [];
    $options = [];

    $axes = [
        'x_axis' => [],
        'y_axis' => []
    ];

    //----------------------------//
    // 2. Process Data
    foreach ($results as $resKey => $result) {
        foreach ($report['report_meta'] as $key => $axis) {
            if ($key !== 'x_axis' && $key !== 'y_axis') {
                continue;
            }

            switch ($report['report_meta'][$key]['key']) {
                // if it's sum of the column
                case 'column-sum':
                    $axes[$key][] = $result['total'];
                    break;

                // if it's count of the column
                case 'column-count':
                    $axes[$key][] = $result['count'];
                    break;

                // if it's value of the column
                case 'column-value':
                    $column = sprintf('%s_%s', $report['report_schema'], $report['report_meta'][$key]['value']);
                    $axes[$key][] = $result[$column];

                    if (!isset($options[$key]['labels'])) {
                        $options[$key] = ['labels' => []];
                    }

                    $options[$key]['labels'][] = $report['report_meta'][$key]['value'] . ' ' . $result[$column];
                    break;

                case 'year':
                    $column = sprintf('%s_%s', $report['report_schema'], $report['report_meta'][$key]['value']);
                    $axes[$key][] = $result[$column];
                    $options[$key] = [
                        'type' => 'time',
                        'time' => [
                            'unit' => 'year'
                        ]
                    ];
                    break;

                case 'month':
                    $column = sprintf('%s_%s', $report['report_schema'], $report['report_meta'][$key]['value']);
                    $axes[$key][] = $result[$column];
                    $options[$key] = [
                        'type' => 'time',
                        'time' => [
                            'unit' => 'month'
                        ]
                    ];
                    break;

                case 'year-month':
                    $column = sprintf('%s_%s', $report['report_schema'], $report['report_meta'][$key]['value']);
                    $axes[$key][] = $result[$column];
                    $options[$key] = [
                        'type' => 'time',
                        'time' => [
                            'displayFormats' => 'MMM YY',
                            'unit' => 'month'
                        ]
                    ];
                    break;

                default:
                    break;
            }
        }
    }

    //----------------------------//
    // 3. Send Results
    $results = [
        'dataset' => $axes['y_axis'],
        'labels' => $axes['x_axis'],
        'options' => $options
    ];

    $response->setResults($results);
});

/**
 * Prepare Bar Chart
 *
 * @param Request $request
 * @param Response $response
 */
$this->on('bar-chart-prepare', function ($request, $response) {
    //----------------------------//
    // 1. Get Data
    $data = $request->getStage();
    $results = $data['results'];
    $report = $data['report'];
    $dataset = [];
    $options = [];

    //----------------------------//
    // 2. Process Data
    foreach ($results as $resKey => $result) {
        foreach ($report['report_meta'] as $key => $axis) {
            if ($key !== 'x_axis' && $key !== 'y_axis') {
                continue;
            }

            $axisPoint = str_replace('_axis', '', $key);
            // deal with the x-axis first
            switch ($report['report_meta'][$key]['key']) {
                // if it's sum of the column
                case 'column-sum':
                    $dataset[$resKey][$axisPoint] = $result['total'];
                    $options[$key]['ticks'] = ['beginAtZero' => true];
                    break;

                // if it's count of the column
                case 'column-count':
                    $dataset[$resKey][$axisPoint] = $result['count'];
                    $options[$key]['ticks'] = ['beginAtZero' => true];
                    break;

                case 'column-value':
                    $column = sprintf('%s_%s', $report['report_schema'], $report['report_meta'][$key]['value']);
                    $dataset[$resKey][$axisPoint] = $result[$column];

                    if (!isset($options[$key]['labels'])) {
                        $options[$key] = ['labels' => []];
                    }

                    $options[$key]['labels'][] = sprintf('%s %s', $report['report_meta'][$key]['value'], $result[$column]);
                    break;

                case 'year':
                    $column = sprintf('%s_%s', $report['report_schema'], $report['report_meta'][$key]['value']);
                    $dataset[$resKey][$axisPoint] = $result[$column];
                    $options[$key] = [
                        'type' => 'time',
                        'time' => [
                            'unit' => 'year'
                        ]
                    ];
                    break;

                case 'month':
                    $column = sprintf('%s_%s', $report['report_schema'], $report['report_meta'][$key]['value']);
                    $dataset[$resKey][$axisPoint] = $result[$column];
                    $options[$key] = [
                        'type' => 'time',
                        'time' => [
                            'unit' => 'month'
                        ]
                    ];
                    break;

                case 'year-month':
                    $column = sprintf('%s_%s', $report['report_schema'], $report['report_meta'][$key]['value']);
                    $dataset[$resKey][$axisPoint] = $result[$column];
                    $options[$key] = [
                        'type' => 'time',
                        'time' => [
                            'displayFormats' => 'MMM YY',
                            'unit' => 'month'
                        ]
                    ];
                    break;

                default:
                    break;
            }
        }
    }

    //----------------------------//
    // 3. Send Results
    $results = [
        'dataset' => $dataset,
        'options' => $options
    ];

    $response->setResults($results);
});

/**
 * Prepare Circular Chart
 *
 * @param Request $request
 * @param Response $response
 */
$this->on('circular-chart-prepare', function ($request, $response) {
    //----------------------------//
    // 1. Get Data
    $data = $request->getStage();
    $results = $data['results'];
    $report = $data['report'];
    $dataset = [];
    $options = [];
    $compiled = null;

    $schema = Schema::i($report['report_schema'])
        ->getAll(false);

    //----------------------------//
    // 2. Process Data
    if (isset($schema['suggestion'])) {
        $handlebars = cradle('global')->handlebars();
        $compiled = $handlebars->compile($schema['suggestion']);
    }

    $column = sprintf('%s_%s', $report['report_schema'], $report['report_meta']['base']);
    foreach ($results as $key => $result) {
        if (isset($report['report_meta']['base']) && isset($result[$column])) {
            $dataset[] = $result[$column];
        }

        if ($compiled) {
            $labels[] = $compiled($result);
        }
    }

    //----------------------------//
    // 3. Send Results
    $results = [
        'dataset' => $dataset,
        'labels' => $labels
    ];

    $response->setResults($results);
});
