describe('J2Commerce admin smoke test', () => {
  beforeEach(() => {
    cy.doAdministratorLogin(Cypress.env('username'), Cypress.env('password'));
  });

  it('loads the dashboard with no PHP notices', () => {
    cy.visit('administrator/index.php?option=com_j2commerce');
    cy.get('h1, .page-title').should('exist');
    cy.checkForPhpNoticesOrWarnings();
  });

  it('opens the products list', () => {
    cy.visit('administrator/index.php?option=com_j2commerce&view=products');
    cy.get('#j2commerce, main, .com-j2commerce').should('exist');
    cy.checkForPhpNoticesOrWarnings();
  });
});
