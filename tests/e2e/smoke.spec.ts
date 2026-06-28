import { test, expect } from '@playwright/test';

const EMAIL    = process.env['CI_TEST_EMAIL']    ?? 'test@ci.local';
const PASSWORD = process.env['CI_TEST_PASSWORD'] ?? 'testpass123';

async function login(page: import('@playwright/test').Page) {
  await page.goto('/login.php');
  await page.fill('input[name="email"]',    EMAIL);
  await page.fill('input[name="password"]', PASSWORD);
  await page.click('button[type="submit"]');
  await page.waitForURL(/index\.php/);
}

test.describe('Auth flow', () => {
  test('unauthenticated request redirects to login', async ({ page }) => {
    await page.goto('/index.php');
    await expect(page).toHaveURL(/login\.php/);
  });

  test('login with valid credentials redirects to dashboard', async ({ page }) => {
    await page.goto('/login.php');
    await page.fill('input[name="email"]',    EMAIL);
    await page.fill('input[name="password"]', PASSWORD);
    await page.click('button[type="submit"]');
    await expect(page).toHaveURL(/index\.php/);
  });

  test('login with wrong password shows error', async ({ page }) => {
    await page.goto('/login.php');
    await page.fill('input[name="email"]',    EMAIL);
    await page.fill('input[name="password"]', 'wrong-password');
    await page.click('button[type="submit"]');
    await expect(page).toHaveURL(/login\.php/);
    await expect(page.locator('.error, [class*="error"]')).toBeVisible();
  });
});

test.describe('Dashboard (requires login)', () => {
  test('dashboard loads and contains card table', async ({ page }) => {
    await login(page);
    await expect(page.locator('table.card-table, table')).toBeVisible();
  });

  test('add-card form is reachable', async ({ page }) => {
    await login(page);
    await page.goto('/card-add.php');
    await expect(page.locator('#add-form')).toBeVisible();
    await expect(page.locator('input[name="name"]')).toBeVisible();
  });

  test('logout ends the session', async ({ page }) => {
    await login(page);
    await page.goto('/logout.php');
    // After logout, a protected page must redirect to login
    await page.goto('/card-add.php');
    await expect(page).toHaveURL(/login\.php/);
  });
});
