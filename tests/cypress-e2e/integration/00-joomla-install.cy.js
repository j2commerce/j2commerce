describe('Joomla installation', () => {
  it('installs Joomla via web installer', () => {
    cy.installJoomla(Cypress.env());
    cy.doAdministratorLogin(Cypress.env('username'), Cypress.env('password'));
  });
});
