<?php //-->
/**
 * This file is part of a Custom Project.
 * (c) 2016-2018 Acme Products Inc.
 *
 * Copyright and license information can be found at LICENSE.txt
 * distributed with this package.
 */

use Cradle\Package\System\Schema;
use Cradle\Package\System\Schema\Service;

/**
 * Render Admin JS
 *
 * @param Request $request
 * @param Response $response
 */
$this->get('/scripts/admin.js', function ($request, $response) {
    $response->addHeader('Content-Type', 'text/javascript');
    $response->setContent(file_get_contents(__DIR__ . '/admin.js'));
});

/**
 * Render Admin CSS
 *
 * @param Request $request
 * @param Response $response
 */
$this->get('/styles/admin.css', function ($request, $response) {
    $response->addHeader('Content-Type', 'text/css');
    $response->setContent(file_get_contents(__DIR__ . '/admin.css'));
});

/**
 * Render Template Actions
 *
 * @param Request $request
 * @param Response $response
 */
$this->get('/admin', function ($request, $response) {
    return $this->routeTo('get', '/admin/dashboard', $request, $response);
});

/**
 * Render Template Actions
 *
 * @param Request $request
 * @param Response $response
 */
$this->get('/admin/dashboard', function ($request, $response) {
    //----------------------------//
    // 1. Prepare body
    $data = $request->getStage();

    // pull top 5 recent activities
    $request->setStage('range', 5);
    $request->setStage('order', ['history_created' => 'DESC']);
    $this->trigger('history-search', $request, $response);
    $data['activities'] = $response->getResults('rows');

    // get schemas
    $this->trigger('system-schema-search', $request, $response);

    // schemas
    $schemas = [];

    // get the schemas
    $results = $response->getResults('rows');

    // if we have results
    if (!empty($results)) {
        foreach($results as $schema) {
            $schemas[$schema['name']] = [
                'name' => $schema['name'],
                'label' => ucwords($schema['name']),
                'path' => sprintf(
                    '/admin/system/model/%s/search',
                    $schema['name']
                ),
                'records' => 0
            ];
        }

        // get the database name
        $database = $this->package('global')->config('services', 'sql-main')['name'];

        // get the record count
        $records = Service::get('sql')->getSchemaTableRecordCount($database);

        // on each record
        foreach($records as $record) {
            $name = null;
            $rows = null;

            //mysql 5.8
            if (isset($record['TABLE_NAME'])) {
                $name = $record['TABLE_NAME'];
                $rows = $record['TABLE_ROWS'];

            //mysql <= 5.6
            } else {
                $name = $record['table_name'];
                $rows = $record['table_rows'];
            }

            if (isset($schemas[$name])) {
                $schemas[$name]['records'] = $rows;
            }
        }
    }

    // set schemas to data
    $data['schemas'] = $schemas;
    $data['title'] = $this->package('global')->translate('Admin Dashboard');

    //----------------------------//
    // 2. Render Template
    $class = sprintf('page-admin-dashboard page-admin');
    $body = cradle('cradlephp/cradle-admin')->template('dashboard', $data);

    //set content
    $response
        ->setPage('title', $data['title'])
        ->setPage('class', $class)
        ->setContent($body);

    //render page
    $this->trigger('admin-render-page', $request, $response);
});

/**
 * Render Template Actions
 *
 * @param Request $request
 * @param Response $response
 */
$this->get('/admin/template/:action', function ($request, $response) {
    //----------------------------//
    // 1. Prepare body
    $action = $request->getStage('action');
    $data['title'] = $this->package('global')->translate('System Template ' . ucfirst($action));

    //----------------------------//
    // 2. Render Template
    $class = sprintf('page-admin-template-%s page-admin', $action);
    $body = cradle('cradlephp/cradle-admin')->template('template/' . $action, $data);

    //set content
    $response
        ->setPage('title', $data['title'])
        ->setPage('class', $class)
        ->setContent($body);

    //render page
    $this->trigger('admin-render-page', $request, $response);
});

