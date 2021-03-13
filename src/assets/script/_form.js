/**
 * General Forms
 */
(function() {
  /**
   * Direct CDN Upload
   */
  $(window).on('cdn-upload-submit', function(e, target, next) {
    //the total of files to be uploaded
    var total = 0;
    //hiddens will have base 64
    $('input[type="hidden"]', target).each(function() {
      var hidden = $(this);
      var data = hidden.val();
      //check for base 64
      if(data.indexOf(';base64,') === -1) {
        return;
      }

      //add on to the total
      total ++;
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

      var message = '<div>' + config.progress + '</div>';
      var progress = '<div class="progress"><div class="progress-bar"'
      + 'role="progressbar" aria-valuenow="2" aria-valuemin="0"'
      + 'aria-valuemax="100" style="min-width: 2em; width: 0%;">0%</div></div>';

      var notifier = $.notify(message + progress, 'info', 0);
      var bar = $('div.progress-bar', notifier);
    });
  });

  /**
   * JS Native Validator
   */
  $(window).on('bootstrap-validator-init', function (e, target) {
    $('input,select,textarea', target).blur($.formCheck).change($.formCheck);
  });

  /**
   * JS Native Validator
   */
  $(window).on('bootstrap-validator-submit', function (e, target) {
    $('input,select,textarea', target).each(function() {
      $.formCheck.call(this);
    });

    if (!target.reportValidity()) {
      e.preventDefault();
      $.notify('Errors were found in the form you submitted.', 'error');
      e.return = false;
    }
  });
})();
