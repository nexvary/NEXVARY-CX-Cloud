import assert from 'node:assert/strict';
import { chromium } from 'playwright-core';
import { appendFile, mkdir, writeFile } from 'node:fs/promises';
import { join } from 'node:path';

const { QA_BASE_URL: base, QA_SETUP_TOKEN: setupKey, QA_DB_PASSWORD: databasePassword,
    QA_ADMIN_PASSWORD: password, QA_LOCALE: locale, QA_VIEW: view,
    QA_ESL_EXPECTED, QA_CHROMIUM_PATH, GITHUB_SHA, QA_COMMIT } = process.env;

assert(base && setupKey && databasePassword && password && (locale === 'ar' || locale === 'en'));
const viewport = view === 'mobile' ? { width: 390, height: 844 } : { width: 1440, height: 900 };
const dir = join('visual-evidence', `${locale}-${view}`);
await mkdir(dir, { recursive: true });
const browser = await chromium.launch(QA_CHROMIUM_PATH ? {
    executablePath: QA_CHROMIUM_PATH,
    args: ['--no-sandbox', '--no-zygote', '--single-process', '--disable-breakpad', '--disable-software-rasterizer'],
} : {});

const page = await browser.newPage({ viewport, reducedMotion: 'reduce', colorScheme: 'dark' });
page.setDefaultTimeout(15_000);
const errors = [];
page.on('pageerror', error => errors.push(error.message));

const record = async (name, result = 'pass') => {
    await page.locator('h1').waitFor();
    await page.locator('#nprogress').waitFor({ state: 'hidden' });
    assert.equal(await page.locator('html').getAttribute('dir'), locale === 'ar' ? 'rtl' : 'ltr');
    assert(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth + 1), `Horizontal overflow at ${name}`);
    const filename = `${name}.png`;
    await page.screenshot({ path: join(dir, filename), animations: 'disabled' });
    await appendFile(join(dir, 'index.tsv'), [
        filename, name, process.env.RUNNER_OS ?? 'Debian local', `Chromium ${browser.version()}`,
        `${viewport.width}×${viewport.height}`, locale, QA_COMMIT ?? GITHUB_SHA ?? 'working tree', result,
    ].join('\t') + '\n');
};

const checkDashboardCards = async () => {
    const headings = page.getByTestId('dashboard-modules').getByRole('heading', { level: 2 });
    assert.equal(await headings.count(), 8, 'Dashboard module titles are missing');
    const clipped = await headings.evaluateAll(items => items.filter(heading => {
        const description = heading.nextElementSibling;
        return heading.clientWidth === 0 || heading.scrollWidth > heading.clientWidth + 1
            || !description || description.scrollWidth > description.clientWidth + 1;
    }).map(heading => heading.textContent));
    assert.deepEqual(clipped, [], 'Dashboard module text is clipped');
};

const submit = () => page.locator('form button[type=submit]').click();
const field = (name, value) => page.locator(`form [name="${name}"]`).fill(value);
const waitForResult = ok => page.locator(`[data-testid="result"][data-ok="${ok}"]`).waitFor();
const path = () => new URL(page.url()).pathname;
const clickNext = () => page.getByRole('button', { name: locale === 'ar' ? 'التالي' : 'Next', exact: true }).click();

