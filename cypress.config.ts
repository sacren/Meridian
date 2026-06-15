import { defineConfig } from 'cypress';

export default defineConfig({
    e2e: {
        // The app is served from its public origin (see the network-topology rule);
        // override on another host with the CYPRESS_baseUrl env var.
        baseUrl: 'http://laravel.local:8041',
        supportFile: 'cypress/support/e2e.ts',
        specPattern: 'cypress/e2e/**/*.cy.{js,jsx,ts,tsx}',
    },
});
