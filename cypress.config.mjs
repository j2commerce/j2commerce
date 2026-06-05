import { defineConfig } from 'cypress';

export default defineConfig({
  e2e: {
    // The Joomla site under test. Override per-environment with CYPRESS_baseUrl.
    baseUrl: process.env.CYPRESS_baseUrl || 'http://localhost:8080',

    // Keep e2e specs out of the PHP test folders.
    specPattern: 'tests/cypress-e2e/integration/**/*.cy.{js,mjs}',
    supportFile: 'tests/cypress-e2e/support/index.js',
    screenshotsFolder: 'tests/cypress-e2e/output/screenshots',
    videosFolder: 'tests/cypress-e2e/output/videos',
    video: false,

    setupNodeEvents(on, config) {
      return config;
    },
  },

  // Joomla admin credentials + DB details, consumed by joomla-cypress helpers.
  env: {
    sitename: 'J2Commerce Test Site',
    name: 'Admin',
    username: 'admin',
    password: 'admin1234567890',
    email: 'admin@example.com',
    db_type: 'MySQLi',
    db_host: '127.0.0.1',
    db_port: '3306',
    db_name: 'j2commerce_test',
    db_user: 'root',
    db_password: 'root',
    db_prefix: 'jos_',
  },
});
