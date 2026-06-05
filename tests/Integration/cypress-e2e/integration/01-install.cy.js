// Runs first in CI: installs Joomla, then drops J2Commerce in from the repo.
// joomla-cypress reads site/db settings from cypress.config.mjs env block.
describe('J2Commerce installation', () => {
  it('installs Joomla and the J2Commerce package', () => {
    cy.installJoomla({
      sitename: Cypress.env('sitename'),
      name: Cypress.env('name'),
      username: Cypress.env('username'),
      password: Cypress.env('password'),
      email: Cypress.env('email'),
      db_type: Cypress.env('db_type'),
      db_host: Cypress.env('db_host'),
      db_name: Cypress.env('db_name'),
      db_user: Cypress.env('db_user'),
      db_password: Cypress.env('db_password'),
      db_prefix: Cypress.env('db_prefix'),
    });

    // Install the built package. Point this at your build/ output (the
    // com_j2commerce_v6.x.x.zip), or use installExtensionFromFolder during dev.
    cy.installExtensionFromFolder('/var/www/html/j2commerce-src');
    cy.checkForPhpNoticesOrWarnings();
  });
});
