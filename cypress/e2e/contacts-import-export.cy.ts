/// <reference types="cypress" />

/**
 * Storage stage integration checkpoint — the CSV import/export round-trip driven
 * through a real browser against the live stack (the real s3/MinIO disk + the
 * user's queue:listen processing the ImportContacts job):
 *
 *   1. a freshly-registered owner creates a campaign;
 *   2. exports the (empty) campaign — the download request succeeds and returns
 *      the CSV with its column-contract header row;
 *   3. imports a two-row CSV fixture through the file input;
 *   4. the import settles asynchronously on the queue — the status surface
 *      reaches "completed" with the imported/failed counts;
 *   5. the imported contacts appear in the campaign's contact list.
 *
 * ASYNC: the import is a queued job settling pending -> processing -> completed
 * against the real object store, so step 4 waits (the page polls itself while an
 * import is settling; the assertions carry a generous timeout) rather than
 * asserting immediately.
 *
 * Each run registers a brand-new user with a unique email, so it needs no seeded
 * state. Requires the live stack up: `sail up -d` (MinIO + its bucket per T1),
 * the Vite dev server, and `queue:listen` to process the import.
 */
describe('contacts import/export round-trip', () => {
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

    it('exports a CSV then imports one back, settling the status and listing the contacts', () => {
        const campaignName = `Acme Outreach ${Date.now()}`;

        register('E2E Owner');

        // Create the campaign and land on its dashboard as owner.
        cy.visit('/campaigns');
        cy.get('[data-test="campaign-name-input"]').type(campaignName);
        cy.get('[data-test="create-campaign-button"]').click();
        cy.get('[data-test="campaign-dashboard"]').should('exist');

        cy.location('pathname').then((campaignPath) => {
            cy.visit(`${campaignPath}/contacts`);

            // 1. Export — the download request succeeds and returns the CSV with
            // its header row. A streamed file download is a plain <a href> (not an
            // Inertia visit), so its success is asserted by requesting the href
            // directly, carrying the logged-in session cookie.
            cy.get('[data-test="export-contacts-link"]')
                .should('have.attr', 'href')
                .then((href) => {
                    cy.request(`${href}`).then((response) => {
                        expect(response.status).to.eq(200);
                        expect(response.headers['content-type']).to.contain(
                            'text/csv',
                        );
                        expect(response.body).to.contain(
                            'name,email,phone,custom_fields',
                        );
                    });
                });

            // 2. Import — attach the two-row fixture and submit the upload.
            cy.get('[data-test="contact-import-input"]').selectFile(
                'cypress/fixtures/contacts-import.csv',
            );
            cy.get('[data-test="import-contacts-button"]').click();

            // 3. The import settles on the queue. The page polls itself while an
            // import is pending/processing; the assertion waits (generous timeout)
            // for the status surface to reach "completed".
            cy.get('[data-test="import-status"]', { timeout: 60000 }).should(
                'be.visible',
            );
            cy.get('[data-test="import-status-badge"]', { timeout: 60000 })
                .invoke('text')
                .should('match', /completed/i);

            // 4. Counts settle: both fixture rows imported, none failed.
            cy.get('[data-test="import-imported-count"]').should(
                'have.text',
                '2',
            );
            cy.get('[data-test="import-failed-count"]').should(
                'have.text',
                '0',
            );

            // 5. The imported contacts appear in the campaign's contact list.
            cy.contains('grace@example.com').should('be.visible');
            cy.contains('alan@example.com').should('be.visible');
        });
    });
});
