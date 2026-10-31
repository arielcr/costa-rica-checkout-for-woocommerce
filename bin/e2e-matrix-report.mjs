/* eslint-disable no-console -- Command line report. */
/**
 * Prints the e2e matrix results (tests/e2e/.results/matrix/*.json) as a Markdown table.
 */
import { readdirSync, readFileSync } from 'node:fs';
import { join } from 'node:path';

const dir = join( process.cwd(), 'tests/e2e/.results/matrix' );
const columns = [
	[ 'classic checkout, guest', 'Classic · guest' ],
	[ 'classic checkout, customer', 'Classic · logged in' ],
	[ 'blocks checkout, guest', 'Blocks · guest' ],
	[ 'blocks checkout, customer', 'Blocks · logged in' ],
	[ 'not offered above the maximum', 'Max. amount' ],
];

const collect = ( suite, out ) => {
	for ( const spec of suite.specs || [] ) {
		out[ spec.title ] = spec.tests.every( ( t ) =>
			t.results.every( ( r ) => r.status === 'passed' )
		);
	}
	for ( const child of suite.suites || [] ) {
		collect( child, out );
	}
	return out;
};

const rows = readdirSync( dir )
	.filter( ( file ) => file.endsWith( '.json' ) )
	.sort()
	.map( ( file ) => {
		const [ php, currency, storage ] = file
			.replace( '.json', '' )
			.split( '-' );
		const results = collect(
			{
				suites: JSON.parse( readFileSync( join( dir, file ), 'utf8' ) )
					.suites,
			},
			{}
		);
		const cells = columns.map( ( [ needle ] ) => {
			const key = Object.keys( results ).find( ( title ) =>
				title.includes( needle )
			);
			if ( ! key ) {
				return '—';
			}
			return results[ key ] ? '✅' : '❌';
		} );
		return `| ${ php } | ${ currency.toUpperCase() } | ${ storage } | ${ cells.join( ' | ' ) } |`;
	} );

console.log(
	`| PHP | Currency | Order storage | ${ columns.map( ( c ) => c[ 1 ] ).join( ' | ' ) } |`
);
console.log( `|---|---|---|${ columns.map( () => '---' ).join( '|' ) }|` );
console.log( rows.join( '\n' ) );
