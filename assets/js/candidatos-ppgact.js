(function () {
  'use strict';

  function initialize() {
    var form = document.querySelector('.fafar-schedule-form');
    if (!form) {
      return;
    }

    var status = form.querySelector('.fafar-schedule-copy-status');
    form.querySelectorAll('[data-copy-from][data-copy-to]').forEach(function (button) {
      var from = button.getAttribute('data-copy-from');
      var to = button.getAttribute('data-copy-to');
      if (['production', 'test'].indexOf(from) === -1 || ['production', 'test'].indexOf(to) === -1 || from === to) {
        return;
      }

      var source = form.querySelector('[data-schedule-context="' + from + '"]');
      var target = form.querySelector('[data-schedule-context="' + to + '"]');
      if (!source || !target) {
        return;
      }

      button.hidden = false;
      button.addEventListener('click', function () {
        source.querySelectorAll('input[type="checkbox"], input[type="datetime-local"]').forEach(function (field) {
          var name = field.name.replace('[' + from + ']', '[' + to + ']');
          var destination = form.elements.namedItem(name);
          if (!destination || destination.type !== field.type) {
            return;
          }

          if (field.type === 'checkbox') {
            destination.checked = field.checked;
          } else {
            destination.value = field.value;
          }
          destination.dispatchEvent(new Event('change', { bubbles: true }));
        });

        if (status) {
          var title = target.querySelector('legend').textContent.trim().toLowerCase();
          status.textContent = 'Configurações copiadas para o ' + title + '. Clique em “Salvar configurações” para aplicar.';
          status.hidden = false;
        }
      });
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initialize);
  } else {
    initialize();
  }
}());
