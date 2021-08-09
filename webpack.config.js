const path = require('path');
const webpack = require('webpack');

module.exports = {
    entry: './assets/src/main.jsx',
    output: {
        path: path.resolve(__dirname, 'dist'),
        filename: 'main.bundle.js',
    },
    resolve: {
        extensions: ['.js', '.jsx']
    },
    module: {
        rules: [
            {
                test: /\.jsx$/,
                exclude: /(node_modules)/,
                use: {
                    loader: 'babel-loader'
                }
            }
        ]
    },
// externals: {
//     // Use external version of React
//     'react': 'React',
//     'react-dom': 'ReactDOM'
// }
};