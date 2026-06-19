/// <reference types="cypress" />

/**
 * Regression guard for the segment builder's live preview: a stale preview result
 * must not linger across client-only form transitions. After running a preview,
 * clicking Edit (load another segment) or Cancel must clear the preview card,
 * because the displayed match no longer corresponds to the builder's criteria.
 *
 * Each run registers a brand-new owner with a unique email, so it needs no seeded state.
 */
describe('segment preview reset', () => {
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

    it('clears a stale preview when an existing segment is edited', () => {
        register('E2E Owner');

        // Create a campaign and capture its slug.
        cy.visit('/campaigns');
        cy.get('[data-test="campaign-name-input"]').type(`Acme ${Date.now()}`);
        cy.get('[data-test="create-campaign-button"]').click();
        cy.get('[data-test="campaign-dashboard"]').should('exist');

        cy.location('pathname').then((dashboardPath) => {
            const slug = dashboardPath.split('/').pop();

            // Add a contact so previews have something to match.
            cy.visit(`/campaigns/${slug}/contacts`);
            cy.get('[data-test="contact-name-input"]').type('Ada Lovelace');
            cy.get('[data-test="contact-email-input"]').type('ada@gmail.com');
            cy.get('[data-test="create-contact-button"]').click();

            // Save a segment so there is a row to edit later.
            cy.visit(`/campaigns/${slug}/segments`);
            cy.get('[data-test="segment-name-input"]').type('Saved segment');
            cy.get('[data-test="save-segment"]').click();
            cy.get('[data-test^="segment-row-"]').should('have.length', 1);

            // Run a preview on the (empty) builder — the result card appears.
            cy.get('[data-test="preview-segment"]').click();
            cy.get('[data-test="preview-result"]').should('be.visible');

            // Editing the saved segment must clear the stale preview.
            cy.get('[data-test^="segment-row-"]')
                .first()
                .find('button')
                .contains('Edit')
                .click();
            cy.get('[data-test="preview-result"]').should('not.exist');

            // Preview again, then Cancel must also clear it.
            cy.get('[data-test="preview-segment"]').click();
            cy.get('[data-test="preview-result"]').should('be.visible');
            cy.contains('button', 'Cancel').click();
            cy.get('[data-test="preview-result"]').should('not.exist');
        });
    });
});
