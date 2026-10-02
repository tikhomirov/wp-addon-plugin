/**
 * Логика страницы бэкапов.
 *
 * Долгие операции (дамп и восстановление) выполняются одним AJAX-запросом,
 * поэтому прогресс-полоса отражает факт завершения этапа, а не процент
 * обработанных строк: сервер не отдаёт статус по ходу работы.
 */
(function ($) {
    'use strict';

    function post(action, data) {
        return $.ajax({
            url: wpAddonBackup.ajaxUrl,
            type: 'POST',
            dataType: 'json',
            data: $.extend({ action: action, nonce: wpAddonBackup.nonce }, data || {})
        });
    }

    var statusBox = $('#wp-addon-backup-status');
    var progress = $('#wp-addon-backup-progress');
    var bar = progress.find('.wp-backup__progress-bar');

    function setStatus(message, kind) {
        statusBox
            .removeClass('wp-backup__status--error wp-backup__status--success')
            .addClass(kind ? 'wp-backup__status--' + kind : '')
            .text(message);
    }

    function setProgress(percent) {
        if (percent < 0 || percent > 100) {
            return;
        }

        progress.prop('hidden', false);
        bar.css('width', percent + '%');
    }

    function hideProgress() {
        progress.prop('hidden', true);
        bar.css('width', '0');
    }

    function setBusy(busy) {
        statusBox.toggleClass('wp-backup__status--busy', busy);
        $('.wp-backup__actions button, #wp-addon-backup-restore, #wp-addon-backup-delete, #wp-addon-backup-upload-form button')
            .prop('disabled', busy);
    }

    function withBusy(button, task) {
        setBusy(true);
        setStatus(wpAddonBackup.strings.working, null);

        return task()
            .done(function (response) {
                if (response && response.success && response.data) {
                    setStatus(response.data.message, 'success');
                } else {
                    var message = response && response.data && response.data.message
                        ? response.data.message
                        : 'Ошибка запроса.';
                    setStatus(message, 'error');
                }
            })
            .fail(function (xhr) {
                var payload = xhr && xhr.responseJSON && xhr.responseJSON.data;
                setStatus(payload && payload.message ? payload.message : 'Ошибка соединения с сервером.', 'error');
            })
            .always(function () {
                setBusy(false);
                hideProgress();
            });
    }

    function refreshList() {
        return post('wp_backup_list')
            .done(function (response) {
                if (response && response.success) {
                    $('#wp-addon-backup-list').html(response.data.html);
                    fillSelect(response.data.html);
                }
            });
    }

    /**
     * Наполняет select файлами из таблицы: имена берём из data-backup,
     * чтобы не дублировать разметку и не сломать экранирование.
     */
    function fillSelect(html) {
        var select = $('#wp-addon-backup-select');
        var current = select.val();
        select.empty();

        $('<div>').html(html).find('[data-backup]').each(function () {
            var name = $(this).attr('data-backup');
            select.append($('<option>').attr('value', name).text(name));
        });

        if (current) {
            select.val(current);
        }

        select.prop('disabled', select.find('option').length === 0);
    }

    $('#wp-addon-backup-create').on('click', function () {
        withBusy($(this), function () {
            setProgress(10);
            return post('wp_backup_create');
        });
    });

    $('#wp-addon-backup-restore').on('click', function () {
        var backup = $('#wp-addon-backup-select').val();

        if (!backup) {
            setStatus('Сначала создайте или загрузите дамп.', 'error');
            return;
        }

        if (!window.confirm(wpAddonBackup.strings.confirmRestore)) {
            return;
        }

        withBusy($(this), function () {
            setProgress(10);
            return post('wp_backup_restore', { backup: backup });
        });
    });

    $('#wp-addon-backup-list').on('click', '.wp-addon-backup-delete', function () {
        var backup = $(this).attr('data-backup');

        if (!window.confirm(wpAddonBackup.strings.confirmDelete)) {
            return;
        }

        var button = $(this);

        setBusy(true);
        setStatus(wpAddonBackup.strings.working, null);

        post('wp_backup_delete', { backup: backup })
            .done(function (response) {
                if (response && response.success) {
                    setStatus(response.data.message, 'success');
                } else {
                    setStatus('Не удалось удалить файл.', 'error');
                }
                refreshList();
            })
            .fail(function () {
                setStatus('Не удалось удалить файл.', 'error');
            })
            .always(function () {
                setBusy(false);
                button.prop('disabled', false);
            });
    });

    $('#wp-addon-backup-upload-form').on('submit', function (event) {
        event.preventDefault();

        var form = this;
        var input = $('#wp-addon-backup-upload');

        if (!input.val()) {
            setStatus('Выберите файл дампа.', 'error');
            return;
        }

        setBusy(true);
        setStatus(wpAddonBackup.strings.working, null);

        var payload = new FormData();
        payload.append('action', 'wp_backup_upload');
        payload.append('nonce', wpAddonBackup.nonce);
        payload.append('backup_file', input[0].files[0]);

        $.ajax({
            url: wpAddonBackup.ajaxUrl,
            type: 'POST',
            data: payload,
            processData: false,
            contentType: false,
            dataType: 'json'
        })
            .done(function (response) {
                if (response && response.success) {
                    setStatus(response.data.message, 'success');
                    form.reset();
                    refreshList();
                } else {
                    var message = response && response.data && response.data.message
                        ? response.data.message
                        : 'Не удалось загрузить файл.';
                    setStatus(message, 'error');
                }
            })
            .fail(function (xhr) {
                var payloadError = xhr && xhr.responseJSON && xhr.responseJSON.data;
                setStatus(payloadError && payloadError.message ? payloadError.message : 'Ошибка загрузки.', 'error');
            })
            .always(function () {
                setBusy(false);
            });
    });

    $(function () {
        fillSelect($('#wp-addon-backup-list').html());
    });
})(jQuery);
