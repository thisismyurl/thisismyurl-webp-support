jQuery(function($) {
    const config = window.TIMUWebPSupportData || {};
    const pendingIds = Array.isArray(config.pendingIds) ? config.pendingIds.slice() : [];
    const strings = config.strings || {};
    const actions = config.actions || {};
    const nonce = config.nonce || '';
    const ajaxUrl = config.ajaxUrl || window.ajaxurl;
    const batchSize = Math.max(1, parseInt(config.batchSize, 10) || 10);

    let completed = 0;
    let isCancelled = false;

    const updateProgress = (total) => {
        const pct = total > 0 ? Math.round((completed / total) * 100) : 100;
        $('#fwo-progress-bar').css('width', pct + '%');
        $('#fwo-progress-text').text(pct + '%');
        $('#p-cnt').text(Math.max(0, total - completed));
    };

    const postJson = (payload) => {
        return $.post(ajaxUrl, payload);
    };

    $(document).on('click', '.restore-btn', function() {
        const $btn = $(this);
        $btn.prop('disabled', true).text('...');

        postJson({
            action: actions.restore,
            attachment_id: $btn.data('id'),
            nonce: nonce
        }).always(function() {
            window.location.reload();
        });
    });

    $('#btn-restore-all').on('click', function() {
        const ids = ($(this).data('ids') || []).slice();
        if (!window.confirm(strings.confirmRestoreAll || 'Restore all images?')) {
            return;
        }

        $(this).prop('disabled', true).text(strings.restoring || 'Restoring...');

        const processRestore = () => {
            if (!ids.length) {
                window.location.reload();
                return;
            }

            postJson({
                action: actions.restore,
                attachment_id: ids.shift(),
                nonce: nonce
            }).always(processRestore);
        };

        processRestore();
    });

    $('#btn-start').on('click', function() {
        const $btn = $(this);
        const total = pendingIds.length;

        if (!total) {
            return;
        }

        $btn.prop('disabled', true).text(strings.processing || 'Processing...');
        $('#btn-cancel').show();
        $('#fwo-progress-container').fadeIn();

        const processNextBatch = () => {
            if (isCancelled || !pendingIds.length) {
                return;
            }

            const currentBatch = pendingIds.splice(0, batchSize);
            postJson({
                action: actions.batch,
                attachment_ids: currentBatch,
                nonce: nonce
            }).done(function(res) {
                if (!res || !res.success || !res.data) {
                    isCancelled = true;
                    return;
                }

                const processed = Array.isArray(res.data.processed_ids) ? res.data.processed_ids : [];
                const failed = Array.isArray(res.data.failed_ids) ? res.data.failed_ids : [];
                const errors = Array.isArray(res.data.errors) ? res.data.errors : [];

                processed.forEach(function(id) {
                    completed += 1;
                    $('#fwo-row-' + id).remove();
                });

                failed.forEach(function(id) {
                    completed += 1;
                    $('#fwo-row-' + id).remove();
                });

                if (errors.length) {
                    const prefix = strings.failedPrefix || 'Some images failed:';
                    window.alert(prefix + '\n- ' + errors.join('\n- '));
                }

                updateProgress(total);

                if (!pendingIds.length) {
                    window.location.reload();
                    return;
                }
            }).always(processNextBatch);
        };

        processNextBatch();
    });

    $('#btn-cancel').on('click', function() {
        isCancelled = true;
        window.location.reload();
    });
});
