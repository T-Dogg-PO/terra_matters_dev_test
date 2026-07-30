export default defineNuxtConfig({
  compatibilityDate: '2025-07-15',

  devtools: { enabled: false },

  css: ['~/assets/css/main.css'],

  devServer: {
    host: '0.0.0.0',
    port: 3000,
  },

  runtimeConfig: {
    public: {
      // Overridden by NUXT_PUBLIC_API_BASE in docker-compose.yml.
      apiBase: 'http://localhost:8000/api',
    },
  },
})
