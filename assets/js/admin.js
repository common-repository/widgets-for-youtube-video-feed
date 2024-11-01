if (typeof TrustindexJsLoaded === 'undefined') {
	var TrustindexJsLoaded = {};
}

TrustindexJsLoaded.common = true;

jQuery(document).ready(function($) {
	// toggle opacity
	$('.ti-toggle-opacity').css('opacity', 1);

	// checkbox
	jQuery('.ti-checkbox:not(.ti-disabled)').on('click', function() {
		let checkbox = jQuery(this).find('input[type=checkbox], input[type=radio]');
		checkbox.prop('checked', !checkbox.prop('checked')).trigger('change');

		return false;
	});

	// communication from iframe
	let iframe = $('#ti-admin-iframe');
	let actionProcessing = false;
	if (iframe.length) {
		let iframeLoaded = false;
		let handleIframeMessages = function(event) {
			if ((event.data.source || "") !== 'ti-feed-iframe') {
				return;
			}

			let form;

			switch (event.data.action || "") {
				// iframe height changed
				case 'adjust-height':
					let height = event.data.height;
					if ('full' === event.data.height) {
						height = Math.floor($(window).height() - iframe.offset().top);
					}

					iframe.css('height', height);

					if (!iframeLoaded) {
						iframeLoaded = true;
						iframe.get(0).contentWindow.postMessage({
							source: 'ti-feed-iframe',
							action: 'iframe-loaded'
						}, '*');
					}

					break;

				// connect source
				case 'source-connect':
					if (actionProcessing) {
						return false;
					}

					form = $('#ti-connect-source-form');
					if (form.length) {
						form.find('input[name="data"]').val(JSON.stringify(event.data.data));
						form.submit();
						actionProcessing = true;
					}

					break;

				// save feed
				case 'feed-editor-save':
					if (actionProcessing) {
						return false;
					}

					form = $('#ti-widget-editor-form');
					if (form.length) {
						let data = event.data.data;
						data.css = event.data.css;

						form.find('input[name="data"]').val(JSON.stringify(data));
						form.submit();
						actionProcessing = true;
					}

					break;

				// pro feature redirect
				case 'pro-feature-redirect':
					window.location.href = jQuery('.ti-header-nav .ti-nav-item[href*="get-more-features"]').attr('href');
					break;
			}
		};

		window.addEventListener('message', handleIframeMessages);
	}

	// widget editor form
	let widgetEditorForm = $('#ti-widget-editor-form');
	if (widgetEditorForm.length) {
		let iframe = widgetEditorForm.find('iframe[name=ti-widget-editor-iframe]');
		if (iframe.length) {
			// send data to iframe
			// - create form
			let form = document.createElement('form');
			form.setAttribute('method', 'post');
			form.setAttribute('target', 'ti-widget-editor-iframe');
			form.setAttribute('action', iframe.data('src'));

			// - create input
			let input = document.createElement('input');
			input.setAttribute('type', 'hidden');
			input.setAttribute('name', 'wp-data');
			input.value = widgetEditorForm.find('script[type="application/ld+json"]').html();

			// - add input to form, and form to DOM
			form.appendChild(input);
			$(document.body).append(form);

			// - submit form
			form.submit();

			// save
			$('.btn-feed-editor-save').on('click', function(event) {
				event.preventDefault();

				let btn = $(this);
				btn.blur();

				// show loading
				jQuery('#ti-loading').addClass('ti-active');

				iframe.get(0).contentWindow.postMessage({
					source: 'ti-feed-iframe',
					action: 'feed-editor-save'
				}, '*');
			});
		}
	}
});


// - ../../../_wordpress_source_code/static/js/import/btn-loading.js
// loading on click
jQuery(document).on('click', '.ti-btn-loading-on-click', function() {
	let btn = jQuery(this);

	btn.addClass('ti-btn-loading').blur();
});

// - ../../../_wordpress_source_code/static/js/import/copy-to-clipboard.js
jQuery(document).on('click', '.btn-copy2clipboard', function(event) {
	event.preventDefault();

	let btn = jQuery(this);
	btn.blur();

	let obj = jQuery(btn.attr('href'));
	let text = obj.html() ? obj.html() : obj.val();

	// parse html
	let textArea = document.createElement('textarea');
	textArea.innerHTML = text;
	text = textArea.value;

	let feedback = () => {
		btn.removeClass('ti-toggle-tooltip').addClass('ti-show-tooltip');

		if (typeof this.timeout !== 'undefined') {
			clearTimeout(this.timeout);
		}

		this.timeout = setTimeout(() => btn.removeClass('ti-show-tooltip').addClass('ti-toggle-tooltip'), 3000);
	};

	if (!navigator.clipboard) {
		// fallback
		textArea = document.createElement('textarea');
		textArea.value = text;
		textArea.style.position = 'fixed'; // avoid scrolling to bottom
		document.body.appendChild(textArea);
		textArea.focus();
		textArea.select();

		try {
			var successful = document.execCommand('copy');

			feedback();
		}
		catch (err) { }

		document.body.removeChild(textArea);
		return;
	}

	navigator.clipboard.writeText(text).then(feedback);
});

