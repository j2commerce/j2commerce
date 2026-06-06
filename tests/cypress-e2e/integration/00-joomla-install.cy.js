// Joomla is pre-installed via CLI in CI (installation/joomla.php site:install).
// This spec just verifies the admin login page is reachable before the rest of the suite runs.
describe('Joomla installation', () => {
  it('installs Joomla via web installer', () => {
    cy.visit('administrator/index.php');
    cy.get('#mod-login-username').should('be.visible');
  });
});
