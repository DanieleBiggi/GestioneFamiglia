const assert = require('node:assert/strict');
const { chromium } = require('playwright');

(async () => {
  const browser = await chromium.launch({ executablePath: process.env.GF_MENU_BROWSER });
  try {
    const origin = 'http://127.0.0.1:38081';
    const context = await browser.newContext();
    await context.route('**/*', route => new URL(route.request().url()).origin === origin ? route.continue() : route.abort());
    await context.addCookies([{ name: 'PHPSESSID', value: 'family-a', url: origin }]);
    const page = await context.newPage();
    await page.goto(origin + '/Gestionale25/menu_cene_config.php');
    await page.getByRole('heading', { name: 'Configurazione · Menù cene · Master' }).waitFor();
    await page.locator('#cfgWeek').selectOption('2');
    const field = page.locator('[data-day="Lunedì"] [data-field="cena"]');
    assert.equal(await field.inputValue(), 'Pasta; INSALATA + tè 🍋');
    await field.fill('Modifica libera dal browser');
    page.once('dialog', dialog => dialog.accept());
    await page.getByRole('button', { name: 'Salva modifiche' }).click();
    await page.getByText('Configurazione salvata.', { exact: false }).waitFor();
    await page.reload();
    await page.getByRole('heading', { name: 'Configurazione · Menù cene · Master' }).waitFor();
    await page.locator('#cfgWeek').selectOption('2');
    assert.equal(await field.inputValue(), 'Modifica libera dal browser');
    console.log('OK: modifica libera e persistenza dall’editor web in Chromium');
  } finally {
    await browser.close();
  }
})().catch(error => {
  console.error(error);
  process.exitCode = 1;
});
