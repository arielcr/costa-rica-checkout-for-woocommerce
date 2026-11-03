/**
 * Dependent province → canton → district selects for Costa Rican addresses in the classic
 * checkout and My Account → Addresses. WooCommerce shows and hides the fields per country
 * (country locale); this script fills the options and keeps city (canton) and postcode in sync.
 *
 * Data: window.crkitLocations = { 'CR-SJ': { '101': { name, districts: { '10101': 'Carmen' } } } }.
 */
import { __ } from '@wordpress/i18n';

const $ = window.jQuery;
const data = window.crkitLocations || {};
const GROUPS = [ 'billing', 'shipping' ];

/**
 * Replaces the options of a select, keeping the value if it is still available.
 *
 * @param {Object} $select     jQuery select.
 * @param {Object} options     value => label.
 * @param {string} placeholder Placeholder label.
 * @param {string} value       Value to keep.
 */
const setOptions = ( $select, options, placeholder, value ) => {
	$select.empty().append( new window.Option( placeholder, '' ) );

	Object.entries( options ).forEach( ( [ key, label ] ) => {
		$select.append( new window.Option( label, key, false, key === value ) );
	} );

	$select.val( value && options[ value ] ? value : '' );

	if ( $.fn.selectWoo ) {
		$select.selectWoo( { width: '100%' } );
	}
};

const field = ( group, key ) => $( `#${ group }_${ key }` );

const cantonsOf = ( state ) => data[ state ] || {};

/**
 * Writes the canton as city and the district postcode, then refreshes the checkout totals.
 *
 * @param {string} group `billing` or `shipping`.
 */
const syncCityAndPostcode = ( group ) => {
	const state = field( group, 'state' ).val();
	const canton = field( group, 'crkit_canton' ).val();
	const district = field( group, 'crkit_district' ).val();
	const cantonData = cantonsOf( state )[ canton ];

	field( group, 'city' ).val( cantonData && district ? cantonData.name : '' );
	field( group, 'postcode' ).val( cantonData && district ? district : '' );

	$( document.body ).trigger( 'update_checkout' );
};

const fillDistricts = ( group ) => {
	const state = field( group, 'state' ).val();
	const canton = field( group, 'crkit_canton' ).val();
	const $district = field( group, 'crkit_district' );
	const cantonData = cantonsOf( state )[ canton ];

	setOptions(
		$district,
		cantonData ? cantonData.districts : {},
		__( 'Select a district…', 'costa-rica-checkout-for-woocommerce' ),
		$district.val()
	);
};

const fillCantons = ( group ) => {
	const $canton = field( group, 'crkit_canton' );
	const cantons = {};

	Object.entries( cantonsOf( field( group, 'state' ).val() ) ).forEach(
		( [ id, canton ] ) => {
			cantons[ id ] = canton.name;
		}
	);

	setOptions(
		$canton,
		cantons,
		__( 'Select a canton…', 'costa-rica-checkout-for-woocommerce' ),
		$canton.val()
	);
	fillDistricts( group );
};

const init = () => {
	GROUPS.forEach( ( group ) => {
		if ( ! field( group, 'crkit_canton' ).length ) {
			return;
		}

		fillCantons( group );

		$( document.body ).on(
			'change',
			`#${ group }_state, #${ group }_country`,
			() => {
				if ( 'CR' === field( group, 'country' ).val() ) {
					fillCantons( group );
					syncCityAndPostcode( group );
				}
			}
		);
		$( document.body ).on( 'change', `#${ group }_crkit_canton`, () => {
			fillDistricts( group );
			syncCityAndPostcode( group );
		} );
		$( document.body ).on( 'change', `#${ group }_crkit_district`, () =>
			syncCityAndPostcode( group )
		);
	} );
};

$( init );
