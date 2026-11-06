const defaultTheme = require('tailwindcss/defaultTheme');

/** @type {import('tailwindcss').Config} */
module.exports = {
  // Every file that contains Tailwind class names, including classes built in JS strings.
  content: [
    './app/**/*.php',
    './public/scripts/**/*.js',
  ],
  theme: {
    extend: {
      fontFamily: {
        // Montserrat is loaded from Google Fonts in app/Views/partials/head.php.
        sans: ['Montserrat', ...defaultTheme.fontFamily.sans],
      },
    },
  },
  // Bundled with the standalone CLI. Gives inputs, selects and textareas their base styling.
  plugins: [require('@tailwindcss/forms')],
};
