describe('J2Commerce storefront', () => {
  it('renders a store/category page on the frontend', () => {
    // Ignore JS parse errors from Joomla/third-party assets that return HTML in CI
    // (PHP built-in server serving 404 HTML for missing assets causes "Unexpected token '<'")
    cy.on('uncaughtException', (err) => {
      if (err.message && err.message.includes("Unexpected token '<'")) {
        return false;
      }
    });

    cy.visit('index.php?option=com_j2commerce&view=categories');
    cy.get('body').should('be.visible');
    cy.checkForPhpNoticesOrWarnings();
  });
});
