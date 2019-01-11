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
        if($response->isError()) {
            return;
        }
    }

    // return response format
    $response->setError(false)->setResults($results);
});