/**
 * Render the Configuration Page
 *
 * @param Request $request
 * @param Response $response
 */
$this->get('/admin/configuration', function ($request, $response) {
    //----------------------------//
    // 1. Security Checks
    //----------------------------//
    // 2. Prepare Data

    // default type
    if (!$request->hasStage('type')) {
        $request->setStage('type', 'none');
    }

    // valid types
    $valid = ['none', 'general', 'deploy', 'service', 'test'];

    // valid type ?
    if (!in_array($request->getStage('type'), $valid)) {
        $this->package('global')->flash('Please select a valid configuration', 'error');
        return $this->package('global')->redirect('/admin/configuration');
    }

    // get the file type
    $file = $request->getStage('type');

    // switch between config to load
    switch($file) {
        case 'general' :
            $data['item'] = $this->package('global')->config('settings');
            break;

        case 'deploy' :
            $data['item'] = $this->package('global')->config('deploy');
            break;

        case 'service' :
            $data['item'] = $this->package('global')->config('services');
            break;

        case 'test' :
            $data['item'] = $this->package('global')->config('test');
            break;

        default :
            $data['item'] = [];
    }

    $data['type'] = $request->getStage('type');

    //
    // We need to unpair the configuration
    // so that we can do recursive templating
    // on the front end.
    //
    $unpair = function ($configuration) use (&$unpair) {
        // unpaired array
        $unpaired = [];

        // iterate on each configuration
        foreach($configuration as $key => $value) {
            // if config is an array
            if (is_array($value)) {
                // loop through
                $unpaired[] = [
                    'key' => $key,
                    'value' => null,
                    'children' => $unpair($value)
                ];

                continue;
            }

            // set config data
            $unpaired[] = [
                'key' => $key,
                'value' => $value,
                'children' => null
            ];
        }

        return $unpaired;
    };

    // unpair config
    $data['item'] = $unpair($data['item']);

    //----------------------------//
    // 3. Render Template
    $class = 'page-admin-configuration-search page-admin';
    $data['title'] = $this->package('global')->translate('System Configuration');
    $body = $this->package('cradlephp/cradle-admin')->template('configuration', $data, [
        'configuration_item',
        'configuration_input'
    ]);

    //set content
    $response
        ->setPage('title', $data['title'])
        ->setPage('class', $class)
        ->setContent($body);

    //render page
    $this->trigger('admin-render-page', $request, $response);
});

/**
 * Process the Configuration Page
 *
 * @param Request $request
 * @param Response $response
 */
$this->post('/admin/configuration', function ($request, $response) {
    //----------------------------//
    // 1. Security Checks
    //only for admin
    $this->package('global')->requireLogin('admin');

    //----------------------------//
    // 2. Prepare Data

    // default type
    if (!$request->hasStage('type')) {
        $request->setStage('type', 'none');
    }

    // valid types
    $valid = ['none', 'general', 'deploy', 'service', 'test'];

    // valid type ?
    if (!in_array($request->getStage('type'), $valid)) {
        // trigger route
        return $this
            ->routeTo(
                'get',
                '/admin/configuration',
                $request,
                $response
            );
    }

    // get the file type
    $file = $request->getStage('type');

    // switch between config to load
    switch($file) {
        case 'general' :
            $file = 'settings';
            break;

        case 'deploy' :
            $file = 'deploy';
            break;

        case 'service' :
            $file = 'services';
            break;

        case 'test' :
            $file = 'test';
            break;

        default :
            $file = null;
    }

    //
    // We need to bring the config back
    // to it's original structure by generating
    // key value pair.
    //
    $pair = function ($data) use (&$pair) {
        // paired data
        $paired = [];

        // iterate on each data
        foreach($data as $key => $value) {
            // skip if it doesn't have key
            if (!isset($value['key'])) {
                continue;
            }

            // if value has children
            if (isset($value['children'])
            && is_array($value['children'])) {
                // loop through
                $paired[$value['key']] = $pair($value['children']);

                continue;
            }

            // set key-pair
            $paired[$value['key']] = $value['value'];
        }

        return $paired;
    };

    // pair data
    $data = $pair($request->getPost('item'));

    // if file is set
    if ($file) {
        // export config
        $this->package('global')->config($file, $data);
    }

    $this->package('global')->flash('Configuration was updated', 'success');

    // redirect back
    return $this->package('global')->redirect(
        '/admin/configuration?type=' . $request->getStage('type')
    );
});

