jQuery(($) => {
  $(window).on('table-sortable-init', function(e, target) {
    $.require('components/jquery-sortable/source/js/jquery-sortable-min.js', function() {
      $(target).sortable({
        containerSelector: 'table',
        itemPath: '> tbody',
        itemSelector: 'tr',
        placeholder: '<tr class="placeholder"/>',
        handle: $(target).data('handle'),
        onDrop: function ($item, container, _super, event) {
          $item.removeClass(container.group.options.draggedClass).removeAttr('style');
          $('body').removeClass(container.group.options.bodyClass);

          //todo
        }
      });
    });
  });

  $(window).on('fieldset-form-submit', function(e, target) {
    e.preventDefault();
    var form = $(target);

    const method = form.attr('method') || 'post';
    const action = form.attr('action') || window.location.href;

    //validate
    e.type = 'bootstrap-validator-submit';
    $(window).trigger(e, [target]);
    if (e.return === false) {
      return false;
    }

    if (!target.checkValidity()) {
      return false;
    }

    //get the data
    const data = form.form2json() || {};

    data.fields = [];
    $('script.field-data', form).each(function () {
      data.fields.push(JSON.parse($(this).html().trim()));
    });

    //ajax it up
    $[method](action, data, function(response) {
      //if no response
      if (typeof response !== 'object') {
        $.notify('Server Error', 'error');
        return;
      }
      //if response error
      if (response.error) {
        const message = response.message || 'Server Error';
        if (!response.validation) {
          $.notify(message, 'error');
          return;
        }

        $.notify(
          $.buildNotification(message, response.validation),
          'error'
        );
        return;
      }

      window.location.reload();
    });

    return false;
  });

  // fieldset - slugger
  $(window).on('slugger-init', function(e, target) {
    if (!$(target).hasClass('fieldset-name')) {
      return;
    }

    $(target).change(function(e) {
      setTimeout(function() {
        var fieldsetName = $(target).val();
        //update every row
        $('table.table-fields tbody tr').each(function() {
          var fieldName = $(this).data('name');
          $('span.field-name', this).text(`${fieldsetName}_${fieldName}`);
        });
      });
    }).trigger('change');
  });
});
