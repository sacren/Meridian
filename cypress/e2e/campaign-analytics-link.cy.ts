/// <reference types="cypress" />

/**
 * End-to-end proof that the campaign dashboard's "Manage" section exposes a working
 * link into analytics: a freshly-registered owner creates a campaign, then follows
 * the dashboard's "Analytics" card to the campaign-scoped analytics page.
 *
 * Each run registers a brand-new user with a unique email, so it needs no seeded state.
 */
describe('campaign dashboard analytics link', () => {
    const password = 'password1234';

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

    it('navigates from the dashboard to the campaign analytics page', () => {
        const campaignName = `Acme Outreach ${Date.now()}`;

        register('E2E Owner');

        cy.visit('/campaigns');
        cy.get('[data-test="campaign-name-input"]').type(campaignName);
        cy.get('[data-test="create-campaign-button"]').click();

        cy.get('[data-test="campaign-dashboard"]').should('exist');

        cy.location('pathname').then((campaignPath) => {
            cy.get('[data-test="analytics-link"]').should('be.visible').click();

            // We land on the campaign-scoped analytics page, with its totals card.
            cy.location('pathname').should('eq', `${campaignPath}/analytics`);
            cy.get('[data-test="analytics-totals"]').should('exist');
        });
    });
});
