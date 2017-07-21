jQuery(document).ready(function($) {
	
	if (eshop_fixed_discounts.is_admin) {
		// Remove % from header
		var th_html = $('th#ediscount').html();
		th_html = th_html.replace(/% /, '');
		$('th#ediscount').html(th_html);
		
		// Widen the box a bit
		$('table.eshopdisc').css('min-width','80%');
		
		// Add select box
		$.each([1,2,3], function(undefined,j){
			var sel = $('<select />', {'style': 'margin-left:5px; margin-bottom:10px', name: 'usc_fixed_discount_type_'+j, id: 'usc_fixed_discount_type_'+j})
					  .append( $('<option />', {value:'%'}).text('%') )
					  .append( $('<option />', {value:'$'}).text('$') );
			
			$('option[value=\\'+eshop_fixed_discounts.opts[j]+']',sel).prop('selected', true);
			
			$('input#eshop_discount_value'+j).after(sel);
		});
		
		// Add our nonce
		$('#eshop-settings').append( $('<input />', {type:'hidden', name: eshop_fixed_discounts.nonce_name}).val(eshop_fixed_discounts.nonce) );
	}
	else {
		var html = $('#subtotal').html();
		html = html.replace(/<small>.*<\/small>/, eshop_fixed_discounts.lang.discount_applied);
		$('#subtotal').html(html);
	}
});