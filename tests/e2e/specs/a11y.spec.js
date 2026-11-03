/**
 * Accessibility: axe (WCAG 2.1 A/AA) on the plugin's components in both checkouts, the order
 * received page and My Account, keyboard use of the dependent selects and the receipt form, and
 * announced error messages. Needs `npm run seed`.
 *
 * The plugin's components must have no violations. Whole-page results (theme and WooCommerce
 * included) are saved to tests/e2e/.results/a11y/ for reference.
 */
const fs = require( 'fs' );
const path = require( 'path' );
const { test, expect } = require( '@playwright/test' );
const AxeBuilder = require( '@axe-core/playwright' ).default;
const u = require( '../utils' );

const TAGS = [ 'wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa' ];
const REPORT_DIR = path.join( __dirname, '../.results/a11y' );

const summarize = ( violations ) =>
	violations.map( ( v ) => ( {
		id: v.id,
		impact: v.impact,
		help: v.help,
		targets: v.nodes.map( ( n ) => n.target.join( ' ' ) ),
	} ) );

/**
 * Runs axe on the plugin's components (asserted) and on the whole page (saved).
 *
 * @param {import('@playwright/test').Page} page      Page.
 * @param {string}                          name      Report name.
 * @param {string[]}                        selectors Plugin components.
 */
async function audit( page, name, selectors ) {
	for ( const selector of selectors ) {
		await expect( page.locator( selector ).first() ).toBeAttached();
	}

	const own = new AxeBuilder( { page } ).withTags( TAGS );
	selectors.forEach( ( selector ) => own.include( selector ) );
	const ownResults = await own.analyze();

	const full = await new AxeBuilder( { page } ).withTags( TAGS ).analyze();
	fs.mkdirSync( REPORT_DIR, { recursive: true } );
	fs.writeFileSync(
		path.join( REPORT_DIR, `${ name }.json` ),
		JSON.stringify(
			{
				url: page.url(),
				plugin: summarize( ownResults.violations ),
				page: summarize( full.violations ),
			},
			null,
			2
		)
	);

	expect( summarize( ownResults.violations ) ).toEqual( [] );
}

const ID = ( key ) => `[id="${ key }"]`;

/**
 * WooCommerce focuses the first notice (e.g. "added to your cart") 500 ms after the page loads.
 * Wait for it, so it does not take the focus in the middle of a keyboard test.
 *
 * @param {import('@playwright/test').Page} page Page.
 */
async function settleNoticeFocus( page ) {
	const notice = page
		.locator(
			'.wc-block-components-notice-banner[role="alert"], .woocommerce-message[role="alert"], .woocommerce-info[role="alert"]'
		)
		.first();
	if ( await notice.count() ) {
		await expect( notice ).toBeFocused();
	}
}

test( 'classic checkout: fields, errors and keyboard use of the dependent selects', async ( {
	page,
} ) => {
	await u.addToCart( page, 'CRK-COFFEE' );
	await page.goto( '/checkout-clasico/' );
	await page.waitForSelector( 'form.checkout' );
	await settleNoticeFocus( page );
	await page.locator( '#billing_state' ).selectOption( 'CR-H' );

	// Keyboard: canton and district (selectWoo) without the mouse.
	await page
		.locator( '#billing_crkit_canton_field .select2-selection' )
		.focus();
	await page.keyboard.press( 'Enter' );
	await page.keyboard.type( 'Barva' );
	await page.keyboard.press( 'Enter' );
	await expect( page.locator( '#billing_crkit_canton' ) ).toHaveValue(
		'402'
	);
	// selectWoo returns the focus to the canton once its list closes.
	await expect(
		page.locator( '#billing_crkit_canton_field .select2-selection' )
	).toBeFocused();
	await page.keyboard.press( 'Tab' );
	await expect(
		page.locator( '#billing_crkit_district_field .select2-selection' )
	).toBeFocused();
	await page.keyboard.press( 'Enter' );
	await page.keyboard.type( 'San José de la Montaña' );
	await page.keyboard.press( 'Enter' );
	await expect( page.locator( '#billing_crkit_district' ) ).toHaveValue(
		'40206'
	);

	await u.selectClassicSinpe( page );
	await page.locator( '#crkit_electronic_invoice' ).check();
	await audit( page, 'classic-checkout', [
		'#billing_crkit_canton_field',
		'#billing_crkit_district_field',
		'#crkit_electronic_invoice_field',
		'#crkit_id_type_field',
		'#crkit_id_number_field',
		'.payment_method_crkit_sinpe_movil',
	] );

	// Errors are announced.
	await page.click( '#place_order' );
	await expect(
		page
			.locator( '[role="alert"]' )
			.filter( { hasText: /electronic invoice|factura electrónica/ } )
			.first()
	).toBeVisible();
	await audit( page, 'classic-checkout-errors', [
		'#crkit_id_type_field',
		'#crkit_id_number_field',
	] );
} );

