(function ($) {
    'use strict';

    // --- Feed Management ---

    // Add feed
    $('#wpa-add-feed').on('click', function () {
        var name = $('#wpa-feed-name').val().trim();
        var url = $('#wpa-feed-url').val().trim();

        if (!url) {
            $('#wpa-feed-message').text('URL er påkrevd.').removeClass('success').addClass('error');
            return;
        }

        var $btn = $(this);
        var $spinner = $('#wpa-feed-spinner');

        $btn.prop('disabled', true);
        $spinner.addClass('is-active');
        $('#wpa-feed-message').text('');

        $.post(wpaAdmin.ajaxUrl, {
            action: 'wpa_add_feed',
            nonce: wpaAdmin.nonce,
            name: name,
            url: url
        }, function (response) {
            $btn.prop('disabled', false);
            $spinner.removeClass('is-active');

            if (response.success) {
                $('#wpa-feed-name').val('');
                $('#wpa-feed-url').val('');
                renderFeeds(response.data.feeds);
                $('#wpa-feed-message').text('Feed lagt til.').removeClass('error').addClass('success');
            } else {
                $('#wpa-feed-message').text(response.data || 'Feil ved tillegging.').removeClass('success').addClass('error');
            }
        }).fail(function () {
            $btn.prop('disabled', false);
            $spinner.removeClass('is-active');
            $('#wpa-feed-message').text('Nettverksfeil.').removeClass('success').addClass('error');
        });
    });

    // Delete feed
    $(document).on('click', '.wpa-delete-feed', function () {
        if (!confirm('Er du sikker på at du vil slette denne feeden?')) {
            return;
        }

        var feedId = $(this).data('feed-id');

        $.post(wpaAdmin.ajaxUrl, {
            action: 'wpa_delete_feed',
            nonce: wpaAdmin.nonce,
            feed_id: feedId
        }, function (response) {
            if (response.success) {
                renderFeeds(response.data.feeds);
            }
        });
    });

    // Toggle feed
    $(document).on('click', '.wpa-toggle-feed', function () {
        var feedId = $(this).data('feed-id');

        $.post(wpaAdmin.ajaxUrl, {
            action: 'wpa_toggle_feed',
            nonce: wpaAdmin.nonce,
            feed_id: feedId
        }, function (response) {
            if (response.success) {
                renderFeeds(response.data.feeds);
            }
        });
    });

    function renderFeeds(feeds) {
        var $body = $('#wpa-feeds-body');
        $body.empty();

        if (!feeds || feeds.length === 0) {
            $body.append('<tr class="wpa-no-feeds"><td colspan="4">Ingen feeds lagt til ennå.</td></tr>');
            return;
        }

        feeds.forEach(function (feed) {
            var statusClass = feed.active ? 'wpa-active' : 'wpa-inactive';
            var statusText = feed.active ? 'Aktiv' : 'Inaktiv';
            var toggleText = feed.active ? 'Deaktiver' : 'Aktiver';

            var row = '<tr data-feed-id="' + feed.id + '">' +
                '<td>' + escHtml(feed.name) + '</td>' +
                '<td><code>' + escHtml(feed.url) + '</code></td>' +
                '<td><span class="wpa-status-badge ' + statusClass + '">' + statusText + '</span></td>' +
                '<td>' +
                '<button type="button" class="button wpa-toggle-feed" data-feed-id="' + feed.id + '">' + toggleText + '</button> ' +
                '<button type="button" class="button wpa-delete-feed" data-feed-id="' + feed.id + '">Slett</button>' +
                '</td></tr>';

            $body.append(row);
        });
    }

    // --- Run Now ---

    $('#wpa-run-now').on('click', function () {
        var $btn = $(this);
        var $spinner = $('#wpa-action-spinner');
        var $msg = $('#wpa-action-message');

        $btn.prop('disabled', true);
        $spinner.addClass('is-active');
        $msg.text('Kjører autopilot... Dette kan ta noen minutter.').removeClass('error success');

        $.post(wpaAdmin.ajaxUrl, {
            action: 'wpa_run_now',
            nonce: wpaAdmin.nonce
        }, function (response) {
            $btn.prop('disabled', false);
            $spinner.removeClass('is-active');

            if (response.success) {
                $msg.text(response.data.message).removeClass('error').addClass('success');
                refreshLog();
            } else {
                $msg.text(response.data || 'Feil under kjøring.').removeClass('success').addClass('error');
            }
        }).fail(function () {
            $btn.prop('disabled', false);
            $spinner.removeClass('is-active');
            $msg.text('Nettverksfeil eller timeout.').removeClass('success').addClass('error');
        });
    });

    // --- Re-index ---

    $('#wpa-reindex').on('click', function () {
        var $btn = $(this);
        var $spinner = $('#wpa-action-spinner');
        var $msg = $('#wpa-action-message');

        $btn.prop('disabled', true);
        $spinner.addClass('is-active');
        $msg.text('Re-indekserer...').removeClass('error success');

        $.post(wpaAdmin.ajaxUrl, {
            action: 'wpa_reindex',
            nonce: wpaAdmin.nonce
        }, function (response) {
            $btn.prop('disabled', false);
            $spinner.removeClass('is-active');

            if (response.success) {
                $msg.text(response.data.message).removeClass('error').addClass('success');
            } else {
                $msg.text(response.data || 'Feil under re-indeksering.').removeClass('success').addClass('error');
            }
        }).fail(function () {
            $btn.prop('disabled', false);
            $spinner.removeClass('is-active');
            $msg.text('Nettverksfeil.').removeClass('success').addClass('error');
        });
    });

    // --- Log ---

    $('#wpa-refresh-log').on('click', function () {
        refreshLog();
    });

    function refreshLog() {
        $.post(wpaAdmin.ajaxUrl, {
            action: 'wpa_get_log',
            nonce: wpaAdmin.nonce
        }, function (response) {
            if (response.success && response.data.logs) {
                var $body = $('#wpa-log-body');
                $body.empty();

                if (response.data.logs.length === 0) {
                    $body.append('<tr><td colspan="4">Ingen logg-innslag ennå.</td></tr>');
                    return;
                }

                response.data.logs.forEach(function (log) {
                    var context = log.context || '';
                    if (context.length > 100) {
                        context = context.substring(0, 100) + '...';
                    }

                    var row = '<tr>' +
                        '<td>' + escHtml(log.created_at) + '</td>' +
                        '<td><span class="wpa-log-level wpa-log-' + escHtml(log.level) + '">' + escHtml(log.level) + '</span></td>' +
                        '<td>' + escHtml(log.message) + '</td>' +
                        '<td><small>' + escHtml(context) + '</small></td>' +
                        '</tr>';

                    $body.append(row);
                });
            }
        });
    }

    // --- Author Method Toggle ---

    $('#wpa_author_method').on('change', function () {
        var method = $(this).val();
        if (method === 'single') {
            $('#wpa-authors-row').hide();
        } else {
            $('#wpa-authors-row').show();
        }

        if (method === 'percentage') {
            $('.wpa-author-weight').show();
        } else {
            $('.wpa-author-weight').hide();
        }
    });

    // Sync author checkboxes and weights to hidden JSON field.
    function syncAuthorsJSON() {
        var authors = [];
        $('.wpa-author-check:checked').each(function () {
            var id = parseInt($(this).val(), 10);
            var weight = parseInt($(this).closest('.wpa-author-item').find('.wpa-weight-input').val(), 10) || 1;
            authors.push({ id: id, weight: weight });
        });
        $('#wpa_post_authors').val(JSON.stringify(authors));
    }

    $(document).on('change', '.wpa-author-check, .wpa-weight-input', syncAuthorsJSON);

    // Sync on page load.
    if ($('#wpa_post_authors').length) {
        syncAuthorsJSON();
    }

    // --- Inline Images Toggle ---

    $('#wpa_inline_images_enabled').on('change', function () {
        var show = $(this).is(':checked');
        $('#wpa-inline-freq-row, #wpa-inline-model-row, #wpa-inline-custom-row').toggle(show);
    });

    // --- Writing Style Analysis ---

    var writingStyles = (typeof wpaAdmin !== 'undefined' && wpaAdmin.writingStyles) ? wpaAdmin.writingStyles : {};

    // Load style when author changes.
    $('#wpa_style_author').on('change', function () {
        var authorId = $(this).val();
        var style = writingStyles[authorId] || '';
        $('#wpa_writing_style_text').val(style);
        $('#wpa-style-message').text('');
    }).trigger('change');

    // Analyze style button.
    $('#wpa-analyze-style').on('click', function () {
        var $btn = $(this);
        var $spinner = $('#wpa-style-spinner');
        var $msg = $('#wpa-style-message');
        var authorId = $('#wpa_style_author').val();
        var numPosts = $('#wpa_style_num_posts').val();

        $btn.prop('disabled', true);
        $spinner.addClass('is-active');
        $msg.text('Analyserer skrivestil...').removeClass('error success');

        $.post(wpaAdmin.ajaxUrl, {
            action: 'wpa_analyze_style',
            nonce: wpaAdmin.nonce,
            author_id: authorId,
            num_posts: numPosts
        }, function (response) {
            $btn.prop('disabled', false);
            $spinner.removeClass('is-active');

            if (response.success) {
                $('#wpa_writing_style_text').val(response.data.style);
                $msg.text('Analyse fullført.').removeClass('error').addClass('success');
            } else {
                $msg.text(response.data || 'Feil under analyse.').removeClass('success').addClass('error');
            }
        }).fail(function () {
            $btn.prop('disabled', false);
            $spinner.removeClass('is-active');
            $msg.text('Nettverksfeil eller timeout.').removeClass('success').addClass('error');
        });
    });

    // Save style button.
    $('#wpa-save-style').on('click', function () {
        var $btn = $(this);
        var $spinner = $('#wpa-style-spinner');
        var $msg = $('#wpa-style-message');
        var authorId = $('#wpa_style_author').val();
        var style = $('#wpa_writing_style_text').val();

        $btn.prop('disabled', true);
        $spinner.addClass('is-active');

        $.post(wpaAdmin.ajaxUrl, {
            action: 'wpa_save_writing_style',
            nonce: wpaAdmin.nonce,
            author_id: authorId,
            style: style
        }, function (response) {
            $btn.prop('disabled', false);
            $spinner.removeClass('is-active');

            if (response.success) {
                // Update local cache.
                writingStyles[authorId] = style;
                $msg.text('Skrivestil lagret.').removeClass('error').addClass('success');
            } else {
                $msg.text(response.data || 'Feil ved lagring.').removeClass('success').addClass('error');
            }
        }).fail(function () {
            $btn.prop('disabled', false);
            $spinner.removeClass('is-active');
            $msg.text('Nettverksfeil.').removeClass('success').addClass('error');
        });
    });

    // --- Utility ---

    function escHtml(str) {
        if (!str) return '';
        var div = document.createElement('div');
        div.appendChild(document.createTextNode(str));
        return div.innerHTML;
    }

})(jQuery);
