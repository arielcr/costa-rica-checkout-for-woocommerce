/**
 * Helpers for the end-to-end specs.
 */
const path = require( 'path' );
const { expect } = require( '@playwright/test' );

const ADMIN = { user: 'admin', pass: 'password' };
const CUSTOMER = { user: 'cliente', pass: 'cliente' };
const RECEIPT_PNG = path.join( __dirname, 'fixtures', 'comprobante-sinpe.png' );
const RECEIPT_PDF = path.join( __dirname, 'fixtures', 'comprobante-sinpe.pdf' );

/**
 * Current scenario, set by bin/e2e-matrix.sh.
 *
 * @return {{storage: string, currency: string, php: string}} Scenario.
 */
const scenario = () => ( {
	storage: process.env.CRKIT_E2E_STORAGE || 'hpos',
	currency: process.env.CRKIT_E2E_CURRENCY || 'usd',
	php: process.env.CRKIT_E2E_PHP || '',
	locale: process.env.CRKIT_E2E_LOCALE || '',
} );

/**
 * Expected plugin texts for the site language (es_CR and es_ES share the translation).
 * Null when the language is not known, so text checks are skipped.
 */
const TEXTS = {
	es: {
		instructionsTitle: 'Cómo pagar con SINPE Móvil',
		receiptThanks: '¡Gracias! Recibimos su comprobante',
		estimate: 'Monto a pagar: aproximadamente',
	},
	en: {
		instructionsTitle: 'How to pay with SINPE Móvil',
		receiptThanks: 'Thank you! We received your receipt',
		estimate: 'Amount to pay: about',
	},
};
const texts = () => {
	const { locale } = scenario();
	if ( locale.startsWith( 'es_' ) ) {
		return TEXTS.es;
	}
	return 'en_US' === locale ? TEXTS.en : null;
};

async function login( page, { user, pass } ) {
	await page.goto( '/wp-login.php' );
	await page.fill( '#user_login', user );
	await page.fill( '#user_pass', pass );
	await Promise.all( [
		page.waitForURL( ( url ) => ! url.pathname.includes( 'wp-login.php' ) ),
		page.click( '#wp-submit' ),
	] );
}

async function productId( request, sku ) {
	const response = await request.get(
		`/wp-json/wc/store/v1/products?sku=${ sku }`
	);
	const products = await response.json();
	expect(
		products.length,
		`Product ${ sku } exists (run npm run seed)`
	).toBeGreaterThan( 0 );
	return products[ 0 ].id;
}

async function addToCart( page, sku ) {
	const id = await productId( page.request, sku );
	await page.goto( `/?add-to-cart=${ id }` );
}

const GUEST = {
	email: 'invitado@example.com',
	first: 'Luis',
	last: 'Solano',
	address: 'Barrio Escalante, avenida 7',
	city: 'San José',
	postcode: '10101',
	phone: '88881111',
};

async function waitForBlockUi( page ) {
	await page.waitForFunction(
		() => ! document.querySelector( '.blockUI.blockOverlay' )
	);
}

/**
 * Classic (shortcode) checkout: fills the form for guests and selects SINPE Móvil.
 *
 * @param {import('@playwright/test').Page} page  Page.
 * @param {boolean}                         guest Fill guest fields.
 */
async function openClassicCheckout( page, guest ) {
	await page.goto( '/checkout-clasico/' );
	await page.waitForSelector( 'form.checkout' );

	if ( guest ) {
		await page.fill( '#billing_first_name', GUEST.first );
		await page.fill( '#billing_last_name', GUEST.last );
		await page.fill( '#billing_address_1', GUEST.address );
		await page.fill( '#billing_city', GUEST.city );
		await page.fill( '#billing_postcode', GUEST.postcode );
		await page.fill( '#billing_phone', GUEST.phone );
		await page.fill( '#billing_email', GUEST.email );
	}

	await waitForBlockUi( page );
}

async function selectClassicSinpe( page ) {
	await page
		.locator( 'label[for="payment_method_crkit_sinpe_movil"]' )
		.click();
	await waitForBlockUi( page );
}

