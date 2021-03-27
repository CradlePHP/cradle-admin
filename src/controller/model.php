<?php //-->
/**
 * This file is part of a package designed for the CradlePHP Project.
 *
 * Copyright and license information can be found at LICENSE.txt
 * distributed with this package.
 */

use Cradle\Package\System\Schema;
use Cradle\Package\System\Fieldset;

/* Search/Bulk Routes
-------------------------------- */

/**
 * Render the System Model Search Page
 *
 * @param Request $request
 * @param Response $response
 */
$this('http')->get('/admin/system/model/:schema/search', function ($request, $response) {
  //----------------------------//
  // 1. Prepare Data
  //get schema data
  $schema = Schema::load($request->getStage('schema'));

  //if this is a return back from processing
  //this form and it's because of an error
  if ($response->isError()) {
    //pass the error messages to the template
    $response->setFlash($response->getMessage(), 'error');
  }

  //set a default range
  if (!$request->hasStage('range')) {
    $request->setStage('range', 50);
  }

  //filter possible filter options
  //we do this to prevent SQL injections
  if (is_array($request->getStage('filter'))) {
    foreach ($request->getStage('filter') as $key => $value) {
      //if invalid key format or there is no value
      if (!preg_match('/^[a-zA-Z0-9_\.]+$/', $key) || !strlen($value)) {
        $request->removeStage('filter', $key);
      }
    }
  }

  //filter possible sort options
  //we do this to prevent SQL injections
  if (is_array($request->getStage('order'))) {
    foreach ($request->getStage('order') as $key => $value) {
      if (!preg_match('/^[a-zA-Z0-9_\.]+$/', $key)) {
        $request->removeStage('order', $key);
      }
    }
  }

  //trigger job
  $this('event')->emit('system-model-search', $request, $response);

  //if we only want the raw data
  if ($request->getStage('render') === 'false') {
    return;
  }

  //form the data
  $data = array_merge(
    //we need to case for things like
    //filter and sort on the template
    $request->getStage(),
    //this is from the search event
    $response->getResults()
  );

  //set listable column schemas
  $data['columns'] = $schema->getFields('listed');

  //set 1:1 relation schemas only
  $relations = $schema->getRelations(1);
  foreach ($relations as $table => $relation) {
    $data['relations'][$table] = $relation->get();
    $data['relations'][$table]['primary'] = $relation->getPrimaryName();
  }

  $data['schema'] = $schema->get();
  $data['schema']['primary'] = $schema->getPrimaryName();
  $data['schema']['restorable'] = $schema->isRestorable();

  $name = $data['schema']['name'];
  $primary = $data['schema']['primary'];
  foreach ($data['rows'] as $i => $row) {
    $data['rows'][$i]['primary'] = $row[$primary];
  }

  //----------------------------//
  // 2. Render Template
  //set the class name
  $class = 'page-admin-system-model-search page-admin';
  $data['title'] = $schema->getPlural('plural');

  $template = dirname(__DIR__) . '/template/model';
  if (is_dir($response->getPage('template_root'))) {
    $template = $response->getPage('template_root');
  }

  $body = $this('handlebars')
    ->setTemplateFolder($template)
    ->registerPartialFromFolder('search_bulk')
    ->registerPartialFromFolder('search_form')
    ->registerPartialFromFolder('search_head')
    ->registerPartialFromFolder('search_links')
    ->registerPartialFromFolder('search_row')
    ->renderFromFolder('search', $data);

  //set content
  $response
    ->setPage('title', $data['title'])
    ->setPage('class', $class)
    ->setContent($body);

  //if we only want the body
  if ($request->getStage('render') === 'body') {
    return;
  }

  //render page
  $this('admin')->render($request, $response);
});

/**
 * Process the System Model Search Actions
 *
 * @param Request $request
 * @param Response $response
 */
