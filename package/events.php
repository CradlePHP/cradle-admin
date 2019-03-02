<?php //-->
/**
 * This file is part of Cradle's Custom Package.
 * (c) 2018 Sterling Technologies.
 *
 * Copyright and license information can be found at LICENSE.txt
 * distributed with this package.
 */

use Cradle\Storm\SqlFactory;

use Cradle\Package\System\Schema;
use Cradle\Package\System\Exception;

use Cradle\Http\Request;
use Cradle\Http\Response;

/**
 * $ cradle package install cradlephp/cradle-admin
 * $ cradle package install cradlephp/cradle-admin 1.0.0
 * $ cradle cradlephp/cradle-admin install
 * $ cradle cradlephp/cradle-admin install 1.0.0
 *
 * @param Request $request
 * @param Response $response
 */
$this->on('cradlephp-cradle-admin-install', function ($request, $response) {
    //custom name of this package
    $name = 'cradlephp/cradle-admin';

    //get the current version
    $current = $this->package('global')->config('packages', $name);

    // if version is set
    if (is_array($current) && isset($current['version'])) {
        // get the current version
        $current = $current['version'];
    } else {
        $current = null;
    }

    //if it's already installed
    if ($current) {
        $message = sprintf('%s is already installed', $name);
        return $response->setError(true, $message);
    }

    // install package
    $version = $this->package('cradlephp/cradle-admin')->install('0.0.0');

    // update the config
    $this->package('global')->config('packages', $name, [
        'version' => $version,
        'active' => true
    ]);

    $response->setResults('version', $version);
});

/**
 * $ cradle package update cradlephp/cradle-admin
 * $ cradle package update cradlephp/cradle-admin 1.0.0
 * $ cradle cradlephp/cradle-admin update
 * $ cradle cradlephp/cradle-admin update 1.0.0
 *
 * @param Request $request
 * @param Response $response
 */
$this->on('cradlephp-cradle-admin-update', function ($request, $response) {
    //custom name of this package
    $name = 'cradlephp/cradle-admin';

    //get the current version
    $current = $this->package('global')->config('packages', $name);

    // if version is set
    if (is_array($current) && isset($current['version'])) {
        // get the current version
        $current = $current['version'];
    } else {
        $current = null;
    }

    //if it's not installed
    if (!$current) {
        $message = sprintf('%s is not installed', $name);
        return $response->setError(true, $message);
    }

    // get available version
    $version = $this->package($name)->version();

    //if available < current
    if (version_compare($version, $current, '<')) {
        $message = sprintf('%s < %s', $version, $current);
        $response->setResults('logs', 'cradlephp/cradle-admin', $version, [
            [
                'type' => 'error',
                'message' => $message
            ]
        ]);

        return $response->setError(true, $message);
    //if available = current
    } else if (version_compare($version, $current, '=')) {
        $message = sprintf('%s = %s', $version, $current);
        $response->setResults('logs', 'cradlephp/cradle-admin', $version, [
            [
                'type' => 'error',
                'message' => $message
            ]
        ]);

        return;
    }

    // update package
    $version = $this->package('cradlephp/cradle-admin')->install($current);

    // update the config
    $this->package('global')->config('packages', $name, [
        'version' => $version,
        'active' => true
    ]);

    $response->setResults('version', $version);
});

/**
 * $ cradle package remove cradlephp/cradle-admin
 * $ cradle cradlephp/cradle-admin remove
 *
 * @param Request $request
 * @param Response $response
 */
$this->on('cradlephp-cradle-admin-remove', function ($request, $response) {
    //custom name of this package
    $name = 'cradlephp/cradle-admin';

    // if it's not installed
    if (!$this->package('global')->config('packages', $name)) {
        $message = sprintf('%s is not installed', $name);
        return $response->setError(true, $message);
    }

    //setup result counters
    $errors = [];

    // processed data
    $processed = [];

    //scan through each file
    foreach (scandir(__DIR__ . '/schema') as $file) {
        //if it's not a php file
        if(substr($file, -4) !== '.php') {
            //skip
            continue;
        }

        //get the schema data
        $data = include sprintf('%s/schema/%s', __DIR__, $file);

        //if no name
        if (!isset($data['name'])) {
            //skip
            continue;
        }

        //----------------------------//
        // 1. Prepare Data
        $request->setStage('schema', $data['name']);

        //----------------------------//
        // 2. Process Request
        $this->trigger('system-schema-remove', $request, $response);

        //----------------------------//
        // 3. Interpret Results
        if ($response->isError()) {
            //collect all the errors
            $errors[$data['name']] = $response->getMessage();
            continue;
        }

        $processed[] = $data['name'];
    }

    if (!empty($errors)) {
        $response->set('json', 'validation', $errors);
    }

    // get package config
    $packages = $this->package('global')->config('packages');

    // remove package from config
    if (isset($packages[$name])) {
        unset($packages[$name]);
    }

    // update package config
    $this->package('global')->config('packages', $packages);

    $response->setResults('schemas', $processed);
});

