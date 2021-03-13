<?php //-->

use Cradle\IO\Request\RequestInterface;
use Cradle\IO\Response\ResponseInterface;

//register a pseudo admin and load it
$this->register('admin')->package('admin')
  /**
   * Render Admin Page
   *
   * @param *RequestInterface  $request
   * @param *ResponseInterface $response
   * @param string             $layout
   *
   * @return string
   */
  ->addMethod('render', function(
    RequestInterface $request,
    ResponseInterface $response,
    string $layout = 'app'
  ): string {
    $handler = $this->getPackageHandler();

    $menu = $response->get('menu');
    if (!is_array($menu)) {
      $menu = $handler('config')->get('menu');
    }

    if (!is_array($menu)) {
      $menu = [];
    }

    $host = $handler('host')->all();
    foreach ($menu as $i => $item) {
      if (isset($item['submenu']) && is_array($item['submenu'])) {
        $active = false;
        foreach ($item['submenu'] as $j => $subitem) {
          if (isset($subitem['path']) && strpos($subitem['path'], $host['path']) === 0) {
            $menu[$i]['submenu'][$j]['active'] = true;
            $active = true;
          }
        }

        if ($active) {
          $menu[$i]['active'] = true;
        }

        continue;
      }

      if (strpos($item['path'], $host['path']) === 0) {
        $menu[$i]['active'] = true;
      }
    }

    $data = [
      'page' => $response->getPage(),
      'results' => $response->getResults(),
      'content' => $response->getContent(),
      'i18n' => $request->getSession('i18n'),
      'host' => $host,
      'menu' => $menu
    ];

    $template = dirname(__DIR__) . '/template';

    $page = $handler('handlebars')
      ->registerPartialFromFile('head', $template . '/_head.html')
      ->registerPartialFromFile('left', $template . '/_left.html')
      ->registerPartialFromFile('right', $template . '/_right.html')
      ->registerPartialFromFile('flash', $template . '/_flash.html')
      ->renderFromFile(sprintf('%s/_%s.html', $template, $layout), $data);

    $response->setContent($page);
    return $page;
  });
