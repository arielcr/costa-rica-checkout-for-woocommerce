/**
 * Screenshots of phase 2 (docs/screenshots/fase-2/). Only runs when CRKIT_E2E_SCREENSHOTS=2:
 *
 *   npm run seed && CRKIT_E2E_SCREENSHOTS=2 npx playwright test screenshots-fase-2
 */
const path = require( 'path' );
const { execSync } = require( 'child_process' );
const { test, expect } = require( '@playwright/test' );
const u = require( '../utils' );

const DIR = path.join( __dirname, '../../../docs/screenshots/fase-2' );
const shot = ( page, name, options = {} ) =>
	page.screenshot( {
		path: path.join( DIR, `${ name }.png` ),
		fullPage: true,
		...options,
	} );
const noSkeletons = ( page ) =>
	expect(
		page.locator( '.wc-block-checkout__sidebar [class*="skeleton"]' )
	).toHaveCount( 0 );

test.skip(
	'2' !== process.env.CRKIT_E2E_SCREENSHOTS,
	'Set CRKIT_E2E_SCREENSHOTS=2 to take the phase 2 screenshots.'
);

test( 'phase 2 screenshots', async ( { browser } ) => {
	const shopper = await browser.newContext( {
		viewport: { width: 1280, height: 900 },
	} );
	const page = await shopper.newPage();
	await u.addToCart( page, 'CRK-COFFEE' );

	// Classic checkout with the dependent selects (canton list open).
	await page.goto( '/checkout-clasico/' );
	await page.waitForSelector( 'form.checkout' );
	await page.locator( '#billing_state' ).selectOption( 'CR-H' );
	await page.locator( '#billing_crkit_canton' ).selectOption( '402' );
	await page.locator( '#billing_crkit_district' ).selectOption( '40206' );
	await shot( page, '01-checkout-clasico-canton-distrito' );
	await page
		.locator( '#billing_crkit_district_field .select2-selection' )
		.click();
	await shot( page, '02-checkout-clasico-lista-distritos', {
		fullPage: false,
	} );
	await page.keyboard.press( 'Escape' );

	// Block checkout: district of the selected province.
	await page.goto( '/checkout/' );
	await page.waitForSelector( '.wc-block-checkout__form' );
	await page.fill( '#email', 'invitado@example.com' );
	await page.fill( '#shipping-first_name', 'Luis' );
	await page.fill( '#shipping-last_name', 'Solano' );
	await page.fill( '#shipping-address_1', 'Barrio Escalante, avenida 7' );
	await page.locator( '#shipping-state' ).selectOption( 'CR-SJ' );
	await page.locator( '#shipping-crkit-district-sj' ).selectOption( '10101' );
	await page.waitForLoadState( 'networkidle' );
	await noSkeletons( page );
	await shot( page, '03-checkout-bloques-distrito' );

	await u.placeBlockOrder( page );
	const order = u.orderFromUrl( page );
	await expect( page.locator( 'body' ) ).toContainText(
		'Carmen, San José, San José'
	);
	await shot( page, '04-pedido-recibido-direccion' );

	// Store: order screen with the address and the district field.
	const store = await browser.newContext( {
		viewport: { width: 1280, height: 900 },
	} );
	const admin = await store.newPage();
	await u.login( admin, u.ADMIN );
	await admin.goto( u.orderEditUrl( order.id ) );
	await expect( admin.locator( '#order_data' ) ).toContainText( 'Carmen' );
	await shot( admin, '05-admin-pedido-direccion' );
	await admin.locator( '#order_data .edit_address' ).first().click();
	await admin.locator( '#order_data' ).screenshot( {
		path: path.join( DIR, '06-admin-editar-distrito.png' ),
	} );

	// Customer: My Account addresses and edit form.
	const customer = await browser.newContext( {
		viewport: { width: 1280, height: 900 },
	} );
	const account = await customer.newPage();
	await u.login( account, u.CUSTOMER );
	await account.goto( '/my-account/edit-address/' );
	await shot( account, '07-mi-cuenta-direcciones' );
	await account.goto( '/my-account/edit-address/billing/' );
	await shot( account, '08-mi-cuenta-editar-direccion' );

	// Exchange rate shortcode and block on a page.
	await page.goto( '/tipo-de-cambio/' );
	await expect( page.locator( '.crkit-exchange-rate' ) ).toHaveCount( 2 );
	await shot( page, '09-pagina-tipo-de-cambio' );

	// Block in the editor.
	const pageId = execSync(
		'npx wp-env run cli wp post list --post_type=page --name=tipo-de-cambio --field=ID',
		{ encoding: 'utf8' }
	)
		.split( '\n' )
		.find( ( line ) => /^\d+$/.test( line.trim() ) );
	if ( pageId ) {
		await admin.goto( `/wp-admin/post.php?post=${ pageId }&action=edit` );
		const canvas = admin.frameLocator( 'iframe[name="editor-canvas"]' );
		await canvas
			.locator( '.wp-block-crkit-exchange-rate .crkit-exchange-rate' )
			.first()
			.waitFor( { timeout: 30000 } );
		const welcome = admin.getByRole( 'button', { name: /close|cerrar/i } );
		if ( await welcome.first().isVisible() ) {
			await welcome.first().click();
		}
		await canvas.locator( '.wp-block-crkit-exchange-rate' ).first().click();
		await shot( admin, '10-editor-bloque-tipo-de-cambio', {
			fullPage: false,
		} );
	}

	await shopper.close();
	await store.close();
	await customer.close();
} );