/**
 * $ cradle elastic flush cradlephp/cradle-admin
 * $ cradle cradlephp/cradle-admin elastic-flush
 *
 * @param Request $request
 * @param Response $response
 */
$this->on('cradlephp-cradle-admin-elastic-flush', function ($request, $response) {
    $processed = $errors = [];
    //scan through each file
    foreach (scandir(__DIR__ . '/schema') as $file) {
        //if it's not a php file
        if(substr($file, -4) !== '.php') {
            //skip
            continue;
        }

        //get the schema data
        $data = include sprintf('%s/schema/%s', __DIR__, $file);

        // if name is not set
        if (!isset ($data['name'])) {
            // skip
            continue;
        }

        // set parameters
        $request->setStage('name', $data['name']);
        // trigger global schema flush
        $this->trigger('system-schema-flush-elastic', $request, $response);
        // intercept error
        if ($response->isError()) {
            //collect all the errors
            $errors[$data['name']] = $response->getMessage();
            continue;
        }


        $processed[] = $data['name'];
    }

    if (!empty($errors)) {
        $response->set('json', 'validation', $errors);
    }

    // set response
    $response->setResults('schema', $processed);
});

/**
 * $ cradle elastic map cradlephp/cradle-admin
 * $ cradle cradlephp/cradle-admin elastic-map
 *
 * @param Request $request
 * @param Response $response
 */
$this->on('cradlephp-cradle-admin-elastic-map', function ($request, $response) {
    $processed = $errors = [];
    //scan through each file
    foreach (scandir(__DIR__ . '/schema') as $file) {
        //if it's not a php file
        if(substr($file, -4) !== '.php') {
            //skip
            continue;
        }

        //get the schema data
        $data = include sprintf('%s/schema/%s', __DIR__, $file);
        // if name is not set
        if (!isset ($data['name'])) {
            // skip
            continue;
        }

        // set parameters
        $request->setStage('name', $data['name']);
        // trigger global schema flush
        $this->trigger('system-schema-map-elastic', $request, $response);

        // intercept error
        if ($response->isError()) {
            //collect all the errors
            $errors[$data['name']] = $response->getMessage();
            continue;
        }

        $processed[] = $data['name'];
    }

    // set response error
    if (!empty ($errors)) {
        $response->set('json', 'validation', $errors);
    }

    $response->setResults('schema', $processed);
});

/**
 * $ cradle elastic populate cradlephp/cradle-admin
 * $ cradle cradlephp/cradle-admin elastic-populate
 *
 * @param Request $request
 * @param Response $response
 */
$this->on('cradlephp-cradle-admin-elastic-populate', function ($request, $response) {
    $processed = $errors = [];
    //scan through each file
    foreach (scandir(__DIR__ . '/schema') as $file) {
        //if it's not a php file
        if(substr($file, -4) !== '.php') {
            //skip
            continue;
        }

        //get the schema data
        $data = include sprintf('%s/schema/%s', __DIR__, $file);
        // if name is not set
        if (!isset ($data['name'])) {
            // skip
            continue;
        }

        // set parameters
        $request->setStage('name', $data['name']);
        // trigger global schema flush
        $this->trigger('system-schema-populate-elastic', $request, $response);
        // intercept error
        if ($response->isError()) {
            $errors[$data['name']] = $response->getMessage();
            continue;
        }

        $processed[] = $data['name'];

    }

    // set response error
    if (!empty($errors)) {
        $response->set('json', 'validation', $errors);
    }

    // set response
    $response->setResults('schema', 'profile');
});

