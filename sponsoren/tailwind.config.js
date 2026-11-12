import path from 'path'
import { fileURLToPath } from 'url'
const __dirname = path.dirname(fileURLToPath(import.meta.url))

/** @type {import('tailwindcss').Config} */
export default {
  content: [
    path.join(__dirname, 'index.html'),
    path.join(__dirname, 'src/**/*.{vue,js,ts,jsx,tsx}'),
  ],
  theme: {
    extend: {
      colors: {
        bnz: {
          50: '#FDF2F4',
          100: '#FCE7EB',
          200: '#F9D0D9',
          300: '#F5A9B9',
          400: '#EE7290',
          500: '#E03D64',
          600: '#C41E3A',
          700: '#9B1630',
          800: '#7D1226',
          900: '#5C0D1B',
        }
      },
      fontFamily: {
        sans: ['IBM Plex Sans', 'system-ui', '-apple-system', 'sans-serif'],
      },
      animation: {
        'fade-in': 'fadeIn 0.5s ease-out',
        'slide-up': 'slideUp 0.4s ease-out',
      },
      keyframes: {
        fadeIn: {
          '0%': { opacity: '0' },
          '100%': { opacity: '1' },
        },
        slideUp: {
          '0%': { opacity: '0', transform: 'translateY(20px)' },
          '100%': { opacity: '1', transform: 'translateY(0)' },
        },
      },
    },
  },
  plugins: [],
  safelist: [
    'from-bnz-600', 'to-bnz-700',
    'from-bnz-500', 'to-bnz-700',
    'from-red-700', 'to-bnz-600',
    'from-sky-600', 'to-bnz-500',
  ],
}