$this('http')->post('/admin/system/model/:schema/search', function ($request, $response) {
  //----------------------------//
  // 1. Prepare Data
  //get schema data
  $schema = Schema::i($request->getStage('schema'));

  //determine route
  $route = sprintf(
    '/admin/system/model/%s/search',
    $request->getStage('schema')
  );

  //this is for flexibility
  if ($request->hasStage('route')) {
    $route = $request->getStage('route');
  }

  $action = $request->getStage('bulk-action');
  $ids = $request->getStage($schema->getPrimaryFieldName());

  if (empty($ids)) {
    $response->setError(true, 'No IDs chosen');
    //let the form route handle the rest
    return $this->routeTo('get', $route, $request, $response);
  }

  //----------------------------//
  // 2. Process Request
  $errors = [];
  foreach ($ids as $id) {
    //table_id, 1 for example
    $request->setStage($schema->getPrimaryFieldName(), $id);

    //case for actions
    switch ($action) {
      case 'remove':
        $this('event')->emit('system-model-remove', $request, $response);
        break;
      case 'restore':
        $this('event')->emit('system-model-restore', $request, $response);
        break;
      default:
        //set an error
        $response->setError(true, 'No valid action chosen');
        //let the search route handle the rest
        return $this->routeTo('get', $route, $request, $response);
    }

    if ($response->isError()) {
      $errors[] = $response->getMessage();
    } else {
      $this->log(
        sprintf(
          '%s #%s %s',
          $schema->getSingular(),
          $id,
          $action
        ),
        $request,
        $response
      );
    }
  }

  //----------------------------//
  // 3. Interpret Results
  //redirect
  $redirect = sprintf(
    '/admin/system/model/%s/search',
    $schema->getName()
  );

  //if there is a specified redirect
  if ($request->getStage('redirect_uri')) {
    //set the redirect
    $redirect = $request->getStage('redirect_uri');
  }

  //if we dont want to redirect
  if ($redirect === 'false') {
    return;
  }

  //add a flash
  $global = $this->package('global');
  if (!empty($errors)) {
    $global->flash(
      'Some items could not be processed',
      'error',
      $errors
    );
  } else {
    $global->flash(
      sprintf(
        'Bulk action %s successful',
        $action
      ),
      'success'
    );
  }

  $global->redirect($redirect);
});

/* Detail Routes
-------------------------------- */

/**
 * Render the System Model Detail Page
 *
 * @param Request $request
 * @param Response $response
 */
$this('http')->get('/admin/spa/system/model/:schema/detail/:id', function ($request, $response) {
  //----------------------------//
  // 1. Prepare Data
  //get schema data
  $schema = Schema::load($request->getStage('schema'));
  $name = $schema->getName();
  $primary = $schema->getPrimaryName();
  $id = $request->getStage('id');

  //pass the item with only the post data
  $data = [];

  //also pass the schema to the template
  $data['schema'] = $schema->get();
  $data['schema']['primary'] = $primary;
  $data['schema']['fields'] = $schema->getFields();
  $data['schema']['restorable'] = $schema->isRestorable();

  //set relation schemas
  $relations = $schema->getRelations();
  foreach ($relations as $table => $relation) {
    $data['relations'][$table] = $relation->get();
    $data['relations'][$table]['primary'] = $relation->getPrimaryName();
  }

  //table_id, 1 for example
  $request->setStage($primary, $id);

  //get the original table row
  $this('event')->emit('system-model-detail', $request, $response);

  //if we only want the raw data
  if ($request->getStage('render') === 'false') {
    return;
  }

  //can we view ?
  if ($response->isError()) {
    return;
  }

  $data['item'] = $response->getResults();
  $data['primary'] = $data['item'][$primary];

  //determine the suggestion
  $data['item']['suggestion'] = $schema->getSuggestion($data['item']);

  $data['uuid'] = uniqid();

  //----------------------------//
  // 2. Render Template
  $template = dirname(__DIR__) . '/template/model';
  if (is_dir($response->getPage('template_root'))) {
    $template = $response->getPage('template_root');
  }

  $body = $this('handlebars')
    ->setTemplateFolder($template)
    //->registerPartialFromFolder('search_row')
    ->renderFromFolder('detail', $data);

  //set content
  $response->setContent($body);
});

/* Create Routes
-------------------------------- */

/**
 * Render the System Model Create Page
 *
 * @param Request $request
 * @param Response $response
 */