/**
 * $ cradle redis flush cradlephp/cradle-admin
 * $ cradle cradlephp/cradle-admin redis-flush
 *
 * @param Request $request
 * @param Response $response
 */
$this->on('cradlephp-cradle-admin-redis-flush', function ($request, $response) {
    // initialize schema
    $schema = Schema::i('profile');
    // get redis service
    $redis = $schema->model()->service('redis');
    // remove cached search and detail from redis
    $redis->removeSearch();
    $redis->removeDetail();

    $response->setResults('schema', 'profile');
});

/**
 * $ cradle redis populate cradlephp/cradle-admin
 * $ cradle cradlephp/cradle-admin redis-populate
 *
 * @param Request $request
 * @param Response $response
 */
$this->on('cradlephp-cradle-admin-redis-populate', function ($request, $response) {
    // initialize schema
    $schema = Schema::i('profile');
    // get sql service
    $sql = $schema->model()->service('sql');
    $redis = $schema->model()->service('redis');
    // get sql data
    $data = $sql->search();
    // if there is no results
    if (!isset($data['total']) && $data['total'] < 1) {
        // do not proceed
        return $response->setResults('schema', 'profile');
    }

    // get slugable fields
    $slugs = $schema->getSlugableFieldNames($schema->getPrimaryFieldName());
    // loop through rows
    foreach ($data['rows'] as $entry) {
        // loop thru slugs
        foreach ($slugs as $slug) {
            // if entry found
            if (isset($entry[$slug])) {
                // create cache data on redis
                $redis->createDetail($slug . '-' . $entry[$slug], $entry);
            }
        }

    }

    $response->setResults('schema', 'profile');

});

/**
 * $ cradle sql build cradlephp/cradle-admin
 * $ cradle cradlephp/cradle-admin sql-build
 *
 * @param Request $request
 * @param Response $response
 */
$this->on('cradlephp-cradle-admin-sql-build', function ($request, $response) {
    //load up the database
    $pdo = $this->package('global')->service('sql-main');
    $database = SqlFactory::load($pdo);

    //setup result counters
    $errors = [];
    $processed = [];

    //scan through each file
    foreach (scandir(__DIR__ . '/schema') as $file) {
        //if it's not a php file
        if(substr($file, -4) !== '.php') {
            //skip
            continue;
        }

        //get the schema data
        $data = include sprintf('%s/schema/%s', __DIR__, $file);

        //if no name
        if (!isset($data['name'])) {
            //skip
            continue;
        }

        try {
            $schema = Schema::i($data['name']);
        } catch(Exception $e) {
            continue;
        }

        //remove primary table
        $database->query(sprintf('DROP TABLE IF EXISTS `%s`', $schema->getName()));

        //loop through relations
        foreach ($schema->getRelations() as $table => $relation) {
            //remove relation table
            $database->query(sprintf('DROP TABLE IF EXISTS `%s`', $table));
        }

        //now build it back up
        //set the data
        $request->setStage($schema->get());

        //----------------------------//
        // 1. Prepare Data
        //if detail has no value make it null
        if ($request->hasStage('detail')
            && !$request->getStage('detail')
        ) {
            $request->setStage('detail', null);
        }

        //if fields has no value make it an array
        if ($request->hasStage('fields')
            && !$request->getStage('fields')
        ) {
            $request->setStage('fields', []);
        }

        //if validation has no value make it an array
        if ($request->hasStage('validation')
            && !$request->getStage('validation')
        ) {
            $request->setStage('validation', []);
        }

        //----------------------------//
        // 2. Process Request
        //now trigger
        $this->trigger('system-schema-update', $request, $response);

        //----------------------------//
        // 3. Interpret Results
        //if the event returned an error
        if ($response->isError()) {
            //collect all the errors
            $errors[$data['name']] = $response->getValidation();
            continue;
        }

        $processed[] = $data['name'];
    }

    if (!empty($errors)) {
        $response->set('json', 'validation', $errors);
    }

    $response->setResults(['schemas' => $processed]);
});

/**
 * $ cradle sql flush cradlephp/cradle-admin
 * $ cradle cradlephp/cradle-admin sql-flush
 *
 * @param Request $request
 * @param Response $response
 */
