describe('J2Commerce storefront', () => {
  it('renders a store/category page on the frontend', () => {
    // Suppress JS parse errors from assets served as 404 HTML by PHP built-in server in CI
    cy.on('uncaught:exception', (err) => {
      if (err.message && err.message.includes("Unexpected token '<'")) {
        return false;
      }
    });

    cy.visit('index.php?option=com_j2commerce&view=categories');
    cy.get('body').should('be.visible');
    cy.checkForPhpNoticesOrWarnings();
  });
});
