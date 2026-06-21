/// <reference types="cypress" />

/**
 * Domain stage integration checkpoint — the full Contact -> Segment -> Blast funnel
 * driven through a real browser against the live stack:
 *
 *   1. a freshly-registered owner creates a campaign;
 *   2. adds contacts (two on gmail, one elsewhere);
 *   3. builds a "Gmail users" segment and previews it (matching exactly the two);
 *   4. composes a draft blast targeting that segment;
 *   5. the draft persists, listed with its subject, its target segment, and Draft status.
 *
 * This is the stage's end-to-end proof that the verticals compose. Tenant isolation
 * (non-member 403) is proven separately in campaign-boundary.cy.ts and is not repeated
 * here; per-card dashboard navigation is covered by the campaign-*-link specs.
 *
 * Each run registers a brand-new user with a unique email, so it needs no seeded state.
 */
describe('domain funnel: contact to segment to draft blast', () => {
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

    /** Add a contact through the inline form and wait for its row to appear. */
    function addContact(name: string, email: string): void {
        cy.get('[data-test="contact-name-input"]').clear().type(name);
        cy.get('[data-test="contact-email-input"]').clear().type(email);
        cy.get('[data-test="create-contact-button"]').click();
        cy.contains(email).should('be.visible');
    }

    it('carries a draft blast from contacts through a segment target', () => {
        const campaignName = `Acme Outreach ${Date.now()}`;

        register('E2E Owner');

        // Create the campaign and land on its dashboard as owner.
        cy.visit('/campaigns');
        cy.get('[data-test="campaign-name-input"]').type(campaignName);
        cy.get('[data-test="create-campaign-button"]').click();
        cy.get('[data-test="campaign-dashboard"]').should('exist');

        cy.location('pathname').then((campaignPath) => {
            // 1. Contacts — two gmail addresses and one elsewhere.
            cy.visit(`${campaignPath}/contacts`);
            addContact('Ada Lovelace', 'ada@gmail.com');
            addContact('Bob Babbage', 'bob@gmail.com');
            addContact('Carol Yahoo', 'carol@yahoo.com');

            // 2. Segment — "email contains @gmail.com", previewed then saved.
            cy.visit(`${campaignPath}/segments`);
            cy.get('[data-test="segment-name-input"]').type('Gmail users');
            cy.get('[data-test="add-rule"]').click();
            cy.get('[data-test="criteria-field"]').select('email');
            cy.get('[data-test="criteria-operator"]').select('contains');
            cy.get('[data-test="criteria-value"]').type('@gmail.com');

            cy.get('[data-test="preview-segment"]').click();
            cy.get('[data-test="preview-result"]')
                .should('be.visible')
                .and('contain', '2 contact')
                .and('contain', 'ada@gmail.com')
                .and('contain', 'bob@gmail.com');

            cy.get('[data-test="save-segment"]').click();
            cy.contains('Gmail users').should('be.visible');

            // 3. Blast — compose a draft targeting the segment we just built.
            cy.visit(`${campaignPath}/blasts`);
            cy.get('[data-test="blast-subject-input"]').type('Spring Sale');
            cy.get('[data-test="blast-body-input"]').type('Twenty percent off for our Gmail crowd.');
            cy.get('[data-test="blast-segment-select"]').select('Gmail users');
            cy.get('[data-test="save-blast"]').click();

            // 4. The draft persists: listed with its subject, target, and Draft status.
            cy.get('[data-test^="blast-row-"]')
                .should('have.length', 1)
                .and('contain', 'Spring Sale')
                .and('contain', 'Gmail users')
                .contains(/draft/i)
                .should('exist');
        });
    });
});
