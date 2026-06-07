// Joomla is pre-installed via CLI in CI (installation/joomla.php site:install).
// This spec only installs the J2Commerce extension into the running Joomla site.
describe('J2Commerce installation', () => {
  it('installs the J2Commerce package', () => {
    cy.doAdministratorLogin(Cypress.env('username'), Cypress.env('password'));

    // CYPRESS_joomlaExtensionPath set by CI workflow; falls back for local dev.
    // The path has no root manifest, so install library and component separately —
    // each subdirectory carries its own j2commerce.xml manifest.
    const extPath = Cypress.env('joomlaExtensionPath') || '/var/www/html/j2commerce-src';
    cy.installExtensionFromFolder(`${extPath}/libraries/j2commerce`);
    cy.installExtensionFromFolder(`${extPath}/administrator/components/com_j2commerce`);
    cy.checkForPhpNoticesOrWarnings();
  });
});
