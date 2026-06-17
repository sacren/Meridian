/// <reference types="cypress" />

/**
 * End-to-end proof that the campaign dashboard exposes a working navigation link
 * into its contacts: a freshly-registered owner creates a campaign, then follows
 * the dashboard's "Contacts" card to the campaign-scoped contacts index.
 *
 * Each run registers a brand-new user with a unique email, so it needs no seeded state.
 */
describe('campaign dashboard contacts link', () => {
    const password = 'password1234';

    /** Register a brand-new user through the UI; Fortify logs them in on success. */
    function register(name: string): void {
        const email = `e2e-${Date.now()}-${Math.random().toString(36).slice(2, 8)}@example.test`;

        cy.visit('/register');
        cy.get('input[name="name"]').type(name);
        cy.get('input[name="email"]').type(email);
        cy.get('input[name="password"]').type(password);
        cy.get('input[name="password_confirmation"]').type(password);
        cy.get('[data-test="register-user-button"]').click();

        cy.url().should('not.include', '/register');
    }

    it('navigates from the dashboard to the campaign contacts page', () => {
        const campaignName = `Acme Outreach ${Date.now()}`;

        register('E2E Owner');

        cy.visit('/campaigns');
        cy.get('[data-test="campaign-name-input"]').type(campaignName);
        cy.get('[data-test="create-campaign-button"]').click();

        // Landed on the new campaign's dashboard as its owner.
        cy.get('[data-test="campaign-dashboard"]').should('exist');

        cy.location('pathname').then((campaignPath) => {
            // Follow the dashboard's Contacts card.
            cy.get('[data-test="contacts-link"]').should('be.visible').click();

            // We land on the campaign-scoped contacts index, with its create form.
            cy.location('pathname').should('eq', `${campaignPath}/contacts`);
            cy.get('[data-test="contact-name-input"]').should('exist');
            cy.get('[data-test="create-contact-button"]').should('exist');
        });
    });
});
