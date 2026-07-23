/** @type {import('tailwindcss').Config} */
export default {
  content: ['./index.html', './src/**/*.{js,ts,jsx,tsx}'],
  theme: {
    screens: {
      xs: '480px',
      sm: '640px',
      md: '768px',
      lg: '1024px',
      xl: '1280px',
      '2xl': '1440px',
    },
    extend: {
      colors: {
        acep: {
          primary: '#2563eb',
          surface: '#f8fafc',
          border: '#e2e8f0',
        },
      },
      spacing: {
        'safe-bottom': 'env(safe-area-inset-bottom, 0px)',
        'app-content': 'calc(5rem + env(safe-area-inset-bottom, 0px))',
      },
      minHeight: {
        14: '3.5rem',
      },
    },
  },
  plugins: [],
};
