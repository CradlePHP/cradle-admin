/**
* General Search
*/
(function () {
  /**
   * Search table check all
   */
  $(window).on('table-checkall-init', function (e, trigger) {
    var target = $(trigger).parents('table').eq(0);

    function toggleBulkActions(on) {
      if (on) {
        $('.bulk-form').removeClass('d-none');
        $('.search-form').addClass('d-none');
      } else {
        $('.bulk-form').addClass('d-none');
        $('.search-form').removeClass('d-none');
      }
    }

    $(trigger).click(function () {
      if ($(trigger).prop('checked')) {
        $('td input[type="checkbox"]', target).prop('checked', true);
        toggleBulkActions(true);
      } else {
        $('td input[type="checkbox"]', target).prop('checked', false);
        toggleBulkActions(false);
      }
    });

    $('td input[type="checkbox"]', target).click(function () {
      var anyChecked = false;
      var allChecked = true;
      $('td input[type="checkbox"]', target).each(function () {
        if (!$(this).prop('checked')) {
          allChecked = false;
        }

        if ($(this).prop('checked')) {
          anyChecked = true;
        }
      });

      $(trigger).prop('checked', allChecked);
      toggleBulkActions(anyChecked);
    });
  });

  /**
   * Importer init
   */
  $(window).on('import-init', function (e, trigger) {
    $(trigger).toggleClass('disabled');

    $.require('components/papaparse/papaparse.min.js', function () {
      $(trigger).toggleClass('disabled');
    });
  });

  /**
   * Importer tool
   */
  $(window).on('import-click', function (e, trigger) {
    var url = $(trigger).attr('data-url');
    var progress = $(trigger).attr('data-progress');
    var complete = $(trigger).attr('data-complete');

    if (typeof progress === 'undefined') {
      progress = "We are importing you data. Please do not refresh page.";
    }

    //make a file
    $('<input type="file" />')
      .attr(
        'accept',
        [
          'text/plain',
          'text/csv',
          'text/x-csv',
          'application/vnd.ms-excel',
          'application/csv',
          'application/x-csv',
          'text/comma-separated-values',
          'text/x-comma-separated-values',
          'text/tab-separated-values'
        ].join(',')
      )
      .change(function () {
        var message = '<div>'+progress+'</div>';
        var notifier = $.notify(message, 'info', 0);

        $(this).parse({
          config: {
            header: true,
            skipEmptyLines: true,
            complete: function (results, file) {
              $.post(url, JSON.stringify(results.data), function (response) {
                //process data
                try {
                  response = JSON.parse(response);
                } catch (e) {
                  //fix for echos and print_r
                  response = {
                    error: false,
                    message: 'No data loaded',
                  }
                }

                if (response.error) {
                  var message = response.message;

                  response.errors.forEach(function (error) {
                    message += '<br />' + error;
                  });

                  notifier.fadeOut('fast', function () {
                    notifier.remove();
                  });

                  $.notify(message, 'danger');
                } else {
                  if (typeof complete === 'undefined') {
                    complete = response.message;
                  }

                  notifier.fadeOut('fast', function () {
                    notifier.remove();
                  });

                  $.notify(complete, 'success');

                  setTimeout(function () {
                    window.location.reload();
                  }, 1000);
                }
              });
            },
            error: function (error, file, input, reason) {
              $.notify(error.message, 'error');
            }
          }
        });
      })
      .click();
  });

  /**
   * Confirm UI
   */
  $(window).on('confirm-click', function (e, trigger) {
    if (!window.confirm('Are you sure you want to remove?')) {
      e.preventDefault();
      e.stopPropagation();
      return false;
    }
  });
})();
