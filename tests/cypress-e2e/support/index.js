import { registerCommands } from 'joomla-cypress';
registerCommands();

// joomla-cypress 1.x checks h1.page-title after login which was removed in Joomla 6.
// Replace the command so login success is verified by the login form disappearing.
Cypress.Commands.overwrite('doAdministratorLogin', (_originalFn, user, password) => {
  user = user || Cypress.env('username');
  password = password || Cypress.env('password');

  return cy.session([user, password, 'back'], () => {
    cy.visit('administrator/index.php');
    cy.get('#mod-login-username').type(user);
    cy.get('#mod-login-password').type(password);
    cy.get('#btn-login-submit').click();
    cy.get('#mod-login-username', { timeout: 30000 }).should('not.exist');
  }, { cacheAcrossSpecs: true });
});
