<?php //-->
/**
 * This file is part of a package designed for the CradlePHP Project.
 *
 * Copyright and license information can be found at LICENSE.txt
 * distributed with this package.
 */

use Cradle\Package\System\Schema;
use Cradle\Package\System\Field\FieldRegistry;
use Cradle\Package\System\Format\FormatterRegistry;
use Cradle\Package\System\Validation\ValidatorRegistry;

/* Search/Bulk Routes
-------------------------------- */

/**
 * Render schema search page
 *
 * @param Request $request
 * @param Response $response
 */
$this('http')->get('/admin/system/schema/search', function ($request, $response) {
  //----------------------------//
  // 1. Prepare Data
  if (!$request->hasStage()) {
    $request->setStage('filter', 'active', 1);
  }

  //trigger job
  $this('event')->emit('system-schema-search', $request, $response);

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

  //organize by groups
  $data['groups'] = [];
  if (isset($data['rows']) && is_array($data['rows'])) {
    foreach ($data['rows'] as $row) {
      $group = 'Custom';
      if (isset($row['group']) && trim($row['group'])) {
      $group = $row['group'];
      }

      $data['groups'][$group][] = $row;
    }
  }

  ksort($data['groups']);

  //----------------------------//
  // 2. Render Template
  $class = 'page-admin-system-schema-search page-admin';
  $data['title'] = $this('lang')->translate('Schemas');

  $template = dirname(__DIR__) . '/template/schema';
  if (is_dir($response->getPage('template_root'))) {
    $template = $response->getPage('template_root');
  }

  $fieldScript = dirname(__DIR__) . '/template/field/assets/_script.js';

  $body = $this('handlebars')
    ->setTemplateFolder($template)
    ->registerPartialFromFolder('asset_style', 'css')
    ->registerPartialFromFolder('asset_script', 'js')
    ->registerPartialFromFolder('asset_script_form', 'js')
    ->registerPartialFromFolder('asset_script_relation', 'js')
    ->registerPartialFromFolder('search_head')
    ->registerPartialFromFolder('search_links')
    ->registerPartialFromFolder('search_row')
    ->registerPartialFromFolder('search_tabs')
    ->registerPartialFromFile('field_script', $fieldScript)
    ->renderFromFolder('search', $data);

  //if we only want the body
  if ($request->getStage('render') === 'body') {
    return;
  }

  //set content
  $response
    ->setPage('title', $data['title'])
    ->setPage('class', $class)
    ->setContent($body);

  //render page
  $this('admin')->render($request, $response);
});

/* Create Routes
-------------------------------- */

/**
 * SPA: Render the schema create screen
 *
 * @param *Request $request
 * @param *Response $response
 */
$this('http')->get('/admin/spa/system/schema/create', function($request, $response) {
  //----------------------------//
  // 1. Prepare Data
  $data['mode'] = 'create';
  $data['item'] = $request->getStage();
  $data['uuid'] = uniqid();

  if (isset($data['item']['fields']) && is_array($data['item']['fields'])) {
    foreach ($data['item']['fields'] as $i => $field) {
      $data['item']['fields'][$i]['root'] = $data['item']['name'];
    }
  }

  //----------------------------//
  // 2. Render Template
  $template = dirname(__DIR__) . '/template/schema';
  if (is_dir($response->getPage('template_root'))) {
    $template = $response->getPage('template_root');
  }

  $body = $this('handlebars')
    ->setTemplateFolder($template)
    ->registerPartialFromFolder('form_content')
    ->registerPartialFromFolder('form_fields')
    ->registerPartialFromFolder('form_relations')
    ->registerPartialFromFolder('form_row')
    ->renderFromFolder('form', $data);

  //set content
  $response->setContent($body);
});

/**
 * SPA: Render the schema create screen (copy)
 *
 * @param *Request $request
 * @param *Response $response
 */
$this('http')->get('/admin/spa/system/schema/create/:name', function($request, $response) {
  //----------------------------//
  // 1. Prepare Data
  //get the original schema row
  $this('event')->emit('system-schema-detail', $request, $response);

  //can we create ?
  if ($response->isError()) {
    $body = $this('handlebars')->renderFromFile(
      dirname(__DIR__) . '/template/invalid.html',
      $response->get('json')
    );

    return $response->setContent($body);
  }

  //if we only want the raw data
  if ($request->getStage('render') === 'false') {
    return;
  }

  $request->setStage($response->getResults());

  //----------------------------//
  // 2. Render Template
  $this('http')->routeTo('get', '/admin/spa/system/schema/create', $request, $response);
});

