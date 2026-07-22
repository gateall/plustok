/** @type {import('tailwindcss').Config} */
export default {
  content: ['./index.html', './src/**/*.{js,ts,jsx,tsx}'],
  theme: {
    extend: {
      colors: {
        acep: {
          primary: '#2563eb',
          surface: '#f8fafc',
          border: '#e2e8f0',
        },
      },
    },
  },
  plugins: [],
};
