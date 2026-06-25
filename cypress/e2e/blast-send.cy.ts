/// <reference types="cypress" />

/**
 * End-to-end proof of the blast send flow, driven through a real browser against
 * the live stack (the queue listener must be running for the deliveries to settle):
 *
 *   1. a freshly-registered owner creates a campaign and adds two Gmail contacts;
 *   2. builds a "Gmail users" segment matching them both;
 *   3. composes a draft blast targeting that segment;
 *   4. sends it — the row flips out of Draft and the deliveries fan out;
 *   5. once the queue drains the blast settles on Sent, and the row reports the
 *      full recipient count as sent ("2/2 sent");
 *   6. a sent blast is read-only: Send/Edit/Delete are gone.
 *
 * Each run registers a brand-new owner with a unique email, so it needs no seeded
 * state. The owner role grants ManageContent, so the Send affordance is offered.
 */
describe('blast send: draft to sent', () => {
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

    /**
     * Deliveries settle on the queue, so the Sending -> Sent transition lands
     * server-side after the redirect has already rendered. Reload until the row
     * reports Sent (bounded, so a stuck queue fails rather than hangs).
     */
    function reloadUntilSent(attempts = 10): void {
        cy.get('[data-test^="blast-status-"]').then(($status) => {
            if (/sent/i.test($status.text())) {
                return;
            }

            expect(
                attempts,
                'blast settled on Sent before running out of reloads',
            ).to.be.greaterThan(0);
            cy.wait(1000);
            cy.reload();
            reloadUntilSent(attempts - 1);
        });
    }

    it('sends a targeted draft and settles it on Sent', () => {
        const campaignName = `Send Co ${Date.now()}`;

        register('Send Owner');

        cy.visit('/campaigns');
        cy.get('[data-test="campaign-name-input"]').type(campaignName);
        cy.get('[data-test="create-campaign-button"]').click();
        cy.get('[data-test="campaign-dashboard"]').should('exist');

        cy.location('pathname').then((campaignPath) => {
            // Contacts — two gmail addresses to make up the audience.
            cy.visit(`${campaignPath}/contacts`);
            addContact('Ada Lovelace', 'ada@gmail.com');
            addContact('Bob Babbage', 'bob@gmail.com');

            // Segment — "email contains @gmail.com", matching both contacts.
            cy.visit(`${campaignPath}/segments`);
            cy.get('[data-test="segment-name-input"]').type('Gmail users');
            cy.get('[data-test="add-rule"]').click();
            cy.get('[data-test="criteria-field"]').select('email');
            cy.get('[data-test="criteria-operator"]').select('contains');
            cy.get('[data-test="criteria-value"]').type('@gmail.com');
            cy.get('[data-test="save-segment"]').click();
            cy.contains('Gmail users').should('be.visible');

            // Blast — compose a draft targeting that segment.
            cy.visit(`${campaignPath}/blasts`);
            cy.get('[data-test="blast-subject-input"]').type('Spring Sale');
            cy.get('[data-test="blast-body-input"]').type(
                'Twenty percent off for our Gmail crowd.',
            );
            cy.get('[data-test="blast-segment-select"]').select('Gmail users');
            cy.get('[data-test="save-blast"]').click();

            // The draft is listed with its Draft badge and offers the Send button.
            cy.get('[data-test^="blast-row-"]').should('have.length', 1);
            cy.get('[data-test^="blast-status-"]').should('contain', 'draft');
            cy.get('[data-test^="send-blast-"]').should('be.visible').click();

            // The send redirect leaves Draft immediately; the queue then settles
            // it on Sent, at which point the row reports the whole audience sent.
            cy.get('[data-test^="blast-status-"]').should(
                'not.contain',
                'draft',
            );
            reloadUntilSent();
            cy.get('[data-test^="blast-status-"]').should('contain', 'sent');
            cy.get('[data-test^="blast-sent-count-"]').should(
                'contain',
                '2/2 sent',
            );

            // A sent blast is read-only: no Send/Edit/Delete are offered.
            cy.get('[data-test^="send-blast-"]').should('not.exist');
            cy.contains('button', 'Edit').should('not.exist');
            cy.contains('button', 'Delete').should('not.exist');
        });
    });
});
