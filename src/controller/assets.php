<?php //-->

/**
 * Render the JSON files we have
 *
 * @param Request $request
 * @param Response $response
 */
$this('http')->get('/json/:name.json', function ($request, $response) {
  $name = $request->getStage('name');
  $file = sprintf('%s/assets/json/%s.json', dirname(__DIR__), $name);

  if (!file_exists($file)) {
    return;
  }

  $response->addHeader('Content-Type', 'text/json');
  $response->setContent(file_get_contents($file));
});

/**
 * Render Admin JS
 *
 * @param Request $request
 * @param Response $response
 */
$this('http')->get('/scripts/admin.js', function ($request, $response) {
  $body = $this('handlebars')
    ->setTemplateFolder(dirname(__DIR__) . '/assets')
    ->registerPartialFromFolder('script_app', 'js')
    ->registerPartialFromFolder('script_fields', 'js')
    ->registerPartialFromFolder('script_form', 'js')
    ->registerPartialFromFolder('script_misc', 'js')
    ->registerPartialFromFolder('script_jquery', 'js')
    ->registerPartialFromFolder('script_search', 'js')
    ->renderFromFolder('admin', [], 'js');

  $response->addHeader('Content-Type', 'text/javascript');
  $response->setContent($body);
});

/**
 * Render Admin CSS
 *
 * @param Request $request
 * @param Response $response
 */
$this('http')->get('/styles/admin.css', function ($request, $response) {
  $body = $this('handlebars')
    ->setTemplateFolder(dirname(__DIR__) . '/assets')
    ->registerPartialFromFolder('style_auth', 'css')
    ->registerPartialFromFolder('style_components', 'css')
    ->registerPartialFromFolder('style_error', 'css')
    ->registerPartialFromFolder('style_fields', 'css')
    ->registerPartialFromFolder('style_form', 'css')
    ->registerPartialFromFolder('style_layout', 'css')
    ->registerPartialFromFolder('style_reset', 'css')
    ->registerPartialFromFolder('style_search', 'css')
    ->registerPartialFromFolder('style_theme', 'css')
    ->registerPartialFromFolder('style_twbs', 'css')
    ->registerPartialFromFolder('style_view', 'css')
    ->renderFromFolder('admin', [], 'css');

  $response->addHeader('Content-Type', 'text/css');
  $response->setContent($body);
});
