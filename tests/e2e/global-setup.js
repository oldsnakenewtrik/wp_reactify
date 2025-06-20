/**
 * Global setup for Playwright tests
 */

const { chromium } = require('@playwright/test');

async function globalSetup(config) {
    console.log('Setting up WordPress test environment...');
    
    // Launch browser for setup
    const browser = await chromium.launch();
    const context = await browser.newContext();
    const page = await context.newPage();
    
    try {
        // Navigate to WordPress admin
        await page.goto('/wp-admin/');
        
        // Login if needed
        if (page.url().includes('wp-login.php')) {
            await page.fill('#user_login', process.env.WP_ADMIN_USER || 'admin');
            await page.fill('#user_pass', process.env.WP_ADMIN_PASS || 'password');
            await page.click('#wp-submit');
            await page.waitForURL('/wp-admin/');
        }
        
        // Activate ReactifyWP plugin if not already active
        await page.goto('/wp-admin/plugins.php');
        
        const pluginRow = page.locator('[data-slug="reactifywp"]');
        const isActive = await pluginRow.locator('.deactivate').count() > 0;
        
        if (!isActive) {
            await pluginRow.locator('.activate a').click();
            await page.waitForSelector('.notice-success');
            console.log('ReactifyWP plugin activated');
        }
        
        // Create test pages if needed
        await createTestPages(page);
        
        console.log('WordPress test environment setup complete');
        
    } catch (error) {
        console.error('Error during global setup:', error);
        throw error;
    } finally {
        await browser.close();
    }
}

async function createTestPages(page) {
    // Create a test page for embedding React apps
    await page.goto('/wp-admin/post-new.php?post_type=page');
    
    // Check if test page already exists
    await page.fill('#title', 'ReactifyWP Test Page');
    
    // Switch to text editor
    await page.click('#content-html');
    
    // Add test content
    const testContent = `
<h2>ReactifyWP Test Page</h2>
<p>This page is used for testing ReactifyWP functionality.</p>

<h3>Test App Container</h3>
<div id="reactify-test-container">
    [reactify slug="test-app"]
</div>

<h3>Multiple Apps</h3>
<div id="reactify-calculator">
    [reactify slug="calculator"]
</div>

<div id="reactify-dashboard">
    [reactify slug="dashboard"]
</div>
    `.trim();
    
    await page.fill('#content', testContent);
    
    // Publish the page
    await page.click('#publish');
    await page.waitForSelector('.notice-success');
    
    console.log('Test page created');
}

module.exports = globalSetup;