/**
 * SPA: Process the schema create screen
 *
 * @param Request $request
 * @param Response $response
 */
$this('http')->post('/admin/spa/system/schema/create', function ($request, $response) {
  //----------------------------//
  // 1. Prepare Data
  $data = $request->getPost();

  //if detail has no value make it null
  if (isset($data['detail']) && !trim($data['detail'])) {
    $data['detail'] = null;
  }

  //if fields has no value make it an array
  if (!isset($data['fields']) || !is_array($data['fields'])) {
    $data['fields'] = [];
  }

  //if validation has no value make it an array
  foreach ($data['fields'] as $i => $field) {
    if (!isset($field['validation']) || !is_array($field['validation'])) {
      $data['fields'][$i]['validation'] = [];
    }
  }

  //if relations has no value make it an array
  if (!isset($data['relations']) || !is_array($data['relations'])) {
    $data['relations'] = [];
  }

  //----------------------------//
  // 2. Process Data
  $this('event')->method('system-schema-create', $data, $response);

  //if error
  if ($response->isError()) {
    //abort
    return;
  }

  //record logs
  $this->log(
    sprintf('created schema: %s', $request->getStage('singular')),
    $request, $response, 'create', 'schema', $request->getStage('name')
  );
});

/* Field Routes
-------------------------------- */

/**
 * SPA: Render the field form screen
 *
 * @param *Request $request
 * @param *Response $response
 */
$this('http')->post('/admin/spa/system/schema/field', function($request, $response) {
  $request->setStage('render', 'false');
  $this('http')->routeTo('post', '/admin/spa/system/field', $request, $response);
  $data = $response->getResults();

  //----------------------------//
  // 2. Render Template
  $template = dirname(__DIR__) . '/template/schema';
  if (is_dir($response->getPage('template_root'))) {
    $template = $response->getPage('template_root');
  }

  $field = dirname(__DIR__) . '/template/field';

  $body = $this('handlebars')
    ->setTemplateFolder($template)
    ->registerPartialFromFile(
      'form_detail',
      sprintf('%s/form/_detail.html', $field)
    )
    ->registerPartialFromFile(
      'form_list',
      sprintf('%s/form/_list.html', $field)
    )
    ->registerPartialFromFile(
      'form_type',
      sprintf('%s/form/_type.html', $field)
    )
    ->registerPartialFromFile(
      'form_templates',
      sprintf('%s/form/_templates.html', $field)
    )
    ->registerPartialFromFile(
      'form_validation',
      sprintf('%s/form/_validation.html', $field)
    )
    ->registerPartialFromFile(
      'options_format',
      sprintf('%s/options/_format.html', $field)
    )
    ->registerPartialFromFile(
      'options_type',
      sprintf('%s/options/_type.html', $field)
    )
    ->registerPartialFromFile(
      'options_validation',
      sprintf('%s/options/_validation.html', $field)
    )
    ->renderFromFolder('field', $data);

  //set content
  $response->setContent($body);
});

/**
 * SPA: Process the field form screen
 *
 * @param *Request $request
 * @param *Response $response
 */
$this('http')->post('/admin/spa/system/schema/field/save', function($request, $response) {
  //----------------------------//
  // 1. Prepare Data
  $data = $request->getStage();
  $data['this'] = $data;

  //----------------------------//
  // 2. Process Data
  $template = dirname(__DIR__) . '/template/fieldset';
  if (is_dir($response->getPage('template_root'))) {
    $template = $response->getPage('template_root');
  }

  $body = $this('handlebars')
    ->setTemplateFolder($template)
    ->renderFromFolder('form/_row', $data);

  //set content
  $response->setContent($body);
});

/* Import/Export Routes
-------------------------------- */

/**
 * Process all schema export
 *
 * @param *Request $request
 * @param *Response $response
 */
