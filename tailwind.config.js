/** @type {import('tailwindcss').Config} */
module.exports = {
  // Every file that contains Tailwind class names, including classes built in JS strings.
  content: [
    './app/**/*.php',
    './public/scripts/**/*.js',
  ],
  theme: {
    extend: {},
  },
  plugins: [],
};
