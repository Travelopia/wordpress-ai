/**
 * WordPress AI Webpack Config.
 */

const path = require( 'path' );
const MiniCssExtractPlugin = require( 'mini-css-extract-plugin' );
const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );

module.exports = () => {
	return {
		...defaultConfig,
		entry: {
			settings: [
				'./src/settings/index.tsx',
				'./src/settings/index.css',
			],
			'alt-text': './src/alt-text/index.ts',
		},
		output: {
			...defaultConfig.output,
			path: path.resolve( __dirname, 'dist' ),
			filename: '[name].js',
			publicPath: '/',
			clean: false,
		},
		plugins: [
			...defaultConfig.plugins.filter(
				( plugin ) =>
					plugin && plugin.constructor.name !== 'MiniCssExtractPlugin'
			),
			new MiniCssExtractPlugin( {
				filename: '[name].css',
			} ),
		],
		watchOptions: {
			ignored: /node_modules/,
			aggregateTimeout: 200,
		},
	};
};
