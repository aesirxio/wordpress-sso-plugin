const path = require('path');

module.exports = {
	entry: './index.ts',
	context: path.resolve(__dirname, 'wp-content/plugins/aesirx-login/assets/src'),
	module: {
		rules: [
			{
				test: /\.tsx?$/,
				use: 'ts-loader',
				exclude: /node_modules/,
			},
		],
	},
	resolve: {
		extensions: ['.tsx', '.ts', '.js'],

		fallback: {
			"stream": require.resolve("stream-browserify")
		},
	},
	output: {
		filename: 'login.js',
		path: path.resolve(__dirname, 'wp-content/plugins/aesirx-login/assets/js'),
	},
	mode: 'development',
	devtool: 'inline-source-map'
};