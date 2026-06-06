describe('Joomla installation', () => {
  it('installs Joomla via web installer', () => {
    cy.installJoomla();
    cy.doAdministratorLogin(Cypress.env('username'), Cypress.env('password'));
  });
});