/**
 * Render the System Model Calendar Page
 *
 * @param Request $request
 * @param Response $response
 */
$this->get('/admin/system/model/:schema/calendar', function ($request, $response) {
    //----------------------------//
    // 1. Prepare Data
    $data = $request->getStage();

    // set redirect
    $redirect = sprintf(
        '/admin/system/model/%s/search',
        $request->getStage('schema')
    );

    if ($request->getStage('redirect_uri')) {
        $redirect = $request->getStage('redirect_uri');
    }

    $request->setStage('redirect_uri', $redirect);

    // if no ajax set, set this page as default
    if (!isset($data['ajax']) || empty($data['ajax'])) {
        $data['ajax'] = sprintf(
            '/admin/system/model/%s/search',
            $request->getStage('schema')
        );
    }

    // if no detail set, set update page as the default
    if (!isset($data['detail']) || empty($data['detail'])) {
        $data['detail'] = sprintf(
            '/admin/system/model/%s/detail',
            $request->getStage('schema')
        );
    }

    //----------------------------//
    // 2. Validate
    // does the schema exists?
    try {
        $data['schema'] = Schema::i($request->getStage('schema'))->getAll();
    } catch (\Exception $e) {
        $message = $this
            ->package('global')
            ->translate($e->getMessage());

        $response->setError(true, $message);
    }

    //if this is a return back from processing
    //this form and it's because of an error
    if ($response->isError()) {
        //pass the error messages to the template
        $this
            ->package('global')
            ->flash($response->getMessage(), 'error');

        return $this
            ->package('global')
            ->redirect($redirect);
    }

    //also pass the schema to the template
    $dates = ['date', 'datetime', 'created', 'updated', 'time', 'week', 'month'];

    //check what to show
    if (!isset($data['show']) || !$data['show']) {
        //flash an error message and redirect
        $error = $this
            ->package('global')
            ->translate('Please specify what to plot.');

        $this
            ->package('global')
            ->flash($error, 'error');

        return $this
            ->package('global')
            ->redirect($redirect);
    }

    $data['show'] = explode(',', $data['show']);
    foreach ($data['show'] as $column) {
        if (isset($data['schema']['fields'][$column])
            && in_array($data['schema']['fields'][$column]['field']['type'], $dates)
        ) {
            continue;
        }

        $error = $this
            ->package('global')
            ->translate('%s is not a date field', $column);

        $this
            ->package('global')
            ->flash($error, 'error');

        return $this
            ->package('global')
            ->redirect($redirect);
    }

    //----------------------------//
    // 3. Process
    // set base date & today date for button
    $base = $data['today'] = date('Y-m-d');

    // if there's a start date provide,
    // we have to change our base date
    if (isset($data['start_date']) && $data['start_date']) {
        $base = date('Y-m-d', strtotime($data['start_date']));
    }

    // set default the previous and next date based on our "base" date
    // default is month view
    $prev = strtotime($base . ' -1 month');
    $next = strtotime($base . ' +1 month');

    // set view if not defined
    if (!isset($data['view']) || empty($data['view'])) {
        $data['view'] = 'month';
    }

    // change previous and next date if the user wanted a week view
    if ($data['view'] == 'listWeek' || $data['view'] == 'agendaWeek') {
        $prev = strtotime($base . ' -1 week');
        $next = strtotime($base . ' +1 week');
    }

    // change previous and next date if the user wanted a day view
    if ($data['view'] == 'agendaDay') {
        $prev = strtotime($base .' -1 day');
        $next = strtotime($base .' +1 day');
    }

    // set whatever previous and next date we got from the changes above
    $data['prev'] = date('Y-m-d', $prev);
    $data['next'] = date('Y-m-d', $next);

    // if no breadcrumb set, set a default breadcrumb
    // we will not check if breadcrumb is in array format
    // or has value to be flexible just in case the user
    // doesn't want a breadcrumb
    if (!isset($data['breadcrumb'])) {
        $data['breadcrumb'] = [
            [
                'icon' => 'fas fa-home',
                'link' => '/admin',
                'page' => 'Admin'
            ],
            [
                'icon' => $data['schema']['icon'],
                'link' => $data['schema']['redirect_uri'],
                'page' => $data['schema']['plural']
            ],
            [
                'icon' => 'fas fa-calendar-alt',
                'page' => 'Calendar',
                'active' => true
            ]
        ];
    }

    //----------------------------//
    // 4. Render Template
    $data['title'] = $this
        ->package('global')
        ->translate('%s Calendar', $data['schema']['plural']);

    $class = sprintf(
        'page-admin-%s-calendar page-admin-calendar page-admin',
        $data['schema']['name']
    );

    $body = $this
        ->package('cradlephp/cradle-admin')
        ->template('calendar', $data);

    // set content
    $response
        ->setPage('title', $data['title'])
        ->setPage('class', $class)
        ->setContent($body);

    // if we only want the body
    if ($request->getStage('render') === 'body') {
        return;
    }

    //Render blank page
    $this->trigger('admin-render-page', $request, $response);
});