$this('http')->get('/admin/system/schema/export', function($request, $response) {
  //get the schema folder path
  $path = Schema::getFolder();

  //default redirect
  $redirect = '/admin/system/schema/search';
  //if there is a specified redirect_uri
  if ($request->getStage('redirect_uri')) {
    $redirect = $request->getStage('redirect_uri');
  }

  //check if ZipArchive is installed
  if (!class_exists('ZipArchive')) {
    //add a flash
    $request->setSession('flash', [
      'message' => $this('lang')->translate('ZipArchive module not found'),
      'type' => 'error'
    ]);
    //redirect
    return $this('http')->redirect($redirect);
  }

  //create zip archive
  $zip = new ZipArchive();
  //create temporary file
  $tmp = sys_get_temp_dir() . '/schemas.zip';

  //try to open
  if (!$zip->open($tmp, ZipArchive::CREATE | ZipArchive::OVERWRITE)) {
    //add a flash
    $request->setSession('flash', [
      'message' => $this('lang')->translate('Failed to create archive'),
      'type' => 'error'
    ]);
    //redirect
    return $this('http')->redirect($redirect);
  }

  //create an empty directory
  $zip->addEmptyDir('schema');

  //collect all .php files and add it
  foreach (glob($path . '/*.php') as $file) {
    //determine json filename
    $name = str_replace('.php', '.json', basename($file));
    //read the content
    $content = json_encode(include($file), JSON_PRETTY_PRINT);

    //add the content to zip
    $zip->addFromString('schema/' . $name, $content);
  }

  //close
  $zip->close();

  //check if file exists
  if (!file_exists($tmp)) {
    //add a flash
    $request->setSession('flash', [
      'message' => $this('lang')->translate('Failed to create archive'),
      'type' => 'error'
    ]);
    //redirect
    return $this('http')->redirect($redirect);
  }

  //prepare response
  $response
    ->addHeader('Content-Type', 'application/zip')
    ->addHeader('Content-Transfer-Encoding', 'Binary')
    ->addHeader('Content-Disposition', 'attachment; filename=' . basename($tmp))
    ->addHeader('Content-Length', filesize($tmp));

  return $response->setContent(file_get_contents($tmp));
});

/**
 * Process schema export
 *
 * @param *Request $request
 * @param *Response $response
 */
$this('http')->get('/admin/system/schema/export/:name', function($request, $response) {
  //get the schema folder path
  $path = Schema::getFolder();
  //get the name
  $name = $request->getStage('name');

  //default redirect
  $redirect = '/admin/system/schema/search';
  //if there is a specified redirect_uri
  if ($request->getStage('redirect_uri')) {
    $redirect = $request->getStage('redirect_uri');
  }

  //determine the file
  $file = sprintf('%s/%s.php', $path, $name);

  //file does not exists?
  if (!file_exists($file)) {
    //add a flash
    $request->setSession('flash', [
      'message' => $this('lang')->translate('Not Found'),
      'type' => 'error'
    ]);
    //redirect
    return $this('http')->redirect($redirect);
  }

  //get the filename
  $filename = str_replace('.php', '.json', basename($file));

  //prepare response
  $response
    ->addHeader('Content-Encoding', 'UTF-8')
    ->addHeader('Content-Type', 'text/json; charset=UTF-8')
    ->addHeader('Content-Disposition', 'attachment; filename=' . $filename);

  //include the php file
  $content = json_encode(include($file), JSON_PRETTY_PRINT);

  //return content
  return $response->setContent($content);
});

/**
 * SPA: Render schema import screen
 *
 * @param *Request $request
 * @param *Response $response
 */
$this('http')->get('/admin/spa/system/schema/import', function($request, $response) {
  //----------------------------//
  // 1. Prepare Data
  //----------------------------//
  // 2. Render Template
  $template = dirname(__DIR__) . '/template/schema';
  if (is_dir($response->getPage('template_root'))) {
    $template = $response->getPage('template_root');
  }

  $body = $this('handlebars')
    ->setTemplateFolder($template)
    ->renderFromFolder('import');

  //set content
  $response->setContent($body);
});

/**
 * SPA: Process schema import
 *
 * @param *Request $request
 * @param *Response $response
 */
