const { chromium } = require('playwright');
const BASE = 'http://127.0.0.1:8080';
const OUT = process.argv[2] || '.';
async function login(browser, user, pass) {
  const ctx = await browser.newContext({ viewport: { width: 1280, height: 860 } });
  const page = await ctx.newPage();
  page.on('pageerror', (e) => console.log('pageerror', user, e.message));
  for (let i = 0; i < 3; i++) {
    try {
      await page.goto(BASE + '/admin/login');
      await page.fill('input[name=username]', user);
      await page.fill('input[name=password]', pass);
      await page.click('button[type=submit]');
      await page.waitForURL(/admin\/dashboard/, { timeout: 20000 });
      return page;
    } catch (e) {
      console.log('login retry', user, e.message.split('\n')[0]);
    }
  }
  throw new Error('login failed ' + user);
}
(async () => {
  const browser = await chromium.launch();
  const reader = await (await browser.newContext()).newPage();
  await reader.goto(BASE + '/news/hello');
  const admin = await login(browser, 'admin', 'Admin12345!');
  const mei = await login(browser, 'mei', 'Mei12345!');
  for (const p of [admin, mei]) await p.goto(BASE + '/admin/entries/news/4');
  await admin.waitForSelector('#hibira-presence.hibira-active', { timeout: 20000 });
  console.log('admin sees:', (await admin.textContent('#hibira-presence')).trim());
  await mei.waitForSelector('#hibira-presence.hibira-active', { timeout: 20000 });
  console.log('mei sees:', (await mei.textContent('#hibira-presence')).trim());
  await admin.screenshot({ path: OUT + '/craft-presence.png' });
  await mei.fill('input[name=slug]', 'hello'); await mei.waitForTimeout(500);
  await mei.keyboard.press('Control+s');
  await admin.waitForSelector('.hibira-saved button', { timeout: 20000 });
  console.log('admin saved banner:', (await admin.textContent('.hibira-saved')).trim());
  await admin.screenshot({ path: OUT + '/craft-saved.png' });
  await reader.waitForSelector('#hibira-updated', { timeout: 20000 });
  console.log('reader notice:', (await reader.textContent('#hibira-updated')).trim());
  await reader.screenshot({ path: OUT + '/craft-reader.png' });
  await browser.close();
})().catch((e) => { console.error('FAIL', e.message); process.exit(1); });
