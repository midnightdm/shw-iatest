const path = require('path');
const HtmlWebpackPlugin = require('html-webpack-plugin');

const generateOutputConfig = (filename, outputPath) => ({
    path: path.join(__dirname, outputPath),
    filename: `${filename}.js`,
    chunkFilename: '[id].[chunkhash].js',
    publicPath: './', // Adjust this based on your needs
  });

module.exports = {
  mode: 'development',
  entry: {
    bundle: './src/index.js',
    motion: './src/motion.js',
    wccmotion: './src/wccmotion.js',
    wcc: './src/wcc.js',
    wccedit: './src/wccedit.js',
    wccnews: './src/wccnews.js',
    upmap: './src/upmap.js'
  },
  output: {
    path: path.join(__dirname, 'htdocs'),
    filename: "[name].js",
    chunkFilename: '[id].[chunkhash].js'
  },
  plugins: [
    new HtmlWebpackPlugin({
      template: './src/index.html',
      inject: true,
      chunks: ['bundle'],
      filename: 'index.html',
      publicPath: './'
    }),
    new HtmlWebpackPlugin({
        template: './src/wcc.html',
        inject: true,
        chunks: ['wcc'],
        filename: 'wcc/index.html',
        publicPath: '../'
    }),
    new HtmlWebpackPlugin({
        template: './src/motion.html',
        inject: true,
        chunks: ['motion'],
        filename: 'motion/index.html',
        publicPath: './'
    }),
    new HtmlWebpackPlugin({
        template: './src/wccedit.html',
        inject: true,
        chunks: ['wccedit'],
        filename: 'wcc/edit.html',
        publicPath: '../'
    }),
    new HtmlWebpackPlugin({
        template: './src/wccmotion.html',
        inject: true,
        chunks: ['wccmotion'],
        filename: 'wcc/motion.html',
        publicPath: '../'
    }),
    new HtmlWebpackPlugin({
        template: './src/wccnews.html',
        inject: true,
        chunks: ['wccnews'],
        filename: 'wcc/news.html',
        publicPath: '../'
    }),
    new HtmlWebpackPlugin({
        template: './src/upmap.html',
        inject: true,
        chunks: ['upmap'],
        filename: 'upmap.html',
        publicPath: './'
    })
  ],
  module: {
    rules: [{
      test: /.jsx?$/,
      include: [
        path.resolve(__dirname, 'src')
        
      ],
      exclude: [
        path.resolve(__dirname, 'node_modules')
      ],
      loader: 'babel-loader',
      options: {
        presets: [
          ["@babel/env", {
            "targets": {
              "browsers": "last 2 chrome versions"
            }
          }]
        ]
      }
    }]
  },
  resolve: {
    extensions: ['.json', '.js', '.jsx']
  },
  devtool: 'source-map',
  devServer: {
    contentBase: path.join(__dirname, '/htdocs/'),
    inline: true,
    host: 'localhost',
    port: 8085,
  },
  watch: false
};