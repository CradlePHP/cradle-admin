<?php //-->

use Cradle\IO\Request\RequestInterface;
use Cradle\IO\Response\ResponseInterface;

$this('cradlephp/cradle-admin')
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
    string $layout = 'page'
  ): string {
    $handler = $this->getPackageHandler();

    $menu = $response->get('menu');
    if (!is_array($menu)) {
      $menu = $handler('config')->get('menu');
    }

    if (!is_array($menu)) {
      $menu = [];
    }

    foreach ($menu as $i => $item) {
      if (isset($item['submenu']) && is_array($item['submenu'])) {
        $active = false;
        foreach ($item['submenu'] as $j => $subitem) {
          if (isset($subitem['path']) && strpos($subitem['path'], $path) === 0) {
            $menu[$i]['submenu'][$j]['active'] = true;
            $active = true;
          }
        }

        if ($active) {
          $menu[$i]['active'] = true;
        }

        continue;
      }

      if (strpos($item['path'], $path) === 0) {
        $menu[$i]['active'] = true;
      }
    }

    $data = ['menu' => $menu];

    $template = dirname(__DIR__) . '/template';

    return $handler('handlebars')
      ->registerPartialFromFile('head', $template . '/_head.html')
      ->registerPartialFromFile('left', $template . '/_left.html')
      ->registerPartialFromFile('right', $template . '/_right.html')
      ->renderFromFile(sprintf('%s/_%s.html', $template, $layout), $data);
  });