/**
 * Render the System Model Pipeline Page
 *
 * @param Request $request
 * @param Response $response
 */
$this->get('/admin/system/model/:schema/pipeline', function ($request, $response) {
    //----------------------------//
    // 1. Prepare Data
    $data = $request->getStage();
    // set redirect
    $redirect = sprintf(
        '/admin/system/model/%s/search',
        $request->getStage('schema')
    );

    if ($request->getStage('redirect_uri')) {
        $redirect = $request->getStage('redirect_uri');
    }

    $request->setStage('redirect_uri', $redirect);

    if (!isset($data['ajax'])) {
        $data['ajax'] = [];
    }

    // if no ajax set, set this page as default
    if (!isset($data['ajax']['pull']) || empty($data['ajax']['pull'])) {
        $data['ajax']['pull'] = sprintf(
            '/admin/system/model/%s/search',
            $request->getStage('schema')
        );
    }

    if (!isset($data['ajax']['update']) || empty($data['ajax']['update'])) {
        $data['ajax']['update'] = sprintf(
            '/admin/system/model/%s/pipeline',
            $request->getStage('schema')
        );
    }

    // if no detail set, set update page as the default
    if (!isset($data['detail']) || empty($data['detail'])) {
        $data['detail'] = sprintf(
            '/admin/system/model/%s/detail',
            $request->getStage('schema')
        );
    }

    //----------------------------//
    // 2. Validate
    // does the schema exists?
    try {
        $data['schema'] = Schema::i($request->getStage('schema'))->getAll();
    } catch (\Exception $e) {
        $message = $this
            ->package('global')
            ->translate($e->getMessage());

        $redirect = '/admin/system/schema/search';
        $response->setError(true, $message);
    }

    // if this is a return back from processing
    // this form and it's because of an error
    if ($response->isError()) {
        //pass the error messages to the template
        $this
            ->package('global')
            ->flash($response->getMessage(), 'error');

        return $this
            ->package('global')
            ->redirect($redirect);
    }

    // check what to show
    if (!isset($data['show']) || !$data['show']) {
        // flash an error message and redirect
        $error = $this
            ->package('global')
            ->translate('Please specify what to plot.');

        $this
            ->package('global')
            ->flash($error, 'error');

        return $this
            ->package('global')
            ->redirect($redirect);
    }

    // minimize long array chain
    $fields = $data['schema']['fields'];

    $allowed = ['select', 'radios'];

    if (!isset($fields[$data['show']])
        || !in_array($fields[$data['show']]['field']['type'], $allowed)
    ) {
        $error = $this
            ->package('global')
            ->translate('%s is not a select/radio field', $data['show']);

        $this
            ->package('global')
            ->flash($error, 'error');

        return $this
            ->package('global')
            ->redirect($redirect);
    }

    // pipeline stages
    $data['stages'] = $fields[$data['show']]['field']['options'];

    $dates = ['date', 'datetime', 'created', 'updated', 'time', 'week', 'month'];
    if (isset($data['date'])
        && (!isset($fields[$data['date']])
            || !in_array($fields[$data['date']]['field']['type'], $dates))
    ) {
        $error = $this
            ->package('global')
            ->translate('%s is not a type of date field', $data['date']);

        $this
            ->package('global')
            ->flash($error, 'error');

        return $this
            ->package('global')
            ->redirect($redirect);
    }

    // initialize the stageHeader keys into null
    // so that even there's no total or range, it will not get errors
    $data['stageHeader'] = [
        'total' => null,
        'minRange' => null,
        'maxRange' => null
    ];

    $rangeFieldTypes = ['number', 'small', 'float', 'price'];
    $fields = $data['schema']['fields'];

    // check if the user wants to display the total
    if (isset($data['range'])) {
        if (strpos($data['range'], ',') !== false) {
            $data['range'] = explode(',', $data['range']);
        }

        // if not array, make it an array
        if (!is_array($data['range'])) {
            $data['range'] = [$data['range']];
        }

        foreach ($data['range'] as $field) {
            if (isset($fields[$field])
                && in_array($fields[$field]['field']['type'], $rangeFieldTypes)
            ) {
                continue;
            }

            // flash error message
            $error = $this
                ->package('global')
                ->translate('%s is not a number type field', $field);

            $this
                ->package('global')
                ->flash($error, 'error');

            return $this
                ->package('global')
                ->redirect($redirect);
        }
    }

    if (isset($data['total'])
        && (!isset($fields[$data['total']])
        || !in_array($fields[$data['total']]['field']['type'], $rangeFieldTypes))
    ) {
        // flash error message
        $error = $this
            ->package('global')
            ->translate('%s is not a number type field', $data['total']);

        $this
            ->package('global')
            ->flash($error, 'error');

        return $this
            ->package('global')
            ->redirect($redirect);
    }

    if (isset($data['relations'])) {
        $unrelated = [];
        $one = [];

        $data['relations'] = explode(',', $data['relations']);

        if (isset($data['schema']['relations']) && $data['schema']['relations']) {
            foreach ($data['schema']['relations'] as $key => $relation) {
                $related[$relation['name']] = [
                    'primary' => $relation['primary'],
                    'relationship' => $relation['many'],
                    'suggestion' => $relation['suggestion']
                ];
            }

            $relatedNames = array_keys($related);
            foreach ($data['relations'] as $key => $relation) {
                if (!in_array($relation, $relatedNames)) {
                    $unrelated[] = $relation;
                    unset($data['relations'][$key]);
                    continue;
                }

                if ($related[$relation]['relationship'] != '1') {
                    $one[] = $relation;
                    unset($data['relations'][$key]);
                    continue;
                }

                $entitlement = $relation;

                if ($related[$relation]['suggestion']) {
                    $entitlement = str_replace('{', '', $related[$relation]['suggestion']);
                    $entitlement = str_replace('}', '', $entitlement);
                }

                $data['relations'][$key] = [
                    'name' => $relation,
                    'entitlement' => $entitlement,
                    'primary' => $related[$relation]['primary']
                ];
            }
        } else {
            $unrelated = $data['relations'];
        }

        $message = '';
        if ($unrelated) {
            $message .= $this
                ->package('global')
                ->translate(
                    '%s has no relation with %s',
                    $data['schema']['name'],
                    implode(', ', $unrelated));
        }

        if ($unrelated && $one) {
            $message .= ' and ';
        }

        if ($one) {
            $message .= $this
                ->package('global')
                ->translate(
                    '%s doesn\'t have a 1:1 relation with %s',
                    implode(', ', $one),
                    $data['schema']['name']);
        }

        if ($unrelated || $one) {
            $response
                ->setFlash($message, 'error');
        }
    }

    if (strpos($redirect, '/admin') !== FALSE && strpos($redirect, '/admin') == 0) {
        $data['admin'] = 1;
    }

    $data['schema']['filterable'] = array_values($data['schema']['filterable']);
    //----------------------------//
    // 3. Render Template
    $data['title'] = $this
        ->package('global')
        ->translate('%s Pipeline', $data['schema']['singular']);

    $class = sprintf(
        'page-admin-%s-pipeline page-admin',
        $request->getStage('schema')
    );

    $body = $this
        ->package('cradlephp/cradle-admin')
        ->template(
            'pipeline',
            $data,
            [
                'pipeline_form',
                'pipeline_filters'
            ]
        );

    // Set Content
    $response
        ->setPage('title', $data['title'])
        ->setPage('class', $class)
        ->setContent($body);

    // Render blank page
    $this->trigger('admin-render-page', $request, $response);
});

