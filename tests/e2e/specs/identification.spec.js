/**
 * Identification module: ID type and number in both checkouts, the order screen and My Account.
 * Needs `npm run seed` (module on, "required only for an electronic invoice", customer with a saved
 * cédula física). Type names are the same in every language; messages are matched in English or Spanish.
 */
const { test, expect } = require( '@playwright/test' );
const u = require( '../utils' );

const FORMAT_ERROR = {
	physical: /9 (digits|dígitos)/,
	dimex: /11 (or|o) 12/,
};
// Classic and block themes print notices with different markup: match the message text.
const expectNotice = ( page, text ) =>
	expect( page.getByText( text ).first() ).toBeVisible();
const SAVED = /(changed successfully|cambiado correctamente)/;
const REQUIRED_FOR_INVOICE =
	/(required for an electronic invoice|obligatori[oa] para (la|una) factura electrónica)/i;

test( 'classic checkout: the ID is required only for an electronic invoice and checked against its type', async ( {
	page,
	browser,
} ) => {
	await u.addToCart( page, 'CRK-COFFEE' );
	await u.openClassicCheckout( page, true );
	await u.selectClassicSinpe( page );

	// Asking for an invoice makes both fields required.
	await page.locator( '#crkit_electronic_invoice' ).check();
	await expect(
		page.locator( '#crkit_id_number_field label .required' )
	).toBeVisible();
	await page.click( '#place_order' );
	await expectNotice( page, REQUIRED_FOR_INVOICE );

	// A cédula física cannot start with 0.
	await page.locator( '#crkit_id_type' ).selectOption( '01' );
	await page.fill( '#crkit_id_number', '0-2345-6789' );
	await page.click( '#place_order' );
	await expectNotice( page, FORMAT_ERROR.physical );

	// Hyphens and spaces are removed.
	await page.fill( '#crkit_id_number', '1-2345 6789' );
	await Promise.all( [
		page.waitForURL( /order-received/, { timeout: 60000 } ),
		page.click( '#place_order' ),
	] );
	await expect( page.locator( 'body' ) ).toContainText(
		'Cédula física: 123456789'
	);
	const order = u.orderFromUrl( page );

	// Store: the ID is part of the billing address and the invoice request is shown.
	const store = await browser.newContext();
	const admin = await store.newPage();
	await u.login( admin, u.ADMIN );
	await admin.goto( u.orderEditUrl( order.id ) );
	const billing = admin.locator( '#order_data .order_data_column' ).nth( 1 );
	await expect( billing ).toContainText( 'Cédula física: 123456789' );
	await expect( billing ).toContainText( /(Requested|Solicitada)/ );
	await store.close();
} );

test( 'block checkout: the invoice checkbox requires the ID; numbers are checked on the server', async ( {
	page,
} ) => {
	await u.addToCart( page, 'CRK-COFFEE' );
	await u.openBlockCheckout( page, true );

	const invoice = page.locator( '#contact-crkit-electronic-invoice' );
	const type = page.locator( '#contact-crkit-id-type' );
	const number = page.locator( '#contact-crkit-id-number' );

	await expect(
		page.locator( 'label[for="contact-crkit-id-number"]' )
	).toHaveText( /\((optional|opcional)\)/ );
	await invoice.check();
	await expect(
		page.locator( 'label[for="contact-crkit-id-number"]' )
	).not.toHaveText( /\((optional|opcional)\)/ );

	// A DIMEX has 11 or 12 digits: the server rejects 10.
	await type.selectOption( '03' );
	await number.fill( '1234567890' );
	await u.selectBlockSinpe( page );
	await page
		.locator( '.wc-block-components-checkout-place-order-button' )
		.click();
	await expectNotice( page, FORMAT_ERROR.dimex );

	// Cédula jurídica with letters (Registro Nacional's new format), typed with hyphens.
	await type.selectOption( '02' );
	await number.fill( '3-101-a00001' );
	await u.placeBlockOrder( page );
	await expect( page.locator( 'body' ) ).toContainText(
		'Cédula jurídica: 3101A00001'
	);
} );

test( 'My Account: the saved ID prefills the classic checkout and can be changed in Account details', async ( {
	page,
} ) => {
	await u.login( page, u.CUSTOMER );

	await page.goto( '/my-account/edit-account/' );
	const type = page.locator( '[name="_wc_other/crkit/id-type"]' );
	const number = page.locator( '[name="_wc_other/crkit/id-number"]' );
	await expect( type ).toHaveValue( '01' );
	await expect( number ).toHaveValue( '100000001' );

	// Invalid numbers are rejected.
	await number.fill( '12345' );
	await page.click( 'button[name="save_account_details"]' );
	await expectNotice( page, FORMAT_ERROR.physical );

	// A DIMEX with 12 digits is saved.
	await page.goto( '/my-account/edit-account/' );
	await type.selectOption( '03' );
	await number.fill( '155812345678' );
	await page.click( 'button[name="save_account_details"]' );
	await expectNotice( page, SAVED );

	await u.addToCart( page, 'CRK-COFFEE' );
	await page.goto( '/checkout-clasico/' );
	await expect( page.locator( '#crkit_id_type' ) ).toHaveValue( '03' );
	await expect( page.locator( '#crkit_id_number' ) ).toHaveValue(
		'155812345678'
	);

	// The block checkout reads the session customer, refreshed after the save.
	await page.goto( '/checkout/' );
	await expect( page.locator( '#contact-crkit-id-type' ) ).toHaveValue(
		'03'
	);
	await expect( page.locator( '#contact-crkit-id-number' ) ).toHaveValue(
		'155812345678'
	);

	// Restore the seeded ID for the other specs.
	await page.goto( '/my-account/edit-account/' );
	await type.selectOption( '01' );
	await number.fill( '100000001' );
	await page.click( 'button[name="save_account_details"]' );
	await expectNotice( page, SAVED );
} );
