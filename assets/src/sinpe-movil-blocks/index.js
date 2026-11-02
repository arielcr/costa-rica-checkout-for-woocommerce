/**
 * SINPE Móvil payment method for the WooCommerce block checkout.
 *
 * Data comes from BlocksSupport::get_payment_method_data(): texts, the effective exchange
 * rate (already including the surcharge) and the maximum amount in colones.
 */
import { registerPaymentMethod } from '@woocommerce/blocks-registry';
import { getSetting } from '@woocommerce/settings';
import { decodeEntities } from '@wordpress/html-entities';
import { __, sprintf } from '@wordpress/i18n';

const settings = getSetting( 'crkit_sinpe_movil_data', {} );
const currencySettings = getSetting( 'currency', {} );

const title =
	decodeEntities( settings.title || '' ) ||
	__( 'SINPE Móvil', 'costa-rica-kit-for-woocommerce' );

/**
 * Amount in whole colones for a cart total, or null if it cannot be converted.
 * Mirrors QuoteCalculator::round_up(): round to cents, then round up.
 *
 * @param {number} total Cart total in the store currency (major units).
 * @return {number|null} Amount in colones.
 */
export const toColones = ( total ) => {
	if ( settings.storeCurrency === 'CRC' ) {
		return Math.ceil( Math.round( total * 100 ) / 100 );
	}

	if ( ! settings.rateAvailable || ! settings.rate ) {
		return null;
	}

	return Math.ceil( Math.round( total * settings.rate * 100 ) / 100 );
};

/**
 * Cart total in major units from Store API totals.
 *
 * @param {Object} totals                     Cart totals.
 * @param {string} totals.total_price         Total in minor units.
 * @param {number} totals.currency_minor_unit Minor unit.
 * @return {number} Total.
 */
const totalFromCart = ( totals ) =>
	parseInt( totals?.total_price ?? '0', 10 ) /
	10 ** ( totals?.currency_minor_unit ?? 2 );

/**
 * Colones with the store separators, e.g. "₡45,604".
 *
 * @param {number} amount Amount in colones.
 * @return {string} Formatted amount.
 */
const formatColones = ( amount ) => {
	const separator = currencySettings.thousandSeparator ?? ',';
	return '₡' + String( amount ).replace( /\B(?=(\d{3})+(?!\d))/g, separator );
};

const Content = ( { billing } ) => {
	const description = decodeEntities( settings.description || '' );
	const minorUnit = billing?.currency?.minorUnit ?? 2;
	const total = ( billing?.cartTotal?.value ?? 0 ) / 10 ** minorUnit;
	const colones =
		settings.storeCurrency !== 'CRC' ? toColones( total ) : null;

	return (
		<div className="crkit-sinpe-movil-blocks">
			{ description && <p>{ description }</p> }
			{ colones !== null && (
				<p className="crkit-sinpe-movil-blocks__estimate">
					{ sprintf(
						/* translators: %s: estimated amount in colones. */
						__(
							'Amount to pay: about %s. You will see the exact amount in colones after placing the order.',
							'costa-rica-kit-for-woocommerce'
						),
						formatColones( colones )
					) }
				</p>
			) }
		</div>
	);
};

const Label = ( { components } ) => {
	const { PaymentMethodLabel } = components;
	return <PaymentMethodLabel text={ title } />;
};

registerPaymentMethod( {
	name: 'crkit_sinpe_movil',
	label: <Label />,
	content: <Content />,
	edit: <Content />,
	ariaLabel: title,
	canMakePayment: ( { cartTotals } ) => {
		const colones = toColones( totalFromCart( cartTotals ) );

		if ( colones === null ) {
			return false;
		}

		return ! (
			settings.maxAmountCrc > 0 && colones > settings.maxAmountCrc
		);
	},
	supports: {
		features: settings.supports ?? [ 'products' ],
	},
} );