try {
    await writeFile(join(dir, 'index.tsv'), 'file\tscreen\tenvironment\tbrowser\tviewport\tlanguage\tcommit\tresult\n');
    const response = await page.goto(base + '/setup');
    assert.equal(response.status(), 200);
    await page.locator('#setup-locale').selectOption(locale);
    await page.locator(`html[lang="${locale}"]`).waitFor();
    await record('01-welcome');
    const csrf = await page.locator('meta[name="csrf-token"]').getAttribute('content');
    const unauthorized = await page.request.post(base + '/setup/complete', { headers: { 'X-CSRF-TOKEN': csrf, Accept: 'application/json' }, data: {} });
    assert.equal(unauthorized.status(), 403);
    const payloadWithWrongToken = await page.request.post(base + '/setup/readiness', { headers: { 'X-Setup-Token': setupKey, Accept: 'application/json' }, data: {} });
    assert.equal(payloadWithWrongToken.status(), 419);

    await field('token', setupKey);
    await submit();
    await page.locator('[role=progressbar][aria-valuenow="2"]').waitFor();
    await page.getByRole('button', { name: locale === 'ar' ? 'التالي' : 'Next', exact: true }).waitFor();
    await record('02-system-readiness');
    await clickNext();

    await record('03-postgresql-configuration');
    await field('password', 'deliberately-incorrect-for-isolated-test');
    await submit();
    await waitForResult(false);
    await record('04-postgresql-rejected-credentials', 'expected failure');
    await field('password', databasePassword);
    await submit();
    await waitForResult(true);
    await record('05-postgresql-recovered');
    await clickNext();

    await record('06-redis-configuration');
    const redisPort = await page.locator('input[name="port"]').inputValue();
    await field('port', '1');
    await submit();
    await waitForResult(false);
    await record('07-redis-connection-failed', 'expected failure');
    await field('port', redisPort);
    await submit();
    await waitForResult(true);
    await record('08-redis-recovered');
    await clickNext();

    await record('09-freeswitch-configuration');
    await submit();
    const esl = QA_ESL_EXPECTED === 'true';
    await waitForResult(esl);
    await record('10-freeswitch-check', esl ? 'authenticated status verified' : 'service unavailable; installer continues');
    await page.getByRole('button', {
        name: esl ? (locale === 'ar' ? 'التالي' : 'Next') : (locale === 'ar' ? 'المتابعة دون الاتصالات الهاتفية' : 'Continue without telephony'),
        exact: true,
    }).click();

    await record('11-initial-administrator');
    await field('name', 'QA Administrator');
    await field('email', 'admin@example.test');
    await field('password', password);
    await field('password_confirmation', 'different-test-password');
    await submit();
    await waitForResult(false);
    await record('12-administrator-validation', 'expected failure');
    await field('password_confirmation', password);
    await submit();
    await page.locator('[role=progressbar][aria-valuenow="7"]').waitFor();
    await record('13-review-and-validation');

    const complete = page.waitForResponse(response => new URL(response.url()).pathname === '/setup/complete');
    await submit();
    await page.getByTestId('working').waitFor({ timeout: 4000 });
    await record('14-installation-progress');
    const installed = await complete;
    assert.equal(installed.status(), 200, 'Installation request failed');
    await page.locator('[role=progressbar][aria-valuenow="8"]').waitFor();
    await record('15-installation-success');
    assert.equal((await page.request.get(base + '/setup')).status(), 404);
    await page.locator('a[href="/login"]').click();
    await page.waitForURL('**/login');
    const installedCsrf = await page.locator('meta[name="csrf-token"]').getAttribute('content');
    const repeated = await page.request.post(base + '/setup/complete', {
        headers: { 'X-CSRF-TOKEN': installedCsrf, 'X-Setup-Token': setupKey, Accept: 'application/json' }, data: {},
    });
    assert.equal(repeated.status(), 404);
    await record('16-login');
    await field('email', 'admin@example.test');
    await field('password', 'deliberately-wrong-admin-password');
    await submit();
    await page.locator('[role=alert]').waitFor();
    assert.equal(path(), '/login');
    await record('17-login-rejected', 'expected failure');
    await field('password', password);
    await submit();
    await page.waitForURL('**/dashboard');
    const switchToDay = page.getByRole('button', { name: locale === 'ar' ? 'التبديل إلى المظهر النهاري' : 'Switch to day theme', exact: true });
    const switchToNight = page.getByRole('button', { name: locale === 'ar' ? 'التبديل إلى المظهر الليلي' : 'Switch to night theme', exact: true });
    await page.locator('html[data-nx-theme="night"]').waitFor();
    await checkDashboardCards();
    await record('18-authenticated-dashboard');
    await switchToDay.click();
    await page.locator('html[data-nx-theme="day"]').waitFor();
    await page.reload();
    await page.locator('html[data-nx-theme="day"]').waitFor();
    assert.equal(path(), '/dashboard', 'Session was lost after reload');
    await checkDashboardCards();
    await record('18-authenticated-dashboard-day');
    await switchToNight.click();
    await page.locator('html[data-nx-theme="night"]').waitFor();

    if (view === 'mobile') {
        await page.locator('button[aria-controls="app-navigation"]').click();
        await record('19-mobile-navigation');
    }
    await page.locator('#app-navigation a[href="/admin"]').click();
    await page.waitForURL('**/admin');
    await record('20-administration-guide');
    for (const module of ['trunks', 'extensions', 'inbound', 'outbound', 'ivr', 'queues', 'sms', 'contacts', 'billing', 'voice', 'security', 'maintenance']) {
        await page.locator(`main a[href="/admin/${module}"]`).click();
        await page.waitForURL(`**/admin/${module}`);
        if (module === 'extensions') {
            await field('number', '1001');
            await field('description', 'QA Reception');
            await page.locator('main form button').click();
            await page.getByText('QA Reception', { exact: true }).waitFor();
        }
        if (module === 'contacts') {
            await field('first_name', 'Test');
            await field('last_name', 'Customer');
            await field('email', 'customer@example.test');
            await page.locator('main form button').click();
            await page.getByText('customer@example.test', { exact: true }).waitFor();
        }
        await record(`21-admin-${module}`);
        await page.locator('main a[href="/admin"]').first().click();
        await page.waitForURL('**/admin');
    }
    if (view === 'mobile') {
        const toggle = page.locator('button[aria-controls="app-navigation"]');
        if (await toggle.getAttribute('aria-expanded') === 'false') await toggle.click();
    }
    await page.locator('#app-navigation [data-action="logout"]').click();
    await page.waitForURL('**/login');
    const protectedPage = await page.request.get(base + '/dashboard', { maxRedirects: 0 });
    assert.equal(protectedPage.status(), 302);
    assert.match(protectedPage.headers().location, /\/login$/);
    assert.deepEqual(errors, []);
    console.log(`Browser scenario passed for ${locale} / ${view} (${viewport.width}×${viewport.height})`);
} catch (error) {
    if (errors.length) console.error(errors);
    throw error;
} finally {
    await browser.close();
}
