/** @type {import('tailwindcss').Config} */
export default {
  content: [
    "./index.html",
    "./src/**/*.{js,ts,jsx,tsx}",
  ],
  theme: {
    extend: {
      colors: {
        brand: {
          green: '#1DB954',
          dark: '#0b1020',
          darker: '#05080d',
          light: '#191a1f'
        }
      },
      fontFamily: {
        sans: ['system-ui', 'sans-serif']
      }
    },
  },
  plugins: [],
  darkMode: 'class'
}
