import { defineConfig } from 'cypress';

export default defineConfig({
    // Adopt Cypress 15's secure default ahead of Cypress 16: forbid the deprecated
    // Cypress.env() in-browser access (removed in v16). This suite reads no such
    // values, so the flag is a guardrail with no behavioral cost. Remove this line
    // when upgrading to v16, where the option no longer exists.
    allowCypressEnv: false,
    e2e: {
        // The app is served from its public origin (see the network-topology rule);
        // override on another host with the CYPRESS_baseUrl env var.
        baseUrl: 'http://laravel.local:8041',
        supportFile: 'cypress/support/e2e.ts',
        specPattern: 'cypress/e2e/**/*.cy.{js,jsx,ts,tsx}',
    },
});
