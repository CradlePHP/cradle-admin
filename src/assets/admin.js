jQuery(function($) {
  {{> script_fields}}
  {{> script_form}}
  {{> script_search}}
  {{> script_app}}
  {{> script_misc}}
  {{> script_jquery}}

  /**
   * Initialize
   */
  (function() {
    var cdn = $('html').attr('data-cdn') || '';
    // configure require
    require.config({
      cdn: {
        root : cdn
      },
      components: {
        root : cdn + '/components'
      }
    });

    //need to load dependencies
    $.require(
      [
        'components/doon/doon.min.js',
        'components/toastr/build/toastr.min.css',
        'components/toastr/build/toastr.min.js'
      ],
      function() {
        //activate all scripts
        $(document.body).doon();
      }
    );
  })();
});
