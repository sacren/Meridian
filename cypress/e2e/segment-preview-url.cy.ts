/// <reference types="cypress" />

/**
 * Regression guard for the segment builder's live preview URL handling. The
 * preview endpoint returns an Inertia render rather than a redirect, so the
 * builder must keep the browser URL pinned to the segments page. Otherwise the
 * URL becomes `.../segments/preview` (a POST-only route), and the next redirect
 * "back" — e.g. previewing an incomplete rule fails validation and redirects to
 * the referer — is followed as a GET, crashing with a 405
 * MethodNotAllowedHttpException.
 *
 * Reproduces the exact reported steps: preview a valid segment, add a fresh
 * (incomplete) rule, then preview again — which must not error.
 *
 * Each run registers a brand-new owner with a unique email, so it needs no seeded state.
 */
describe('segment preview URL', () => {
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

    it('stays on the segments page across repeated previews and an added rule', () => {
        register('E2E Owner');

        // Create a campaign and capture its slug.
        cy.visit('/campaigns');
        cy.get('[data-test="campaign-name-input"]').type(`Acme ${Date.now()}`);
        cy.get('[data-test="create-campaign-button"]').click();
        cy.get('[data-test="campaign-dashboard"]').should('exist');

        cy.location('pathname').then((dashboardPath) => {
            const slug = dashboardPath.split('/').pop();

            // A contact for the preview to match.
            cy.visit(`/campaigns/${slug}/contacts`);
            cy.get('[data-test="contact-name-input"]').type('Ada Lovelace');
            cy.get('[data-test="contact-email-input"]').type('ada@gmail.com');
            cy.get('[data-test="create-contact-button"]').click();

            // Build and save a segment with one valid rule (email contains @gmail.com).
            cy.visit(`/campaigns/${slug}/segments`);
            cy.get('[data-test="segment-name-input"]').type('Gmail users');
            cy.get('[data-test="add-rule"]').click();
            cy.get('[data-test="criteria-field"]').first().select('email');
            cy.get('[data-test="criteria-operator"]')
                .first()
                .select('contains');
            cy.get('[data-test="criteria-value"]').first().type('@gmail.com');
            cy.get('[data-test="save-segment"]').click();
            cy.get('[data-test^="segment-row-"]').should('have.length', 1);

            // Edit it, then run a first (valid) preview.
            cy.get('[data-test^="segment-row-"]')
                .first()
                .find('button')
                .contains('Edit')
                .click();
            cy.get('[data-test="preview-segment"]').click();
            cy.get('[data-test="preview-result"]').should('be.visible');

            // The successful preview must NOT have moved the URL onto the POST-only
            // preview endpoint.
            cy.location('pathname').should('match', /\/segments$/);

            // Add a fresh, still-incomplete rule, then preview again. Before the fix
            // this second preview crashed with a 405; now it stays on the page.
            cy.get('[data-test="add-rule"]').click();
            cy.get('[data-test="preview-segment"]').click();

            cy.get('[data-test="segment-form"]').should('exist');
            cy.location('pathname').should('match', /\/segments$/);
            cy.contains('MethodNotAllowedHttpException').should('not.exist');
        });
    });
});