$this('http')->post('/admin/spa/system/schema/import', function($request, $response) {
  //get the schema folder path
  $path = Schema::getFolder();
  //get the content
  $schema = $request->getStage('schema');
  //get the type
  $type = substr($schema, 5, strpos($schema, ';base64') - 5);

  //invalid file?
  if ($type !== 'application/json' && $type !== 'application/zip') {
    return $response->setError(true, 'Invalid File');
  }

  //decode the content
  $content = base64_decode(
    substr($schema, strpos($schema, ';base64,') + 8)
  );

  //json file?
  if ($type === 'application/json') {
    //parse the content
    $content = json_decode($content, true);

    //if not name or is not an array
    if (!is_array($content) || !isset($content['name'])) {
      return $response->setError(true, 'Invalid Schema');
    }

    //create payload
    $payload = $this->makePayload();

    //set schema to stage
    $payload['request']->setStage($content);
    //cleanup
    $payload['request']->removeStage('schema');

    //trigger update
    if (file_exists(sprintf('%s/%s.php', $path, $content['name']))) {
      $this('event')->emit('system-schema-update', $payload['request'], $payload['response']);
    //trigger create
    } else {
      $this('event')->emit('system-schema-create', $payload['request'], $payload['response']);
    }

    //error?
    if ($payload['response']->isError()) {
      return $response->invalidate($content['name'], [
        'message' => $payload['response']->getMessage(),
        'validation' => $payload['response']->getValidation()
      ])->setError(true, $payload['response']->getMessage());
    }

    //it was good

    //record logs
    $this->log(
      sprintf('imported schema: %s', $content['name']),
      $request, $response, 'import', 'schema', $content['name']
    );

    //add a flash
    $request->setSession('flash', [
      'message' => $this('lang')->translate('System Schema was Imported'),
      'type' => 'success'
    ]);

    return $response->setError(false);
  }

  //get temporary folder
  $tmp = sys_get_temp_dir();
  //create temporary zip
  $file  = sprintf('%s/%s.zip', $tmp, uniqid());

  //create temporary zip file
  file_put_contents($file, $content);

  //check if ZipArchive is installed
  if (!class_exists('ZipArchive')) {
    return $response->setError(true, 'ZipArchive module not found');
  }

  //open zip archive
  $zip = new ZipArchive();

  //try to open
  if (!$zip->open($file)) {
    return $response->setError(true, 'Failed to parse archive');
  }

  //loop through files
  for ($i = 0; $i < $zip->numFiles; $i++) {
    //get the filename
    $filename = $zip->getNameIndex($i);

    //root or not under schema?
    if ($filename === 'schema/'
      || strpos($filename, 'schema/') === false
    ) {
      continue;
    }

    //parse the content of each filename
    $content = json_decode($zip->getFromName($filename), true);
    //create payload
    $payload = $this->makePayload();

    //skip if schema doesn't have name or is not an array
    if (!isset($content['name']) || !is_array($content)) {
      continue;
    }

    //set the content
    $payload['request']->setStage($content);
    //cleanup
    $payload['request']->removeStage('schema');

    //trigger update
    if (file_exists(sprintf('%s/%s.php', $path, $content['name']))) {
      $this('event')->emit('system-schema-update', $payload['request'], $payload['response']);
    //trigger create
    } else {
      $this('event')->emit('system-schema-create', $payload['request'], $payload['response']);
    }

    //error?
    if ($payload['response']->isError()) {
      //set the message and validation
      $response->invalidate($content['name'], [
        'message' => $payload['response']->getMessage(),
        'validation' => $payload['response']->getValidation()
      ]);

      continue;
    }

    //record logs
    $this->log(
      sprintf('imported schema: %s', $content['name']),
      $request, $response, 'import', 'schema', $content['name']
    );
  }

  //errors?
  if (!$response->isValid()) {
    return $response->setError(true, 'Invalid Parameters');
  }

  //it was good
  //add a flash
  $request->setSession('flash', [
    'message' => $this('lang')->translate('System Schema was Imported'),
    'type' => 'success'
  ]);

  return $response->setError(false);
});

/* Remove/Restore Routes
-------------------------------- */

/**
 * SPA: Render schema remove screen
 *
 * @param *Request $request
 * @param *Response $response
 */
$this('http')->get('/admin/spa/system/schema/remove/:name', function($request, $response) {
  //----------------------------//
  // 1. Prepare Data
  $data = $request->getStage();
  $data['action'] = sprintf(
    '/admin/spa/system/schema/remove/%s',
    $data['name']
  );

  $data['title'] = $this('lang')->translate('Remove %s', $data['name']);

  $data['message'] = $this('lang')->translate(
    'Are you sure you want to remove %s',
    $data['name']
  );

  $this->emit('system-schema-detail', $request, $response);
  $data['item'] = $response->getResults();

  //----------------------------//
  // 2. Render Template
  $template = dirname(__DIR__) . '/template/schema';
  if (is_dir($response->getPage('template_root'))) {
    $template = $response->getPage('template_root');
  }

  $body = $this('handlebars')
    ->setTemplateFolder($template)
    ->renderFromFolder('confirm', $data);

  //set content
  $response->setContent($body);
});

/**
 * SPA: Process schema remove screen
 *
 * @param *Request $request
 * @param *Response $response
 */
$this('http')->post('/admin/spa/system/schema/remove/:name', function($request, $response) {
  //----------------------------//
  // 1. Prepare Data
  //nothing to prepare
  //----------------------------//
  // 2. Process Data
  $this->emit('system-schema-remove', $request, $response);

  //if error
  if ($response->isError()) {
    //abort
    return;
  }

  //record logs
  $this->log(
    sprintf('removed schema: %s', $request->getStage('name')),
    $request, $response, 'remove', 'schema', $request->getStage('name')
  );
});

