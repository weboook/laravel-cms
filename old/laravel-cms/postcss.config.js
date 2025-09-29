module.exports = {
  plugins: {
    // Import processing
    'postcss-import': {
      path: ['resources/css']
    },

    // CSS nesting support
    'postcss-nesting': {},

    // Custom properties processing
    'postcss-custom-properties': {
      preserve: true,
      importFrom: [
        'resources/css/cms-variables.css'
      ]
    },

    // Autoprefixer for browser compatibility
    'autoprefixer': {
      grid: true
    },

    // CSS optimization for production
    ...(process.env.NODE_ENV === 'production' ? {
      // PurgeCSS for removing unused styles
      '@fullhuman/postcss-purgecss': {
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
      },

      // CSS compression
      'cssnano': {
        preset: ['default', {
          discardComments: {
            removeAll: true,
          },
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
      }
    } : {})
  }
}