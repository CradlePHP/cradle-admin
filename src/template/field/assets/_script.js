jQuery(($) => {
  $(window).on('field-type-change', function(e, target) {
    var select = $(target);
    var option = $('option[value="' + select.val() + '"]', select);
    var form = select.parents('[data-do="field-form"]').eq(0);

    //configuration
    var fieldsets = (option.attr('data-fieldset') || '').split('|');
    var formats = (option.attr('data-format') || '').split('|');

    //reset
    form[0].resetFormState();

    //remove all fieldsets
    select.parent().find('div.fieldset-options').remove();
    select.parent().find('div.fieldset-attributes').remove();
    select.parent().find('div.fieldset-field').remove();

    if (fieldsets.indexOf('attributes') !== -1) {
      //transform template to fieldset
      const attributes = $('#fieldset-attributes-template').compile();
      //append the new fieldset right after the dropdown
      select.after(attributes);
      //start the fieldset js
      attributes.doon();
    }

    if (fieldsets.indexOf('options') !== -1) {
      //transform template to fieldset
      const options = $('#fieldset-options-template').compile();
      //append the new fieldset right after the dropdown
      select.after(options);
      //start the fieldset js
      options.doon();
    }

    //get the template
    var template = $('#fieldset-field-' + select.val());
    //if no template
    if (template.length) {
      //transform template to fieldset
      const fieldset = template.compile({ NAME: 'field' });
      //append the new fieldset right after the dropdown
      select.after(fieldset);
      //start the fieldset js
      fieldset.doon();
    }

    //determine valid formats
    ['string', 'number', 'date', 'html', 'json', 'custom'].forEach(function(type) {
      if(formats.indexOf(type) === -1) {
        var list = $('select.field-list optgroup.filter-group-' + type, form).hide();
        if($('option:selected', list).length) {
          list.parent().val('none').trigger('change');
        }

        var detail = $('select.field-detail optgroup.filter-group-' + type, form).hide();
        if($('option:selected', detail).length) {
          detail.parent().val('none').trigger('change');
        }
      }
    });

    form[0].setFormState();
  });

  $(window).on('field-format-change', function(e, target) {
    var select = $(target);
    var option = $('option[value="' + select.val() + '"]', target);

    //fixing a jQuery bug on clone
    $('option', this).removeAttr('selected');
    option.attr('selected', 'selected');

    //remove the fieldset right after the dropdown if any
    select.next('.fieldset-format').remove();

    //get the template
    var template = $('#fieldset-format-' + select.val());
    //if no template
    if (!template.length) {
      return;
    }

    //transform template to fieldset
    const fieldset = template.compile({ NAME: select.data('name') });
    //append the new fieldset right after the dropdown
    select.after(fieldset);
    //start the fieldset js
    fieldset.doon();
  });

  $(window).on('field-validation-row-init', function(e, target) {
    target = $(target);

    var index = target.data('index');

    $('a.remove:first', target).click(function() {
      target.remove();
    });

    //validation field change
    $('select', target).change(function() {
      var select = $(this);
      var option = $('option[value="' + select.val() + '"]', this);

      //fixing a jQuery bug on clone
      $('option', this).removeAttr('selected');
      option.attr('selected', 'selected');

      //remove the fieldset right after the dropdown if any
      select.parent().next('.fieldset-validation').remove();

      //get the template
      var template = $('#fieldset-validation-' + select.val());
      //if no template
      if (!template.length) {
        return;
      }

      //transform template to fieldset
      const fieldset = template.compile({ NAME: select.data('name') });
      //append the new fieldset right after the dropdown
      select.parent().after(fieldset);
      //start the fieldset js
      fieldset.doon();
    });
  });

  $(window).on('field-validation-add-click', function(e, target) {
    var last = $(target).parent().find('div.validation-row:last');

    var index = 0;
    if(last.length) {
      index = parseInt(last.attr('data-index')) + 1;
    }

    var row = $('#field-validation-row-template').compile({ INDEX: index });

    $(target).before(row);

    row.doon();
  });

  $(window).on('field-form-init', function(e, target) {
    //force attach method to target
    target.setFormState = function () {
      var select = $('select.field-type', target);
      var option = $('option[value="' + select.val() + '"]', select);

      var value = option.attr('data-default');
      var label = option.attr('data-label');
      var name = option.attr('data-name');
      var searchable = option.attr('data-searchable');
      var filterable = option.attr('data-filterable');
      var sortable = option.attr('data-sortable');
      var validation = option.attr('data-validation');
      var indexes = (option.attr('data-index') || '').split('|');

      //determine label
      if(label && label.length) {
        $('input.field-label', target).val(label);
        $('div.form-group-label', target).hide();
      } else {
        $('div.form-group-label', target).show();
      }

      //determine name
      if(name && name.length) {
        $('input.field-name', target).val(name);
        $('div.form-group-name', target).hide();
        select.parent().find('div.input-fieldset').remove();
      } else {
        $('div.form-group-name', target).show();
      }

      //determine validation
      if(validation === 0 || validation === '0') {
        $('div.validation-row', target).remove();
        $('div.form-group-validation', target).hide();
      } else {
        $('div.form-group-validation', target).show();
      }

      //determine default
      if(value && value.length) {
        $('input.field-default', target).val(value);
        $('div.form-group-default', target).hide();
      } else {
        $('div.form-group-default', target).show();
      }

      //determine searchable
      if(searchable && searchable.length) {
        if(searchable == 1) {
          $('input.field-searchable', target).prop('checked', true);
        } else {
          $('input.field-searchable', target).prop('checked', false);
        }

        $('input.field-searchable', target).parent().hide();
      } else {
        $('input.field-searchable', target).parent().show();
      }

      //determine filterable
      if(filterable && filterable.length) {
        if(filterable == 1) {
          $('input.field-filterable', target).prop('checked', true);
        } else {
          $('input.field-filterable', target).prop('checked', false);
        }

        $('input.field-filterable', target).parent().hide();
      } else {
        $('input.field-filterable', target).parent().show();
      }

      //determine sortable
      if(sortable && sortable.length) {
        if(sortable == 1) {
          $('input.field-sortable', target).prop('checked', true);
        } else {
          $('input.field-sortable', target).prop('checked', false);
        }

        $('input.field-sortable', target).parent().hide();
      } else {
        $('input.field-sortable', target).parent().show();
      }

      //determine index
      ['searchable', 'filterable', 'sortable'].forEach(function(index) {
        if(indexes.indexOf(index) === -1) {
          $('input.field-' + index, target)
            .attr('checked', false)
            .attr('disabled', true);
        }
      });
    };

    target.resetFormState = function () {
      $('select.field-list optgroup', target).show();
      $('select.field-detail optgroup', target).show();
      $('input.field-searchable', target).attr('disabled', false);
      $('input.field-filterable', target).attr('disabled', false);
      $('input.field-sortable', target).attr('disabled', false);
    };

    target.setFormState();

    $(window).trigger('panel-form-init', [target]);
  });

  $(window).on('field-form-submit', function(e, target) {
    e.preventDefault();
    $.require('components/handlebars/dist/handlebars.js', function() {
      var name = $(target).data('name');
      var fieldset = $('form.fieldset-form input.fieldset-name').val();

      $(window).trigger('panel-form-submit', [target, function(response) {
        var exists = $(`#field-${name}`);
        var row = $(response);

        if (exists.length) {
          exists.replaceWith(row);
        } else {
          $('table.table-fields').append(row);
        }

        $('input.fieldset-name').trigger('change');
        $('div.alert-table-fields-empty').addClass('d-none');

        row.doon();
      }]);
    });
    return false;
  });

  $(window).on('field-remove-click', function(e, target) {
    var row = $(target).parent().parent();
    var body = row.parent();
    row.remove();

    if (!body.children().length) {
      $('form.fieldset-form div.alert-table-fields-empty').removeClass('d-none');
    }
  });

  // fieldset - slugger
  $(window).on('slugger-init', function(e, target) {
    if (!$(target).hasClass('field-name')) {
      return;
    }

    var oldname = $(target).parents('form').data('name');
    var group = $(target).parents('div.form-group').eq(0);

    $(target).on('validation-check', function() {
      if (!target.checkValidity()) {
        return;
      }

      var input = $(target);

      var type = input.parents('section.view-body').find('select.field-type').val();
      var message = 'Keyword is using a reserved word.';

      if (oldname && input.val() == oldname) {
        return;
      }

      if(input.val() === 'id') {
        target.setCustomValidity(message);
        return;
      }

      if(input.val() === 'active' && type !== 'active') {
        target.setCustomValidity(message);
        return;
      }

      if(input.val() === 'created' && type !== 'created') {
        target.setCustomValidity(message);
        return;
      }

      if(input.val() === 'updated' && type !== 'updated') {
        target.setCustomValidity(message);
        return;
      }

      $('table.table-fields tbody tr').each(function() {
        var value = input.val();
        if($(this).data('name') === value) {
          target.setCustomValidity('Keyword already exists.');
        }
      });
    });
  });
});
