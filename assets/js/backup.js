/**
 * Логика секции «Backup DB» на странице настроек WP Addon.
 *
 * Долгие операции (дамп и восстановление) выполняются одним AJAX-запросом,
 * поэтому прогресс-полоса отражает факт завершения этапа, а не процент
 * обработанных строк: сервер не отдаёт статус по ходу работы.
 */
(function ($) {
    'use strict';

    if (typeof wpAddonBackup === 'undefined') {
        return;
    }

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
    var bar = progress.find('.wp-addon-backup__progress-bar');

    function setStatus(message, kind) {
        statusBox
            .removeClass('wp-addon-backup__status--error wp-addon-backup__status--success')
            .addClass(kind ? 'wp-addon-backup__status--' + kind : '')
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
        statusBox.toggleClass('wp-addon-backup__status--busy', busy);
        $('.wp-addon-backup__actions button, #wp-addon-backup-restore, #wp-addon-backup-upload-btn, .wp-addon-backup-delete')
            .prop('disabled', busy);
    }

    function withBusy(task) {
        setBusy(true);
        setStatus(wpAddonBackup.strings.working, null);

        return task()
            .done(function (response) {
                if (response && response.success) {
                    setStatus(response.data && response.data.message ? response.data.message : 'Готово.', 'success');
                } else {
                    var payload = response && response.data;
                    setStatus(payload && payload.message ? payload.message : 'Операция не выполнена.', 'error');
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
        return post('wp_addon_backup_list')
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
        select.append($('<option>').attr('value', '').text('Выберите файл дампа…'));

        $('<div>').html(html).find('[data-backup]').each(function () {
            var name = $(this).attr('data-backup');
            select.append($('<option>').attr('value', name).text(name));
        });

        if (current) {
            select.val(current);
        }
    }

    $('#wp-addon-backup-create').on('click', function () {
        withBusy(function () {
            setProgress(10);
            return post('wp_addon_backup_create');
        }).done(refreshList);
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

        withBusy(function () {
            setProgress(10);
            return post('wp_addon_backup_restore', { backup: backup });
        }).done(refreshList);
    });

    $('#wp-addon-backup-list').on('click', '.wp-addon-backup-delete', function () {
        var backup = $(this).attr('data-backup');

        if (!window.confirm(wpAddonBackup.strings.confirmDelete)) {
            return;
        }

        setBusy(true);
        setStatus(wpAddonBackup.strings.working, null);

        post('wp_addon_backup_delete', { backup: backup })
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
            });
    });

    $('#wp-addon-backup-upload-btn').on('click', function () {
        var input = $('#wp-addon-backup-upload');

        if (!input.val()) {
            setStatus('Выберите файл дампа.', 'error');
            return;
        }

        setBusy(true);
        setStatus(wpAddonBackup.strings.working, null);

        var payload = new FormData();
        payload.append('action', 'wp_addon_backup_upload');
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
                    input.val('');
                    refreshList();
                } else {
                    var payloadError = response && response.data;
                    setStatus(payloadError && payloadError.message ? payloadError.message : 'Ошибка загрузки.', 'error');
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
