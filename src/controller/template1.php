<?php //-->

/**
 * Render Template Actions
 *
 * @param Request $request
 * @param Response $response
 */
$this('http')->get('/admin/template1/search', function ($request, $response) {
  //----------------------------//
  // 1. Prepare body
  $data['title'] = $this('lang')->translate('Template1 Search');

  $data['range'] = 50;
  $data['total'] = 10000;
  for ($i = 0; $i < 20; $i++) {
    $data['rows'][] = [
      'template1_id' => 1,
      'template1_reference' => 'ABC-123-' . $i,
      'template1_title' => 'A Sample Title ' . ($i + 1),
      'template1_currency' => 'USD',
      'template1_price' => 100.23 + rand(2, 10000),
      'template1_available' => date(
        'Y-m-d H:i:s',
        strtotime(
          sprintf('- %s days', rand(1, 30))
        )
      ),
      'template1_quantity' => rand(1, 1000),
      'template1_status' => ['pending', 'review', 'ready', 'failed'][rand(0, 3)]
    ];
  }

  //----------------------------//
  // 2. Render Template
  $class = sprintf('page-admin-template-%s page-admin', $path);

  $template = dirname(__DIR__) . '/template';
  if (is_dir($response->getPage('template_root'))) {
    $template = $response->getPage('template_root');
  }

  $file = sprintf('%s/template/search.html', $template);
  $body = $this('handlebars')
    ->setTemplateFolder($template)
    ->registerPartialFromFolder('template1_search_bulk')
    ->registerPartialFromFolder('template1_search_filters')
    ->registerPartialFromFolder('template1_search_form')
    ->registerPartialFromFolder('template1_search_head')
    ->registerPartialFromFolder('template1_search_links')
    ->registerPartialFromFolder('template1_search_row')
    ->registerPartialFromFolder('template1_search_tabs')
    ->renderFromFolder('template1/search', $data);

  //set content
  $response
    ->setPage('title', $data['title'])
    ->setPage('class', $class)
    ->setContent($body);

  //render page
  $admin = $this('cradlephp/cradle-admin');
  $content = $admin->render($request, $response);
  $response->setContent($content);
});
