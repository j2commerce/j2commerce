// J2Commerce is installed via Joomla CLI (extension:discover:install) in CI.
// This spec verifies the installation succeeded.
describe('J2Commerce installation', () => {
  it('installs the J2Commerce package', () => {
    cy.doAdministratorLogin(Cypress.env('username'), Cypress.env('password'));
    cy.visit('administrator/index.php?option=com_j2commerce');
    cy.get('h1, .page-title').should('exist');
    cy.checkForPhpNoticesOrWarnings();
  });
});
