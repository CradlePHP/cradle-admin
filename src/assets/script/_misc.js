/**
 * Other UI
 */
(function() {
  /**
   * General - add class on click
   */
  $(window).on('add-class-click', function (e, target) {
    target = $(target);
    const name = target.data('name');
    const selector = target.data('selector');

    if (!name || !selector) {
      return;
    }

    $(selector).addClass(name);
  });

  /**
   * General - remove class on click
   */
  $(window).on('remove-class-click', function (e, target) {
    target = $(target);
    const name = target.data('name');
    const selector = target.data('selector');

    if (!name || !selector) {
      return;
    }

    $(selector).removeClass(name);
  });

  /**
   * Prettyfy
   */
  $(window).on('prettify-init', function(e, target) {
    var loaded = false;
    $.require.load(
      'components/google-code-prettify/src/prettify.js',
      'components/google-code-prettify/src/prettify.css',
      function() {
        if(!loaded) {
          PR.prettyPrint();
          loaded = true;
        }
      }
    );
  });

  /**
   * Modal Link
   */
  $(window).on('modal-link-init', function(e, target) {
    target = $(target);

    const template = target.data('template');
    const data = {};

    target.on('click', function() {
      $.get(template, data, function(response) {

        let modal = response.results.body;
        const clone = $(modal).clone().modal('show').doon();

        let script = ``;
        if (response.results.script) {
          script = `<script type="text/javascript" src="${response.results.script}"></script>`;
        }

        let css = ``;
        if (response.results.css) {
          css = `<link href="${response.results.css}" rel="stylesheet" type="text/css" />`;
          $('head').append($(css));
        }

        clone.on('shown.bs.modal', function() {
          $('body').append($(script));
        });

        clone.on('hidden.bs.modal', function() {
          clone.remove();
          $('div.modal-backdrop').remove();
          $(`script[src="${response.results.script}"]`).remove();
          $(`link[href="${response.results.css}"]`).remove();
        });
      });
    });
  });

  /**
   * Confirm UI
   */
  $(window).on('confirm-click', function(e, trigger) {
    var message = $(trigger).data('message') || 'Are you sure?';
    if (!window.confirm(message)) {
      e.preventDefault();
      e.stopPropagation();
      return false;
    }
  });

  /**
   * Wizard UI
   */
  $(window).on('wizard-init', function(e, container) {
    container = $(container);
    const steps = container.children();

    let activeIndex = 0;
    let activeStep = steps.eq(activeIndex);

    container.on('wizard-prev-step', function(e, callback) {
      callback = callback || function() {};
      if ((activeIndex - 1) < 0) {
        return;
      }

      activeStep = steps.eq(--activeIndex);

      $(container).animate({
        scrollLeft: container.width() * activeIndex
      }, 1000, function() {
        if (activeStep.attr('id')) {
          window.location.hash = activeStep.attr('id');
        }

        callback();
      });
    });

    container.on('wizard-next-step', function(e, callback) {
      callback = callback || function() {};

      if ((activeIndex + 1) >= steps.length) {
        if (container[0].tagName === 'FORM') {
          container.submit();
        }

        return;
      }

      activeStep = steps.eq(++activeIndex);

      $(container).animate({
        scrollLeft: container.width() * activeIndex
      }, 1000, function() {
        if (activeStep.attr('id')) {
          window.location.hash = activeStep.attr('id')
        }

        callback();
      });
    });

    steps.each(function(i) {
      const step = $(this);
      $('.wizard-prev', this).click(function() {
        container.trigger('wizard-prev-step');
      });

      $('.wizard-next', this).click(function() {
        if (!step.data('validate')) {
          return container.trigger('wizard-next-step');
        }

        step.data('validate')(function(error) {
          if (!error) {
            return container.trigger('wizard-next-step');
          }
        });
      });
    });

    if (window.location.hash && steps.index($(window.location.hash)) !== -1) {
      checkMove(container, activeIndex, steps.index($(window.location.hash)));
    }

    function checkMove(container, activeIndex, toIndex) {
      if (activeIndex >= toIndex) {
        return;
      }

      const next = checkMove.bind(null, container, activeIndex + 1, toIndex);
      const steps = container.children();
      let activeStep = steps.eq(activeIndex);

      if (!activeStep.data('validate')) {
        return container.trigger('wizard-next-step', next);
      }

      activeStep.data('validate')(function(error) {
        if (!error) {
          return container.trigger('wizard-next-step', next);
        }
      });
    }
  });
})();

/**
 * Notifier
 */
(function() {
  $(window).on('notify-init', function(e, trigger) {
    var timeout = parseInt($(trigger).attr('data-timeout') || 3000);

    if(!timeout) {
      return;
    }

    setTimeout(function() {
      $(trigger).fadeOut('fast', function() {
        $(trigger).remove();
      });

    }, timeout);
  });
})();
