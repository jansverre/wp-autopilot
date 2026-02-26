(function ($) {
    'use strict';

    var i18n = (typeof wpaAdmin !== 'undefined' && wpaAdmin.i18n) ? wpaAdmin.i18n : {};

    // --- Feed Management ---

    // Add feed
    $('#wpa-add-feed').on('click', function () {
        var name = $('#wpa-feed-name').val().trim();
        var url = $('#wpa-feed-url').val().trim();

        if (!url) {
            $('#wpa-feed-message').text(i18n.urlRequired || 'URL is required.').removeClass('success').addClass('error');
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
                $('#wpa-feed-message').text(i18n.feedAdded || 'Feed added.').removeClass('error').addClass('success');
            } else {
                $('#wpa-feed-message').text(response.data || i18n.errorAdding || 'Error adding feed.').removeClass('success').addClass('error');
            }
        }).fail(function () {
            $btn.prop('disabled', false);
            $spinner.removeClass('is-active');
            $('#wpa-feed-message').text(i18n.networkError || 'Network error.').removeClass('success').addClass('error');
        });
    });

    // Delete feed
    $(document).on('click', '.wpa-delete-feed', function () {
        if (!confirm(i18n.confirmDeleteFeed || 'Are you sure you want to delete this feed?')) {
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
            $body.append('<tr class="wpa-no-feeds"><td colspan="4">' + escHtml(i18n.noFeeds || 'No feeds added yet.') + '</td></tr>');
            return;
        }

        feeds.forEach(function (feed) {
            var statusClass = feed.active ? 'wpa-active' : 'wpa-inactive';
            var statusText = feed.active ? (i18n.active || 'Active') : (i18n.inactive || 'Inactive');
            var toggleText = feed.active ? (i18n.deactivate || 'Deactivate') : (i18n.activate || 'Activate');

            var row = '<tr data-feed-id="' + feed.id + '">' +
                '<td>' + escHtml(feed.name) + '</td>' +
                '<td><code>' + escHtml(feed.url) + '</code></td>' +
                '<td><span class="wpa-status-badge ' + statusClass + '">' + statusText + '</span></td>' +
                '<td>' +
                '<button type="button" class="button wpa-toggle-feed" data-feed-id="' + feed.id + '">' + toggleText + '</button> ' +
                '<button type="button" class="button wpa-delete-feed" data-feed-id="' + feed.id + '">' + escHtml(i18n.delete || 'Delete') + '</button>' +
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
        $msg.text(i18n.runningAutopilot || 'Running autopilot... This may take a few minutes.').removeClass('error success');

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
                $msg.text(response.data || i18n.errorRunning || 'Error during run.').removeClass('success').addClass('error');
            }
        }).fail(function () {
            $btn.prop('disabled', false);
            $spinner.removeClass('is-active');
            $msg.text(i18n.networkOrTimeout || 'Network error or timeout.').removeClass('success').addClass('error');
        });
    });

    // --- Re-index ---

    $('#wpa-reindex').on('click', function () {
        var $btn = $(this);
        var $spinner = $('#wpa-action-spinner');
        var $msg = $('#wpa-action-message');

        $btn.prop('disabled', true);
        $spinner.addClass('is-active');
        $msg.text(i18n.reindexing || 'Re-indexing...').removeClass('error success');

        $.post(wpaAdmin.ajaxUrl, {
            action: 'wpa_reindex',
            nonce: wpaAdmin.nonce
        }, function (response) {
            $btn.prop('disabled', false);
            $spinner.removeClass('is-active');

            if (response.success) {
                $msg.text(response.data.message).removeClass('error').addClass('success');
            } else {
                $msg.text(response.data || i18n.errorReindexing || 'Error during re-indexing.').removeClass('success').addClass('error');
            }
        }).fail(function () {
            $btn.prop('disabled', false);
            $spinner.removeClass('is-active');
            $msg.text(i18n.networkError || 'Network error.').removeClass('success').addClass('error');
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
                    $body.append('<tr><td colspan="4">' + escHtml(i18n.noLogEntries || 'No log entries yet.') + '</td></tr>');
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

    // Use a plain object for the cache (PHP may send [] for empty, which is an array in JS).
    var rawStyles = (typeof wpaAdmin !== 'undefined' && wpaAdmin.writingStyles) ? wpaAdmin.writingStyles : {};
    var writingStyles = {};
    if (rawStyles && typeof rawStyles === 'object') {
        for (var k in rawStyles) {
            if (rawStyles.hasOwnProperty(k)) {
                writingStyles[k] = rawStyles[k];
            }
        }
    }

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
        $msg.text(i18n.analyzingStyle || 'Analyzing writing style...').removeClass('error success');

        $.post(wpaAdmin.ajaxUrl, {
            action: 'wpa_analyze_style',
            nonce: wpaAdmin.nonce,
            author_id: authorId,
            num_posts: numPosts
        }, function (response) {
            $btn.prop('disabled', false);
            $spinner.removeClass('is-active');

            if (response.success) {
                var style = response.data.style;
                $('#wpa_writing_style_text').val(style);
                // Update local cache so switching authors and back preserves the text.
                writingStyles[authorId] = style;
                $msg.text(i18n.analysisComplete || 'Analysis complete. Click "Save writing style" to keep it.').removeClass('error').addClass('success');
            } else {
                $msg.text(response.data || i18n.errorAnalysis || 'Error during analysis.').removeClass('success').addClass('error');
            }
        }).fail(function () {
            $btn.prop('disabled', false);
            $spinner.removeClass('is-active');
            $msg.text(i18n.networkOrTimeout || 'Network error or timeout.').removeClass('success').addClass('error');
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
                // Update local cache with the server-confirmed styles.
                if (response.data.writing_styles) {
                    writingStyles = response.data.writing_styles;
                } else {
                    writingStyles[authorId] = style;
                }
                // Re-set textarea from cache to ensure it stays.
                $('#wpa_writing_style_text').val(writingStyles[authorId] || '');
                $msg.text(i18n.styleSaved || 'Writing style saved.').removeClass('error').addClass('success');
            } else {
                $msg.text(response.data || i18n.errorSaving || 'Error saving.').removeClass('success').addClass('error');
            }
        }).fail(function () {
            $btn.prop('disabled', false);
            $spinner.removeClass('is-active');
            $msg.text(i18n.networkError || 'Network error.').removeClass('success').addClass('error');
        });
    });

    // --- Facebook Sharing ---

    // Toggle FB settings visibility.
    $('#wpa_fb_enabled').on('change', function () {
        $('#wpa-fb-settings').toggle($(this).is(':checked'));
    });

    // Toggle poster settings visibility.
    $('#wpa_fb_image_mode').on('change', function () {
        $('#wpa-fb-poster-settings').toggle($(this).val() === 'generated_poster');
    });

    // Toggle author photos visibility.
    $('#wpa_fb_author_face').on('change', function () {
        $('#wpa-fb-author-photos').toggle($(this).is(':checked'));
    });

    // Test Facebook connection.
    $('#wpa-test-fb').on('click', function () {
        var $btn = $(this);
        var $spinner = $('#wpa-fb-test-spinner');
        var $msg = $('#wpa-fb-test-message');
        var pageId = $('#wpa_fb_page_id').val().trim();
        var token = $('#wpa_fb_access_token').val().trim();

        if (!pageId || !token) {
            $msg.text(i18n.fillPageIdAndToken || 'Please enter Page ID and access token.').removeClass('success').addClass('error');
            return;
        }

        $btn.prop('disabled', true);
        $spinner.addClass('is-active');
        $msg.text('').removeClass('error success');

        $.post(wpaAdmin.ajaxUrl, {
            action: 'wpa_test_fb',
            nonce: wpaAdmin.nonce,
            page_id: pageId,
            access_token: token
        }, function (response) {
            $btn.prop('disabled', false);
            $spinner.removeClass('is-active');

            if (response.success) {
                $msg.text((i18n.connected || 'Connected:') + ' ' + response.data.name + ' (ID: ' + response.data.id + ')').removeClass('error').addClass('success');
            } else {
                $msg.text(response.data || i18n.connectionError || 'Connection error.').removeClass('success').addClass('error');
            }
        }).fail(function () {
            $btn.prop('disabled', false);
            $spinner.removeClass('is-active');
            $msg.text(i18n.networkError || 'Network error.').removeClass('success').addClass('error');
        });
    });

    // WP Media uploader for author photos.
    $(document).on('click', '.wpa-fb-upload-photo', function (e) {
        e.preventDefault();
        var authorId = $(this).data('author-id');
        var $preview = $('.wpa-fb-photo-preview[data-author-id="' + authorId + '"]');
        var $input = $('.wpa-fb-photo-input[data-author-id="' + authorId + '"]');
        var $removeBtn = $('.wpa-fb-remove-photo[data-author-id="' + authorId + '"]');

        var frame = wp.media({
            title: i18n.selectAuthorPhoto || 'Select author photo',
            button: { text: i18n.useThisImage || 'Use this image' },
            multiple: false,
            library: { type: 'image' }
        });

        frame.on('select', function () {
            var attachment = frame.state().get('selection').first().toJSON();
            var thumbUrl = attachment.sizes && attachment.sizes.thumbnail
                ? attachment.sizes.thumbnail.url
                : attachment.url;

            $preview.html('<img src="' + thumbUrl + '" style="max-width: 60px; max-height: 60px; border-radius: 4px;">');
            $input.val(attachment.id);
            $removeBtn.show();
        });

        frame.open();
    });

    // Remove author photo.
    $(document).on('click', '.wpa-fb-remove-photo', function (e) {
        e.preventDefault();
        var authorId = $(this).data('author-id');
        var $preview = $('.wpa-fb-photo-preview[data-author-id="' + authorId + '"]');
        var $input = $('.wpa-fb-photo-input[data-author-id="' + authorId + '"]');

        $preview.html('<span class="dashicons dashicons-format-image" style="font-size: 40px; color: #ccc;"></span>');
        $input.val('');
        $(this).hide();
    });

    // --- License Activation ---

    $('#wpa-activate-license').on('click', function () {
        var $btn = $(this);
        var $spinner = $('#wpa-license-spinner');
        var $msg = $('#wpa-license-message');
        var key = $('#wpa-license-key-input').val().trim();

        if (!key) {
            $msg.text(i18n.licenseKeyRequired || 'Please enter a license key.').removeClass('success').addClass('error');
            return;
        }

        $btn.prop('disabled', true);
        $spinner.addClass('is-active');
        $msg.text(i18n.licenseActivating || 'Activating...').removeClass('error success');

        $.post(wpaAdmin.ajaxUrl, {
            action: 'wpa_activate_license',
            nonce: wpaAdmin.nonce,
            license_key: key
        }, function (response) {
            $btn.prop('disabled', false);
            $spinner.removeClass('is-active');

            if (response.success) {
                $msg.text(response.data.message).removeClass('error').addClass('success');
                // Reload to update UI state.
                setTimeout(function () { location.reload(); }, 1500);
            } else {
                $msg.text(response.data || 'Activation failed.').removeClass('success').addClass('error');
            }
        }).fail(function () {
            $btn.prop('disabled', false);
            $spinner.removeClass('is-active');
            $msg.text(i18n.networkError || 'Network error.').removeClass('success').addClass('error');
        });
    });

    $('#wpa-deactivate-license').on('click', function () {
        var $btn = $(this);
        var $spinner = $('#wpa-license-spinner');
        var $msg = $('#wpa-license-message');

        $btn.prop('disabled', true);
        $spinner.addClass('is-active');
        $msg.text(i18n.licenseDeactivating || 'Deactivating...').removeClass('error success');

        $.post(wpaAdmin.ajaxUrl, {
            action: 'wpa_deactivate_license',
            nonce: wpaAdmin.nonce
        }, function (response) {
            $btn.prop('disabled', false);
            $spinner.removeClass('is-active');

            if (response.success) {
                $msg.text(response.data.message).removeClass('error').addClass('success');
                setTimeout(function () { location.reload(); }, 1500);
            } else {
                $msg.text(response.data || 'Error.').removeClass('success').addClass('error');
            }
        }).fail(function () {
            $btn.prop('disabled', false);
            $spinner.removeClass('is-active');
            $msg.text(i18n.networkError || 'Network error.').removeClass('success').addClass('error');
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