$this('http')->get('/admin/spa/system/model/:schema/create', function ($request, $response) {
  //----------------------------//
  // 1. Prepare Data
  //get schema data
  $schema = Schema::load($request->getStage('schema'));

  //pass the item with only the post data
  $data = ['item' => $request->getPost()];

  //also pass the schema to the template
  $data['schema'] = $schema->get();
  $data['schema']['primary'] = $primary;
  $data['schema']['fields'] = $schema->getFields();
  $data['schema']['restorable'] = $schema->isRestorable();

  //set relation schemas
  $relations = $schema->getRelations(0);
  foreach ($relations as $table => $relation) {
    $data['relations'][$table] = $relation->get();
    $data['relations'][$table]['primary'] = $relation->getPrimaryName();
  }

  $relations = $schema->getRelations(1);
  foreach ($relations as $table => $relation) {
    $data['relations'][$table] = $relation->get();
    $data['relations'][$table]['primary'] = $relation->getPrimaryName();
  }

  //if this is a return back from processing
  //this form and it's because of an error
  if ($response->isError()) {
    //pass the error messages to the template
    $response->setFlash($response->getMessage(), 'error');
    $data['errors'] = $response->getValidation();
  }

  //if we only want the data
  if ($request->getStage('render') === 'false') {
    return $response->setJson($data);
  }

  //----------------------------//
  // 2. Render Template
  //set the action
  $data['action'] = 'create';

  $template = dirname(__DIR__) . '/template/model';
  if (is_dir($response->getPage('template_root'))) {
    $template = $response->getPage('template_root');
  }

  //render the body
  $body = $this('handlebars')
    ->setTemplateFolder($template)
    ->renderFromFolder('form', $data);

  //set content
  $response->setContent($body);
});

/**
 * Process the System Model Create Page
 *
 * @param Request $request
 * @param Response $response
 */
$this('http')->post('/admin/spa/system/model/:schema/create', function ($request, $response) {
  //----------------------------//
  // 1. Prepare Data
  //get schema data
  $schema = Schema::load($request->getStage('schema'));

  //get all the schema field data
  $fields = $schema->getFields();

  //these are invalid types to set
  $invalidTypes = ['none', 'active', 'created', 'updated'];

  //for each field
  foreach ($fields as $name => $field) {
    //if the field is invalid
    if (in_array($field['field']['type'], $invalidTypes)) {
      $request->removeStage($name);
      continue;
    }

    //if no value
    if ($request->hasStage($name) && $request->getStage($name) === '') {
      //make it null
      $request->setStage($name, null);
      continue;
    }

    if (//if there is a default
      isset($field['default'])
      && trim($field['default'])
      // and there's no stage
      && $request->hasStage($name)
      && $request->getStage($name) === ''
    ) {
      if (strtoupper($field['default']) === 'NOW()') {
        $field['default'] = date('Y-m-d H:i:s');
      }

      //set the default
      $request->setStage($name, $field['default']);
      continue;
    }
  }

  //----------------------------//
  // 2. Process Request
  $this('event')->emit('system-model-create', $request, $response);

  //----------------------------//
  // 3. Interpret Results
  //if the event returned an error
  if ($response->isError()) {
    return;
  }

  //it was good
  $label = $schema->getSingular();
  $primary = $schema->getPrimaryName();

  //record logs
  $this->log(
    sprintf('created new %s', $label),
    $request, $response, 'create',
    $request->getStage('schema'),
    $response->getResults($primary)
  );
});

/* Import/Export Routes
-------------------------------- */

/**
 * Process Object Import
 *
 * @param Request $request
 * @param Response $response
 */
$this('http')->post('/admin/spa/system/model/:schema/import', function ($request, $response) {
  //----------------------------//
  // 1. Prepare Data
  $schema = Schema::i($request->getStage('schema'));

  // data
  $data = [];

  // try to parse the data
  try {
    // decode the data
    $data = @json_decode($request->getBody(), true);
  } catch (\Exception $e) {
    return $response
      ->setContent(json_encode([
        'error' => true,
        'message' => 'Unable to parse data',
        'errors' => [
          'Unable to parse data',
          $e->getMessage()
        ]
      ]));
  }

  // set data
  $request->setStage('rows', $data);

  //----------------------------//
  // 2. Process Request
  // catch errors for better debugging
  try {
    $this('event')->emit('system-model-import', $request, $response);
  } catch (\Exception $e) {
    return $response
      ->setContent(json_encode([
        'error' => true,
        'message' => $e->getMessage(),
        'errors' => [
          $e->getMessage()
        ]
      ]));
  }

  //----------------------------//
  // 3. Interpret Results
  //if the import event returned errors
  if ($response->isError()) {
    $errors = [];
    //loop through each row
    foreach ($response->getValidation() as $i => $validation) {
      //and loop through each error
      foreach ($validation as $key => $error) {
        //add the error
        $errors[] = sprintf('ROW %s - %s: %s', $i, $key, $error);
      }
    }

    //Set JSON Content
    return $response->setContent(json_encode([
      'error' => true,
      'message' => $response->getMessage(),
      'errors' => $errors
    ]));
  }

  //record logs
  $this->log(
    sprintf(
      'imported %s',
      $schema->getPlural()
    ),
    $request,
    $response,
    'import'
  );

  //add a flash
  $message = $this->package('global')->translate(
    '%s was Imported',
    $schema->getPlural()
  );

  //Set JSON Content
  return $response->setContent(json_encode([
    'error' => false,
    'message' => $message
  ]));
});

