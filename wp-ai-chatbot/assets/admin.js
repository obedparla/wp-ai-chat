/**
 * Admin page behavior for the AI Chatbot settings, logs, and support pages.
 * Server-side values (nonces, translated strings) arrive on the wpaicAdmin
 * object via wp_localize_script. Each block no-ops when its elements are
 * absent, so the file is safe to load on every plugin admin page.
 */

/* global jQuery, ajaxurl, wpaicAdmin */

// Unsaved-changes guard: tab links are full page loads, so edits
// were silently lost on navigation before this prompt existed.
jQuery(document).ready(function($) {
	var $form = $('.wpaic-admin-wrap form[action="options.php"]');
	if (!$form.length) return;
	// Value-based dirty tracking: compare against a snapshot of the
	// saved values so reverting an edit clears the dirty state.
	// serialize() covers text/hidden/textarea/select values and
	// checkbox presence, so toggles and reverts both register.
	var savedSnapshot = $form.serialize();
	var hasUnsavedChanges = false;
	$form.on('input change', ':input', function() {
		hasUnsavedChanges = $form.serialize() !== savedSnapshot;
		$('#wpaic-unsaved-indicator').toggleClass('hidden', !hasUnsavedChanges);
	});
	$form.on('submit', function() {
		savedSnapshot = $form.serialize();
		hasUnsavedChanges = false;
		$('#wpaic-unsaved-indicator').addClass('hidden');
	});
	window.addEventListener('beforeunload', function(event) {
		if (!hasUnsavedChanges) return;
		event.preventDefault();
		event.returnValue = '';
	});
});

// Onboarding checklist: persist dismissal and the manual "try it" step.
jQuery(document).ready(function($) {
	$('#wpaic-onboarding-dismiss').on('click', function() {
		$.post(ajaxurl, { action: 'wpaic_update_onboarding', dismissed: 1, _wpnonce: wpaicAdmin.onboardingNonce });
		$('#wpaic-onboarding').slideUp(150);
	});
	$('#wpaic-onboarding-try-it').on('click', function() {
		$.post(ajaxurl, { action: 'wpaic_update_onboarding', step: 'try_it', _wpnonce: wpaicAdmin.onboardingNonce });
		var $row = $(this).closest('[data-onboarding-step]');
		$row.attr('data-onboarding-done', '1');
		$row.find('.w-\\[22px\\]')
			.removeClass('bg-canvas text-muted border border-line-2').addClass('bg-success text-white')
			.html('<svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5"/></svg>');
	});
});

// Conversation transcript modal on the Chat Logs page.
jQuery(document).ready(function($) {
	$('.wpaic-view-conversation').on('click', function() {
		var id = $(this).data('id');
		$('#wpaic-conversation-modal').show();
		$('#wpaic-conversation-modal .wpaic-modal-body').html('<p>Loading...</p>');

		$.ajax({
			url: ajaxurl,
			type: 'POST',
			data: {
				action: 'wpaic_get_conversation',
				conversation_id: id,
				_wpnonce: wpaicAdmin.adminNonce
			},
			success: function(response) {
				if (response.success) {
					var html = '';
					response.data.forEach(function(item) {
						if (item.type === 'event') {
							html += '<div class="wpaic-event-chip">' + $('<div>').text(item.label).html() + '</div>';
							return;
						}
						html += '<div class="wpaic-message wpaic-message-' + item.role + '">';
						html += '<div class="wpaic-message-role">' + item.role + '</div>';
						// content_html is server-rendered, escaped markdown (assistant replies).
						if (item.content_html) {
							html += '<div class="wpaic-message-content">' + item.content_html + '</div>';
						} else {
							html += '<div class="wpaic-message-content">' + $('<div>').text(item.content).html().replace(/\n/g, '<br>') + '</div>';
						}
						html += '<div class="wpaic-message-time">' + item.created_at + '</div>';
						html += '</div>';
					});
					$('#wpaic-conversation-modal .wpaic-modal-body').html(html || '<p>No messages.</p>');
				} else {
					$('#wpaic-conversation-modal .wpaic-modal-body').html('<p>Error loading conversation.</p>');
				}
			}
		});
	});

	$('.wpaic-delete-conversation').on('click', function() {
		if (!confirm(wpaicAdmin.deleteConversationConfirm)) {
			return;
		}

		var $btn = $(this);
		var id = $btn.data('id');

		$.ajax({
			url: ajaxurl,
			type: 'POST',
			data: {
				action: 'wpaic_delete_conversation',
				conversation_id: id,
				_wpnonce: wpaicAdmin.adminNonce
			},
			success: function(response) {
				if (response.success) {
					$btn.closest('tr').fadeOut(function() { $(this).remove(); });
				} else {
					alert('Error deleting conversation.');
				}
			}
		});
	});

	$('.wpaic-modal-close, .wpaic-modal-backdrop').on('click', function() {
		$('#wpaic-conversation-modal').hide();
	});
});
