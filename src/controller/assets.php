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
  $file = sprintf('%s/assets/admin.js', dirname(__DIR__), $name);

  if (!file_exists($file)) {
    return;
  }

  $response->addHeader('Content-Type', 'text/javascript');
  $response->setContent(file_get_contents($file));
});

/**
 * Render Admin CSS
 *
 * @param Request $request
 * @param Response $response
 */
$this('http')->get('/styles/admin.css', function ($request, $response) {
  $file = sprintf('%s/assets/admin.css', dirname(__DIR__), $name);

  if (!file_exists($file)) {
    return;
  }

  $response->addHeader('Content-Type', 'text/css');
  $response->setContent(file_get_contents($file));
});
