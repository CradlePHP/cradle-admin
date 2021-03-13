(function() {
  $.extend({
    formCheck: function() {
      const field = $(this);
      //remove errors
      this.setCustomValidity('');
      //remove readonly for now
      const readonly = field.prop('readonly');
      if (readonly) {
        field.prop('readonly', false).removeAttr('readonly');
      }

      if(!this.checkValidity()) {
        this.setCustomValidity('Invalid Format');
        if (this.validity.valueMissing) {
          this.setCustomValidity('Required Field');
        } else if (this.validity.patternMismatch) {
          this.setCustomValidity('Invalid Format');
        } else if (this.validity.tooLong) {
          this.setCustomValidity('Characters Exceed Limit');
        } else if (this.validity.tooShort) {
          this.setCustomValidity('Not Enough Characters');
        } else if (this.validity.rangeOverflow) {
          this.setCustomValidity('Too Large');
        } else if (this.validity.rangeUnderflow) {
          this.setCustomValidity('Too Small');
        }
      }

      if (!this.checkValidity()) {
        const format = field.attr('format');
        if (format && format.length && !(new RegExp(format)).test(field.val())) {
          this.setCustomValidity('Invalid Format');
        }

        if (field.prop('required') && !field.val().length) {
          this.setCustomValidity('Required Field');
        }
      }

      field.trigger('validation-check');
      //put readonly back
      if (readonly) {
        field.prop('readonly', true).attr('readonly', 'readonly');
      }
    },
    buildNotification: function(message, items) {
      const list = [];
      Object.keys(items).forEach(function (name) {
        const value = items[name];
        if (typeof value === 'object' && value !== null) {
          const notifications = buildErrorNotification(name, value);
          list.push(`<li>${notifications}</li>`);
        } else {
          list.push(`<li>${name} - ${value}</li>`);
        }
      });
      return `${message}<ul>${list.join('')}</ul>`;
    },
    makeFieldName: function(...name) {
      // foo, bar, zoo
      // -> foo][bar][zoo]
      name = name.join('][') + ']';
      // -> foo[bar][zoo]
      return name.replace(']', '')
    },
    json2flat: function(json, ...keys) {
      const flat = {};
      Object.keys(json).forEach((key) => {
        if (typeof json[key] !== 'object' || json[key] === null) {
          flat[$.makeFieldName(...keys, key)] = json[key];
        }

        Object.assign(flat, this.json2query(json[key], ...keys, key));
      });

      return flat;
    },
    json2query: function(json, ...keys) {
      return this.param(this.json2flat(json));
    },
    notify: function(message, type, timeout) {
      if(type === 'danger') {
        type = 'error';
      }

      var toast = toastr[type](message, type[0].toUpperCase() + type.substr(1), {
        positionClass: 'toast-bottom-left',
        timeOut: timeout
      });

      return toast;
    },
    registry: function(data = {}) {
      return {
        has: function(...path) {
          if (!path.length) {
            return false;
          }

          let found = true;
          const last = path.pop();
          let pointer = data;

          path.forEach((step, i) => {
            if (!found) {
              return;
            }

            if (typeof pointer[step] !== 'object') {
              found = false;
              return;
            }

            pointer = pointer[step];
          });

          return !(!found || typeof pointer[last] === 'undefined');
        },
        set: function(...path) {
          if (path.length < 1) {
            return this;
          }

          if (typeof path[0] === 'object') {
            Object.keys(path[0]).forEach(key => {
              this.set(key, path[0][key]);
            });

            return this;
          }

          const value = path.pop();
          let last = path.pop(), pointer = data;

          path.forEach((step, i) => {
            if (step === null || step === '') {
              path[i] = step = Object.keys(pointer).length;
            }

            if (typeof pointer[step] !== 'object') {
              pointer[step] = {};
            }

            pointer = pointer[step];
          });

          if (last === null || last === '') {
            last = Object.keys(pointer).length;
          }

          pointer[last] = value;

          //loop through the steps one more time fixing the objects
          pointer = data;
          path.forEach((step) => {
            const next = pointer[step];
            //if next is not an array and next should be an array
            if (!Array.isArray(next) && this.shouldBeAnArray(next)) {
              //transform next into an array
              pointer[step] = this.makeArray(next);
            //if next is an array and next should not be an array
            } else if (Array.isArray(next) && !this.shouldBeAnArray(next)) {
              //transform next into an object
              pointer[step] = this.makeObject(next);
            }

            pointer = pointer[step];
          });

          return this;
        },
        get: function(...path) {
          if (!path.length) {
            return data;
          }

          if (!this.has(...path)) {
            return null;
          }

          const last = path.pop();
          let pointer = data;

          path.forEach((step, i) => {
            pointer = pointer[step];
          });

          return pointer[last];
        },

        shouldBeAnArray: function(object) {
          if (typeof object !== 'object') {
            return false;
          }

          if (!Object.keys(object).length) {
            return false;
          }

          for (let key in object) {
            if (isNaN(parseInt(key)) || String(key).indexOf('.') !== -1) {
              return false;
            }
          }

          return true;
        },
        makeObject: function(array) {
          return Object.assign({}, array);
        },
        makeArray: function(object) {
          const array = [];
          Object.keys(object).sort().forEach(function(key) {
            array.push(object[key]);
          });

          return array;
        }
      };
    },
  });

  $.fn.extend({
    setFieldValue: function(name, value) {
      if (typeof value === 'undefined' || value === null) {
        value = '';
      }

      $(`[name="${name}"]`, this).each(function () {
        const field = $(this);
        //if select
        if (field.is('select')) {
          //loop through the options
          $('option', field).each(function() {
            //update selected
            $(this)
              .attr('selected', this.value == value)
              .prop('selected', this.value == value);
          });
          return;
        }

        //if radio or checkbox
        if (field.attr('type') === 'radio' || field.attr('type') === 'checkbox') {
          //update checked
          $(this)
            .attr('checked', field.val() == value)
            .prop('checked', field.val() == value);
          return;
        }

        //try to set the value
        field.val(value);
      });

    },
    json2form: function(json, ...keys) {
      const form = this;
      Object.keys(json).forEach((key) => {
        if (typeof json[key] !== 'object' || json[key] === null) {
          return form.setFieldValue($.makeFieldName(...keys, key), json[key]);
        }

        form.json2form(json[key], ...keys, key);
      })
    },
    form2json: function() {
      const json = $.registry();
      $(this).serializeArray().forEach((serial) => {
        const path = serial.name.replace(/\][^\]]*$/g, '').replace('[', '][').split('][');
        json.set(...path, serial.value)
      });

      return json.get()
    },
    compile: function(variables) {
      var template = this.text();

      for(var key in variables) {
        template = template.replace(
          new RegExp('\{' + key + '\}', 'ig'),
          variables[key]
        );
      }

      return $(template);
    }
  });
})();