/**
 * Process Object Export
 *
 * @param Request $request
 * @param Response $response
 */
$this('http')->get('/admin/spa/system/model/:schema/export/:type', function ($request, $response) {
  //----------------------------//
  // 1. Prepare Data
  //get schema data
  $schema = Schema::i($request->getStage('schema'));

  //filter possible filter options
  //we do this to prevent SQL injections
  if (is_array($request->getStage('filter'))) {
    foreach ($request->getStage('filter') as $key => $value) {
      //if invalid key format or there is no value
      if (!preg_match('/^[a-zA-Z0-9_\.]+$/', $key) || !strlen($value)) {
        $request->removeStage('filter', $key);
      }
    }
  }

  //filter possible sort options
  //we do this to prevent SQL injections
  if (is_array($request->getStage('order'))) {
    foreach ($request->getStage('order') as $key => $value) {
      if (!preg_match('/^[a-zA-Z0-9_\.]+$/', $key)) {
        $request->removeStage('order', $key);
      }
    }
  }

  $request->setStage('range', 0);

  //----------------------------//
  // 2. Process Request
  $this('event')->emit('system-model-search', $request, $response);

  //----------------------------//
  // 3. Interpret Results
  //get the output type
  $type = $request->getStage('type');
  //get the rows
  $rows = $response->getResults('rows');
  //determine the filename
  $filename = $schema->getPlural() . '-' . date('Y-m-d');

  //flatten all json columns
  foreach ($rows as $i => $row) {
    foreach ($row as $key => $value) {
      //transform oobject to array
      if (is_object($value)) {
        $value = (array) $value;
      }

      //if array, let's flatten
      if (is_array($value)) {
        //if no count
        if (!count($value)) {
          $rows[$i][$key] = '';
          continue;
        }

        //if regular array
        if (isset($value[0])) {
          $rows[$i][$key] = implode(',', $value);
          continue;
        }

        $rows[$i][$key] = json_encode($value);
        continue;
      }

      //provision for any other conversions needed
    }
  }

  //if the output type is csv
  if ($type === 'csv') {
    //if there are no rows
    if (empty($rows)) {
      //at least give the headers
      $rows = [array_keys($schema->getFields())];
    } else {
      //add the headers
      array_unshift($rows, array_keys($rows[0]));
    }

    //set the output headers
    $response
      ->addHeader('Content-Encoding', 'UTF-8')
      ->addHeader('Content-Type', 'text/csv; charset=UTF-8')
      ->addHeader('Content-Disposition', 'attachment; filename=' . $filename . '.csv');

    //open a tmp file
    $file = tmpfile();
    //for each row
    foreach ($rows as $row) {
      //add it to the tmp file as a csv
      fputcsv($file, array_values($row));
    }

    //this is the final output
    $contents = '';

    //rewind the file pointer
    rewind($file);
    //and set all the contents
    while (!feof($file)) {
      $contents .= fread($file, 8192);
    }

    //close the tmp file
    fclose($file);

    //set contents
    return $response->setContent($contents);
  }

  //if the output type is xml
  if ($type === 'xml') {
    //recursive xml parser
    $toXml = function ($array, $xml) use (&$toXml) {
      //for each array
      foreach ($array as $key => $value) {
        //if the value is an array
        if (is_array($value)) {
          //if the key is not a number
          if (!is_numeric($key)) {
            //send it out for further processing (recursive)
            $toXml($value, $xml->addChild($key));
            continue;
          }

          //send it out for further processing (recursive)
          $toXml($value, $xml->addChild('item'));
          continue;
        }

        //add the value
        $xml->addChild($key, htmlspecialchars($value));
      }

      return $xml;
    };

    //set up the xml template
    $root = sprintf(
      "<?xml version=\"1.0\"?>\n<%s></%s>",
      $schema->getName(),
      $schema->getName()
    );

    //set the output headers
    $response
      ->addHeader('Content-Encoding', 'UTF-8')
      ->addHeader('Content-Type', 'text/xml; charset=UTF-8')
      ->addHeader('Content-Disposition', 'attachment; filename=' . $filename . '.xml');

    //get the contents
    $contents = $toXml($rows, new SimpleXMLElement($root))->asXML();

    //set the contents
    return $response->setContent($contents);
  }

  //json maybe?

  //set the output headers
  $response
    ->addHeader('Content-Encoding', 'UTF-8')
    ->addHeader('Content-Type', 'text/json; charset=UTF-8')
    ->addHeader('Content-Disposition', 'attachment; filename=' . $filename . '.json');

  //set content
  $response->set('json', $rows);
});

