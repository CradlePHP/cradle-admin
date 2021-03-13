/**
 * App Panel UI
 */
(function() {
  const cdn = $('html').attr('data-cdn') || '';
  const panel = $('aside.panel-sidebar-right');
  const screens = [];

  $.extend({
    panel: {
      addScreen: function(html, template) {
        const screen = $('<section>').addClass('view-mobile').html(html);

        if (template) {
          screen.attr('data-template', template).data('template', template);
        }

        if (!screens.length) {
          panel.html('');
          panel.append(screen);
        } else {
          panel.append(screen);

          panel.animate({
            scrollLeft: panel.width() * screens.length
          }, 500);
        }

        screens.push(screen.doon());
        return screens.length - 1;
      },
      removeScreen: function(reload, callback = $.noop) {
        if (screens.length < 2) {
          //trigger the class changer thing
          const trigger = $('<button>')
            .data('name', 'no-sidebar-right')
            .data('selector', 'section.layout-panel');

          $(window).trigger('add-class-click', trigger);
          return;
        }

        const backScreenIndex = screens.length - 2;

        //if they want to reload
        if (reload) {
          $.panel.reloadScreen(backScreenIndex);
        } else if (screens[backScreenIndex].data('reload') === true) {
          $.panel.reloadScreen(backScreenIndex);
          screens[backScreenIndex].data('reload', false);
        }

        panel.animate({
          scrollLeft: panel.width() * backScreenIndex
        }, 500, function() {
          screens.pop();
          callback($('section.view-mobile:last').remove());
        });

        return screens.length;
      },
      reloadScreen: function(index) {
        if (!index && index !== 0 && index !== '0') {
          index = screens.length - 1;
        }

        var template = screens[index].data('template');

        // for referenes and files / flows
        if (typeof template === 'undefined') {
          var template = $('section.view-mobile:nth-child(2) form').data('template');

          if ($('form[data-back]').data('back')) {
            var template = $('section.view-mobile:nth-child(3) form').data('back');
          }
        }

        if (!template) {
          return false;
        }

        if (~template.indexOf('://') || template.indexOf('/') === 0) {
          $.panel.replaceScreen(index, `<img class="loading" src="${cdn}/images/loader.gif" />`);

          $.get(template, function(response) {
            $.panel.replaceScreen(index, response);
          });
        } else {
          $.panel.replaceScreen(index, $(template).html());
        }

        return true;
      },
      replaceScreen: function(index, html, template) {
        if (typeof screens[index] === 'undefined') {
          return;
        }

        const screen = $('<section>').addClass('view-mobile').html(html);

        if (template) {
          screen.attr('data-template', template).data('template', template);
        }

        $('section.view-mobile', panel).eq(index).replaceWith(screen);

        panel.animate({
          scrollLeft: panel.width() * screens.length
        }, 500);

        screens[index] = screen.doon();
      }
    }
  });

  function formSubmit(target, next = $.noop) {
    target = $(target);

    const method = target.attr('method') || 'post';
    const action = target.attr('action') || window.location.href;

    if (!target[0].checkValidity()) {
      return false;
    }

    //get the data
    const data = target.serialize() || {};

    //ajax it up
    $[method](action, data, function(response) {
      //allow custom response handling
      if(next(response, data, target) !== false) {
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
        }
      }
    });
  }

  $(window).on('panel-mobile-open-click', function(e, trigger) {
    trigger = $(trigger);

    //empty the screens
    screens.length = 0;

    //trigger the class changer thing
    trigger.data('name', 'no-sidebar-right');
    trigger.data('selector', 'section.layout-panel');
    $(window).trigger('remove-class-click', trigger);

    const template = trigger.data('template');

    if (~template.indexOf('://') || template.indexOf('/') === 0) {
      const index = $.panel.addScreen(
        `<img class="loading" src="${cdn}/images/loader.gif" />`,
        template
      );

      const method = trigger.data('method') || 'get';
      const form = trigger.data('form');

      let data = {};
      if (form) {
        data = $(form).serialize() || {};
      }

      $[method](template, data, function(response) {
        $.panel.replaceScreen(index, response, template);
      });
    //if its HTML
    } else if (template.indexOf('<') === 0) {
      $.panel.addScreen($(template).html());
    //selector?
    } else {
      $.panel.addScreen($(template).html(), template);
    }

    if (trigger.data('container') && trigger.data('id')) {
      $(trigger.data('container')).attr('data-id', trigger.data('id'));
    }
  });

  $(window).on('panel-mobile-forward-click', function(e, trigger) {
    trigger = $(trigger);

    const template = trigger.data('template');

    //if its a URL
    if (~template.indexOf('://') || template.indexOf('/') === 0) {
      const index = $.panel.addScreen(
        `<img class="loading" src="${cdn}/images/loader.gif" />`,
        template
      );

      const method = trigger.data('method') || 'get';

      let data = {};
      let payload = trigger.data('payload') || '';
      if ((trigger.data('payload-selector') || '').length) {
        payload = $(trigger.data('payload-selector')).text();
      }

      if (payload.length) {
        data = payload;
        if (payload.indexOf('[') === 0
          || payload.indexOf('{') === 0
        ) {
          data = JSON.parse(payload);
        }
      }

      $[method](template, data, function(response) {
        $.panel.replaceScreen(index, response, template);
      });
    //if its HTML
    } else if (template.indexOf('<') === 0) {
      $.panel.addScreen($(template).html());
    //selector?
    } else {
      $.panel.addScreen($(template).html(), template);
    }

    if (trigger.data('container') && trigger.data('id')) {
      $(trigger.data('container')).attr('data-id', trigger.data('id'));
    }
  });

  $(window).on('panel-mobile-back-click', function(e, trigger = null, callback = $.noop) {
    let reload = 0;
    if (trigger) {
      reload = $(trigger).data('reload') || 0;
    }

    $.panel.removeScreen(reload, callback);
  });

  $(window).on('panel-mobile-reload-click', function(e, trigger) {
    const index = $(trigger).data('index');
    $.panel.reloadScreen(index);
  });

  $(window).on('panel-form-init', function(e, target) {
    $(window).trigger('bootstrap-validator-init', [target]);
  });

  $(window).on('panel-form-submit', function(e, target, callback) {
    //validate
    e.type = 'bootstrap-validator-submit';
    $(window).trigger(e, [target]);
    if (e.return === false) {
      return;
    }

    e.return = false;
    e.preventDefault();

    var after = $(target).data('after') || 'reload';
    var expected = $(target).data('response') || 'json';
    var next = function(response, data, target) {
      //if no response or error
      if (typeof response === 'object' && response.error) {
        //dont do next
        return;
      }

      if (expected === 'json' && typeof response !== 'object') {
        //dont do next
        return;
      }

      if (after === 'reload') {
        window.location.reload();
      } else if (after === 'reback') {
        $.panel.removeScreen(1);
      } else if (after === 'rebackall') {
        screens.forEach(screen => {
          screen.data('reload', true);
        });
        $.panel.removeScreen(0);
      } else if (after === 'back') {
        $.panel.removeScreen(0);
      }

      if (typeof callback === 'function') {
        callback(response);
      }

      return false;
    };

    //check for upload
    if ($(target).data('s3')) {
      $(window).trigger('cdn-upload-submit', [target, function() {
        formSubmit(target, next);
      }]);
      return;
    }

    formSubmit(target, next);
    return false;
  });
})();
