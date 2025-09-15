/**
 * WordPress AI Webpack Config.
 */

// External dependencies.
import MiniCssExtractPlugin from 'mini-css-extract-plugin';

// Config.
export default () => {
	// Build configuration.
	const scriptConfig = {
		stats: 'minimal',
		cache: {
			type: 'memory',
		},
		entry: {
			editor: './src/editor/js/index.ts',
		},
		module: {
			rules: [
				{
					test: /\.tsx?$/,
					use: [
						{
							loader: 'ts-loader',
							options: {
								transpileOnly: true,
							},
						},
					],
					exclude: /node_modules/,
				},
			],
		},
		output: {
			filename: './js/[name].js',
			publicPath: '/',
		},
		optimization: {
			removeEmptyChunks: true,
			minimize: true,
		},
		plugins: [
			new MiniCssExtractPlugin( {
				filename: './css/[name].css',
			} ),
		],
	};

	// CSS configuration.
	const styleConfig = {
		...scriptConfig,
		entry: {
			editor: './src/editor/scss/index.scss',
		},
		module: {
			rules: [
				{
					test: /\.(sa|sc|c)ss$/,
					use: [
						MiniCssExtractPlugin.loader,
						{
							loader: 'css-loader',
							options: {
								url: false,
							},
						},
						{
							loader: 'sass-loader',
							options: {
								sassOptions: {
									outputStyle: 'compressed',
								},
							},
						},
					],
				},
			],
		},
	};

	// Return build configuration.
	return [ scriptConfig, styleConfig ];
};