/* Remove/Restore Routes
-------------------------------- */

/**
 * Process the System Model Remove
 *
 * @param Request $request
 * @param Response $response
 */
$this('http')->get('/admin/spa/system/model/:schema/remove/:id', function ($request, $response) {
  //----------------------------//
  // 1. Prepare Data
  //get schema data
  $schema = Schema::i($request->getStage('schema'));

  //table_id, 1 for example
  $request->setStage(
    $schema->getPrimaryFieldName(),
    $request->getStage('id')
  );

  //----------------------------//
  // 2. Process Request
  $this('event')->emit('system-model-remove', $request, $response);

  //----------------------------//
  // 3. Interpret Results
  if (!$response->isError()) {
    //record logs
    $this->log(
      sprintf(
        'removed %s #%s',
        $schema->getSingular(),
        $request->getStage('id')
      ),
      $request,
      $response,
      'remove',
      $request->getStage('schema'),
      $request->getStage('id')
    );
  }

  //redirect
  $redirect = sprintf(
    '/admin/system/model/%s/search',
    $schema->getName()
  );

  //if there is a specified redirect
  if ($request->getStage('redirect_uri')) {
    //set the redirect
    $redirect = $request->getStage('redirect_uri');
  }

  //if we dont want to redirect
  if ($redirect === 'false') {
    return;
  }

  $global = $this->package('global');
  if ($response->isError()) {
    //add a flash
    $global->flash($response->getMessage(), 'error');
  } else {
    //add a flash
    $message = sprintf('%s was Removed', $schema->getSingular());
    $global->flash($message, 'success');
  }

  $global->redirect($redirect);
});

/**
 * Process the System Model Restore
 *
 * @param Request $request
 * @param Response $response
 */
$this('http')->get('/admin/spa/system/model/:schema/restore/:id', function ($request, $response) {
  //----------------------------//
  // 1. Prepare Data
  //get schema data
  $schema = Schema::i($request->getStage('schema'));

  //table_id, 1 for example
  $request->setStage(
    $schema->getPrimaryFieldName(),
    $request->getStage('id')
  );

  //----------------------------//
  // 2. Process Request
  $this('event')->emit('system-model-restore', $request, $response);

  //----------------------------//
  // 3. Interpret Results
  if (!$response->isError()) {
    //record logs
    $this->log(
      sprintf(
        'restored %s #%s',
        $schema->getSingular(),
        $request->getStage('id')
      ),
      $request,
      $response,
      'restore',
      $request->getStage('schema'),
      $request->getStage('id')
    );
  }

  //redirect
  $redirect = sprintf(
    '/admin/system/model/%s/search',
    $schema->getName()
  );

  //if there is a specified redirect
  if ($request->getStage('redirect_uri')) {
    //set the redirect
    $redirect = $request->getStage('redirect_uri');
  }

  //if we dont want to redirect
  if ($redirect === 'false') {
    return;
  }

  $global = $this->package('global');
  if ($response->isError()) {
    //add a flash
    $global->flash($response->getMessage(), 'error');
  } else {
    //add a flash
    $message = sprintf('%s was Restored', $schema->getSingular());
    $global->flash($message, 'success');
  }

  $global->redirect($redirect);
});

/* Update Routes
-------------------------------- */