/**
 * SPA: Render schema remove screen
 *
 * @param *Request $request
 * @param *Response $response
 */
$this('http')->get('/admin/spa/system/schema/restore/:name', function($request, $response) {
  //----------------------------//
  // 1. Prepare Data
  $data = $request->getStage();
  $data['action'] = sprintf(
    '/admin/spa/system/schema/restore/%s',
    $data['name']
  );

  $data['title'] = $this('lang')->translate('Restore %s', $data['name']);

  $data['message'] = $this('lang')->translate(
    'Are you sure you want to restore %s',
    $data['name']
  );

  $this->emit('system-schema-detail', $request, $response);
  $data['item'] = $response->getResults();

  //----------------------------//
  // 2. Render Template
  $template = dirname(__DIR__) . '/template/schema';
  if (is_dir($response->getPage('template_root'))) {
    $template = $response->getPage('template_root');
  }

  $body = $this('handlebars')
    ->setTemplateFolder($template)
    ->renderFromFolder('confirm', $data);

  //set content
  $response->setContent($body);
});

/**
 * SPA: Process schema remove screen
 *
 * @param *Request $request
 * @param *Response $response
 */
$this('http')->post('/admin/spa/system/schema/restore/:name', function($request, $response) {
  //----------------------------//
  // 1. Prepare Data
  //nothing to prepare
  //----------------------------//
  // 2. Process Data
  $this->emit('system-schema-restore', $request, $response);

  //if error
  if ($response->isError()) {
    //abort
    return;
  }

  //record logs
  $this->log(
    sprintf('restored schema: %s', $request->getStage('name')),
    $request, $response, 'remove', 'schema', $request->getStage('name')
  );
});

/* Update Routes
-------------------------------- */

/**
 * SPA: Render the schema update screen
 *
 * @param *Request $request
 * @param *Response $response
 */
$this('http')->get('/admin/spa/system/schema/update/:name', function($request, $response) {
  //----------------------------//
  // 1. Prepare Data
  //get the original schema row
  $this('event')->emit('system-schema-detail', $request, $response);

  //can we update ?
  if ($response->isError()) {
    $body = $this('handlebars')->renderFromFile(
      dirname(__DIR__) . '/template/invalid.html',
      $response->get('json')
    );

    return $response->setContent($body);
  }

  $data['mode'] = 'update';
  $data['item'] = $response->getResults();
  $data['uuid'] = uniqid();

  if (isset($data['item']['fields']) && is_array($data['item']['fields'])) {
    foreach ($data['item']['fields'] as $i => $field) {
      $data['item']['fields'][$i]['root'] = $data['item']['name'];
    }
  }

  //----------------------------//
  // 2. Render Template
  $template = dirname(__DIR__) . '/template/schema';
  if (is_dir($response->getPage('template_root'))) {
    $template = $response->getPage('template_root');
  }

  $body = $this('handlebars')
    ->setTemplateFolder($template)
    ->registerPartialFromFolder('form_content')
    ->registerPartialFromFolder('form_fields')
    ->registerPartialFromFolder('form_relations')
    ->registerPartialFromFolder('form_row')
    ->renderFromFolder('form', $data);

  //set content
  $response->setContent($body);
});

/**
 * SPA: Process the schema update screen
 *
 * @param *Request $request
 * @param *Response $response
 */
$this('http')->post('/admin/spa/system/schema/update/:name', function ($request, $response) {
  //----------------------------//
  // 1. Prepare Data
  $data = $request->getPost();

  //if detail has no value make it null
  if (isset($data['detail']) && !trim($data['detail'])) {
    $data['detail'] = null;
  }

  //if fields has no value make it an array
  if (!isset($data['fields']) || !is_array($data['fields'])) {
    $data['fields'] = [];
  }

  //if validation has no value make it an array
  foreach ($data['fields'] as $i => $field) {
    if (!isset($field['validation']) || !is_array($field['validation'])) {
      $data['fields'][$i]['validation'] = [];
    }
  }

  //if relations has no value make it an array
  if (!isset($data['relations']) || !is_array($data['relations'])) {
    $data['relations'] = [];
  }

  //----------------------------//
  // 2. Process Data
  $this('event')->method('system-schema-update', $data, $response);

  //if error
  if ($response->isError()) {
    //abort
    return;
  }

  //record logs
  $this->log(
    sprintf('updated schema: %s', $request->getStage('singular')),
    $request, $response, 'updated', 'schema', $request->getStage('name')
  );
});
