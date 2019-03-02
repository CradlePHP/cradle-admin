<?php //-->
return array (
  'singular' => 'Report',
  'plural' => 'Reports',
  'name' => 'report',
  'group' => 'Admin',
  'icon' => 'fas fa-chart-area fa-fw',
  'detail' => 'Manages report configs',
  'fields' =>
  array (
    0 =>
    array (
      'label' => 'Slug',
      'name' => 'slug',
      'field' =>
      array (
        'type' => 'slug',
      ),
      'validation' =>
      array (
        0 =>
        array (
          'method' => 'required',
          'message' => 'Slug is required',
        ),
        1 =>
        array (
          'method' => 'unique',
          'message' => 'This identifier is already used.',
        ),
      ),
      'list' =>
      array (
        'format' => 'none',
      ),
      'detail' =>
      array (
        'format' => 'none',
      ),
      'default' => '',
      'searchable' => '1',
      'filterable' => '1',
    ),
    1 =>
    array (
      'label' => 'Schema',
      'name' => 'schema',
      'field' =>
      array (
        'type' => 'text',
        'attributes' =>
        array (
          'data-do' => 'update-detail-url',
          'data-on' => 'change',
        ),
      ),
      'validation' =>
      array (
        0 =>
        array (
          'method' => 'required',
          'message' => 'Schema is required',
        ),
      ),
      'list' =>
      array (
        'format' => 'none',
      ),
      'detail' =>
      array (
        'format' => 'none',
      ),
      'default' => '',
      'searchable' => '1',
      'filterable' => '1',
    ),
    2 =>
    array (
      'label' => 'Filter Parameters',
      'name' => 'filter_parameters',
      'field' =>
      array (
        'type' => 'rawjson',
      ),
      'list' =>
      array (
        'format' => 'jsonpretty',
      ),
      'detail' =>
      array (
        'format' => 'jsonpretty',
      ),
      'default' => '',
    ),
    3 =>
    array (
      'label' => 'Chart',
      'name' => 'chart',
      'field' =>
      array (
        'type' => 'select',
        'options' =>
        array (
          0 =>
          array (
            'key' => 'area',
            'value' => 'Area',
          ),
          1 =>
          array (
            'key' => 'bar',
            'value' => 'Bar',
          ),
          2 =>
          array (
            'key' => 'doughnut',
            'value' => 'Doughnut',
          ),
          3 =>
          array (
            'key' => 'line',
            'value' => 'Line',
          ),
          4 =>
          array (
            'key' => 'pie',
            'value' => 'Pie',
          ),
        ),
        'attributes' =>
        array (
          'data-do' => 'show-report-fields',
          'data-on' => 'change',
        ),
      ),
      'list' =>
      array (
        'format' => 'none',
      ),
      'detail' =>
      array (
        'format' => 'none',
      ),
      'default' => '',
      'filterable' => '1',
    ),
    4 =>
    array (
      'label' => 'Meta',
      'name' => 'meta',
      'field' =>
      array (
        'type' => 'table',
      ),
      'list' =>
      array (
        'format' => 'jsonpretty',
      ),
      'detail' =>
      array (
        'format' => 'jsonpretty',
      ),
      'default' => '',
    ),
    5 =>
    array (
      'label' => 'Active',
      'name' => 'active',
      'field' =>
      array (
        'type' => 'active',
      ),
      'list' =>
      array (
        'format' => 'none',
      ),
      'detail' =>
      array (
        'format' => 'none',
      ),
      'default' => '1',
      'sortable' => '1',
    ),
  ),
  'suggestion' => '{{report_slug}}',
);
