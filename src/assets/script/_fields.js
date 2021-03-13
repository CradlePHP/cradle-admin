/**
 * General Form Fields
 */
(function() {
  /**
   * Suggestion Field
   */
  $(window).on('suggestion-field-init', function(e, target) {
    $.require('components/handlebars/dist/handlebars.js', function() {
      target = $(target);

      var container = $('<ul>').appendTo(target);
      var spinnerItem = $(`
        <li class="d-flex align-items-center">
          <div class="spinner-border mr-3" role="status"></div>
          <span>Please Wait ...</span>
        </li>
      `);
      container.prepend(spinnerItem);

      var searching = false,
        xhr = false,
        prevent = false,
        value = target.attr('data-value'),
        format = target.attr('data-format'),
        targetLabel = target.attr('data-target-label'),
        targetValue = target.attr('data-target-value'),
        url = target.attr('data-url'),
        template = target.attr('data-template');

      if (template) {
        template = $(template).html();
      } else {
        template = '<li class="suggestion-item">{VALUE}</li>';
      }

      if(!targetLabel || !targetValue || !url || !value) {
        return;
      }

      targetLabel = $(targetLabel);
      targetValue = $(targetValue);

      $(window).click(function(e) {
        target.addClass('d-none');
      });

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

          var row = Handlebars.compile(template.replace('{VALUE}', label))(item);
          item = { label: label, value: item[value] };

          row = $(row).click(function() {
            callback(item);
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

          //if esc
          if(e.keyCode == 27) {
            //hide dropdown
            return target.addClass('d-none');
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
            //return;
          }

          setTimeout(function() {
            if (targetLabel.val() == '') {
              return;
            }

            if (!spinnerItem.parent().length) {
              container.prepend(spinnerItem);
            }
            target.removeClass('d-none');

            if (xhr) {
              xhr.abort();
            }

            //searching = true;
            xhr = $.ajax({
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
                  if (!item.value) {
                    return;
                  }
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
    var template = `<div class="field-row input-group mb-3">
      <input
        class="meta-input key form-control"
        type="text"
        placeholder="${placeholderKey}"
        required
      />
      <textarea
        class="meta-input value form-control"
        rows="1"
        placeholder="${placeholderValue}"
        required
      ></textarea>
      <input type="hidden" name="" value=""/>
      <div class="input-group-append">
        <a class="input-group-text text-danger remove" href="javascript:void(0)">
          <i class="fas fa-times"></i>
        </a>
      </div>
    </div>`;

    if (target.data('field') === 'input') {
      template = `<div class="field-row input-group mb-3">
        <input
          class="meta-input key form-control"
          type="text"
          placeholder="${placeholderKey}"
          required
        />
        <input
          class="meta-input value form-control"
          placeholder="${placeholderValue}"
          required
        />
        <input type="hidden" name="" value=""/>
        <div class="input-group-append">
          <a class="input-group-text text-danger remove" href="javascript:void(0)">
            <i class="fas fa-times"></i>
          </a>
        </div>
      </div>`;
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
   * Image Field
   * HTML config for single images
   * data-do="image-field"
   * data-name="profile_image"
   * data-width="200"
   * data-height="200"
   * data-alt="Change this Photo"
   *
   * HTML config for multiple images
   * data-do="image-field"
   * data-name="profile_image"
   * data-width="200"
   * data-height="200"
   * data-multiple="1"
   * data-alt="Change this Photo"
   *
   * HTML config for single images / multiple sizes
   * data-do="image-field"
   * data-name="profile_image"
   * data-width="0|200|100"
   * data-height="0|200|100"
   * data-label="original|small|large"
   * data-display="large|small"
   * data-alt="Change this Photo"
   *
   * HTML config for multiple images / multiple sizes
   * data-do="image-field"
   * data-name="profile_image"
   * data-width="0|200|100"
   * data-height="0|200|100"
   * data-label="original|small|large"
   * data-display="large"
   * data-multiple="1"
   * data-alt="Change this Photo"
   */
  $(window).on('image-field-init', function(e, target) {
    $.require('components/yarn-cropper/cropper.min.js', function() {
      //current
      var container = $(target);

      //get meta data

      //for hidden fields
      var name = container.attr('data-name');

      //for file field
      var multiple = container.attr('data-multiple');

      //for image fields
      var alt = container.attr('data-alt');
      var classes = container.attr('data-class');

      var width = parseInt(container.attr('data-width') || 0);
      var height = parseInt(container.attr('data-height') || 0);

      var widths = container.attr('data-width') || '0';
      var heights = container.attr('data-height') || '0';
      var labels = container.attr('data-label') || '';
      var displays = container.attr('data-display') || '';

      widths = widths.split('|');
      heights = heights.split('|');
      labels = labels.split('|');
      displays = displays.split('|');

      if (!displays[0].length) {
        displays = false;
      }

      if (widths.length !== heights.length) {
        throw 'Invalid Attributes. Width and Height counts are not the same.';
      }

      //make an image config
      var config = [];
      widths.forEach(function(width, i) {
        var label = labels[i] || '' + i;

        if (widths.length === 1 &&
          (
            typeof labels[i] === 'undefined' ||
            !labels[i].length
          )
        ) {
          label = false;
        }

        config.push({
          label: label,
          display: !displays || displays.indexOf(label) !== -1,
          width: parseInt(widths[i]),
          height: parseInt(heights[i])
        });
      });

      //make a file
      var file = $('<input type="file" />')
        .attr('accept', 'image/png,image/jpg,image/jpeg,image/gif')
        .addClass('d-none')
        .appendTo(target);

      if (multiple) {
        file.attr('multiple', 'multiple');
      }

      //listen for clicks
      container.click(function(e) {
        if (e.target !== file[0]) {
          file.click();
        }
      });

      var generate = function(file, name, width, height, display) {
        var image = new Image();

        //listen for when the src is set
        image.onload = function() {
          //if no dimensions, get the natural dimensions
          width = width || this.width;
          height = height || this.height;

          //so we can crop
          $.cropper(file, width, height, function(data) {
            //create img and input tags
            $('<input type="hidden" />')
              .attr('name', name)
              .val(data)
              .appendTo(target);

            if (display) {
              $('<img />')
                .addClass(classes)
                .attr('alt', alt)
                .attr('src', data)
                .appendTo(target);
            }
          });
        };

        image.src = URL.createObjectURL(file);
      };

      file.change(function() {
        if (!this.files || !this.files[0]) {
          return;
        }

        //remove all
        $('input[type="hidden"], img', target).remove();

        for (var i = 0; i < this.files.length; i++) {
          config.forEach(function(file, meta) {
            //expecting
            //  meta[label]
            //  meta[display]
            //  meta[width]
            //  meta[height]

            //make a path
            var path = '';

            if (meta.label !== false) {
              path = '[' + meta.label + ']';
            }

            if (multiple) {
              path = '[' + i + ']' + path;
            }

            path = name + path;

            generate(
              file,
              path,
              meta.width,
              meta.height,
              meta.display
            );
          }.bind(null, this.files[i]));
        }

        // if auto submit
        if (container.attr('data-submit') === 'true') {
          setTimeout(function() {
            container.closest('form').submit();
          }, 500);
        }
      });
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
  $(window).on('wysiwyg-editor-init', function(e, target) {
    var advanced = '<div class="wysiwyg-toolbar position-relative" style="display: none;">'
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

    var basic = '<div class="wysiwyg-toolbar position-relative" style="display: none;">'
      + '<div class="btn-group">'
        + '<a class="btn btn-default" data-wysihtml-command="bold" title="CTRL+B"><i class="fas fa-bold"></i></a>'
        + '<a class="btn btn-default" data-wysihtml-command="italic" title="CTRL+I"><i class="fas fa-italic"></i></a>'
        + '<a class="btn btn-default" data-wysihtml-command="underline" title="CTRL+U"><i class="fas fa-underline"></i></a>'
        + '<a class="btn btn-default" data-wysihtml-command="strike" title="CTRL+U"><i class="fas fa-strikethrough"></i></a>'
      + '</div> '
      + '<div class="btn-group">'
        + '<a class="btn btn-default" data-wysihtml-command="insertUnorderedList"><i class="fas fa-list-ul"></i></a>'
        + '<a class="btn btn-default" data-wysihtml-command="insertOrderedList"><i class="fas fa-list-ol"></i></a>'
      + '</div> '
      + '<a class="btn btn-light" data-wysihtml-command="insertSpeech"><i class="fas fa-comments"></i></a> '
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
        var template = basic;
        var mode = $(target).attr('data-mode');
        if (mode === 'advanced') {
          template = advanced;
        }

        var toolbar = $(template);

        $(target).before(toolbar);


        var e = new wysihtml.Editor(target, {
          toolbar:    toolbar[0],
          parserRules:  wysihtmlParserRules
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

        if ($(target).data('select2')) {
          $(target).select2();
        }
      }
    );
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
      target = $(target);

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

      var id = $(row).attr('id');
      if (typeof id !== typeof undefined && id !== false) {
        id = id.replace('{INDEX_0}', indexes['{INDEX_0}']);
        $(row).attr('id', id);
      }

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

      //consider suggestion-fields
      $('.input-suggestion', row).each(function () {
        var targetLabel = $(this).attr('data-target-label');
        var targetValue = $(this).attr('data-target-value');
        for (var index in indexes) {
          targetLabel = targetLabel.replace(index, indexes[index]);
          targetValue = targetValue.replace(index, indexes[index]);
        }

        $(this).attr('data-target-label', targetLabel);
        $(this).attr('data-target-value', targetValue);
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
})();
