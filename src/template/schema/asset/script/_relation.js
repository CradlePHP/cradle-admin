$(window).on('schema-relation-remove-click', function (e, trigger) {
  var row = $(trigger).parent().parent();
  var body = row.parent();
  row.remove();

  //reindex
  $('tr', body).each(function(i) {
    $(this)
      .attr('data-index', i)
      .data('index', i);

    var name = body
      .attr('data-name-format')
      .replace('{INDEX}', i);

    $('select.relation-many', this).attr('name', `${name}[many]`);
    $('input.relation-name', this).attr('name', `${name}[name]`);
  });
});

$(window).on('schema-relation-add-click', function (e, target) {
  var body = $('table.table-relations tbody');
  var last = $('tr:last', body);
  var index = 0;
  if(last.length) {
    index = parseInt(last.attr('data-index')) + 1;
  }

  var singular = $('form.schema-form input.schema-singular').val() || '??';

  var row = $('#relation-row-template').compile({
    SINGULAR: singular,
    INDEX: index
  });

  body.append(row);
  row.doon();
});