$this->on('cradlephp-cradle-admin-sql-flush', function ($request, $response) {
    //load up the database
    $pdo = $this->package('global')->service('sql-main');
    $database = SqlFactory::load($pdo);

    //setup result counters
    $errors = [];
    $processed = [];

    //scan through each file
    foreach (scandir(__DIR__ . '/schema') as $file) {
        //if it's not a php file
        if(substr($file, -4) !== '.php') {
            //skip
            continue;
        }

        //get the schema data
        $data = include sprintf('%s/schema/%s', __DIR__, $file);

        //if no name
        if (!isset($data['name'])) {
            //skip
            continue;
        }

        try {
            $schema = Schema::i($data['name']);
        } catch(Exception $e) {
            continue;
        }

        //remove primary table
        $database->query(sprintf('TRUNCATE `%s`', $schema->getName()));

        //loop through relations
        foreach ($schema->getRelations() as $table => $relation) {
            //remove relation table
            $database->query(sprintf('TRUNCATE `%s`', $table));
        }

        $processed[] = $data['name'];
    }

    $response->setResults('schemas', $processed);
});

/**
 * $ cradle sql populate cradlephp/cradle-admin
 * $ cradle cradlephp/cradle-admin sql-populate
 *
 * @param Request $request
 * @param Response $response
 */
$this->on('cradlephp-cradle-admin-sql-populate', function ($request, $response) {
    //scan through each file
    foreach (scandir(__DIR__ . '/schema') as $file) {
        //if it's not a php file
        if(substr($file, -4) !== '.php') {
            //skip
            continue;
        }

        //get the schema data
        $data = include sprintf('%s/schema/%s', __DIR__, $file);

        //if no name
        if (!isset($data['name'], $data['fixtures'])
            || !is_array($data['fixtures'])
        ) {
            //skip
            continue;
        }

        $actionRequest = Request::i()->load();
        $actionResponse = Response::i()->load();
        foreach($data['fixtures'] as  $fixture) {
            $actionRequest
                ->setStage($fixture)
                ->setStage('schema', 'profile');

            $this->trigger('system-model-create', $actionRequest, $actionResponse);
        }
    }

    if ($this->isPackage('cradlephp/cradle-role')) {
        $payload = $this->makePayload();
        $payload['request']
            ->setStage('schema', 'role')
            ->setStage('role_slug', 'developer');

        $this->trigger(
            'system-model-detail',
            $payload['request'],
            $payload['response']
        );

        if (!$payload['response']->isError()
            && $payload['response']->hasResults('role_admin_menu')
        ) {
            $id = $payload['response']->getResults('role_id');
            $menu = $payload['response']->getResults('role_admin_menu');
            $exists = false;

            foreach ($menu as $item) {
                if ($item['path'] === '#menu-api') {
                    $exists = true;
                    break;
                }
            }

            if (!$exists) {
                $newMenu = [];
                foreach ($menu as $item) {
                    $newMenu[] = $item;
                    if ($item['path'] === '#menu-admin') {
                        $newMenu[] = [
                            'icon' => 'fas fa-code',
                            'path' => '#menu-api',
                            'label' => 'API',
                            'children' => [
                                [
                                    'icon' => 'fas fa-mobile-alt',
                                    'path' => '/admin/system/model/app/search',
                                    'label' => 'Applications'
                                ],
                                [
                                    'icon' => 'fas fa-address-card',
                                    'path' => '/admin/system/model/session/search',
                                    'label' => 'Sessions'
                                ],
                                [
                                    'icon' => 'fas fa-crosshairs',
                                    'path' => '/admin/system/model/scope/search',
                                    'label' => 'Scopes'
                                ],
                                [
                                    'icon' => 'fas fa-phone',
                                    'path' => '/admin/system/model/rest/search',
                                    'label' => 'REST Calls'
                                ],
                                [
                                    'icon' => 'fas fa-comments',
                                    'path' => '/admin/system/model/webhook/search',
                                    'label' => 'Webhooks'
                                ]
                            ]
                        ];
                    }
                }

                $payload['request']
                    ->setStage('role_id', $id)
                    ->setStage(
                        'role_admin_menu',
                        json_encode($newMenu, JSON_PRETTY_PRINT)
                    );

                $this->trigger(
                    'system-model-update',
                    $payload['request'],
                    $payload['response']
                );
            }
        }
    }
});