test( 'block checkout: ID, district and SINPE Móvil', async ( { page } ) => {
	await u.addToCart( page, 'CRK-COFFEE' );
	await u.openBlockCheckout( page, true );
	await page.locator( '#contact-crkit-electronic-invoice' ).check();
	await u.selectBlockSinpe( page );

	// The district is a native select (the browser provides its keyboard use): reachable with Tab.
	await page.locator( '#shipping-phone' ).focus();
	await page.keyboard.press( 'Tab' );
	await expect( page.locator( '#shipping-crkit-district-sj' ) ).toBeFocused();

	await audit( page, 'block-checkout', [
		ID( 'contact-crkit-electronic-invoice' ),
		ID( 'contact-crkit-id-type' ),
		ID( 'contact-crkit-id-number' ),
		ID( 'shipping-crkit-district-sj' ),
		'.crkit-sinpe-movil-blocks',
	] );
} );

test( 'order received and My Account: instructions, receipt form and its errors', async ( {
	page,
} ) => {
	await u.login( page, u.CUSTOMER );
	await u.addToCart( page, 'CRK-COFFEE' );
	await u.openBlockCheckout( page, false );
	await u.placeBlockOrder( page );
	const order = u.orderFromUrl( page );

	await audit( page, 'order-received', [
		'.crkit-sinpe-instructions',
		'#crkit-sinpe-receipt',
	] );

	// Keyboard: file input, then the submit button.
	const file = page.locator( '#crkit-receipt-file' );
	await file.focus();
	await page.keyboard.press( 'Tab' );
	await expect(
		page.locator( '#crkit-sinpe-receipt button[type="submit"]' )
	).toBeFocused();

	// A rejected file: the error is announced and tied to the field.
	await file.setInputFiles( {
		name: 'nota.txt',
		mimeType: 'text/plain',
		buffer: Buffer.from( 'not a receipt' ),
	} );
	await page.locator( '#crkit-sinpe-receipt button[type="submit"]' ).click();
	const error = page.locator( '#crkit-sinpe-receipt [role="alert"]' );
	await expect( error ).toBeVisible();
	await expect( file ).toHaveAttribute( 'aria-invalid', 'true' );
	await expect( file ).toHaveAttribute(
		'aria-describedby',
		/crkit-receipt-error/
	);
	await audit( page, 'order-received-error', [ '#crkit-sinpe-receipt' ] );

	await page.goto( `/my-account/view-order/${ order.id }/` );
	await audit( page, 'my-account-order', [
		'.crkit-sinpe-instructions',
		'#crkit-sinpe-receipt',
	] );

	await page.goto( '/my-account/edit-address/billing/' );
	await audit( page, 'my-account-address', [
		'#billing_crkit_canton_field',
		'#billing_crkit_district_field',
	] );

	await page.goto( '/my-account/edit-account/' );
	await audit( page, 'my-account-details', [
		ID( '_wc_other/crkit/id-type_field' ),
		ID( '_wc_other/crkit/id-number_field' ),
	] );
} );
