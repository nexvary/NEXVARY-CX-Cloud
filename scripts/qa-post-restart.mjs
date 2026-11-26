/**
 * Stage 4 — post-restart verification.
 *
 * Runs after the PHP dev server has been killed and restarted to simulate
 * a server reboot. Verifies:
 *   1. /setup returns 404 (install flag survives restart — DB-backed)
 *   2. Admin can log in with previously created credentials
 *   3. Dashboard loads and session persists after a page reload
 *   4. At least one extension (created during installation scenario) is listed
 *   5. /setup still returns 404 after a fresh CSRF (not a cache artifact)
 */

import assert from 'node:assert/strict';
import { chromium } from 'playwright-core';
import { appendFile, mkdir, writeFile } from 'node:fs/promises';
import { join } from 'node:path';

const {
    QA_BASE_URL: base,
    QA_ADMIN_PASSWORD: password,
    QA_CHROMIUM_PATH,
    QA_COMMIT,
    GITHUB_SHA,
} = process.env;

assert(base, 'QA_BASE_URL is required');
assert(password, 'QA_ADMIN_PASSWORD is required');

const dir = join('visual-evidence', 'reboot-check');
await mkdir(dir, { recursive: true });

const browser = await chromium.launch(QA_CHROMIUM_PATH ? {
    executablePath: QA_CHROMIUM_PATH,
    args: ['--no-sandbox', '--no-zygote', '--single-process', '--disable-breakpad', '--disable-software-rasterizer'],
} : {});

const page = await browser.newPage({ viewport: { width: 1440, height: 900 } });
page.setDefaultTimeout(15_000);

const screenshot = async (name) => {
    await page.screenshot({ path: join(dir, `${name}.png`), animations: 'disabled' });
    await appendFile(join(dir, 'index.tsv'),
        [
            `${name}.png`, name, process.env.RUNNER_OS ?? 'CI',
            `Chromium ${browser.version()}`, '1440×900', 'en',
            QA_COMMIT ?? GITHUB_SHA ?? 'working tree', 'pass',
        ].join('\t') + '\n',
    );
};

try {
    await writeFile(join(dir, 'index.tsv'),
        'file\tscreen\tenvironment\tbrowser\tviewport\tlanguage\tcommit\tresult\n',
    );

    // 1. /setup must be 404 — installation lock survives restart
    const setupStatus = (await page.request.get(base + '/setup')).status();
    assert.equal(setupStatus, 404, `/setup returned ${setupStatus} after restart — install flag lost`);

    // 2. /up health endpoint must respond
    const upStatus = (await page.request.get(base + '/up')).status();
    assert.equal(upStatus, 200, `/up health check failed after restart`);

    // 3. Login with existing admin credentials
    await page.goto(base + '/login');
    await page.locator('h1').waitFor();
    await screenshot('reboot-01-login-page');

    await page.locator('form [name="email"]').fill('admin@example.test');
    await page.locator('form [name="password"]').fill(password);
    await page.locator('form button[type=submit]').click();
    await page.waitForURL('**/dashboard');
    await page.locator('h1').waitFor();
    await screenshot('reboot-02-dashboard-after-restart');

    // 4. Session persists after page reload (session store intact post-restart)
    await page.reload();
    await page.locator('h1').waitFor();
    assert.equal(new URL(page.url()).pathname, '/dashboard', 'Session lost after reload post-restart');
    await screenshot('reboot-03-dashboard-reload');

    // 5. Data created before restart is still present
    // Extension 1001 "QA Reception" was created via /admin/extensions during the installation scenario
    await page.goto(base + '/admin/extensions');
    await page.locator('h1').waitFor();
    await page.getByText('QA Reception', { exact: true }).waitFor();
    await screenshot('reboot-04-admin-extensions-data-intact');

    // 6. /setup still 404 after a fully authenticated session (not a redirect artifact)
    const setupAfterLogin = (await page.request.get(base + '/setup')).status();
    assert.equal(setupAfterLogin, 404, '/setup should remain 404 when authenticated');

    console.log('Post-restart verification PASSED: install lock intact, login works, data persists.');
} finally {
    await browser.close();
}
