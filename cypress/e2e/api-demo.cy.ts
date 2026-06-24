/// <reference types="cypress" />

/**
 * End-to-end proof of the API console: a freshly-registered owner mints a token
 * for their session, then drives the v1 REST API over HTTP from the browser —
 * reading a campaign's contacts and writing a new one with the Bearer token.
 *
 * Each run registers a brand-new owner with a unique email, so it needs no seeded
 * state. The owner role grants ManageContent, so the write (POST) succeeds.
 */
describe('API console demo', () => {
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

    it('mints a token and drives the v1 API from the browser', () => {
        register('API Owner');

        // Create a campaign so the user owns it (read + write over the API).
        const campaignName = `API Demo Co ${Date.now()}`;
        cy.visit('/campaigns');
        cy.get('[data-test="campaign-name-input"]').type(campaignName);
        cy.get('[data-test="create-campaign-button"]').click();
        cy.get('[data-test="campaign-dashboard"]').should('exist');

        // Open the API console; the new campaign is selectable.
        cy.visit('/api-demo');
        cy.get('[data-test="campaign-select"]').should('contain', campaignName);

        // 1. Generate a token for the session — the plaintext appears once.
        cy.get('[data-test="generate-token-button"]').click();
        cy.get('[data-test="api-token"]')
            .should('be.visible')
            .invoke('text')
            .should('have.length.greaterThan', 0);

        // 2. Read the campaign's contacts over the API (empty to start).
        cy.get('[data-test="fetch-contacts-button"]').click();

        // 3. Write a contact over the API; its row renders from the JSON response.
        const contactName = `Ada ${Date.now()}`;
        cy.get('[data-test="api-contact-name-input"]').type(contactName);
        cy.get('[data-test="api-contact-email-input"]').type(
            `ada-${Date.now()}@example.test`,
        );
        cy.get('[data-test="api-create-contact-button"]').click();

        cy.contains('[data-test^="api-contact-row-"]', contactName).should(
            'exist',
        );
    });
});
