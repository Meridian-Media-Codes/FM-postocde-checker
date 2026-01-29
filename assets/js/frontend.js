(function($){

	// Pressing Enter in the postcode field triggers the check
	$(document).on('keydown', '#prc-postcode', function(e){
		if (e.key === 'Enter') {
			e.preventDefault();
			$('#prc-check').trigger('click');
		}
	});

	$(document).on('click', '#prc-check', function(){
		var $wrap = $(this).closest('.prc-widget');
		var pc = $.trim($wrap.find('#prc-postcode').val());
		var $result = $wrap.find('#prc-result');

		if(!pc){
			$result
				.html('<p class="prc-title">Please enter a postcode</p>')
				.addClass('show')
				.css({ background: '#fff4cc', color: '#5a4500' });
			return;
		}

		$result
			.removeClass('success fail')
			.addClass('show')
			.css({ background: '', color: '' })
			.html('<p class="prc-title">Checking…</p>');

		$.post(PRC.ajaxUrl, {
			action: 'prc_check_postcode',
			nonce: PRC.nonce,
			postcode: pc
		}).done(function(resp){
			if(!resp || !resp.success || !resp.data){
				$result.html('<p class="prc-title">Unexpected response</p>');
				return;
			}

			var d = resp.data;
			var s = d.settings || {};
			var inside = !!d.inside;

			// Optional helper line you can keep or delete
			var prefixes = (PRC.allowedPrefixes || '').toString().trim();
			var coverLine = '';
			if (prefixes) {
				coverLine = '<p class="prc-meta">We cover ' + prefixes + ' postcodes.</p>';
			}

			if(inside){
				$result
					.removeClass('fail').addClass('success')
					.css({ background: PRC.colors.successBg, color: PRC.colors.successTx })
					.html(
						'<p class="prc-title">'+ (s.success_msg || 'You are covered!') +'</p>' +
						coverLine +
						'<a class="prc-cta" style="background:'+PRC.colors.btnBg+';color:'+PRC.colors.btnTx+'" href="'+ (s.success_cta_url || '#') +'">'+ (s.success_cta_text || 'Book now') +'</a>'
					);
			}else{
				$result
					.removeClass('success').addClass('fail')
					.css({ background: PRC.colors.failBg, color: PRC.colors.failTx })
					.html(
						'<p class="prc-title">'+ (s.fail_msg || "Sorry, we don't regularly cover this area") +'</p>' +
						coverLine +
						'<a class="prc-cta" style="background:'+PRC.colors.btnBg+';color:'+PRC.colors.btnTx+'" href="'+ (s.fail_cta_url || '#') +'">'+ (s.fail_cta_text || 'But we can still come') +'</a>'
					);
			}
		}).fail(function(){
			$result
				.removeClass('success').addClass('fail')
				.css({ background: PRC.colors.failBg, color: PRC.colors.failTx })
				.html('<p class="prc-title">Unable to check right now</p>');
		});
	});

})(jQuery);
