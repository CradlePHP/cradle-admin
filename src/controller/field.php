<?php //-->
/**
 * This file is part of a package designed for the CradlePHP Project.
 *
 * Copyright and license information can be found at LICENSE.txt
 * distributed with this package.
 */

use Cradle\Package\System\Fieldset;
use Cradle\Package\System\Field\FieldRegistry;
use Cradle\Package\System\Format\FormatterRegistry;
use Cradle\Package\System\Validation\ValidatorRegistry;

/**
 * SPA: Render the field form screen
 *
 * @param *Request $request
 * @param *Response $response
 */
$this('http')->post('/admin/spa/system/field', function($request, $response) {
  //----------------------------//
  // 1. Prepare Data
  $render = $request->getStage('render');
  $request->removeStage('render');
  //if there's a copy
  if ($request->getStage('copy')) {
    //set mode to copy
    $request->setStage('mode', 'copy');
  //if there's stage data
  } else if ($request->hasStage() && !empty($request->getStage())) {
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
        $path = str_replace(['[', ']'], ['.', ''], $path);
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
  if ($format) {
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
  if ($format) {
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

  //make sure there is a list
  if (!isset($data['list'])) {
    $data['list'] = [];
  }

  //make sure there is a detail
  if (!isset($data['detail'])) {
    $data['detail'] = [];
  }

  //if we only want the raw data
  if ($render === 'false') {
    return $response->setResults($data);
  }

  //----------------------------//
  // 2. Render Template
  $template = dirname(__DIR__) . '/template/field';
  if (is_dir($response->getPage('template_root'))) {
    $template = $response->getPage('template_root');
  }

  $body = $this('handlebars')
    ->setTemplateFolder($template)
    ->registerPartialFromFolder('form_detail')
    ->registerPartialFromFolder('form_list')
    ->registerPartialFromFolder('form_type')
    ->registerPartialFromFolder('form_templates')
    ->registerPartialFromFolder('form_validation')
    ->registerPartialFromFolder('options_format')
    ->registerPartialFromFolder('options_type')
    ->registerPartialFromFolder('options_validation')
    ->renderFromFolder('form', $data);

  //set content
  $response->setContent($body);
});

/**
 * SPA: Process the field form screen
 *
 * @param *Request $request
 * @param *Response $response
 */
$this('http')->post('/admin/spa/system/field/save', function($request, $response) {
  //----------------------------//
  // 1. Prepare Data
  $data = $request->getStage();

  //----------------------------//
  // 2. Process Data
  $response->setResults($data);
});