/**
 * Render the System Model Update Page
 *
 * @param Request $request
 * @param Response $response
 */
$this('http')->get('/admin/spa/system/model/:schema/update/:id', function ($request, $response) {
  //----------------------------//
  // 1. Prepare Data
  //get schema data
  $schema = Schema::load($request->getStage('schema'));
  $name = $schema->getName();
  $primary = $schema->getPrimaryName();
  $id = $request->getStage('id');

  //pass the item with only the post data
  $data = ['item' => $request->getPost()];

  //also pass the schema to the template
  $data['schema'] = $schema->get();
  $data['schema']['primary'] = $primary;
  $data['schema']['fields'] = $schema->getFields();
  $data['schema']['restorable'] = $schema->isRestorable();

  //split fields and fieldsets
  foreach ($data['schema']['fields'] as $key => $field) {
    if ($field['field']['type'] !== 'fieldset'
      || !isset($field['field']['parameters'][0])
    ) {
      continue;
    }

    unset($data['schema']['fields'][$key]);
    $data['schema']['fieldsets'][$key] = $field;
    $data['schema']['fieldsets'][$key]['fieldset'] = Fieldset::load(
      $field['field']['parameters'][0]
    )->get();
  }

  //set relation schemas
  $relations = $schema->getRelations();
  foreach ($relations as $table => $relation) {
    $data['relations'][$table] = $relation->get();
    $data['relations'][$table]['primary'] = $relation->getPrimaryName();
  }

  //if this is a return back from processing
  //this form and it's because of an error
  if ($response->isError()) {
    //pass the error messages to the template
    $response->setFlash($response->getMessage(), 'error');
    $data['errors'] = $response->getValidation();
  }

  //table_id, 1 for example
  $request->setStage($primary, $id);

  //get the original table row
  $this('event')->emit('system-model-detail', $request, $response);

  //if we only want the raw data
  if ($request->getStage('render') === 'false') {
    return;
  }

  //can we view ?
  if ($response->isError()) {
    return;
  }

  $data['item'] = $response->getResults();
  $data['primary'] = $data['item'][$primary];

  //determine the suggestion
  $data['item']['suggestion'] = $schema->getSuggestion($data['item']);

  //----------------------------//
  // 2. Render Template
  //set the action
  $data['action'] = 'update';

  $template = dirname(__DIR__) . '/template/model';
  if (is_dir($response->getPage('template_root'))) {
    $template = $response->getPage('template_root');
  }

  $body = $this('handlebars')
    ->setTemplateFolder($template)
    //->registerPartialFromFolder('search_row')
    ->renderFromFolder('form', $data);

  //set content
  $response->setContent($body);
});

/**
 * Process the System Model Update Page
 *
 * @param Request $request
 * @param Response $response
 */
$this('http')->post('/admin/spa/system/model/:schema/update/:id', function ($request, $response) {
  //----------------------------//
  // 1. Prepare Data
  //get schema data
  $schema = Schema::load($request->getStage('schema'));
  $primary = $schema->getPrimaryName();
  $id = $request->getStage('id');

  //table_id, 1 for example
  $request->setStage($primary, $id);

  //get all the schema field data
  $fields = $schema->getFields();

  //these are invalid types to set
  $invalidTypes = ['none', 'active', 'created', 'updated'];

  //for each field
  foreach ($fields as $name => $field) {
    //if the field is invalid
    if (in_array($field['field']['type'], $invalidTypes)) {
      $request->removeStage($name);
      continue;
    }

    //if password has no value
    if ($request->hasStage($name) && !$request->getStage($name)
      && $field['field']['type'] === 'password'
    ) {
      //make it null
      $request->removeStage($name);
      continue;
    }

    //if no value
    if ($request->hasStage($name) && $request->getStage($name) === '') {
      //make it null
      $request->setStage($name, null);
      continue;
    }
  }

  //----------------------------//
  // 2. Process Request
  $this('event')->emit('system-model-update', $request, $response);

  //----------------------------//
  // 3. Interpret Results
  //if the event returned an error
  if ($response->isError()) {
    return;
  }

  //it was good
  $label = $schema->getSingular();

  //record logs
  $this->log(
    sprintf('updated %s #%s', $label, $id),
    $request, $response, 'update',
    $request->getStage('schema'), $id
  );
});