async function placeClassicOrder( page ) {
	await selectClassicSinpe( page );
	await Promise.all( [
		page.waitForURL( /order-received/, { timeout: 60000 } ),
		page.click( '#place_order' ),
	] );
}

/**
 * Block checkout: fills the form for guests.
 *
 * @param {import('@playwright/test').Page} page  Page.
 * @param {boolean}                         guest Fill guest fields.
 */
async function openBlockCheckout( page, guest ) {
	await page.goto( '/checkout/' );
	await page.waitForSelector( '.wc-block-checkout__form' );

	if ( guest ) {
		await page.fill( '#email', GUEST.email );
		await page.fill( '#shipping-first_name', GUEST.first );
		await page.fill( '#shipping-last_name', GUEST.last );
		await page.fill( '#shipping-address_1', GUEST.address );
		await page.fill( '#shipping-city', GUEST.city );
		await page.fill( '#shipping-postcode', GUEST.postcode );
		await page.fill( '#shipping-phone', GUEST.phone );
	}

	// Wait for the Store API to settle after address changes.
	await page.waitForLoadState( 'networkidle' );
}

const blockSinpeRadio = ( page ) =>
	page.locator(
		'#radio-control-wc-payment-method-options-crkit_sinpe_movil'
	);

async function selectBlockSinpe( page ) {
	await page
		.locator(
			'label[for="radio-control-wc-payment-method-options-crkit_sinpe_movil"]'
		)
		.click();
	await expect( blockSinpeRadio( page ) ).toBeChecked();
	// Selecting a method triggers a totals recalculation (`__experimental_calc_totals`).
	await page.waitForLoadState( 'networkidle' );
}

/**
 * Places the block checkout order with SINPE Móvil.
 *
 * WooCommerce 11.2 ignores a "Place order" click made while the totals recalculation that follows
 * a payment method change is still in flight (reproduced with Direct bank transfer too). The click
 * is retried in that case only: a real failure never reaches the order received page and still fails.
 *
 * @param {import('@playwright/test').Page} page Page.
 */
async function placeBlockOrder( page ) {
	await selectBlockSinpe( page );
	const button = page.locator(
		'.wc-block-components-checkout-place-order-button'
	);

	for ( let attempt = 1; attempt <= 3; attempt++ ) {
		await button.click();
		try {
			await page.waitForURL( /order-received/, { timeout: 20000 } );
			return;
		} catch ( error ) {
			if ( 3 === attempt ) {
				throw error;
			}
		}
	}
}

/**
 * Order id and key from the order received URL.
 *
 * @param {import('@playwright/test').Page} page Page.
 * @return {{id: number, key: string}} Order.
 */
function orderFromUrl( page ) {
	const url = new URL( page.url() );
	const id = Number( url.pathname.match( /order-received\/(\d+)/ )[ 1 ] );
	return { id, key: url.searchParams.get( 'key' ) };
}

async function uploadReceipt( page, file = RECEIPT_PNG ) {
	await page.setInputFiles( '#crkit-receipt-file', file );
	await Promise.all( [
		page.waitForURL( /crkit_receipt=/ ),
		page.click( '.crkit-sinpe-receipt__form button[type="submit"]' ),
	] );
}

function orderEditUrl( id ) {
	return 'posts' === scenario().storage
		? `/wp-admin/post.php?post=${ id }&action=edit`
		: `/wp-admin/admin.php?page=wc-orders&action=edit&id=${ id }`;
}

module.exports = {
	texts,
	ADMIN,
	CUSTOMER,
	RECEIPT_PNG,
	RECEIPT_PDF,
	scenario,
	login,
	addToCart,
	openClassicCheckout,
	selectClassicSinpe,
	placeClassicOrder,
	openBlockCheckout,
	blockSinpeRadio,
	selectBlockSinpe,
	placeBlockOrder,
	orderFromUrl,
	uploadReceipt,
	orderEditUrl,
};
