// Joomla is pre-installed via CLI in CI (installation/joomla.php site:install).
// This spec only installs the J2Commerce extension into the running Joomla site.
describe('J2Commerce installation', () => {
  it('installs the J2Commerce package', () => {
    cy.doAdministratorLogin(Cypress.env('username'), Cypress.env('password'));

    // CYPRESS_joomlaExtensionPath set by CI workflow; falls back for local dev.
    const extPath = Cypress.env('joomlaExtensionPath') || '/var/www/html/j2commerce-src';
    cy.installExtensionFromFolder(extPath);
    cy.checkForPhpNoticesOrWarnings();
  });
});