test( 'phase 2 mobile screenshots (390 px)', async ( { browser } ) => {
	const shopper = await browser.newContext( {
		viewport: { width: 390, height: 844 },
		deviceScaleFactor: 2,
		isMobile: true,
		hasTouch: true,
	} );
	const page = await shopper.newPage();
	await u.addToCart( page, 'CRK-COFFEE' );

	// San José has the longest district list (123 districts).
	await page.goto( '/checkout/' );
	await page.waitForSelector( '.wc-block-checkout__form' );
	await page.locator( '#shipping-state' ).selectOption( 'CR-SJ' );
	await page.waitForLoadState( 'networkidle' );
	const district = page.locator( '#shipping-crkit-district-sj' );
	const center = () =>
		district.evaluate( ( select ) =>
			select.scrollIntoView( { block: 'center' } )
		);
	await center();
	await shot( page, '11-movil-bloques-distrito-san-jose', {
		fullPage: false,
	} );

	// The native select opens the system picker, which a screenshot cannot capture:
	// render the same options as a list box to show their order.
	await district.evaluate( ( select ) => {
		select.setAttribute( 'size', '16' );
		select.style.height = 'auto';
		select.style.position = 'relative';
		select.style.zIndex = '10';
		select.style.background = '#fff';
	} );
	await district.screenshot( {
		path: path.join( DIR, '14-movil-bloques-orden-distritos.png' ),
	} );
	await district.evaluate( ( select ) => {
		select.removeAttribute( 'size' );
		select.removeAttribute( 'style' );
	} );

	await district.selectOption( '10203' );
	await page.waitForLoadState( 'networkidle' );
	await expect( district ).toHaveValue( '10203' );
	await center();
	await shot( page, '12-movil-bloques-distrito-elegido', {
		fullPage: false,
	} );

	await page.fill( '#email', 'invitado@example.com' );
	await page.fill( '#shipping-first_name', 'Luis' );
	await page.fill( '#shipping-last_name', 'Solano' );
	await page.fill( '#shipping-address_1', 'Del parque, 200 m sur' );
	await page.waitForLoadState( 'networkidle' );
	await noSkeletons( page );
	await shot( page, '13-movil-checkout-bloques-completo' );

	await shopper.close();
} );
