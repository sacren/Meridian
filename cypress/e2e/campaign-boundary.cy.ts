/// <reference types="cypress" />

/**
 * End-to-end proof of the tenant boundary through a real browser:
 *   1. a freshly-registered user creates a campaign and lands on its dashboard as owner;
 *   2. a second, unrelated user is blocked from that campaign's URL with a 403.
 *
 * Each run registers brand-new users with unique emails, so it needs no seeded state.
 */
describe('campaign tenant boundary', () => {
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

        // Registration completes by navigating away from /register.
        cy.url().should('not.include', '/register');
    }

    it('lets the owner reach the dashboard and blocks a non-member with 403', () => {
        const campaignName = `Acme Outreach ${Date.now()}`;

        // First user registers, then creates a campaign.
        register('E2E Owner');

        cy.visit('/campaigns');
        cy.get('[data-test="campaign-name-input"]').type(campaignName);
        cy.get('[data-test="create-campaign-button"]').click();

        // They land on the new campaign's dashboard as its owner.
        cy.location('pathname').should('match', /^\/campaigns\/[^/]+$/);
        cy.get('[data-test="campaign-dashboard"]').should('exist');
        cy.contains(campaignName).should('be.visible');
        cy.get('[data-test="campaign-role"]').should('contain', 'owner');

        cy.location('pathname').then((campaignPath) => {
            // Switch identities: drop the session and register a second, unrelated user.
            cy.clearCookies();
            register('E2E Stranger');

            // The non-member is forbidden from the first user's campaign URL.
            cy.request({ url: campaignPath, failOnStatusCode: false })
                .its('status')
                .should('eq', 403);
        });
    });
});
