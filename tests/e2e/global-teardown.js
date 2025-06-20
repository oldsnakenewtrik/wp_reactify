/**
 * Global teardown for Playwright tests
 */

async function globalTeardown(config) {
    console.log('Cleaning up WordPress test environment...');
    
    // Add any cleanup logic here
    // For example, removing test data, resetting options, etc.
    
    console.log('WordPress test environment cleanup complete');
}

module.exports = globalTeardown;
