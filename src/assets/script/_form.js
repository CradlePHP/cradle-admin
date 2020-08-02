/**
 * General Forms
 */
(function() {
  /**
   * Suggestion Field
   */
  $(window).on('suggestion-field-init', function(e, target) {
    $.require('components/handlebars/dist/handlebars.js', function() {
      target = $(target);

      var container = $('<ul>').appendTo(target);

      var searching = false,
        prevent = false,
        value = target.attr('data-value'),
        format = target.attr('data-format'),
        targetLabel = target.attr('data-target-label'),
        targetValue = target.attr('data-target-value'),
        url = target.attr('data-url')
        template = '<li class="suggestion-item">{VALUE}</li>';

      if(!targetLabel || !targetValue || !url || !value) {
        return;
      }

      targetLabel = $(targetLabel);
      targetValue = $(targetValue);

      var loadSuggestions = function(list, callback) {
        container.html('');

        list.forEach(function(item) {
          var label = '';
          //if there is a format, yay.
          if (format) {
            label = Handlebars.compile(format)(item);
          //otherwise best guess?
          } else {
            for (var key in item) {
              if(
                //if it is not a string
                typeof item[key] !== 'string'
                //it's a string but is like a number
                || !isNaN(parseFloat(item[key]))
                //it's a string and is not like a number
                // but the first character is like a number
                || !isNaN(parseFloat(item[key][0]))
              ) {
                continue;
              }

              label = item[key];
            }
          }

          //if still no label
          if(!label.length) {
            //just get the first one, i guess.
            for (var key in item) {
              label = item[key];
              break;
            }
          }

          item = { label: label, value: item[value] };
          var row = template.replace('{VALUE}', item.label);

          row = $(row).click(function() {
            callback(item);
            target.addClass('d-none');
          });

          container.append(row);
        });

        if(list.length) {
          target.removeClass('d-none');
        } else {
          target.addClass('d-none');
        }
      };

      targetLabel
        .keypress(function(e) {
          //if enter
          if(e.keyCode == 13 && prevent) {
            e.preventDefault();
          }
        })
        .keydown(function(e) {
          //if backspace
          if(e.keyCode == 8) {
            //undo the value
            targetValue.val('');
          }

          prevent = false;
          if(!target.hasClass('d-none')) {
            switch(e.keyCode) {
              case 40: //down
                var next = $('li.hover', target).removeClass('hover').index() + 1;

                if(next === $('li', target).length) {
                  next = 0;
                }

                $('li:eq('+next+')', target).addClass('hover');

                return;
              case 38: //up
                var prev = $('li.hover', target).removeClass('hover').index() - 1;

                if(prev < 0) {
                  prev = $('li', target).length - 1;
                }

                $('li:eq('+prev+')', target).addClass('hover');

                return;
              case 13: //enter
                if($('li.hover', target).length) {
                  $('li.hover', target)[0].click();
                  prevent = true;
                }
                return;
              case 37:
              case 39:
                return;
            }
          }

          if(searching) {
            return;
          }

          setTimeout(function() {
            if (targetLabel.val() == '') {
              return;
            }

            searching = true;
            $.ajax({
              url : url.replace('{QUERY}', targetLabel.val()),
              type : 'GET',
              success : function(response) {
                var list = [];

                if(typeof response.results !== 'undefined'
                  && typeof response.results.rows !== 'undefined'
                  && response.results.rows instanceof Array
                ) {
                  list = response.results.rows;
                }

                loadSuggestions(list, function(item) {
                  targetValue.val(item.value);
                  targetLabel.val(item.label).trigger('keyup');
                });

                searching = false;
              }, error : function() {
                searching = false;
              }
            });
          }, 1);
        });
    });
  });

  /**
   * Tag Field
   */
  $(window).on('tag-field-init', function(e, target) {
    target = $(target);

    //TEMPLATES
    var tagTemplate = '<div class="tag"><input type="text" class="tag-input'
    + ' text-field system-form-control" name="{NAME}[]" placeholder="Tag" value="" />'
    + '<a class="remove" href="javascript:void(0)"><i class="fa fa-times">'
    + '</i></a></div>';

    var addResize = function(filter) {
      var input = $('input[type=text]', filter);

      input.keyup(function() {
        var value = input.val() || input.attr('placeholder');

        var test = $('<span>').append(value).css({
          visibility: 'hidden',
          position: 'absolute',
          top: 0, left: 0
        }).appendTo(document.body);

        var width = test.width() + 10;

        $(this).width(Math.max(width, 40));
        test.remove();
      }).trigger('keyup');
    };

    var addRemove = function(filter) {
      $('a.remove', filter).click(function() {
        var val = $('input', filter).val();

        $(this).parent().remove();
      });
    };

    //INITITALIZERS
    var initTag = function(filter) {
      addRemove(filter);
      addResize(filter);

      $('input', filter).blur(function() {
        //if no value
        if(!$(this).val() || !$(this).val().length) {
          //remove it
          $(this).next().click();
        }

        var count = 0;
        var currentTagValue = $(this).val();
        $('div.tag input', target).each(function() {
          if(currentTagValue === $(this).val()) {
            count++;
          }
        });

        if(count > 1) {
          $(this).parent().remove();
        }
      });
    };

    //EVENTS
    target.click(function(e) {
      if($(e.target).hasClass('tag-field')) {
        var last = $('div.tag:last', this);

        if(!last.length || $('input', last).val()) {
          last = $(tagTemplate.replace('{NAME}', target.data('name')));
          target.append(last);

          initTag(last);
        }

        $('input', last).focus();
      }
    });

    //INITIALIZE
    $('div.tag', target).each(function() {
      initTag($(this));
    });
  });

  /**
   * Multiple Input
   */
  $(window).on('multiple-input-init', function (e, target) {
    target = $(target);

    var name = target.attr('data-name');
    var schema = target.attr('data-schema');

    var placeholder = target.attr('data-placeholder');

    //INITITALIZERS
    var initInput = function (filter) {
      $('a.remove', filter).click(function () {
        filter.remove();
      });
    };

    //append meta template
    $('button.input-add', target).click(function () {
      //TEMPLATES
      var template =
        '<div class="field-row input-group mb-3">'
      +    '<input '
      +      'autocomplete = "off" '
      +      'class="form-control text-capitalize {SUGGESTION_LABEL}" '
      +      'name = "_' + schema + '_ids[]" '
      +      'placeholder = "Enter ' + schema+'" '
      +      'type = "text" value = "" '
      +    '/> '
      +    '<div class="input-group-append"> '
      +      '<a class="input-group-text text-danger remove" href="javascript:void(0)"> '
      +        '<i class="fas fa-times"></i> '
      +      '</a> '
      +    '</div> '
      +    '<input class="{SUGGESTION_VALUE}" name="'+ name + '" type="hidden" value="" /> '
      +    '<div class="input-suggestion d-none" '
      +      'data-do="suggestion-field" '
      +      'data-format="\{\{' + schema + '_title\}\}" '
      +      'data-target-label="{LABEL}" '
      +      'data-target-value="{VALUE}" '
      +      'data-url="../' + schema + '/search?q={QUERY}&render=false" '
      +      'data-value="' + schema + '_id"> '
      +    '</div> '
      +  '</div>';

      var total = $('.input-suggestion', target).length;

      template = template
        .replace('{SUGGESTION_LABEL}', 'suggestion-label-' + schema + '_' + total)
        .replace('{SUGGESTION_VALUE}', 'suggestion-value-' + schema + '_' + total)
        .replace('{LABEL}', 'input.suggestion-label-' + schema + '_' + total)
        .replace('{VALUE}', 'input.suggestion-value-' + schema + '_' + total)

      $(this).before(template);
      var item = $(this).prev();

      initInput(item);

      item.doon();
      return false;
    });
  });

  /**
   * Texts Field
   */
  $(window).on('textlist-field-init', function (e, target) {
      target = $(target);

      var name = target.attr('data-name');
      var placeholder = target.attr('data-placeholder');

      //TEMPLATES
      var template ='<div class="field-row input-group mb-3">'
          + '<div class="input-group-prepend">'
          + '<a class="input-group-text text-secondary move-up" href="javascript:void(0)">'
          + '<i class="fas fa-arrow-up"></i></a></div><div class="input-group-prepend">'
          + '<a class="input-group-text text-secondary move-down" href="javascript:void(0)">'
          + '<i class="fas fa-arrow-down"></i></a></div>'
          + '<input class="text-field form-control system-form-control" type="text" name="'
          + name + '[]" value="" /><div class="input-group-append">'
          + '<a class="input-group-text text-danger remove" '
          + 'href="javascript:void(0)">'
          + '<i class="fas fa-times"></i></a></div></div>';

      //INITITALIZERS
      var initTag = function (filter) {
          $('a.remove', filter).click(function () {
              filter.remove();
          });

          $('a.move-up', filter).click(function () {
              var prev = filter.prev();

              if (prev.length && prev.hasClass('field-row')) {
                  prev.before(filter);
              }
          });

          $('a.move-down', filter).click(function () {
              var next = filter.next();

              if (next.length && next.hasClass('field-row')) {
                  next.after(filter);
              }
          });
      };

      //append meta template
      $('a.field-add', target).click(function () {
          var key = $('div.field-row', target).length;
          $(this).before(template);
          var item = $(this).prev();

          if (placeholder) {
              $('input.text-field', item).attr('placeholder', placeholder);
          }

          initTag(item);

          return false;
      });

      //INITIALIZE
      $('div.field-row', target).each(function () {
          initTag($(this));
      });
  });

  /**
   * Textareas Field
   */
  $(window).on('textarealist-field-init', function (e, target) {
      target = $(target);

      var name = target.attr('data-name');
      var rows = target.attr('data-rows');
      var placeholder = target.attr('data-placeholder');

      //TEMPLATES
      var template ='<div class="field-row input-group mb-3">'
          + '<div class="input-group-prepend">'
          + '<a class="input-group-text text-success move-up" href="javascript:void(0)">'
          + '<i class="fas fa-arrow-up"></i></a></div><div class="input-group-prepend">'
          + '<a class="input-group-text text-orange move-down" href="javascript:void(0)">'
          + '<i class="fas fa-arrow-down"></i></a></div>'
          + '<textarea class="text-field form-control system-form-control" name="'
          + name + '[]"></textarea><div class="input-group-append">'
          + '<a class="input-group-text text-danger remove" '
          + 'href="javascript:void(0)">'
          + '<i class="fas fa-times"></i></a></div></div>';

      //INITITALIZERS
      var initTag = function (filter) {
          $('a.remove', filter).click(function () {
              filter.remove();
          });

          $('a.move-up', filter).click(function () {
              var prev = filter.prev();

              if (prev.length && prev.hasClass('field-row')) {
                  prev.before(filter);
              }
          });

          $('a.move-down', filter).click(function () {
              var next = filter.next();

              if (next.length && next.hasClass('field-row')) {
                  next.after(filter);
              }
          });
      };

      //append meta template
      $('a.field-add', target).click(function () {
          var key = $('div.field-row', target).length;
          $(this).before(template);
          var item = $(this).prev();

          if (placeholder) {
              $('textarea.text-field', item).attr('placeholder', placeholder);
          }

          if (rows) {
              $('textarea.text-field', item).attr('rows', rows);
          }

          initTag(item);

          return false;
      });

      //INITIALIZE
      $('div.field-row', target).each(function () {
          initTag($(this));
      });
  });

  /**
   * WYSIWYGs Field
   */
  $(window).on('wysiwyglist-field-init', function (e, target) {
      target = $(target);

      var name = target.attr('data-name');
      var rows = target.attr('data-rows');
      var placeholder = target.attr('data-placeholder');

      //TEMPLATES
      var template = '<div class="field-row mb-3">'
          + '<div class="btn-group mb-2"><a class="btn btn-danger remove" '
          + 'href="javascript:void(0)">'
          + '<i class="fas fa-times"></i></a>'
          + '<a class="btn btn-success move-up" href="javascript:void(0)">'
          + '<i class="fas fa-arrow-up"></i></a>'
          + '<a class="btn btn-orange move-down" href="javascript:void(0)">'
          + '<i class="fas fa-arrow-down"></i></a></div>'
          + '<textarea data-do="wysiwyg" class="text-field form-control system-form-control" name="'
          + name + '[]"></textarea></div>';

      //INITITALIZERS
      var initTag = function (filter) {
          $('a.remove', filter).click(function () {
              console.log(filter[0])
              filter.remove();
          });

          $('a.move-up', filter).click(function () {
              var prev = filter.prev();

              if (prev.length && prev.hasClass('field-row')) {
                  var value1 = $('textarea', filter).data('editor').getValue();
                  var value2 = $('textarea', prev).data('editor').getValue();

                  $('textarea', prev).data('editor').setValue(value1);
                  $('textarea', filter).data('editor').setValue(value2);
              }
          });

          $('a.move-down', filter).click(function () {
              var next = filter.next();

              if (next.length && next.hasClass('field-row')) {
                  var value1 = $('textarea', filter).data('editor').getValue();
                  var value2 = $('textarea', next).data('editor').getValue();

                  $('textarea', next).data('editor').setValue(value1);
                  $('textarea', filter).data('editor').setValue(value2);
              }
          });
      };

      //append meta template
      $('a.field-add', target).click(function () {
          var key = $('div.field-row', target).length;
          $(this).before(template);
          var item = $(this).prev();

          if (placeholder) {
              $('textarea.text-field', item).attr('placeholder', placeholder);
          }

          if (rows) {
              $('textarea.text-field', item).attr('rows', rows);
          }

          initTag(item);

          item.doon();

          return false;
      });

      //INITIALIZE
      $('div.field-row', target).each(function () {
          initTag($(this));
      });
  });

  /**
   * Meta Field
   */
  $(window).on('meta-field-init', function(e, target) {
    target = $(target);

    var placeholderKey = target.data('placeholder-key') || 'Key';
    var placeholderValue = target.data('placeholder-value') || 'Value';

    //TEMPLATES
    var template = '<div class="field-row input-group mb-3">'
      + '<input class="meta-input key form-control" type="text" />'
      + '<textarea class="meta-input value form-control" rows="1"></textarea>'
      + '<input type="hidden" name="" value=""/>'
      + '<div class="input-group-append">'
      + '<a class="input-group-text text-danger remove" '
      + 'href="javascript:void(0)">'
      + '<i class="fas fa-times"></i></a></div></div>';

      if (target.data('field') === 'input') {
        template = '<div class="field-row input-group mb-3">'
          + '<input class="meta-input key form-control" type="text" placeholder="Key" />'
          + '<input class="meta-input value form-control" placeholder="Value" />'
          + '<input type="hidden" name="" value=""/>'
          + '<div class="input-group-append">'
          + '<a class="input-group-text text-danger remove" '
          + 'href="javascript:void(0)">'
          + '<i class="fas fa-times"></i></a></div></div>';
      }

    //INITITALIZERS
    var initTag = function(filter) {
      var hidden = filter.find('input[type="hidden"]')

      $('a.remove', filter).click(function() {
        filter.remove();
      });

      $('.meta-input.key', filter).blur(function() {
        //if no value
        if(!$(this).val() || !$(this).val().length) {
          hidden.attr('name', '');
          return;
        }

        hidden.attr('name', $(target).data('name') + '[' + $(this).val() +']');
      });

      $('.meta-input.value', filter).blur(function() {
        //if no value
        if(!$(this).val() || !$(this).val().length) {
          hidden.attr('value', '');
          return;
        }

        hidden.attr('value', $(this).val());
      });
    };

    //append meta template
    $('a.field-add', target).click(function() {
      var key = $('div.field-row', target).length;
      $(this).before(template);
      var item = $(this).prev();

      $('.meta-input.key', item).attr('placeholder', placeholderKey);
      $('.meta-input.value', item).attr('placeholder', placeholderValue);

      initTag(item);

      return false;
    });

    //INITIALIZE
    $('div.field-row', target).each(function() {
      initTag($(this));
    });
  });

  /**
   * Table Field
   */
  $(window).on('table-field-init', function(e, target) {
    target = $(target);

    //attributes
    var name = target.data('name');
    var columns = target.data('columns') || '';
    columns = columns.split('|');

    //TEMPLATES
    var template ='<tr><td><a class="btn btn-danger '
      + 'remove" href="javascript:void(0)"><i class="fas fa-times">'
      + '</i></a></td></tr>';

    var templateRow = '<td><input class="input-column '
      + 'form-control" type="text" /></td>';

    //INITITALIZERS
    var init = function(row) {
      $('a.remove', row).click(function() {
        row.remove();

        $('tbody tr', target).each(function(index) {
          $(this)
            .data('index', index)
            .attr('data-index', index);

          $('input', this).attr(
            'name',
            name + '[' + index + '][]'
          );
        });
      });
    };

    //append meta template
    $('a.field-add', target).click(function() {
      var index = $('tbody tr', target).length;
      var row = $(template)
        .data('index', index)
        .attr('data-index', index);

      columns.forEach(function(label) {
        var column = $(templateRow);

        $('input', column)
          .attr(
            'name',
            name + '[' + index + '][]'
          )
          .attr(
            'placeholder',
            label
          );

        row.append(column);
      });

      $('tbody', target).append(row);

      init(row);

      return false;
    });

    //INITIALIZE
    $('tbody tr', target).each(function() {
      init($(this));
    });
  });

  /**
   * File Field
   * HTML config for single files
   * data-do="file-field"
   * data-name="post_files"
   *
   * HTML config for multiple files
   * data-do="file-field"
   * data-name="post_files"
   * data-multiple="1"
   */
  $(window).on('file-field-init', function (e, target) {
      var onAcquire = function (extensions) {
          var template = {
              previewFile:
                  '<div class="file-field-preview-container">'
                  + '<i class="fas fa-file text-info"></i>'
                  + '<span class="file-field-extension">{EXTENSION}</span>'
                  + '</div>',
              previewImage:
                  '<div class="file-field-preview-container">'
                  + '<img src="{DATA}" width="50" />'
                  + '</div>',
              actions:
                  '<td class="file-field-actions">'
                      + '<a class="text-info file-field-move-up" href="javascript:void(0)">'
                          + '<i class="fas fa-arrow-up"></i>'
                      + '</a>'
                      + '&nbsp;&nbsp;&nbsp;'
                      + '<a class="text-info file-field-move-down" href="javascript:void(0)">'
                          + '<i class="fas fa-arrow-down"></i>'
                      + '</a>'
                      + '&nbsp;&nbsp;&nbsp;'
                      + '<a class="btn btn-danger file-field-remove" href="javascript:void(0)">'
                          + '<i class="fas fa-times"></i>'
                      + '</a>'
                  + '</td>',
              row:
                  '<tr class="file-field-item">'
                  + '<td class="file-field-preview">{PREVIEW}</td>'
                  + '<td class="file-field-name">'
                      + '{FILENAME}'
                      + '<input class="system-form-control system-file-input form-control" name="{NAME}" type="hidden" value="{DATA}" placeholder="eg. http://website.com/image.jpg" required />'
                  + '</td>'
                  + '{ACTIONS}'
                  + '</tr>'
          };

          //current
          var container = $(target);
          var body = $('tbody', container);
          var foot = $('tfoot', container);

          var noresults = $('tr.file-field-none', body);

          //get meta data

          //for hidden fields
          var name = container.attr('data-name');

          //for file field
          var multiple = container.attr('data-multiple');

          if (
            typeof container.attr('data-img-only') &&
            container.attr('data-img-only') !== false
            ) {
            var imgOnly = container.attr('data-img-only');
          }

          var accept = container.attr('data-accept') || false;
          var classes = container.attr('data-class');
          var width = parseInt(container.attr('data-width') || 0);
          var height = parseInt(container.attr('data-height') || 0);

          //make a file
          var file = $('<input type="file" />').hide();

          if (multiple) {
              file.attr('multiple', 'multiple');
          }

          if (accept) {
              file.attr('accept', accept);
          }

          foot.append(file);

          $('button.file-field-upload', container).click(function (e) {
              file.click();
          });

          $('button.file-field-link', container).click(function (e) {
              var path = name + '[]';
              var actions = template.actions;

              if (!multiple) {
                  $('tr', body).each(function () {
                      if ($(this).hasClass('file-field-none')) {
                          return;
                      }

                      $(this).remove();
                  });

                  actions = '';
                  path = name;
              }

              noresults.hide();

              if (imgOnly) {
                var preview = template.previewFile.replace('{EXTENSION}', 'Not a Valid Image');
              } else {
                var preview = template.previewFile.replace('{EXTENSION}', 'Invalid File');
              }

              var row = $(
                  template.row
                      .replace('{PREVIEW}', preview)
                      .replace('{FILENAME}', '')
                      .replace('{NAME}', path)
                      .replace('{DATA}', '')
                      .replace('{ACTIONS}', actions)
              ).appendTo(body);

              listen(row, body);

              $('input.system-file-input', row)
                  .attr('type', 'url')
                  .blur(function () {
                      var url = $(this).val();
                      if (imgOnly) {
                        var extension = 'Not a Valid Image';
                      } else {
                        var extension = 'Invalid File';
                      }
                      if (url.indexOf('.') !== -1) {
                          extension = url.split('.').pop();
                      }

                      preview = template.previewFile.replace('{EXTENSION}', extension);

                      //if it's an image
                      if (
                          [
                              'jpg',
                              'jpeg',
                              'pjpeg',
                              'svg',
                              'png',
                              'ico',
                              'gif'
                          ].indexOf(extension) !== -1
                      ) {
                          preview = template.previewImage.replace('{DATA}', url);
                      } else if (imgOnly) {
                        preview = template.previewFile.replace('{EXTENSION}', 'Not a Valid Image');
                      }

                      $('td.file-field-preview', row).html(preview);
                  });
          });

          var listen = function (row, body) {
              $('a.file-field-remove', row).click(function () {
                  row.remove();
                  if ($('tr', body).length < 2) {
                      noresults.show();
                  }
              });

              $('a.file-field-move-up', row).click(function () {
                  var prev = row.prev();

                  if (prev.length && !prev.hasClass('file-field-none')) {
                      prev.before(row);
                  }
              });

              $('a.file-field-move-down', row).click(function () {
                  var next = row.next();

                  if (next.length) {
                      next.after(row);
                  }
              });
          };

          var generate = function (file, name, width, height, row) {
              var reader = new FileReader();
              reader.readAsDataURL(file);
              reader.onload = function () {
                  var extension = file.name.split('.').pop();

                  if (file.name.indexOf('.') === -1) {
                      extension = 'unknown';
                  }

                  if (imgOnly) {
                    var preview = template.previewFile.replace('{EXTENSION}', 'Not a Valid Image');
                  } else {
                    var preview = template.previewFile.replace('{EXTENSION}', extension);
                  }

                  if (file.type.indexOf('image/') === 0) {
                      preview = template.previewImage.replace('{DATA}', reader.result);
                  }

                  noresults.hide();

                  row = $(
                      row
                          .replace('{NAME}', name)
                          .replace('{DATA}', reader.result)
                          .replace('{PREVIEW}', preview)
                          .replace('{FILENAME}', file.name)
                  ).appendTo(body);

                  listen(row, body);

                  if (file.type.indexOf('image/') === 0 && (width !== 0 || height !== 0)) {
                      //so we can crop
                      $.cropper(file, width, height, function (data) {
                          $('div.file-field-preview-container img', row).attr('src', data);
                          $('input[type="hidden"]', row).val(data);
                      });
                  }

                  //add mime type
                  if (typeof extensions[file.type] !== 'string') {
                      extensions[file.type] = extension;
                  }
              };
          };

          file.change(function () {
              if (!this.files || !this.files[0]) {
                  return;
              }

              if (!multiple) {
                  $('tr', body).each(function () {
                      if ($(this).hasClass('file-field-none')) {
                          return;
                      }

                      $(this).remove();
                  })
              }

              for (var row, path = '', i = 0; i < this.files.length; i++, path = '') {
                  row = template.row.replace('{ACTIONS}', '');
                  if (multiple) {
                      path = '[]' + path;
                      row = template.row.replace('{ACTIONS}', template.actions);
                  }

                  path = name + path;
                  generate(this.files[i], path, width, height, row);
              }
          });

          $('tr', body).each(function () {
              if ($(this).hasClass('file-field-none')) {
                  return;
              }

              listen($(this), body)
          });
      };

      $.require([
          'cdn/json/extensions.json',
          'components/yarn-cropper/cropper.min.js'
      ], onAcquire);
  });

  /**
   * Direct CDN Upload
   */
  $(window).on('wysiwyg-init', function(e, target) {
    var template = '<div class="wysiwyg-toolbar position-relative" style="display: none;">'
      + '<div class="btn-group">'
        + '<a class="btn btn-default" data-wysihtml-command="bold" title="CTRL+B"><i class="fas fa-bold"></i></a>'
        + '<a class="btn btn-default" data-wysihtml-command="italic" title="CTRL+I"><i class="fas fa-italic"></i></a>'
        + '<a class="btn btn-default" data-wysihtml-command="underline" title="CTRL+U"><i class="fas fa-underline"></i></a>'
        + '<a class="btn btn-default" data-wysihtml-command="strike" title="CTRL+U"><i class="fas fa-strikethrough"></i></a>'
      + '</div> '
      + '<div class="btn-group">'
        + '<a class="btn btn-info" data-wysihtml-command="createLink"><i class="fas fa-external-link-alt"></i></a>'
        + '<a class="btn btn-danger" data-wysihtml-command="removeLink"><i class="fas fa-ban"></i></a>'
      + '</div> '
      + '<a class="btn btn-purple" data-wysihtml-command="insertImage"><i class="fas fa-image"></i></a> '
      + '<div class="dropdown d-inline-block">'
        + '<button aria-haspopup="true" aria-expanded="false" class="btn btn-grey" data-toggle="dropdown" type="button">Headers <i class="fas fa-chevron-down"></i></button>'
        + '<div class="dropdown-menu">'
          + '<a class="dropdown-item" data-wysihtml-command="formatBlock" data-wysihtml-command-blank-value="true">Normal</a>'
          + '<a class="dropdown-item" data-wysihtml-command="formatBlock" data-wysihtml-command-value="h1">Header 1</a>'
          + '<a class="dropdown-item" data-wysihtml-command="formatBlock" data-wysihtml-command-value="h2">Header 2</a>'
          + '<a class="dropdown-item" data-wysihtml-command="formatBlock" data-wysihtml-command-value="h3">Header 3</a>'
          + '<a class="dropdown-item" data-wysihtml-command="formatBlock" data-wysihtml-command-value="h4">Header 4</a>'
          + '<a class="dropdown-item" data-wysihtml-command="formatBlock" data-wysihtml-command-value="h5">Header 5</a>'
          + '<a class="dropdown-item" data-wysihtml-command="formatBlock" data-wysihtml-command-value="h6">Header 6</a>'
        + '</div>'
      + '</div> '
      + '<div class="dropdown d-inline-block">'
        + '<button aria-haspopup="true" aria-expanded="false" class="btn btn-pink" data-toggle="dropdown" type="button">Colors <i class="fas fa-chevron-down"></i></button>'
        + '<div class="dropdown-menu">'
          + '<a class="dropdown-item text-danger" data-wysihtml-command="foreColor" data-wysihtml-command-value="red"><i class="fas fa-square-full"></i> Red</a>'
          + '<a class="dropdown-item text-success" data-wysihtml-command="foreColor" data-wysihtml-command-value="green"><i class="fas fa-square-full"></i> Green</a>'
          + '<a class="dropdown-item text-primary" data-wysihtml-command="foreColor" data-wysihtml-command-value="blue"><i class="fas fa-square-full"></i> Blue</a>'
          + '<a class="dropdown-item text-purple" data-wysihtml-command="foreColor" data-wysihtml-command-value="purple"><i class="fas fa-square-full"></i> Purple</a>'
          + '<a class="dropdown-item text-warning" data-wysihtml-command="foreColor" data-wysihtml-command-value="orange"><i class="fas fa-square-full"></i> Orange</a>'
          + '<a class="dropdown-item text-yellow" data-wysihtml-command="foreColor" data-wysihtml-command-value="yellow"><i class="fas fa-square-full"></i> Yellow</a>'
          + '<a class="dropdown-item text-pink" data-wysihtml-command="foreColor" data-wysihtml-command-value="pink"><i class="fas fa-square-full"></i> Pink</a>'
          + '<a class="dropdown-item text-white" data-wysihtml-command="foreColor" data-wysihtml-command-value="white"><i class="fas fa-square-full"></i> White</a>'
          + '<a class="dropdown-item text-inverse" data-wysihtml-command="foreColor" data-wysihtml-command-value="black"><i class="fas fa-square-full"></i> Black</a>'
        + '</div>'
      + '</div> '
      + '<div class="btn-group">'
        + '<a class="btn btn-default" data-wysihtml-command="insertUnorderedList"><i class="fas fa-list-ul"></i></a>'
        + '<a class="btn btn-default" data-wysihtml-command="insertOrderedList"><i class="fas fa-list-ol"></i></a>'
      + '</div> '
      + '<div class="btn-group">'
        + '<a class="btn btn-light" data-wysihtml-command="undo"><i class="fas fa-undo"></i></a><a class="btn btn-light" data-wysihtml-command="redo"><i class="fas fa-redo"></i></a>'
      + '</div> '
      + '<a class="btn btn-light" data-wysihtml-command="insertSpeech"><i class="fas fa-comments"></i></a> '
      + '<a class="btn btn-inverse" data-wysihtml-action="change_view"><i class="fas fa-code"></i></a> '
      + '<div class="wysiwyg-dialog" data-wysihtml-dialog="createLink" style="display: none;">'
        + '<input class="form-control" data-wysihtml-dialog-field="href" placeholder="http://" />'
        + '<input class="form-control mb-2" data-wysihtml-dialog-field="title" placeholder="Title" />'
        + '<a class="btn btn-primary" data-wysihtml-dialog-action="save" href="javascript:void(0)">OK</a>'
        + '<a class="btn btn-danger" data-wysihtml-dialog-action="cancel" href="javascript:void(0)">Cancel</a>'
      + '</div>'
      + '<div class="wysiwyg-dialog" data-wysihtml-dialog="insertImage" style="display: none;">'
        + '<input class="form-control" data-wysihtml-dialog-field="src" placeholder="http://">'
        + '<input class="form-control" data-wysihtml-dialog-field="alt" placeholder="alt">'
        + '<select class="form-control mb-2" data-wysihtml-dialog-field="className">'
          + '<option value="">None</option>'
          + '<option value="float-left">Left</option>'
          + '<option value="float-right">Right</option>'
        + '</select>'
        + '<a class="btn btn-primary" data-wysihtml-dialog-action="save" href="javascript:void(0)">OK</a>'
        + '<a class="btn btn-danger" data-wysihtml-dialog-action="cancel" href="javascript:void(0)">Cancel</a>'
      + '</div>'
    + '</div>';

    $.require.load(
      [
        'components/wysihtml/dist/minified/wysihtml.min.js',
        'components/wysihtml/dist/minified/wysihtml.all-commands.min.js',
        'components/wysihtml/dist/minified/wysihtml.table_editing.min.js',
        'components/wysihtml/dist/minified/wysihtml.toolbar.min.js',
        'components/wysihtml/parser_rules/advanced_unwrap.js'
      ],
      function() {
        var toolbar = $(template);
        $(target).before(toolbar);

        var e = new wysihtml.Editor(target, {
          toolbar:    toolbar[0],
          parserRules:  wysihtmlParserRules,
          stylesheets:  '/styles/admin.css'
        });
      }
    );
  });

  /**
   * Code Editor - Ace
   */
  $(window).on('code-editor-init', function(e, target) {
    $.require.load(
      'components/ace-editor-builds/src/ace.js',
      function() {
        target = $(target);

        var mode = target.attr('data-mode');
        var width = target.attr('data-height') || 0;
        var height = target.attr('data-height') || 500;

        var container = $('<section>')
          .addClass('form-control')
          .addClass('code-editor-container');

        if(width) {
          container.width(width);
        }

        if(height) {
          container.height(height);
        }

        target.after(container);

        var editor = ace.edit(container[0]);

        if(mode) {
          // set mode
          editor.getSession().setMode('ace/mode/' + mode);
        }

        // set editor default value
        editor.setValue(target.val());

        target.closest('form').submit(function() {
          target.val(editor.getValue());
        });
      }
    );
  });

  /**
   * Markdown Editor -
   */
  $(window).on('markdown-editor-init', function(e, target) {
    $.require.load(
      [
        'components/bootstrap-markdown-editor-4/dist/css/bootstrap-markdown-editor.min.css',
        'components/ace-editor-builds/src/ace.js',
        'components/bootstrap-markdown-editor-4/dist/js/bootstrap-markdown-editor.min.js'
      ],
      function() {
        target = $(target);

        var width = target.attr('data-height') || 0;
        var height = target.attr('data-height') || 500;

        if(width) {
          target.width(width);
        }

        if(height) {
          target.height(height);
        }

        target.markdownEditor();
      }
    );
  });

  /**
   * Generate Slug
   */
  $(window).on('slugger-init', function(e, target) {
    var source = $(target).attr('data-source');

    if(!source || !source.length) {
      return;
    }

    var upper = $(target).attr('data-upper');
    var space = $(target).attr('data-space') || '-';

    $(source).keyup(function() {
      var slug = $(this)
        .val()
        .toString()
        .toLowerCase()
        .replace(/\s+/g, '-')       // Replace spaces with -
        .replace(/[^\w\-]+/g, '')     // Remove all non-word chars
        .replace(/\-\-+/g, '-')     // Replace multiple - with single -
        .replace(/^-+/, '')       // Trim - from start of text
        .replace(/-+$/, '');

      if (upper != 0) {
        slug = slug.replace(
          /(^([a-zA-Z\p{M}]))|([ -][a-zA-Z\p{M}])/g,
          function(s) {
            return s.toUpperCase();
          }
        );
      }

      slug = slug.replace(/\-/g, space);

      $(target).val(slug);
    });
  });

  /**
   * Mask
   */
  $(window).on('mask-field-init', function(e, target) {
    $.require(
      'components/inputmask/dist/min/jquery.inputmask.bundle.min.js',
      function() {
        var format = $(target).attr('data-format');
        $(target).inputmask(format);
      }
    );
  });

  /**
   * Mask
   */
  $(window).on('knob-field-init', function(e, target) {
    $.require(
      'components/jquery-knob/dist/jquery.knob.min.js',
      function() {
        $(target).knob();
      }
    );
  });

  /**
   * Select
   */
  $(window).on('select-field-init', function(e, target) {
    $.require(
      [
        'components/select2/dist/css/select2.min.css',
        'components/select2/dist/js/select2.full.min.js'
      ],
      function() {
        const value = $(target).data('value');
        $('option', target).each(function() {
          if ($(this).val() === value) {
            $(this).attr('selected', 'selected');
            $(this).prop('selected', true);
          }
        });
        $(target).select2();
      }
    );
  });

  $(window).on('select-tree-field-init', function(e, target) {
    const displayTpl = '<div class="select-tree-display"><input class="select-tree-display-value" name="{NAME}" type="hidden" value="{VALUE}" /><label class="select-tree-display-label"><span>{LABEL}</span>{INDICATOR}</label></div>';

    const dropdownTpl = '<div class="select-tree-dropdown d-none"></div>';

    const backTpl = '<li class="select-tree-dropdown-back"><button type="button"><i class="fas fa-chevron-left"></i></button> {LABEL}</li>';

    const itemTpl = '<li data-value="{VALUE}"><a class="text-primary select-tree-dropdown-option" href="javascript:void(0)">{LABEL}</a></li>';

    const forwardTpl = '<button class="select-tree-dropdown-forward" type="button"><i class="fas fa-chevron-right"></i></button>';

    const defaultIndiicator = '<i class="fas fa-chevron-down ml-2"></i>';
    const removeIndiicator = '<i class="fas fa-times text-danger ml-2 remove"></i>';

    //provide methods to populate
    target = $(target);

    const name = target.data('name') || '';
    const label = target.data('label') || '';
    const value = target.data('value') || '';
    const remove = target.data('remove') || '';
    let indicator = defaultIndiicator;

    if (remove) {
      indicator = removeIndiicator;
    }

    const display = $(displayTpl
      .replace('{NAME}', name)
      .replace('{LABEL}', label)
      .replace('{VALUE}', value)
      .replace('{INDICATOR}', indicator)
    ).appendTo(target);

    display.click(function() {
      $('div.select-tree-dropdown').not(dropdown).addClass('d-none');
      dropdown.toggleClass('d-none');
    });

    const dropdown = $(dropdownTpl).appendTo(target);

    const fieldUI = {
      events: { forward: [], back: [], select: [], remove: [] },
      getDisplay() {
        return display;
      },
      getDropdown() {
        return dropdown;
      },
      goBack() {
        dropdown.animate({
          scrollLeft: dropdown.width() * Math.max(dropdown.children().length - 2, 0)
        }, 500, function() {
          dropdown.children().last().remove();
        });
        return this;
      },
      goForward() {
        dropdown.animate({
          scrollLeft: dropdown.width() * dropdown.children().length
        }, 500);
        return this;
      },
      remove() {
        target.remove();
        fieldUI.events.remove.forEach(function(callback) {
          callback();
        });
      },
      onBack(callback) {
        fieldUI.events.back.push(callback);
        return this;
      },
      onForward(callback) {
        fieldUI.events.forward.push(callback);
        return this;
      },
      onSelect(callback) {
        fieldUI.events.select.push(callback);
        return this;
      },
      onRemove(callback) {
        fieldUI.events.remove.push(callback);
        return this;
      },
      screen() {
        const screen = $('<ul>');
        return {
          addBack(label) {
            const back = $(backTpl.replace('{LABEL}', label));
            screen.prepend(back);
            back.click(function() {
              fieldUI.goBack();
              fieldUI.events.back.forEach(function(callback) {
                callback(label);
              });
            });
            return back;
          },
          addOption(value, label, next = false) {
            const option = $(itemTpl
              .replace('{VALUE}', value)
              .replace('{LABEL}', label)
            );

            screen.append(option);
            $('a.select-tree-dropdown-option', option).click(() => {
              fieldUI.select(value, label);
            });

            if (next) {
              option.append(forwardTpl);
              $('button.select-tree-dropdown-forward', option).click(function() {
                fieldUI.events.forward.forEach(function(callback) {
                  callback(value, label);
                });
              });
            }

            return option;
          },
          append() {
            screen.appendTo(dropdown);
            return this;
          },
          getScreen() {
            return screen;
          }
        };
      },
      select(value, label) {
        $('input.select-tree-display-value', display).val(value);
        $('label.select-tree-display-label span', display).text(label);
        dropdown.addClass('d-none');
        this.events.select.forEach(function(callback) {
          callback(value, label);
        });
      }
    };

    if (remove) {
      $('.remove', display).click(() => {
        fieldUI.remove();
      });
    }

    target.data('select-tree', fieldUI);
  });

  $(window).on('select-multi-tree-field-init', function(e, target) {
    const fieldTpl = '<div class="select-tree-field" data-do="select-tree-field" data-remove="true" data-name="{NAME}" data-label="{LABEL}" data-value="{VALUE}"></div>';

    target = $(target);
    const name = target.data('name') || '';
    const label = target.data('label') || '';
    const value = target.data('value') || '';

    const add = $('button.select-multi-tree-add', target);
    const container = $('div.select-multi-tree-fields', target);

    add.click(function() {
      const field = $(fieldTpl
        .replace('{NAME}', name)
        .replace('{LABEL}', label)
        .replace('{VALUE}', value)
      );

      container.append(field)
      field.doon();
    });
  });

  /**
   * Countries Dropdown
   */
  $(window).on('country-dropdown-init', function(e, target) {
    $.require(
      [
        'cdn/json/countries.json',
        'components/select2/dist/css/select2.min.css',
        'components/select2/dist/js/select2.full.min.js'
      ],
      function(countries) {
        const value = $(target).data('value');
        //populate
        countries.forEach(function(country) {
          const option = $('<option>')
            .attr('value', country.abbreviation)
            .text(country.country)
            .appendTo(target);

          if (country.abbreviation === value) {
            option.attr('selected', 'selected');
            option.prop('selected', true);
          }
        });

        if ($(target).data('select2') !== 'false'
          && $(target).data('select2') !== false
        ) {
          $(target).select2();
        }
      }
    );
  });

  /**
   * Currency Dropdown
   */
  $(window).on('currency-dropdown-init', function(e, target) {
    $.require(
      [
        'cdn/json/currencies.json',
        'components/select2/dist/css/select2.min.css',
        'components/select2/dist/js/select2.full.min.js'
      ],
      function(currencies) {
        const value = $(target).data('value');
        //populate
        Object.keys(currencies).forEach(function(abbr) {
          const name = currencies[abbr].name
          const symbol = currencies[abbr].symbol_native

          const option = $('<option>')
            .attr('value', abbr)
            .text(`${name} ( ${symbol} )`)
            .appendTo(target);

          if (abbr === value) {
            option.attr('selected', 'selected');
            option.prop('selected', true);
          }
        });

        if ($(target).data('select2') !== 'false'
          && $(target).data('select2') !== false
        ) {
          $(target).select2();
        }
      }
    );
  });

  /**
   * Accounting Dropdown
   */
  $(window).on('accounting-dropdown-init', function(e, target) {
    $.require(
      [
        'cdn/json/accounting.json',
        'components/select2/dist/css/select2.min.css',
        'components/select2/dist/js/select2.full.min.js'
      ],
      function(categories) {
        const value = $(target).data('value');
        //populate
        Object.keys(categories).forEach(function(category) {
          const group = $('<optgroup>').attr('label', category).appendTo(target);
          Object.keys(categories[category]).forEach(function(activity) {
            const label = categories[category][activity];

            const option = $('<option>')
              .attr('value', activity)
              .text(label)
              .appendTo(group);

            if (activity === value) {
              option.attr('selected', 'selected');
              option.prop('selected', true);
            }
          });
        });

        if ($(target).data('select2') !== 'false'
          && $(target).data('select2') !== false
        ) {
          $(target).select2();
        }
      }
    );
  });

  /**
   * Multirange
   */
  $(window).on('multirange-field-init', function(e, target) {
    var onAcquire = function() {
      target = $(target);

      var params = {};
      // loop all attributes
      $.each(target[0].attributes,function(index, attr) {
        // skip if data do and on
        if (attr.name == 'data-do' || attr.name == 'data-on') {
          return true;
        }

        // look for attr with data- as prefix
        if (attr.name.search(/data-/g) > -1) {
          // get parameter name
          var key = attr.name
            .replace('data-', '')
            .replace('-', '_');

          // prepare parameter
          params[key] = attr.value;

          // if value is boolean
          if(attr.value == 'true') {
            params[key] = attr.value == 'true' ? true : false;
          }
        }
      });

      target.ionRangeSlider(params);
    };

    $.require(
      [
        'components/ion-rangeSlider/css/ion.rangeSlider.css',
        'components/ion-rangeSlider/css/ion.rangeSlider.skinFlat.css',
        'components/ion-rangeSlider/js/ion.rangeSlider.min.js'
      ],
      onAcquire
    );
  });

  /**
   * Date Field
   */
  $(window).on('date-field-init', function(e, target) {
    $.require(
      [
        'components/flatpickr/dist/flatpickr.min.css',
        'components/flatpickr/dist/flatpickr.min.js'
      ],
      function() {
        $(target).flatpickr({
          dateFormat: "Y-m-d",
        });
      }
    );
  });

  /**
   * Time Field
   */
  $(window).on('time-field-init', function(e, target) {
    $.require(
      [
        'components/flatpickr/dist/flatpickr.min.css',
        'components/flatpickr/dist/flatpickr.min.js'
      ],
      function() {
        $(target).flatpickr({
          enableTime: true,
          noCalendar: true,
          dateFormat: "H:i",
        });
      }
    );
  });

  /**
   * DateTime Field
   */
  $(window).on('datetime-field-init', function(e, target) {
    $.require(
      [
        'components/flatpickr/dist/flatpickr.min.css',
        'components/flatpickr/dist/flatpickr.min.js'
      ],
      function() {
        $(target).flatpickr({
          enableTime: true,
          dateFormat: "Y-m-d H:i",
        });
      }
    );
  });

  /**
   * Date Range Field
   */
  $(window).on('date-range-field-init', function(e, target) {
    $.require(
      [
        'components/flatpickr/dist/flatpickr.min.css',
        'components/flatpickr/dist/flatpickr.min.js'
      ],
      function() {
        $(target).flatpickr({
          mode: "range",
          dateFormat: "Y-m-d",
        });
      }
    );
  });

  /**
   * DateTime Range Field
   */
  $(window).on('datetime-range-field-init', function(e, target) {
    $.require(
      [
        'components/flatpickr/dist/flatpickr.min.css',
        'components/flatpickr/dist/flatpickr.min.js'
      ],
      function() {
        $(target).flatpickr({
          mode: "range",
          enableTime: true,
          dateFormat: "Y-m-d H:i",
        });
      }
    );
  });

  /**
   * Datetime range splitter
   */
  $(window).on('datetime-range-splitter-init', function(e, target) {
    const display = $('input.datetime-display', target);
    const start = $('input.datetime-start', target);
    const end = $('input.datetime-end', target);

    function updateDates() {
      const value = display.val();
      const range = value.split(' to ');
      if (range.length === 1) {
        range.push(range[0]);
      }

      if (range[0].trim().length) {
        start.val(range[0].trim() + ':00');
      } else {
        start.val('');
      }

      if (range[1].trim().length) {
        end.val(range[1].trim() + ':00');
      } else {
        end.val('');
      }

      start.trigger('change');
      end.trigger('change');
    }

    display.on('change', updateDates).on('blur', updateDates)
  });

  /**
   * Icon field
   */
  $(window).on('icon-field-init', function(e, target) {
    $.require('cdn/json/icons.json', function(icons) {
      var target = $(target);

      var targetLevel = parseInt(target.attr('data-target-parent')) || 0;

      var suggestion = $('<div>')
        .addClass('input-suggestion')
        .addClass('icon-field')
        .hide();

      var parent = target;
      for(var i = 0; i < targetLevel; i++) {
        parent = parent.parent();
      }

      parent.after(suggestion);

      target.click(function() {
          suggestion.show();
        })
        .blur(function() {
          setTimeout(function() {
            suggestion.hide();
          }, 100);
        });

      icons.forEach(function(icon) {
        $('<i>')
          .addClass(icon)
          .addClass('fa-fw')
          .appendTo(suggestion)
          .click(function() {
            var input = target.parent().find('input').eq(0);
            input.val(this.className.replace(' fa-fw', ''));

            var preview = target.parent().find('i').eq(0);
            if(!preview.parent().hasClass('icon-suggestion')) {
              preview[0].className = this.className;
            }

            suggestion.hide();
            target.focus();
          });
      });

      $('i', target.attr('data-target'));
    });
  });

  /**
   * Max Char Field
   */
  $(window).on('max-char-field-init', function(e, target) {
    target = $(target);
    const max = target.attr('maxlength');
    const count = target.val().length;
    const counter = $('<div>')
      .css('position', 'absolute')
      .css('right', '16px')
      .text(`${count}/${max}`);

    target.after(counter);

    target.on('keyup', function() {
      setTimeout(function() {
        const count = target.val().length;
        counter.text(`${count}/${max}`);
      });
    });
  });

  /**
   * Copy Field
   */
  $(window).on('copy-field-init', function(e, target) {
    target = $(target);
    const source = $(target.data('source'));
    source.on('change', function(e) {
      target.val(source.val());
    });
  });

  /**
   * Object Range Change
   */
  $(window).on('object-range-change', function(e, target) {
    var target = $(target);

    var form = $('<form>')
      .attr('method', 'get');

    //if relation exists
    if (typeof target.val() !== 'undefined' && target.val() !== '') {
      $('<input>')
        .attr('type', 'hidden')
        .attr('name', 'range')
        .attr('value', target.val())
        .appendTo(form);
    }

    form.hide().appendTo(document.body).submit();
  });

  /**
   * Direct CDN Upload
   */
  $(window).on('cdn-upload-submit', function(e, target, next) {
    $.require('cdn/json/extensions.json', function(extensions) {
      //setup cdn configuration
      var container = $(target);
      var config = { form: {}, inputs: {} };

      //though we upload this with s3 you may be using cloudfront
      config.cdn = container.attr('data-cdn');
      config.progress = container.attr('data-progress');
      config.complete = container.attr('data-complete');

      //form configuration
      config.form['enctype'] = container.attr('data-enctype');
      config.form['method'] = container.attr('data-method');
      config.form['action'] = container.attr('data-action');

      //inputs configuration
      config.inputs['acl'] = container.attr('data-acl');
      config.inputs['key'] = container.attr('data-key');
      config.inputs['X-Amz-Credential'] = container.attr('data-credential');
      config.inputs['X-Amz-Algorithm'] = container.attr('data-algorythm');
      config.inputs['X-Amz-Date'] = container.attr('data-date');
      config.inputs['Policy'] = container.attr('data-policy');
      config.inputs['X-Amz-Signature'] = container.attr('data-signature');

      var id = 0,
        // /upload/123abc for example
        prefix = config.inputs.key,
        //the total of files to be uploaded
        total = 0,
        //the amount of uploads complete
        completed = 0;

      //hiddens will have base 64
      $('input[type="hidden"]', target).each(function() {
        var hidden = $(this);
        var data = hidden.val();
        //check for base 64
        if(data.indexOf(';base64,') === -1) {
          return;
        }

        //parse out the base 64 so we can make a file
        var base64 = data.split(';base64,');
        var mime = base64[0].split(':')[1];

        var extension = extensions[mime] || 'unknown';
        //this is what hidden will be assigned to when it's uploaded
        var path = prefix + (++id) + '.' + extension;

        //EPIC: Base64 to File Object
        var byteCharacters = window.atob(base64[1]);
        var byteArrays = [];

        for (var offset = 0; offset < byteCharacters.length; offset += 512) {
          var slice = byteCharacters.slice(offset, offset + 512);

          var byteNumbers = new Array(slice.length);

          for (var i = 0; i < slice.length; i++) {
            byteNumbers[i] = slice.charCodeAt(i);
          }

          var byteArray = new Uint8Array(byteNumbers);

          byteArrays.push(byteArray);
        }

        var file = new File(byteArrays, {type: mime});

        //This Code is to verify that we are
        //encoding the file data correctly
        //see: http://stackoverflow.com/questions/16245767/creating-a-blob-from-a-base64-string-in-javascript
        //var reader  = new FileReader();
        //var preview = $('<img>').appendTo(target)[0];
        //reader.addEventListener("load", function () {
        //  preview.src = reader.result;
        //}, false);
        //reader.readAsDataURL(file);
        //return;

        //add on to the total
        total ++;

        //prepare the S3 form to upload just this file
        var form = new FormData();
        for(var name in config.inputs) {
          if(name === 'key') {
            form.append('key', path);
            continue;
          }

          form.append(name, config.inputs[name]);
        }

        //lastly add this file object
        form.append('file', file);

        // Need to use jquery ajax
        // so that auth can catch
        // up request, and append access
        // token into it
        $.ajax({
          url: config.form.action,
          type: config.form.method,
          // form data
          data: form,
          // disable cache
          cache: false,
          // do not set content type
          contentType: false,
          // do not proccess data
          processData: false,
          // on error
          error: function(xhr, status, message) {
            notifier.fadeOut('fast', function() {
              notifier.remove();
            });

            $.notify(message, 'danger');
          },
          // on success
          success : function() {
            //now we can reassign hidden value from
            //base64 to CDN Link
            hidden.val(config.cdn + '/' + path);

            //if there is more to upload
            if ((++completed) < total) {
              //update bar
              var percent = Math.floor((completed / total) * 100);
              bar.css('width', percent + '%').html(percent + '%');

              //do nothing else
              return;
            }

            notifier.fadeOut('fast', function() {
              notifier.remove();
            });

            $.notify(config.complete, 'success');

            //all hidden fields that could have possibly
            //been converted has been converted
            //submit the form
            if (typeof next === 'function') {
              return next(e, target);
            }

            target.submit();
          }
        });
      });

      //if there is nothing to upload
      if(!total) {
        if (typeof next === 'function') {
          return next(e, target);
        }
        //let the form submit as normal
        return;
      }

      //otherwise we are uploading something, so we need to wait
      e.preventDefault();

      var message = '<div>' + config.progress + '</div>';
      var progress = '<div class="progress"><div class="progress-bar"'
      + 'role="progressbar" aria-valuenow="2" aria-valuemin="0"'
      + 'aria-valuemax="100" style="min-width: 2em; width: 0%;">0%</div></div>';

      var notifier = $.notify(message + progress, 'info', 0);
      var bar = $('div.progress-bar', notifier);
    });
  });

  /**
   * Fieldset Init
   */
  $(window).on('fieldset-init', function (e, target) {
    var target = $(target);
    //name of the field
    var name = target.data('name');
    //keyword of fieldset
    var fieldset = target.data('fieldset');
    //whether to show the add button
    var multiple = target.data('multiple');
    //get uuid
    var uuid = target.data('uuid') || '';
    //label name
    var label = target.data('label');
    //get the template
    var template = target.data('template');

    if (template) {
      template = $(template).html();
    } else {
      template = target.children('.template-fieldset-row')
        .remove()
        .html();
    }

    //INITITALIZERS
    var init = function (row) {
      row.find('a.fieldset-remove').click(function () {
        if ($(this).parents('[data-do=fieldset]')[0] !== target[0]) {
          return;
        }
        //we only need to change the
        //elements after the one removed
        var rows = row.nextAll('.fieldset-row');

        var index = $(row).index() || 0;

        // Now remove the target
        row.remove();

        rows.each(function () {
          //update the label, it's easy! :D
          var labelTemplate = $(this).parent().attr('data-label');
          var rows = $(this)
              .parents('.fieldset-row[data-multiple]')
              .get()
              .reverse();

          rows.forEach(function (row, i) {
            labelTemplate = labelTemplate.replace(
              new RegExp('{INDEX_' + i + '}', 'g'),
              $(row).index() + 1
            );
          });

          labelTemplate = labelTemplate.replace(
            new RegExp('{INDEX_' + rows.length + '}', 'g'),
            $(this).index() + 1
          );

          $(this)
            .children('div.box-head')
            .find('h3.fieldset-label')
            .html(labelTemplate);

          //next update the fields, it's hard... :(
          // Get all the inputs
          var fields = $('.system-form-field', this);

          // Re-index
          reindex(fields, true, index++);
        });
      });

      $(row).doon();
    };

    //append meta template
    $('.fieldset-add', target).click(function () {
      if ($(this).parents('[data-do=fieldset]')[0] !== target[0]) {
        return;
      }

      var indexes = {};
      var rows = $(this)
        .parents('.fieldset-row[data-multiple]')
        .get()
        .reverse();

      rows.forEach(function (row, i) {
        indexes['{INDEX_' + i + '}'] = $(row).index();
      });

      indexes['{INDEX_' + rows.length + '}'] = $(this).siblings('.fieldset-row').length;

      var row = $(template.replace(
        new RegExp('{UUID}','g'),
        uuid + '' + rows.length)
      );

      $('.system-form-control', row).each(function () {
        var name = $(this).attr('name');
        for (var index in indexes) {
          name = name.replace(index, indexes[index]);
        }

        $(this).attr('name', name);
      });

      //consider file fields
      $('[data-name]', row).each(function () {
        var name = $(this).attr('data-name');
        for (var index in indexes) {
          name = name.replace(index, indexes[index]);
        }

        $(this).attr('data-name', name);
      });

      var labelTemplate = label;
      for (var index in indexes) {
        labelTemplate = labelTemplate.replace(index, indexes[index] + 1);
      }

      $('h3.fieldset-label', row).html(labelTemplate);

      //insert and activate scripts
      $(this).before(row);

      init(row);
    });

    //INITIALIZE
    $(target)
      .children('.fieldset-row')
      .each(function () {
        init($(this));
      });

    var reindex = function (fields, filter, start) {
      var inputs = $('.system-form-control', fields);
      // Get the input names
      var names = {};
      $.each(inputs, function (index, element) {
        // Get the original name
        var original = $(element).attr('name');
        // Convert to dot e.g a.b.c
        var name = original
          .replace(/\[|\]/g, '.')
          .replace(/\.\./g, '.');

        // Trim trailing dots
        name = name.substr(0, name.length - 1);

        // Convert to object
        dotToObject(name, original, names);
      });

      // Get the re-index filters
      var filters = [];

      // Filter?
      // if (filter) {
      //     $('[data-name]').map(function(index, element) {
      //         filters.push($(element).data('name'));
      //     });
      // }

      // Re-index names
      var reindexed = arrange(names, filters, start);
      // Serialized it so we can build something like a[b][c]
      var serialized = serialize(reindexed);
      // Split by pairs
      serialized = serialized.split('&');

      // On each serialized pairs
      for (var i in serialized) {
        // Get the parts key & value
        var parts = serialized[i].split('=');

        // Iterate on each input elements
        $.each(inputs, function (index, element) {
          // Get the name
          var name = $(element).attr('name');

          // Has matched the original name?
          if (name === parts[1]) {
            // Replace it with the re-indexed name
            $(element).attr('name', parts[0]);

            // Case for Filelist/File Fields
            if ($(element).hasClass('system-file-input')) {
              var container = $(element).parentsUntil('.system-form-field').last();
              container.attr('data-name', parts[0].replace('[]', ''));
            }

            // Case for tag field
            if ($(element).hasClass('tag-input')) {
              var container = $(element).parentsUntil('div.form-tag').last();
              container
                .data('name', parts[0].replace('[]', ''))
                .attr('data-name', parts[0].replace('[]', ''));
            }
          }
        });
      }
    };

    var arrange = function (object, filters, start = 0) {
      // Re-arranged object
      var rearranged = {};
      // Get all the keys
      var keys = Object.keys(object);
      // Current index
      var index = start;

      // On each keys
      for (var i in keys) {
        // Get the current key
        var key = keys[i];
        // Get the current value
        var current = object[keys[i]];
        // Get the type
        var type = Object.prototype.toString.call(current);

        // If it's a string but it's an object
        if (isNaN(parseInt(key)) && type === '[object Object]' && key !== '{INDEX}') {
          // If it's not fieldset
          if (filters.length && filters.indexOf(key) === -1) {
            continue;
          }

          // Recurse object
          rearranged[key] = arrange(current, filters, index);

          continue;
        // If it's a number and it's an object, re-index
        } else if ((!isNaN(parseInt(key)) || key === '{INDEX}') && type === '[object Object]') {
          // Re-index the object
          rearranged[index] = arrange(current, filters);
          index++;
        } else {
          // Just set the value
          rearranged[key] = current;
        }
      }

      return rearranged;
    };

    var dotToObject = function (path, value, object) {
      // Get the parts
      var parts = path.split('.'), part;
      var last = parts.pop();
      var pointer = object;
      // On each part
      while (part = parts.shift()) {
        // Create if doesnt exists
        if (typeof pointer[part] !== 'object') {
          pointer[part] = {};
        }

        // Update pointer
        pointer = pointer[part];
      }

      // Set value
      pointer[last] = value;
    };

    var serialize = function (object, prefix) {
      var string = [], property;

      // On each property
      for (property in object) {
        if (object.hasOwnProperty(property)) {
          // Figure out the key
          var key = prefix ?
            prefix + '[' + property + ']' :
            property;

          // Get the value
          var value = object[property];

          // Push or recurse the pair
          string.push(
            (value !== null && typeof value === 'object') ?
            serialize(value, key) :
            key + '=' + value
          );
        }
      }

      return string.join('&');
    };
  });

  /**
   * Star Rating Field Init
   */
  $(window).on('stars-field-init', function (e, target) {
      target = $(target);

      var input = target.find('input.system-form-control');

      //cache rows
      var rows = target.find('.star');
      var range = 0, stop = 0;

      //INITIALIZER
      var init = function () {
          rows
          .each(function () {
              var icon = $(this).find('i');
              icon.on('mousemove', hover.bind(icon, icon.outerWidth()));
              icon.on('click', function () {
                  //not sure why .val is not working :(
                  input.attr('value', range);
              });
          });

          //reset if didn't select
          target.on('mouseleave', function () {
              range = 0, stop = 0;
              fill(input.val());
          });
      };

      //on hover determine steps
      var hover = function (width, e) {
          var index = $(this).parent().index();
          //determine whether it's half step
          var half = Math.ceil(width / 2);
          var position = Math.ceil(
              e.pageX - $(this).parent().offset().left
          );

          //small threshold to be able to reset to 0
          if (index === 0 && position < 8) {
              range = 0;
              return fill(0);
          }

          //half step?
          if (position <= half) {
              range = index + .5;

          //whole step?
          } else {
              range = index + 1;
          }

          //do not rerender if value
          //doesn't change
          if (stop === range) {
              return;
          }

          //set stop threshold
          fill(range);
          stop = range;
      };

      //fill the stars
      var fill = function (range) {
          //determine whether it's a full or half step
          var half = range.toString().indexOf('.5') > 0;
          range = Math.round(range);

          //fill in each rows
          rows.each(function (index) {
              var star = $(this).find('i');

              //half step?
              if (index === range - 1 && half) {
                  star.attr('class', 'fas fa-star-half-alt text-warning');
                  return;
              }

              //whole step?
              if (index < range) {
                  star.attr('class', 'fas fa-star text-warning');

              //empty step?
              } else {
                  star.attr('class', 'far fa-star');
              }
          });
      };

      //INITIALIZE
      init();
  });

  $(window).on('bootstrap-validator-init', function (e, target) {
    function checker() {
      const field = $(this);

      if (typeof field.data('group') === 'undefined') {
        field.data('group', field.parents('div.form-group').eq(0));
      }

      //if there is no form group
      if (!field.data('group').length) {
        //no where to error
        return;
      }

      const group = field.data('group').removeClass('has-error');

      if (typeof group.data('helper') === 'undefined') {
        group.data('helper', $('<em class="help-text has-error"></em>'));
      }

      const helper = group.data('helper').remove();
      const readonly = field.prop('readonly');

      if (readonly) {
        field.prop('readonly', false).removeAttr('readonly');
      }

      if(!this.checkValidity()) {
        helper.text('Invalid Format');
        if (this.validity.valueMissing) {
          helper.text('Required Field');
        } else if (this.validity.patternMismatch) {
          helper.text('Invalid Format');
        } else if (this.validity.tooLong) {
          helper.text('Characters Exceed Limit');
        } else if (this.validity.tooShort) {
          helper.text('Not Enough Characters');
        } else if (this.validity.rangeOverflow) {
          helper.text('Too Large');
        } else if (this.validity.rangeUnderflow) {
          helper.text('Too Small');
        }

        group.addClass('has-error');
        group.after(helper);
      }

      if (!group.hasClass('has-error')) {
        const format = field.attr('format');
        if (format && format.length && !(new RegExp(format)).test(field.val())) {
          helper.text('Invalid Format');
          group.addClass('has-error');
          group.after(helper);
        }

        if (field.prop('required') && !field.val().length) {
          helper.text('Required Field');
          group.addClass('has-error');
          group.after(helper);
        }
      }

      if (readonly) {
        field.prop('readonly', true).attr('readonly', 'readonly');
      }
    }

    $(target)
      .prop('novalidate', true)
      .attr('novalidate', true);

    let submit = false;
    $('input,select,textarea', target).blur(checker).change(checker);

    $(target).on('submit', function(e) {
      submit = true;

      $('input,select,textarea', target).each(function() {
        checker.call(this);
      });

      submit = false;

      if ($('.has-error', target).length) {
        e.preventDefault();
        $.notify('Errors were found in the form you submitted.', 'error');
        return false;
      }
    });
  });
})();
