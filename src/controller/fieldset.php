<?php //-->
/**
 * This file is part of a package designed for the CradlePHP Project.
 *
 * Copyright and license information can be found at LICENSE.txt
 * distributed with this package.
 */

use Cradle\Package\System\Fieldset;

/* Search/Bulk Routes
-------------------------------- */

/**
 * Render fieldset search page
 *
 * @param Request $request
 * @param Response $response
 */
$this('http')->get('/admin/system/fieldset/search', function ($request, $response) {
  //----------------------------//
  // 1. Prepare Data
  if (!$request->hasStage()) {
    $request->setStage('filter', 'active', 1);
  }

  //trigger job
  $this('event')->emit('system-fieldset-search', $request, $response);

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

  //----------------------------//
  // 2. Render Template
  $class = 'page-admin-system-fieldset-search page-admin';
  $data['title'] = $this('lang')->translate('Fieldsets');

  $template = dirname(__DIR__) . '/template/fieldset';
  if (is_dir($response->getPage('template_root'))) {
    $template = $response->getPage('template_root');
  }

  $fieldScript = dirname(__DIR__) . '/template/field/assets/_script.js';

  $body = $this('handlebars')
    ->setTemplateFolder($template)
    ->registerPartialFromFolder('asset_style', 'css')
    ->registerPartialFromFolder('asset_script', 'js')
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
 * SPA: Render the fieldset create screen
 *
 * @param *Request $request
 * @param *Response $response
 */
$this('http')->get('/admin/spa/system/fieldset/create', function($request, $response) {
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
  $template = dirname(__DIR__) . '/template/fieldset';
  if (is_dir($response->getPage('template_root'))) {
    $template = $response->getPage('template_root');
  }

  $body = $this('handlebars')
    ->setTemplateFolder($template)
    ->registerPartialFromFolder('form_content')
    ->registerPartialFromFolder('form_fields')
    ->registerPartialFromFolder('form_row')
    ->renderFromFolder('form', $data);

  //set content
  $response->setContent($body);
});

/**
 * SPA: Render the fieldset create screen (copy)
 *
 * @param *Request $request
 * @param *Response $response
 */
$this('http')->get('/admin/spa/system/fieldset/create/:name', function($request, $response) {
  //----------------------------//
  // 1. Prepare Data
  //get the original fieldset row
  $this('event')->emit('system-fieldset-detail', $request, $response);

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
  $this('http')->routeTo('get', '/admin/spa/system/fieldset/create', $request, $response);
});

/**
 * SPA: Process the fieldset create screen
 *
 * @param Request $request
 * @param Response $response
 */
$this('http')->post('/admin/spa/system/fieldset/create', function ($request, $response) {
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
  $this('event')->method('system-fieldset-create', $data, $response);

  //if error
  if ($response->isError()) {
    //abort
    return;
  }

  //record logs
  $this->log(
    sprintf('created fieldset: %s', $request->getStage('singular')),
    $request, $response, 'create', 'fieldset', $request->getStage('name')
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
$this('http')->post('/admin/spa/system/fieldset/field', function($request, $response) {
  $request->setStage('render', 'false');
  $this('http')->routeTo('post', '/admin/spa/system/field', $request, $response);
  $data = $response->getResults();

  //----------------------------//
  // 2. Render Template
  $template = dirname(__DIR__) . '/template/fieldset';
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
$this('http')->post('/admin/spa/system/fieldset/field/save', function($request, $response) {
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
 * Process all fieldset export
 *
 * @param *Request $request
 * @param *Response $response
 */
$this('http')->get('/admin/system/fieldset/export', function($request, $response) {
  //get the fieldset folder path
  $path = Fieldset::getFolder();

  //default redirect
  $redirect = '/admin/system/fieldset/search';
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
  $tmp = sys_get_temp_dir() . '/fieldsets.zip';

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
  $zip->addEmptyDir('fieldset');

  //collect all .php files and add it
  foreach (glob($path . '/*.php') as $file) {
    //determine json filename
    $name = str_replace('.php', '.json', basename($file));
    //read the content
    $content = json_encode(include($file), JSON_PRETTY_PRINT);

    //add the content to zip
    $zip->addFromString('fieldset/' . $name, $content);
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
 * Process fieldset export
 *
 * @param *Request $request
 * @param *Response $response
 */
$this('http')->get('/admin/system/fieldset/export/:name', function($request, $response) {
  //get the fieldset folder path
  $path = Fieldset::getFolder();
  //get the name
  $name = $request->getStage('name');

  //default redirect
  $redirect = '/admin/system/fieldset/search';
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
 * SPA: Render fieldset import screen
 *
 * @param *Request $request
 * @param *Response $response
 */
$this('http')->get('/admin/spa/system/fieldset/import', function($request, $response) {
  //----------------------------//
  // 1. Prepare Data
  //----------------------------//
  // 2. Render Template
  $template = dirname(__DIR__) . '/template/fieldset';
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
 * SPA: Process fieldset import
 *
 * @param *Request $request
 * @param *Response $response
 */
$this('http')->post('/admin/spa/system/fieldset/import', function($request, $response) {
  //get the fieldset folder path
  $path = Fieldset::getFolder();
  //get the content
  $fieldset = $request->getStage('fieldset');
  //get the type
  $type = substr($fieldset, 5, strpos($fieldset, ';base64') - 5);

  //invalid file?
  if ($type !== 'application/json' && $type !== 'application/zip') {
    return $response->setError(true, 'Invalid File');
  }

  //decode the content
  $content = base64_decode(
    substr($fieldset, strpos($fieldset, ';base64,') + 8)
  );

  //json file?
  if ($type === 'application/json') {
    //parse the content
    $content = json_decode($content, true);

    //if not name or is not an array
    if (!is_array($content) || !isset($content['name'])) {
      return $response->setError(true, 'Invalid Fieldset');
    }

    //create payload
    $payload = $this->makePayload();

    //set fieldset to stage
    $payload['request']->setStage($content);
    //cleanup
    $payload['request']->removeStage('fieldset');

    //trigger update
    if (file_exists(sprintf('%s/%s.php', $path, $content['name']))) {
      $this('event')->emit('system-fieldset-update', $payload['request'], $payload['response']);
    //trigger create
    } else {
      $this('event')->emit('system-fieldset-create', $payload['request'], $payload['response']);
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
      sprintf('imported fieldset: %s', $content['name']),
      $request, $response, 'import', 'fieldset', $content['name']
    );

    //add a flash
    $request->setSession('flash', [
      'message' => $this('lang')->translate('System Fieldset was Imported'),
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

    //root or not under fieldset?
    if ($filename === 'fieldset/'
      || strpos($filename, 'fieldset/') === false
    ) {
      continue;
    }

    //parse the content of each filename
    $content = json_decode($zip->getFromName($filename), true);
    //create payload
    $payload = $this->makePayload();

    //skip if fieldset doesn't have name or is not an array
    if (!isset($content['name']) || !is_array($content)) {
      continue;
    }

    //set the content
    $payload['request']->setStage($content);
    //cleanup
    $payload['request']->removeStage('fieldset');

    //trigger update
    if (file_exists(sprintf('%s/%s.php', $path, $content['name']))) {
      $this('event')->emit('system-fieldset-update', $payload['request'], $payload['response']);
    //trigger create
    } else {
      $this('event')->emit('system-fieldset-create', $payload['request'], $payload['response']);
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
      sprintf('imported fieldset: %s', $content['name']),
      $request, $response, 'import', 'fieldset', $content['name']
    );
  }

  //errors?
  if (!$response->isValid()) {
    return $response->setError(true, 'Invalid Parameters');
  }

  //it was good
  //add a flash
  $request->setSession('flash', [
    'message' => $this('lang')->translate('System Fieldset was Imported'),
    'type' => 'success'
  ]);

  return $response->setError(false);
});

/* Remove/Restore Routes
-------------------------------- */

/**
 * SPA: Render fieldset remove screen
 *
 * @param *Request $request
 * @param *Response $response
 */
$this('http')->get('/admin/spa/system/fieldset/remove/:name', function($request, $response) {
  //----------------------------//
  // 1. Prepare Data
  $data = $request->getStage();
  $data['action'] = sprintf(
    '/admin/spa/system/fieldset/remove/%s',
    $data['name']
  );

  $data['title'] = $this('lang')->translate('Remove %s', $data['name']);

  $data['message'] = $this('lang')->translate(
    'Are you sure you want to remove %s',
    $data['name']
  );

  $this->emit('system-fieldset-detail', $request, $response);
  $data['item'] = $response->getResults();

  //----------------------------//
  // 2. Render Template
  $template = dirname(__DIR__) . '/template/fieldset';
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
 * SPA: Process fieldset remove screen
 *
 * @param *Request $request
 * @param *Response $response
 */
$this('http')->post('/admin/spa/system/fieldset/remove/:name', function($request, $response) {
  //----------------------------//
  // 1. Prepare Data
  //nothing to prepare
  //----------------------------//
  // 2. Process Data
  $this->emit('system-fieldset-remove', $request, $response);

  //if error
  if ($response->isError()) {
    //abort
    return;
  }

  //record logs
  $this->log(
    sprintf('removed fieldset: %s', $request->getStage('name')),
    $request, $response, 'remove', 'fieldset', $request->getStage('name')
  );
});

/**
 * SPA: Render fieldset remove screen
 *
 * @param *Request $request
 * @param *Response $response
 */
$this('http')->get('/admin/spa/system/fieldset/restore/:name', function($request, $response) {
  //----------------------------//
  // 1. Prepare Data
  $data = $request->getStage();
  $data['action'] = sprintf(
    '/admin/spa/system/fieldset/restore/%s',
    $data['name']
  );

  $data['title'] = $this('lang')->translate('Restore %s', $data['name']);

  $data['message'] = $this('lang')->translate(
    'Are you sure you want to restore %s',
    $data['name']
  );

  $this->emit('system-fieldset-detail', $request, $response);
  $data['item'] = $response->getResults();

  //----------------------------//
  // 2. Render Template
  $template = dirname(__DIR__) . '/template/fieldset';
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
 * SPA: Process fieldset remove screen
 *
 * @param *Request $request
 * @param *Response $response
 */
$this('http')->post('/admin/spa/system/fieldset/restore/:name', function($request, $response) {
  //----------------------------//
  // 1. Prepare Data
  //nothing to prepare
  //----------------------------//
  // 2. Process Data
  $this->emit('system-fieldset-restore', $request, $response);

  //if error
  if ($response->isError()) {
    //abort
    return;
  }

  //record logs
  $this->log(
    sprintf('restored fieldset: %s', $request->getStage('name')),
    $request, $response, 'remove', 'fieldset', $request->getStage('name')
  );
});

/* Update Routes
-------------------------------- */

/**
 * SPA: Render the fieldset update screen
 *
 * @param *Request $request
 * @param *Response $response
 */
$this('http')->get('/admin/spa/system/fieldset/update/:name', function($request, $response) {
  //----------------------------//
  // 1. Prepare Data
  //get the original fieldset row
  $this('event')->emit('system-fieldset-detail', $request, $response);

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
  $template = dirname(__DIR__) . '/template/fieldset';
  if (is_dir($response->getPage('template_root'))) {
    $template = $response->getPage('template_root');
  }

  $body = $this('handlebars')
    ->setTemplateFolder($template)
    ->registerPartialFromFolder('form_content')
    ->registerPartialFromFolder('form_fields')
    ->registerPartialFromFolder('form_row')
    ->renderFromFolder('form', $data);

  //set content
  $response->setContent($body);
});

/**
 * SPA: Process the fieldset update screen
 *
 * @param *Request $request
 * @param *Response $response
 */
$this('http')->post('/admin/spa/system/fieldset/update/:name', function ($request, $response) {
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
  $this('event')->method('system-fieldset-update', $data, $response);

  //if error
  if ($response->isError()) {
    //abort
    return;
  }

  //record logs
  $this->log(
    sprintf('updated fieldset: %s', $request->getStage('singular')),
    $request, $response, 'updated', 'fieldset', $request->getStage('name')
  );
});