/**
 * Process Pipeline Update
 *
 * @param Request $request
 * @param Response $response
 */
$this->post('/admin/system/model/:schema/pipeline', function ($request, $response) {
    //----------------------------//
    // get json response data only
    $request->setStage('redirect_uri', 'false');
    $request->setStage('render', 'false');

    $data = [];
    if ($request->getStage()) {
        $data = $request->getStage();
    }

    $filters = [];

    if (!isset($data['moved']) && !isset($data['sort'])) {
        return $this->trigger('system-model-pipeline-update', $request, $response);
    }

    // if it reached here, then we're assumming
    // that the user attempted to do an order/sort update
    if (isset($data['moved']) && isset($data['sort'])) {
        if (isset($data['stage']) && isset($data[$data['stage']])) {
            $filters[] = sprintf(
                '%s = "%s"',
                $data['stage'],
                $data[$data['stage']]
            );
        }

        // if it was moved upwards
        // then we only update the rows from this item to the previous elder
        if ($request->getStage('moved') == 'upwards') {
            $request->setStage(
                'fields',
                [$data['sort'] => $data['sort'] . '+1']
            );

            $filters[] = $data['sort'] . ' > ' . $data['new_elder'];

            if (!isset($data['previous_stage'])) {
                $filters[] = $data['sort'] . ' <= ' . ($data['previous_elder'] + 1);
            }
        }

        // if it was moved downwards
        // then we update from the previous elder to the newest elder
        if ($request->getStage('moved') == 'downwards') {
            $request->setStage(
                'fields',
                [$data['sort'] => $data['sort'] . '-1']
            );

            $filters[] = $data['sort'] . ' >= ' . $data['previous_elder'];

            if (!isset($data['previous_stage'])) {
                $filters[] = $data['sort'] . ' <= ' . $data['new_elder'];
            }
        }

        $request->setStage('filters', $filters);

        $this->trigger('system-model-pipeline-update', $request, $response);

        if (isset($data['previous_stage'])
            && $data['previous_stage'] !== $data[$data['stage']]
        ) {
            $request->setStage(
                'fields',
                [$data['sort'] => $data['sort'] . '-1']
            );

            // we should only update cards in the previous stage
            $filters = [sprintf(
                '%s = "%s"',
                $data['stage'],
                $data['previous_stage']
            )];

            $filters[] = $data['sort'] . ' > ' . $data['previous_elder'];

            $request->setStage('filters', $filters);
            $request->setStage('previous_column', true);
            $this->trigger('system-model-pipeline-update', $request, $response);
        }
    }
});
