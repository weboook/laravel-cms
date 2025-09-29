const mix = require('laravel-mix');
const path = require('path');

/*
 |--------------------------------------------------------------------------
 | CMS Asset Compilation
 |--------------------------------------------------------------------------
 |
 | Modern build configuration for the CMS editor with optimization,
 | theming support, and performance budgets.
 |
 */

// Configuration
const isProduction = mix.inProduction();
const enableSourceMaps = !isProduction;

// CSS Processing Configuration
mix.options({
  postCss: [
    require('postcss-import')({
      path: ['resources/css']
    }),
    require('postcss-nesting')(),
    require('postcss-custom-properties')({
      preserve: true
    }),
    require('autoprefixer')({
      grid: true
    }),
    ...(isProduction ? [
      require('@fullhuman/postcss-purgecss')({
        content: [
          './resources/views/**/*.blade.php',
          './resources/js/**/*.js',
          './resources/js/**/*.vue',
          './src/**/*.php'
        ],
        defaultExtractor: content => content.match(/[\w-/:]+(?<!:)/g) || [],
        safelist: {
          standard: [
            'cms-animate-fade-in',
            'cms-animate-slide-in-down',
            'cms-animate-slide-in-up',
            'cms-animate-pulse',
            'cms-animate-spin',
            'cms-animate-bounce',
            'show',
            'open',
            'active',
            'editing',
            'error',
            'success',
            'loading'
          ],
          deep: [
            /^cms-/,
            /data-theme/,
            /data-cms-debug/
          ],
          greedy: [
            /^cms-toast-/,
            /^cms-modal-/,
            /^cms-dropdown-/,
            /^cms-floating-panel-/
          ]
        }
      }),
      require('cssnano')({
        preset: ['default', {
          discardComments: { removeAll: true },
          normalizeWhitespace: true,
          colormin: true,
          convertValues: true,
          discardDuplicates: true,
          discardEmpty: true,
          mergeRules: true,
          minifyFontValues: true,
          minifyGradients: true,
          minifyParams: true,
          minifySelectors: true,
          normalizeCharset: true,
          normalizeDisplayValues: true,
          normalizePositions: true,
          normalizeRepeatStyle: true,
          normalizeString: true,
          normalizeTimingFunctions: true,
          normalizeUnicode: true,
          normalizeUrl: true,
          orderedValues: true,
          reduceIdents: true,
          reduceInitial: true,
          reduceTransforms: true,
          svgo: true,
          uniqueSelectors: true
        }]
      })
    ] : [])
  ],
  processCssUrls: false
});

// Main CSS compilation
mix.postCss('resources/css/cms-editor.css', 'public/css', [])
   .postCss('resources/css/cms-variables.css', 'public/css', []);

// Theme compilation
mix.postCss('resources/css/themes/dark.css', 'public/css/themes', [])
   .postCss('resources/css/themes/high-contrast.css', 'public/css/themes', []);

// Critical CSS extraction for above-the-fold content
if (isProduction) {
  const CriticalCssPlugin = require('critical-css-webpack-plugin');

  mix.webpackConfig({
    plugins: [
      new CriticalCssPlugin({
        base: 'public/',
        src: 'index.html',
        dest: 'css/critical.css',
        dimensions: [
          { width: 320, height: 568 },   // Mobile
          { width: 768, height: 1024 },  // Tablet
          { width: 1200, height: 900 }   // Desktop
        ],
        penthouse: {
          blockJSRequests: false,
          timeout: 60000
        }
      })
    ]
  });
}

// JavaScript compilation
mix.js('resources/js/cms/app.js', 'public/js/cms')
   .js('resources/js/cms/editor.js', 'public/js/cms')
   .js('resources/js/cms/preview.js', 'public/js/cms');

// Vue.js support for complex components
mix.vue({ version: 3 });

// Asset optimization
if (isProduction) {
  mix.version(); // Add file hashing for cache busting

  // Bundle analysis
  mix.webpackConfig({
    plugins: [
      new (require('webpack-bundle-analyzer').BundleAnalyzerPlugin)({
        analyzerMode: 'static',
        openAnalyzer: false,
        reportFilename: 'bundle-report.html'
      })
    ]
  });
}

// Source maps for development
if (enableSourceMaps) {
  mix.sourceMaps();
}

// Copy static assets
mix.copyDirectory('resources/images', 'public/images/cms')
   .copyDirectory('resources/fonts', 'public/fonts/cms');

// Hot reload configuration for development
if (!isProduction) {
  mix.browserSync({
    proxy: 'cms.test', // Adjust to your local domain
    files: [
      'resources/views/**/*.blade.php',
      'resources/css/**/*.css',
      'resources/js/**/*.js',
      'public/js/**/*.js',
      'public/css/**/*.css'
    ],
    open: false
  });
}

// Performance budgets
mix.webpackConfig({
  performance: {
    maxAssetSize: 250000,    // 250KB
    maxEntrypointSize: 300000, // 300KB
    hints: isProduction ? 'error' : 'warning'
  }
});

// Webpack optimization
mix.webpackConfig({
  resolve: {
    alias: {
      '@css': path.resolve('resources/css'),
      '@js': path.resolve('resources/js'),
      '@components': path.resolve('resources/js/components'),
      '@utils': path.resolve('resources/js/utils')
    }
  },
  optimization: {
    splitChunks: {
      chunks: 'all',
      cacheGroups: {
        vendor: {
          test: /[\\/]node_modules[\\/]/,
          name: 'vendors',
          priority: 10,
          enforce: true
        },
        common: {
          name: 'common',
          minChunks: 2,
          priority: 5,
          reuseExistingChunk: true
        }
      }
    }
  }
});

// CSS custom build for different environments
mix.extend('cms', (webpackConfig, ...args) => {
  webpackConfig.module.rules.push({
    test: /\.css$/,
    use: [
      'style-loader',
      {
        loader: 'css-loader',
        options: {
          importLoaders: 1,
          modules: {
            localIdentName: '[name]__[local]___[hash:base64:5]'
          }
        }
      },
      'postcss-loader'
    ]
  });
});

// Build notification
mix.then(() => {
  console.log('✅ CMS assets compiled successfully!');

  if (isProduction) {
    console.log('📦 Production build completed with optimizations');
    console.log('🧹 CSS purged and minified');
    console.log('📊 Bundle analysis available in bundle-report.html');
  }
});