// - ../../../_wordpress_source_code/static/js/import/modal.js
jQuery(document).on('click', '.btn-modal-close', function(event) {
	event.preventDefault();

	jQuery(this).closest('.ti-modal').fadeOut();
});

jQuery(document).on('click', '.ti-modal', function(event) {
	if (event.target.nodeName !== 'A') {
		event.preventDefault();

		if (!jQuery(event.target).closest('.ti-modal-dialog').length) {
			jQuery(this).fadeOut();
		}
	}
});

// - ../../../_wordpress_source_code/static/js/import/feature-request.js
jQuery(document).on('click', '.btn-send-feature-request', function(event) {
	event.preventDefault();

	let btn = jQuery(this);
	btn.blur();

	let container = jQuery('.ti-feature-request');
	let email = container.find('input[name="email"]').val().trim();
	let text = container.find('textarea[name="description"]').val().trim();

	// hide errors
	container.find('.is-invalid').removeClass('is-invalid');

	// check email
	if (email === "" || !/^(([^<>()\[\]\\.,;:\s@"]+(\.[^<>()\[\]\\.,;:\s@"]+)*)|(".+"))@((\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\])|(([a-zA-Z\-0-9]+\.)+[a-zA-Z]{2,}))$/.test(email)) {
		container.find('input[name="email"]').addClass('is-invalid');
	}

	// check text
	if (text === "") {
		container.find('textarea[name="description"]').addClass('is-invalid');
	}

	// there is error
	if (container.find('.is-invalid').length) {
		return false;
	}

	// show loading animation
	btn.addClass('ti-btn-loading');

	let data = new FormData(jQuery('.ti-feature-request form').get(0));

	// ajax request
	jQuery.ajax({
		type: 'POST',
		data: data,
		cache: false,
		contentType: false,
		processData: false
	}).always(function() {
		btn.removeClass('ti-btn-loading');

		btn.addClass('ti-show-tooltip').removeClass('ti-toggle-tooltip');
		setTimeout(() => btn.removeClass('ti-show-tooltip').addClass('ti-toggle-tooltip'), 3000);
	});
});

// - ../../../_wordpress_source_code/static/js/import/rate-us.js
// remember on hover
jQuery(document).on('mouseenter', '.ti-quick-rating', function(event) {
	let container = jQuery(this);
	let selected = container.find('.ti-star-check.ti-active, .star-check.active');

	if (selected.length) {
		// add index to data & remove all active stars
		container.data('selected', selected.index()).find('.ti-star-check, .star-check').removeClass('ti-active active');

		// give back active star on mouse enter
		container.one('mouseleave', () => container.find('.ti-star-check, .star-check').eq(container.data('selected')).addClass('ti-active active'));
	}
});

// star click
jQuery(document).on('click', '.ti-rate-us-box .ti-quick-rating .ti-star-check', function(event) {
	event.preventDefault();

	let star = jQuery(this);
	let container = star.parent();

	// add index to data & remove all active stars
	container.data('selected', star.index()).find('.ti-star-check').removeClass('ti-active');

	// select current star
	star.addClass('ti-active');

	// show modals
	if (parseInt(star.data('value')) >= 4) {
		// open new window
		window.open(location.href + '&command=rate-us-feedback&_wpnonce='+ container.data('nonce') +'&star=' + star.data('value'), '_blank');

		jQuery('.ti-rate-us-box').fadeOut();
	}
	else {
		let feedbackModal = jQuery('#ti-rateus-modal-feedback');

		if (feedbackModal.data('bs') == '5') {
			feedbackModal.modal('show');
			setTimeout(() => feedbackModal.find('textarea').focus(), 500);
		}
		else {
			feedbackModal.fadeIn();
			feedbackModal.find('textarea').focus();
		}

		feedbackModal.find('.ti-quick-rating .ti-star-check').removeClass('ti-active').eq(star.index()).addClass('ti-active');
	}
});

// write to support
jQuery(document).on('click', '.btn-rateus-support', function(event) {
	event.preventDefault();

	let btn = jQuery(this);
	btn.blur();

	let container = jQuery('#ti-rateus-modal-feedback');
	let email = container.find('input[type=text]').val().trim();
	let text = container.find('textarea').val().trim();

	// hide errors
	container.find('input[type=text], textarea').removeClass('is-invalid');

	// check email
	if (email === "" || !/^(([^<>()\[\]\\.,;:\s@"]+(\.[^<>()\[\]\\.,;:\s@"]+)*)|(".+"))@((\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\])|(([a-zA-Z\-0-9]+\.)+[a-zA-Z]{2,}))$/.test(email)) {
		container.find('input[type=text]').addClass('is-invalid').focus();
	}

	// check text
	if (text === "") {
		container.find('textarea').addClass('is-invalid').focus();
	}

	// there is error
	if (container.find('.is-invalid').length) {
		return false;
	}

	// show loading animation
	btn.addClass('ti-btn-loading');
	container.find('a, button').css('pointer-events', 'none');

	// ajax request
	jQuery.ajax({
		type: 'post',
		dataType: 'application/json',
		data: {
			command: 'rate-us-feedback',
			_wpnonce: btn.data('nonce'),
			email: email,
			text: text,
			star: container.find('.ti-quick-rating .ti-star-check.ti-active').data('value')
		}
	}).always(() => location.reload(true));
});
