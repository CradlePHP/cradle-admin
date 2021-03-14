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
    ->registerPartialFromFolder('field_row')
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
  //----------------------------//
  // 1. Prepare Data
  //if there's a copy
  if ($request->getStage('copy')) {
    //set mode to copy
    $request->setStage('mode', 'copy');
  //if there's stage data
  } else if ($request->hasStage()) {
    //set mode to update
    $request->setStage('mode', 'update');
  //when in doubt
  } else {
    //set mode to create
    $request->setStage('mode', 'create');
  }

  $fieldsets = [
    'field' => FieldRegistry::getFields(),
    'format' => FormatterRegistry::getFormatters(),
    'validation' => ValidatorRegistry::getValidators()
  ];

  //for each fieldset
  foreach ($fieldsets as $name => $fieldset) {
    //for each registered fieldset
    foreach($fieldset as $option) {
      //set it as an option in the $name dropdown
      $config = $option::toConfigArray();
      $request->setStage(
        'config', $name, 'options', $option::TYPE, $option::NAME, $config
      );

      //foreach additional fieldset configuration
      foreach ($option::getConfigFieldset() as $i => $field) {
        //add the fieldset configuration
        $id = sprintf('fieldset-%s-%s', $name, $option::NAME);
        $template = $field->render();
        $request->setStage(
          'config', $name, 'fieldsets', $id, 'templates', $i, $template
        );
      }
    }
  }

  //generate config and fieldset for field type
  $type = $request->getStage('field', 'type');
  if ($type && $type !== 'none') {
    //make a field given the type
    $field = FieldRegistry::makeField($type);
    if ($field) {
      //set the field config
      $config = $field::toConfigArray();
      $request->setStage('field', 'config', $config);
      //foreach additional fieldset configuration
      foreach ($field::getConfigFieldset() as $i => $field) {
        //fix the field name
        $name = str_replace('{NAME}', 'field', $field->getName());
        //set the name
        $field->setName($name);
        //find the value path
        $path = str_replace('][', '.', $name);
        $path = str_replace(['[', ']'], '', $path);
        //should look like field,parameters,0
        $path = explode('.', $path);
        //render the template
        $template = $field->render($request->getStage(...$path));
        //add the fieldset configuration
        $request->setStage('field', 'config', 'fieldset', $i, $template);
      }
    }
  }

  //generate config and fieldset for list format
  $format = $request->getStage('list', 'format');
  if ($format && $format !== 'none') {
    //make a formatter given the format
    $formatter = FormatterRegistry::makeFormatter($format);
    if ($formatter) {
      //set the list config
      $config = $formatter::toConfigArray();
      $request->setStage('list', 'config', $config);
      //foreach additional fieldset configuration
      foreach ($formatter::getConfigFieldset() as $i => $field) {
        //fix the format name
        $name = str_replace('{NAME}', 'list', $field->getName());
        //set the name
        $field->setName($name);
        //find the value path
        $path = str_replace('][', '.', $name);
        $path = str_replace(['[', ']'], '', $path);
        //should look like list,parameters,0
        $path = explode('.', $path);
        //render the template
        $template = $field->render($request->getStage(...$path));
        //add the fieldset configuration
        $request->setStage('list', 'config', 'fieldset', $i, $template);
      }
    }
  }

  //generate config and fieldset for detail format
  $format = $request->getStage('detail', 'format');
  if ($format && $format !== 'none') {
    //make a formatter given the format
    $formatter = FormatterRegistry::makeFormatter($format);
    if ($formatter) {
      //set the detail config
      $config = $formatter::toConfigArray();
      $request->setStage('detail', 'config', $config);
      //foreach additional fieldset configuration
      foreach ($formatter::getConfigFieldset() as $i => $field) {
        //fix the format name
        $name = str_replace('{NAME}', 'detail', $field->getName());
        //set the name
        $field->setName($name);
        //find the value path
        $path = str_replace('][', '.', $name);
        $path = str_replace(['[', ']'], '', $path);
        //should look like list,parameters,0
        $path = explode('.', $path);
        //render the template
        $template = $field->render($request->getStage(...$path));
        //add the fieldset configuration
        $request->setStage('detail', 'config', 'fieldset', $i, $template);
      }
    }
  }

  //if there is validation
  if (is_array($request->getStage('validation'))) {
    //for each validation
    foreach ($request->getStage('validation') as $i => $validation) {
      //make a validator given the validation
      $validator = ValidatorRegistry::makeValidator($validation['method']);
      if ($validator) {
        //set the validation config
        $config = $validator::toConfigArray();
        $request->setStage('validation', $i, 'config', $config);
        //foreach additional fieldset configuration
        foreach ($validator::getConfigFieldset() as $j => $field) {
          //fix the validation name
          $name = sprintf('validation[%s]', $i);
          $name = str_replace('{NAME}', $name, $field->getName());
          //set the name
          $field->setName($name);
          //find the value path
          $path = str_replace('][', '.', $name);
          $path = str_replace(['[', ']'], '', $path);
          //should look like list,parameters,0
          $path = explode('.', $path);
          //render the template
          $template = $field->render($request->getStage(...$path));
          //add the fieldset configuration
          $request->setStage(
            'validation', $i, 'config', 'fieldset', $j, $template
          );
        }
      }
    }
  }

  $data = $request->getStage();

  //----------------------------//
  // 2. Render Template
  $template = dirname(__DIR__) . '/template/schema';
  if (is_dir($response->getPage('template_root'))) {
    $template = $response->getPage('template_root');
  }

  $body = $this('handlebars')
    ->setTemplateFolder($template)
    ->registerPartialFromFolder('field_detail')
    ->registerPartialFromFolder('field_list')
    ->registerPartialFromFolder('field_type')
    ->registerPartialFromFolder('field_templates')
    ->registerPartialFromFolder('field_validation')
    ->registerPartialFromFolder('field_options_format')
    ->registerPartialFromFolder('field_options_type')
    ->registerPartialFromFolder('field_options_validation')
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

  //----------------------------//
  // 2. Process Data
  $response->setResults($data);
  $data['this'] = $data;

  //----------------------------//
  // 3. Render Template
  $template = dirname(__DIR__) . '/template/schema';
  if (is_dir($response->getPage('template_root'))) {
    $template = $response->getPage('template_root');
  }

  $body = $this('handlebars')
    ->setTemplateFolder($template)
    ->renderFromFolder('field/_row', $data);

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

  $body = $this('handlebars')
    ->setTemplateFolder($template)
    ->registerPartialFromFolder('asset_style', 'css')
    ->registerPartialFromFolder('asset_script', 'js')
    ->registerPartialFromFolder('asset_script_field', 'js')
    ->registerPartialFromFolder('asset_script_form', 'js')
    ->registerPartialFromFolder('asset_script_relation', 'js')
    ->registerPartialFromFolder('search_head')
    ->registerPartialFromFolder('search_links')
    ->registerPartialFromFolder('search_row')
    ->registerPartialFromFolder('search_tabs')
    ->renderFromFolder('search', $data);

  //set content
  $response
    ->setPage('title', $data['title'])
    ->setPage('class', $class)
    ->setContent($body);

  //render page
  $this('admin')->render($request, $response);
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

  //if we only want the raw data
  if ($request->getStage('render') === 'false') {
    return;
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
    ->registerPartialFromFolder('field_row')
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
