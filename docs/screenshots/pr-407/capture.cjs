const puppeteer = require('puppeteer');

const BASE = 'http://127.0.0.1:8765';
const OUT  = __dirname;

(async () => {
  const browser = await puppeteer.launch({
    headless: true,
    args: ['--no-sandbox', '--ignore-certificate-errors'],
  });

  const page = await browser.newPage();
  await page.setViewport({ width: 1440, height: 1024 });

  page.on('console', msg => console.log('BROWSER:', msg.type(), msg.text()));
  page.on('pageerror', err => console.log('PAGE ERROR:', err.message));
  page.on('response', res => { if (res.status() >= 400) console.log('HTTP', res.status(), res.url()); });

  const targets = [
    { name: 'upcoming-1day',  url: BASE + '/totem/tasks/upcoming' },
    { name: 'tasks-list',     url: BASE + '/totem/tasks' },
  ];

  for (const t of targets) {
    await page.goto(t.url, { waitUntil: 'domcontentloaded', timeout: 30000 });
    // Wait 8 seconds for Vue to fully mount and AJAX to complete
    await new Promise(r => setTimeout(r, 8000));
    if (t.name === 'upcoming-1day') {
      const state = await page.evaluate(() => {
        const root = document.querySelector('#root');
        const vueInst = root ? root.__vue__ : null;
        let compState = null;
        if (vueInst && vueInst.$children && vueInst.$children.length > 0) {
          // Walk all children to find one with loading/events data
          for (const child of vueInst.$children) {
            if ('loading' in child) {
              compState = {
                loading: child.loading,
                error: child.error,
                eventsCount: child.events ? child.events.length : -1,
                days: child.days,
                optionsName: child.$options ? child.$options.name : 'none',
              };
              break;
            }
          }
          if (!compState) {
            compState = { childrenCount: vueInst.$children.length, childNames: vueInst.$children.map(c => c.$options && c.$options.name) };
          }
        }
        return {
          axiosAvailable: typeof window.axios !== 'undefined',
          calendarExists: !!document.querySelector('.totem-calendar'),
          vueRootExists: !!vueInst,
          compState: compState,
          bodyText: document.body.innerText.substring(0, 500),
        };
      });
      console.log('STATE:', JSON.stringify(state, null, 2));
    }
    const filename = `${OUT}/${t.name}.png`;
    await page.screenshot({ path: filename, fullPage: true });
    console.log(`Saved: ${t.name}.png`);
  }

  // 3-day view: click the "3 Days" button and screenshot
  await page.goto(BASE + '/totem/tasks/upcoming', { waitUntil: 'domcontentloaded', timeout: 30000 });
  await new Promise(r => setTimeout(r, 10000));
  // Click the 3 Days button
  await page.evaluate(() => {
    const buttons = Array.from(document.querySelectorAll('button'));
    const btn = buttons.find(b => b.textContent.trim() === '3 Days');
    if (btn) btn.click();
  });
  await new Promise(r => setTimeout(r, 3000));
  await page.screenshot({ path: `${OUT}/upcoming-3day.png`, fullPage: true });
  console.log('Saved: upcoming-3day.png');

  await browser.close();
  console.log('Done.');
})();
