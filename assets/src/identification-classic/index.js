/**
 * Classic checkout, with the ID required only for an electronic invoice: marks the ID type and number
 * as required while "I need an electronic invoice" is checked. The server validates them either way.
 */
const ROWS = [ '#crkit_id_type_field', '#crkit_id_number_field' ];

// The "(optional)" marker WooCommerce printed for each row, restored when the box is unchecked.
const optionalMarkers = new Map();

const requiredMarker = () => {
	const marker = document.createElement( 'span' );
	marker.className = 'required';
	marker.setAttribute( 'aria-hidden', 'true' );
	marker.textContent = '*';
	return marker;
};

const update = ( checked ) => {
	ROWS.forEach( ( selector ) => {
		const row = document.querySelector( selector );
		if ( ! row ) {
			return;
		}

		row.classList.toggle( 'validate-required', checked );
		if ( ! checked ) {
			row.classList.remove(
				'woocommerce-invalid',
				'woocommerce-invalid-required-field'
			);
		}

		const optional = row.querySelector( 'label .optional' );
		const required = row.querySelector( 'label .required' );
		if ( checked && optional ) {
			optionalMarkers.set( selector, optional );
			optional.replaceWith( requiredMarker() );
		} else if ( ! checked && required && optionalMarkers.has( selector ) ) {
			required.replaceWith( optionalMarkers.get( selector ) );
		}

		const input = row.querySelector( 'input, select' );
		if ( input ) {
			input.setAttribute( 'aria-required', checked ? 'true' : 'false' );
		}
	} );
};

document.addEventListener( 'change', ( event ) => {
	if ( event.target && 'crkit_electronic_invoice' === event.target.id ) {
		update( event.target.checked );
	}
} );

const checkbox = document.getElementById( 'crkit_electronic_invoice' );
if ( checkbox ) {
	update( checkbox.checked );
}
