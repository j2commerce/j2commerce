describe('J2Commerce storefront', () => {
  it('renders a store/category page on the frontend', () => {
    // Adjust the Itemid/menu path to a real J2Commerce menu item on the test site.
    cy.visit('index.php?option=com_j2commerce&view=categories');
    cy.get('body').should('be.visible');
    cy.checkForPhpNoticesOrWarnings();
  });
});
