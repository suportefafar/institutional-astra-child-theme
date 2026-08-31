(function () {
  'use strict';

  function ready(callback) {
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', callback);
    } else {
      callback();
    }
  }

  ready(function () {
    var form = document.getElementById('fafar-inscricao-ppgcf');

    if (!form) {
      return;
    }

    var config = window.fafarPpgcfConfig || {};
    var status = document.getElementById('fafar-form-status');
    var errorSummary = document.getElementById('fafar-form-errors');
    var errorList = document.getElementById('fafar-form-errors-list');
    var submitButton = form.querySelector('.fafar-submit');
    var conditionalGroups = Array.prototype.slice.call(form.querySelectorAll('[data-condition]'));
    var uploadedFiles = {};
    var uploadStates = {};
    var uploadSequence = {};
    var uploadControllers = {};
    var uploadTimeouts = {};
    var uploadAbortReasons = {};
    var uploadTimeoutMs = parseInt(config.uploadTimeoutMs || '120000', 10);
    var submitTimeoutMs = parseInt(config.submitTimeoutMs || '60000', 10);
    var submitController = null;
    var submitTimeout = null;
    var fieldErrors = {};

    function fieldLabel(field) {
      var wrapper = field.closest('.fafar-field, .fafar-file-field');
      var label = wrapper ? wrapper.querySelector('.fafar-label') : null;

      if (!label && field.id && wrapper) {
        label = wrapper.querySelector('label[for="' + field.id + '"]');
      }
      if (!label && field.labels && field.labels.length) {
        label = field.labels[0];
      }

      return (label ? label.textContent : (field.name || 'Campo'))
        .replace(/\*/g, '')
        .replace(/\s+/g, ' ')
        .trim();
    }

    function renderErrorSummary() {
      var keys = Object.keys(fieldErrors);

      if (!errorSummary || !errorList) {
        return;
      }

      errorList.textContent = '';
      keys.forEach(function (key) {
        var item = document.createElement('li');
        item.textContent = fieldErrors[key].label + ': ' + fieldErrors[key].message;
        errorList.appendChild(item);
      });
      errorSummary.hidden = keys.length === 0;
    }

    function setFieldError(field, message) {
      var key = field.name || field.id;
      var wrapper;

      if (!key) {
        return;
      }
      fieldErrors[key] = {
        label: fieldLabel(field),
        message: message
      };
      wrapper = field.closest('.fafar-field, .fafar-file-field');
      if (wrapper) {
        wrapper.classList.add('is-invalid');
      }
      field.setAttribute('aria-invalid', 'true');
      renderErrorSummary();
    }

    function clearFieldError(field) {
      var key = field.name || field.id;
      var wrapper;

      if (!key) {
        return;
      }
      delete fieldErrors[key];
      wrapper = field.closest('.fafar-field, .fafar-file-field');
      if (wrapper) {
        wrapper.classList.remove('is-invalid');
      }
      field.removeAttribute('aria-invalid');
      renderErrorSummary();
    }

    function setFormError(message) {
      fieldErrors.__form = {
        label: 'Envio do formulário',
        message: message
      };
      renderErrorSummary();
    }

    function clearFormError() {
      delete fieldErrors.__form;
      renderErrorSummary();
    }

    function parseJsonResponse(response) {
      return response.text().then(function (text) {
        try {
          return JSON.parse(text);
        } catch (error) {
          throw new Error('O servidor não respondeu corretamente. Tente novamente quando o serviço estiver disponível.');
        }
      });
    }

    function responseError(response, fallback) {
      var data = response && response.data && typeof response.data === 'object' ? response.data : {};
      var error = new Error(data.message || fallback);
      error.field = data.field || '';
      error.code = data.code || '';
      return error;
    }

    function friendlyRequestError(error, fallback) {
      var message = error && error.message ? error.message : '';
      if (error && error.name === 'AbortError') {
        return fallback + ' A operação excedeu o tempo limite.';
      }
      if (!message || /failed to fetch|networkerror|load failed/i.test(message)) {
        return fallback + ' Não foi possível comunicar com o servidor.';
      }
      return message;
    }

    function checkedValue(name) {
      var checked = form.querySelector('input[name="' + name + '"]:checked');
      return checked ? checked.value : '';
    }

    function conditionMatches(condition) {
      var type = checkedValue('TipoDaVaga');
      var sex = checkedValue('Sexo');
      var level = checkedValue('NivelDaVaga');

      switch (condition) {
        case 'brasileiro':
        case 'arquivos-brasileiro':
          return type !== 'Estrangeiro';
        case 'estrangeiro':
        case 'arquivos-estrangeiro':
          return type === 'Estrangeiro';
        case 'militar':
          return type !== 'Estrangeiro' && sex === 'Masculino';
        case 'doutorado':
          return level === 'Doutorado';
        case 'edital-regular':
          return [
            'Ampla concorrência',
            'Estrangeiro',
            'Reserva de vagas para negros'
          ].indexOf(type) !== -1;
        case 'indigenas':
          return type === 'Reserva de vagas para indígenas';
        case 'pcds':
          return type === 'Reserva de vagas para pessoas com deficiência';
        default:
          return false;
      }
    }

    function fileUiElements(input) {
      var wrapper = input.closest('.fafar-file-field');
      return {
        wrapper: wrapper,
        status: wrapper ? wrapper.querySelector('.fafar-upload-status') : null,
        retry: wrapper ? wrapper.querySelector('[data-upload-action="retry"]') : null,
        cancel: wrapper ? wrapper.querySelector('[data-upload-action="cancel"]') : null,
        remove: wrapper ? wrapper.querySelector('[data-upload-action="remove"]') : null
      };
    }

    function setUploadUi(input, state, message) {
      var ui = fileUiElements(input);
      var hasFile = Boolean(input.files && input.files.length);

      if (!ui.wrapper) {
        return;
      }

      ui.wrapper.setAttribute('data-upload-state', state || 'idle');
      if (ui.status) {
        ui.status.textContent = message || '';
        ui.status.hidden = !message;
      }
      if (ui.retry) {
        ui.retry.hidden = state !== 'error' || !hasFile;
      }
      if (ui.cancel) {
        ui.cancel.hidden = state !== 'uploading' || typeof window.AbortController !== 'function';
      }
      if (ui.remove) {
        ui.remove.hidden = !hasFile || state === 'uploading';
      }
    }

    function finishUploadRequest(fieldName, controller) {
      if (uploadTimeouts[fieldName]) {
        window.clearTimeout(uploadTimeouts[fieldName]);
        delete uploadTimeouts[fieldName];
      }
      if (!controller || uploadControllers[fieldName] === controller) {
        delete uploadControllers[fieldName];
        delete uploadAbortReasons[fieldName];
      }
    }

    function removeUpload(fieldName, reason) {
      var controller;

      if (!fieldName) {
        return;
      }

      uploadSequence[fieldName] = (uploadSequence[fieldName] || 0) + 1;
      controller = uploadControllers[fieldName];
      if (controller) {
        uploadAbortReasons[fieldName] = reason || 'replaced';
        controller.abort();
      }
      finishUploadRequest(fieldName, controller);
      delete uploadedFiles[fieldName];
      uploadStates[fieldName] = '';
    }

    function resetFileInput(input, announce) {
      removeUpload(input.name, 'removed');
      input.value = '';
      input.setCustomValidity('');
      clearFieldError(input);
      setUploadUi(input, 'idle', announce ? 'Arquivo removido.' : '');
      clearFormError();
      updateSubmitAvailability();
    }

    function cancelUpload(input) {
      var fieldName = input.name;
      var controller = uploadControllers[fieldName];

      if (uploadStates[fieldName] !== 'uploading') {
        return;
      }

      if (controller) {
        uploadAbortReasons[fieldName] = 'cancelled';
        controller.abort();
        setUploadUi(input, 'uploading', 'Cancelando upload…');
      }
    }

    function initializeFileUi(input) {
      var wrapper = input.closest('.fafar-file-field');
      var statusElement;
      var actions;
      var retryButton;
      var cancelButton;
      var removeButton;
      var describedBy;

      if (!wrapper || wrapper.querySelector('.fafar-upload-ui')) {
        return;
      }

      statusElement = document.createElement('p');
      statusElement.className = 'fafar-upload-status';
      statusElement.id = input.id + '-upload-status';
      statusElement.setAttribute('role', 'status');
      statusElement.setAttribute('aria-live', 'polite');
      statusElement.hidden = true;

      actions = document.createElement('div');
      actions.className = 'fafar-upload-actions';

      retryButton = document.createElement('button');
      retryButton.type = 'button';
      retryButton.className = 'fafar-upload-action';
      retryButton.setAttribute('data-upload-action', 'retry');
      retryButton.textContent = 'Tentar novamente';
      retryButton.hidden = true;
      retryButton.addEventListener('click', function () {
        clearFormError();
        setStatus('', '');
        uploadFile(input);
      });

      cancelButton = document.createElement('button');
      cancelButton.type = 'button';
      cancelButton.className = 'fafar-upload-action';
      cancelButton.setAttribute('data-upload-action', 'cancel');
      cancelButton.textContent = 'Cancelar upload';
      cancelButton.hidden = true;
      cancelButton.addEventListener('click', function () {
        cancelUpload(input);
      });

      removeButton = document.createElement('button');
      removeButton.type = 'button';
      removeButton.className = 'fafar-upload-action';
      removeButton.setAttribute('data-upload-action', 'remove');
      removeButton.textContent = 'Remover arquivo';
      removeButton.hidden = true;
      removeButton.addEventListener('click', function () {
        resetFileInput(input, true);
      });

      actions.appendChild(retryButton);
      actions.appendChild(cancelButton);
      actions.appendChild(removeButton);

      var ui = document.createElement('div');
      ui.className = 'fafar-upload-ui';
      ui.appendChild(statusElement);
      ui.appendChild(actions);
      wrapper.appendChild(ui);

      describedBy = (input.getAttribute('aria-describedby') || '').split(/\s+/).filter(Boolean);
      if (describedBy.indexOf(statusElement.id) === -1) {
        describedBy.push(statusElement.id);
        input.setAttribute('aria-describedby', describedBy.join(' '));
      }

      retryButton.setAttribute('aria-label', 'Tentar novamente o upload de ' + fieldLabel(input));
      cancelButton.setAttribute('aria-label', 'Cancelar o upload de ' + fieldLabel(input));
      removeButton.setAttribute('aria-label', 'Remover o arquivo de ' + fieldLabel(input));
      setUploadUi(input, 'idle', '');
    }

    function clearField(field) {
      var wrapper;

      if (field.type === 'file') {
        field.value = '';
        removeUpload(field.name, 'hidden');
        setUploadUi(field, 'idle', '');
      } else if (field.type !== 'radio' && field.type !== 'checkbox') {
        field.value = '';
      }
      field.setCustomValidity('');
      clearFieldError(field);
      wrapper = field.closest('.fafar-field, .fafar-file-field');
      if (wrapper) {
        wrapper.classList.remove('is-invalid');
      }
    }

    function syncConditionals() {
      conditionalGroups.forEach(function (group) {
        var visible = conditionMatches(group.getAttribute('data-condition'));
        var fields = group.querySelectorAll('input, select, textarea');

        group.hidden = !visible;
        group.setAttribute('aria-hidden', visible ? 'false' : 'true');

        Array.prototype.forEach.call(fields, function (field) {
          if (field.hasAttribute('data-required')) {
            field.required = visible && field.getAttribute('data-required') === 'true';
          }

          if (!visible) {
            clearField(field);
          }
        });
      });

      updateSubmitAvailability();
    }

    function digits(value) {
      return value.replace(/\D/g, '');
    }

    function applyMask(input, mask) {
      var original = input.value;
      var value = digits(original);
      var cursor = input.selectionStart;
      var digitsBeforeCursor = cursor === null ? 0 : digits(original.slice(0, cursor)).length;

      if (mask === 'cpf') {
        value = value.slice(0, 11);
        value = value.replace(/(\d{3})(\d)/, '$1.$2');
        value = value.replace(/(\d{3})(\d)/, '$1.$2');
        value = value.replace(/(\d{3})(\d{1,2})$/, '$1-$2');
      } else if (mask === 'cep') {
        value = value.slice(0, 8);
        value = value.replace(/(\d{5})(\d)/, '$1-$2');
      } else if (mask === 'telefone') {
        value = value.slice(0, 11);
        if (value.length > 10) {
          value = value.replace(/(\d{2})(\d{5})(\d{1,4})/, '($1) $2-$3');
        } else {
          value = value.replace(/(\d{2})(\d{4})(\d{1,4})/, '($1) $2-$3');
        }
      }

      /* Não reatribui o mesmo valor: alguns navegadores perdem o foco/cursor
       * quando value é definido dentro do próprio evento input. */
      if (value === original) {
        return;
      }

      input.value = value;

      if (document.activeElement === input && input.setSelectionRange) {
        var nextCursor = 0;
        var seenDigits = 0;
        while (nextCursor < value.length && seenDigits < digitsBeforeCursor) {
          if (/\d/.test(value.charAt(nextCursor))) {
            seenDigits += 1;
          }
          nextCursor += 1;
        }
        input.setSelectionRange(nextCursor, nextCursor);
      }
    }

    function fileExtension(file) {
      var parts = file.name.toLowerCase().split('.');
      return parts.length > 1 ? parts.pop() : '';
    }

    function validateFile(input) {
      if (!input.files || !input.files.length) {
        input.setCustomValidity('');
        clearFieldError(input);
        setUploadUi(input, 'idle', '');
        return true;
      }

      var file = input.files[0];
      var maxSize = parseInt(input.getAttribute('data-max-size') || '0', 10);
      var extension = fileExtension(file);
      var isImage = ['jpg', 'jpeg', 'png'].indexOf(extension) !== -1;
      var isPdf = extension === 'pdf';
      var accept = input.getAttribute('accept') || '';
      var accepted = accept.indexOf('image/') !== -1 ? isImage : (accept.indexOf('application/pdf') !== -1 ? isPdf : true);
      var error = '';
      var wrapper;

      if (maxSize && file.size > maxSize) {
        error = 'O arquivo deve ter no máximo 8 MB.';
      } else if (!accepted) {
        error = 'Selecione um arquivo no formato solicitado.';
      }

      input.setCustomValidity(error);
      wrapper = input.closest('.fafar-file-field');
      if (wrapper) {
        wrapper.classList.toggle('is-invalid', Boolean(error));
      }
      if (error) {
        setFieldError(input, error);
        setUploadUi(input, 'error', error);
      } else {
        clearFieldError(input);
      }
      return !error;
    }

    function setStatus(message, kind) {
      status.textContent = message;
      status.className = 'fafar-form-status' + (kind ? ' is-' + kind : '');
      status.hidden = !message;
    }

    function uploadFile(input) {
      var file = input.files && input.files[0];
      var fieldName = input.name;
      var sequence;
      var requestData;
      var controller = null;
      var fetchOptions;

      removeUpload(fieldName, 'replaced');
      sequence = uploadSequence[fieldName];

      if (!file) {
        input.setCustomValidity('');
        clearFieldError(input);
        setUploadUi(input, 'idle', '');
        updateSubmitAvailability();
        return;
      }

      if (!validateFile(input)) {
        uploadStates[fieldName] = 'error';
        updateSubmitAvailability();
        return;
      }

      if (!config.ajaxUrl || !config.nonce || typeof window.fetch !== 'function') {
        var unavailableMessage = 'O upload assíncrono não está disponível neste momento.';
        input.setCustomValidity(unavailableMessage);
        uploadStates[fieldName] = 'error';
        setFieldError(input, unavailableMessage);
        setUploadUi(input, 'error', unavailableMessage);
        setStatus(unavailableMessage, 'error');
        updateSubmitAvailability();
        return;
      }

      uploadStates[fieldName] = 'uploading';
      input.setCustomValidity('O upload ainda está em andamento.');
      clearFieldError(input);
      setUploadUi(input, 'uploading', 'Enviando ' + file.name + '…');
      setStatus('Enviando ' + file.name + '…', '');
      updateSubmitAvailability();

      requestData = new FormData();
      requestData.append('action', 'ppgcf_upload_file');
      requestData.append('nonce', config.nonce);
      requestData.append('schedule_context', config.scheduleContext || 'production');
      requestData.append('field_name', fieldName);
      requestData.append('file', file);

      fetchOptions = {
        method: 'POST',
        body: requestData,
        credentials: 'same-origin'
      };

      if (typeof window.AbortController === 'function') {
        controller = new window.AbortController();
        uploadControllers[fieldName] = controller;
        fetchOptions.signal = controller.signal;
        uploadTimeouts[fieldName] = window.setTimeout(function () {
          if (uploadControllers[fieldName] === controller) {
            uploadAbortReasons[fieldName] = 'timeout';
            controller.abort();
          }
        }, uploadTimeoutMs);
      }

      window.fetch(config.ajaxUrl, fetchOptions).then(function (response) {
        return parseJsonResponse(response);
      }).then(function (response) {
        if (uploadSequence[fieldName] !== sequence) {
          return;
        }

        if (!response.success) {
          throw responseError(response, 'Não foi possível enviar o arquivo.');
        }

        finishUploadRequest(fieldName, controller);
        uploadedFiles[fieldName] = response.data.token;
        uploadStates[fieldName] = 'ready';
        input.setCustomValidity('');
        clearFieldError(input);
        setUploadUi(input, 'ready', 'Arquivo enviado com sucesso.');
        setStatus('Arquivo enviado: ' + file.name, 'success');
        updateSubmitAvailability();
      }).catch(function (error) {
        var abortReason;
        var message;

        if (uploadSequence[fieldName] !== sequence) {
          return;
        }

        abortReason = uploadAbortReasons[fieldName] || '';
        finishUploadRequest(fieldName, controller);
        if (abortReason === 'timeout') {
          message = 'O upload excedeu o tempo limite. Tente novamente.';
        } else if (abortReason === 'cancelled') {
          message = 'Upload cancelado. Tente novamente ou remova o arquivo.';
        } else {
          message = friendlyRequestError(error, 'Não foi possível enviar o arquivo.');
        }

        uploadStates[fieldName] = 'error';
        input.setCustomValidity(message);
        setFieldError(input, message);
        setUploadUi(input, 'error', message);
        setStatus('Um ou mais arquivos não puderam ser enviados.', 'error');
        updateSubmitAvailability();
      });
    }

    function allRequiredUploadsReady() {
      var ready = true;
      form.querySelectorAll('input[type="file"]').forEach(function (input) {
        if (input.required && (!input.files || !input.files.length || !uploadedFiles[input.name])) {
          ready = false;
        }
        if (input.files && input.files.length && uploadStates[input.name] !== 'ready') {
          ready = false;
        }
      });
      return ready;
    }

    function updateSubmitAvailability() {
      var blocked = form.getAttribute('data-submitted') === 'true' || form.getAttribute('data-submitting') === 'true' || !allRequiredUploadsReady();
      if (submitButton) {
        submitButton.disabled = blocked;
        submitButton.setAttribute('aria-disabled', blocked ? 'true' : 'false');
      }
    }

    function serializeCandidateData() {
      var data = {};
      form.querySelectorAll('input, select, textarea').forEach(function (field) {
        if (!field.name || field.type === 'file' || field.type === 'submit' || field.type === 'button' || (field.type === 'radio' && !field.checked)) {
          return;
        }
        data[field.name] = field.value;
      });
      return data;
    }

    function collectValidationErrors() {
      form.querySelectorAll('input, select, textarea').forEach(function (field) {
        var hiddenGroup = field.closest('[data-condition][hidden]');

        if (field.disabled || hiddenGroup || !field.willValidate) {
          clearFieldError(field);
          return;
        }

        if (!field.checkValidity()) {
          setFieldError(field, field.validity.valueMissing ? 'Este campo é obrigatório.' : (field.validationMessage || 'Valor inválido.'));
        } else if (uploadStates[field.name] !== 'error') {
          clearFieldError(field);
        }
      });
    }

    function findFieldByName(name) {
      var fields = form.querySelectorAll('input, select, textarea');
      var found = null;

      Array.prototype.some.call(fields, function (field) {
        if (field.name === name) {
          found = field;
          return true;
        }
        return false;
      });
      return found;
    }

    function finishSubmitRequest() {
      if (submitTimeout) {
        window.clearTimeout(submitTimeout);
        submitTimeout = null;
      }
      submitController = null;
      form.removeAttribute('data-submitting');
      form.removeAttribute('aria-busy');
    }

    function lockSubmittedForm(message) {
      form.setAttribute('data-submitted', 'true');
      form.removeAttribute('data-submitting');
      form.removeAttribute('aria-busy');
      form.querySelectorAll('input, select, textarea, button').forEach(function (control) {
        control.disabled = true;
      });
      if (submitButton) {
        submitButton.textContent = 'Inscrição enviada';
        submitButton.disabled = true;
        submitButton.setAttribute('aria-disabled', 'true');
      }
      setStatus(message || 'Inscrição registrada com sucesso.', 'success');
    }

    function applySubmissionFieldError(error, message) {
      var field = error.field ? findFieldByName(error.field) : null;

      if (!field) {
        setFormError(message);
        return false;
      }

      field.setCustomValidity(message);
      setFieldError(field, message);
      if (field.type === 'file') {
        delete uploadedFiles[field.name];
        uploadStates[field.name] = 'error';
        setUploadUi(field, 'error', message);
      }
      return true;
    }

    function submitForm() {
      var requestData;
      var fetchOptions;

      if (form.getAttribute('data-submitted') === 'true' || form.getAttribute('data-submitting') === 'true') {
        return;
      }

      clearFormError();

      if (!allRequiredUploadsReady()) {
        collectValidationErrors();
        setStatus('Faça o upload de todos os documentos obrigatórios antes de enviar.', 'error');
        return;
      }

      if (!form.checkValidity()) {
        collectValidationErrors();
        setStatus('Revise os campos destacados antes de continuar.', 'error');
        form.reportValidity();
        return;
      }

      if (!config.ajaxUrl || !config.nonce || typeof window.fetch !== 'function') {
        var unavailableMessage = 'O envio não está disponível neste momento.';
        setFormError(unavailableMessage);
        setStatus(unavailableMessage, 'error');
        return;
      }

      requestData = new FormData();
      requestData.append('action', 'ppgcf_submit_form');
      requestData.append('nonce', config.nonce);
      requestData.append('schedule_context', config.scheduleContext || 'production');
      requestData.append('candidate_data', JSON.stringify(serializeCandidateData()));
      requestData.append('uploaded_files', JSON.stringify(uploadedFiles));
      setStatus('Registrando inscrição…', '');
      form.setAttribute('data-submitting', 'true');
      form.setAttribute('aria-busy', 'true');
      submitButton.disabled = true;

      fetchOptions = {
        method: 'POST',
        body: requestData,
        credentials: 'same-origin'
      };

      if (typeof window.AbortController === 'function') {
        submitController = new window.AbortController();
        fetchOptions.signal = submitController.signal;
        submitTimeout = window.setTimeout(function () {
          if (submitController) {
            submitController.abort();
          }
        }, submitTimeoutMs);
      }

      window.fetch(config.ajaxUrl, fetchOptions).then(function (response) {
        return parseJsonResponse(response);
      }).then(function (response) {
        if (!response.success) {
          throw responseError(response, 'Não foi possível registrar a inscrição.');
        }
        finishSubmitRequest();
        fieldErrors = {};
        renderErrorSummary();
        lockSubmittedForm(response.data.message || 'Inscrição registrada com sucesso.');
      }).catch(function (error) {
        var message = friendlyRequestError(error, 'Não foi possível registrar a inscrição.');
        finishSubmitRequest();
        applySubmissionFieldError(error, message);
        setStatus('A inscrição não pôde ser enviada.', 'error');
        updateSubmitAvailability();
      });
    }

    form.querySelectorAll('input[type="radio"]').forEach(function (input) {
      input.addEventListener('change', function () {
        form.querySelectorAll('input[type="radio"][name="' + input.name + '"]').forEach(function (radio) {
          radio.setCustomValidity('');
        });
        clearFieldError(input);
        syncConditionals();
        setStatus('', '');
      });
    });

    form.querySelectorAll('[data-fafar-mask]').forEach(function (input) {
      /* A máscara no blur nunca interrompe a digitação, inclusive em
       * teclados móveis e navegadores que reposicionam o cursor ao alterar
       * input.value durante o evento input. */
      input.addEventListener('blur', function () {
        applyMask(input, input.getAttribute('data-fafar-mask'));
      });
    });

    form.querySelectorAll('input[type="file"]').forEach(function (input) {
      initializeFileUi(input);
      input.addEventListener('change', function () {
        uploadFile(input);
      });
    });

    form.addEventListener('input', function (event) {
      if (form.getAttribute('data-submitted') === 'true') {
        return;
      }
      if (event.target.type !== 'file') {
        event.target.setCustomValidity('');
        clearFieldError(event.target);
        clearFormError();
        setStatus('', '');
      }
      updateSubmitAvailability();
    });

    form.addEventListener('invalid', function (event) {
      var field = event.target;
      setFieldError(field, field.validity.valueMissing ? 'Este campo é obrigatório.' : (field.validationMessage || 'Valor inválido.'));
    }, true);

    form.addEventListener('submit', function (event) {
      event.preventDefault();
      submitForm();
    });

    syncConditionals();
  });
}());
