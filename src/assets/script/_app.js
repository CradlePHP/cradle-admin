/**
 * App Panel UI
 */
(function() {
  const cdn = $('html').attr('data-cdn') || '';
  const panel = $('aside.panel-sidebar-right');
  const screens = [];

  function addScreen(html, template) {
    const screen = $('<section>').addClass('panel-mobile-screen').html(html);

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
  }

  function removeScreen(reload, callback = $.noop) {
    if (screens.length < 2) {
      //trigger the class changer thing
      const trigger = $('<button>')
        .data('name', 'no-sidebar-right')
        .data('selector', 'section.app-panel');

      $(window).trigger('add-class-click', trigger);
      return;
    }

    const backScreenIndex = screens.length - 2;

    //if they want to reload
    if (reload) {
      reloadScreen(backScreenIndex);
    } else if (screens[backScreenIndex].data('reload') === true) {
      reloadScreen(backScreenIndex);
      screens[backScreenIndex].data('reload', false);
    }

    panel.animate({
      scrollLeft: panel.width() * backScreenIndex
    }, 500, function() {
      screens.pop();
      callback($('section.panel-mobile-screen:last').remove());
    });

    return screens.length;
  }

  function reloadScreen(index) {
    if (!index && index !== 0 && index !== '0') {
      index = screens.length - 1;
    }

    var template = screens[index].data('template');

    // for referenes and files / flows
    if (typeof template === 'undefined') {
      var template = $('section.panel-mobile-screen:nth-child(2) form').data('template');

      if ($('form[data-back]').data('back')) {
        var template = $('section.panel-mobile-screen:nth-child(3) form').data('back');
      }
    }

    if (!template) {
      return false;
    }

    if (~template.indexOf('://') || template.indexOf('/') === 0) {
      replaceScreen(index, `<img class="loading" src="${cdn}/images/loader.gif" />`);

      $.get(template, function(response) {
        replaceScreen(index, response);
      });
    } else {
      replaceScreen(index, $(template).html());
    }

    return true;
  }

  function replaceScreen(index, html, template) {
    if (typeof screens[index] === 'undefined') {
      return;
    }

    const screen = $('<section>').addClass('panel-mobile-screen').html(html);

    if (template) {
      screen.attr('data-template', template).data('template', template);
    }

    $('section.panel-mobile-screen', panel).eq(index).replaceWith(screen)

    screens[index] = screen.doon();
  }

  $(window).on('panel-mobile-open-click', function(e, trigger) {
    trigger = $(trigger);

    //empty the screens
    screens.length = 0;

    //trigger the class changer thing
    trigger.data('name', 'no-sidebar-right');
    trigger.data('selector', 'section.app-panel');
    $(window).trigger('remove-class-click', trigger);

    const template = trigger.data('template');

    if (~template.indexOf('://') || template.indexOf('/') === 0) {
      const index = addScreen(
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
        replaceScreen(index, response, template);
      });
    //if its HTML
    } else if (template.indexOf('<') === 0) {
      addScreen($(template).html());
    //selector?
    } else {
      addScreen($(template).html(), template);
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
      const index = addScreen(
        `<img class="loading" src="${cdn}/images/loader.gif" />`,
        template
      );

      let data = trigger.data('payload') || {};

      $.get(template, data, function(response) {
        replaceScreen(index, response, template);
      });
    //if its HTML
    } else if (template.indexOf('<') === 0) {
      addScreen($(template).html());
    //selector?
    } else {
      addScreen($(template).html(), template);
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

    removeScreen(reload, callback);
  });

  $(window).on('panel-mobile-reload-click', function(e, trigger) {
    const index = $(trigger).data('index');
    reloadScreen(index);
  });

  $(window).on('panel-mobile-back-form-init', function(e, target, callback = $.noop) {
    target = $(target);

    const method = target.attr('method') || 'post';
    const action = target.attr('action') || window.location.href;

    //on submit
    target.submit(function(e) {
      e.preventDefault();
      if ($('.has-error', target).length) {
        return false;
      }

      //get the data
      const data = target.serialize() || {};
      //ajax it up
      $[method](action, data, function(response) {
        //allow custom response handling
        callback(response);

        //if no response
        if (typeof response !== 'object') {
          $.notify('Server Error', 'error');
          return;
        }

        //if response error
        if (response.error) {
          $.notify(response.message || 'Server Error', 'error');
          return;
        }

        $.notify(target.attr('data-success') || 'Updated', 'success');
        //remove this screen and refresh all
        $(window).trigger('panel-mobile-reload-screens');
        removeScreen();
      });

      return false;
    });
  });

  $(window).on('panel-mobile-reload-form-init', function(e, target, callback = $.noop) {
    target = $(target);
    if (!target.data('on') || target.data('on').indexOf('submit') === -1) {

      target.submit(function(e) {
        e.preventDefault();
        $(window).trigger('panel-mobile-reload-form-submit', [target, callback]);
        return false;
      });
    }
  });

  $(window).on('panel-mobile-reload-form-submit', function(e, target, callback = $.noop) {
    target = $(target);

    const method = target.attr('method') || 'post';
    const action = target.attr('action') || window.location.href;

    e.preventDefault();
    if ($('.has-error', target).length) {
      return false;
    }

    //get the data
    const data = target.serialize() || {};

    //ajax it up
    $[method](action, data, function(response) {
      //allow custom response handling
      callback(response);

      //if no response
      if (typeof response !== 'object') {
        $.notify('Server Error', 'error');
        return;
      }

      //if response error
      if (response.error) {
        $.notify(response.message || 'Server Error', 'error');
        return;
      }

      window.location.reload();
    });

    return false;
  });

  $(window).on('panel-mobile-reload-screens', function(e) {
    screens.forEach(screen => {
      screen.data('reload', true);
    });
  });
})();
