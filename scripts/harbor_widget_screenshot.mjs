import { chromium } from 'playwright';
import fs from 'fs';
import path from 'path';

function parseArgs() {
  const args = process.argv.slice(2);
  const out = {};
  for (let i = 0; i < args.length; i += 1) {
    const key = args[i];
    if (!key.startsWith('--')) continue;
    const value = args[i + 1];
    out[key.replace(/^--/, '')] = value;
    i += 1;
  }
  return out;
}

function ensureDir(filePath) {
  const dir = path.dirname(filePath);
  fs.mkdirSync(dir, { recursive: true });
}

const args = parseArgs();
const url = args.url;
const selector = args.selector || '';
const desktopPath = args.desktop;
const mobilePath = args.mobile;
const timeout = Number(args.timeout || 45000);
const waitMs = Number(args.wait || 3000);

if (!url || !desktopPath || !mobilePath) {
  console.error('Missing required arguments.');
  process.exit(1);
}

ensureDir(desktopPath);
ensureDir(mobilePath);

const consoleErrors = [];

const browser = await chromium.launch({ headless: true });
const context = await browser.newContext({
  viewport: { width: 1280, height: 720 },
  userAgent: 'NauticSecureWidgetMonitor/1.0'
});
const page = await context.newPage();

page.on('pageerror', (error) => {
  consoleErrors.push(error.message || String(error));
});

page.on('console', (message) => {
  if (message.type() === 'error') {
    consoleErrors.push(message.text());
  }
});

let widgetFound = false;
let widgetVisible = false;
let widgetClickable = false;
let loadTimeMs = null;

try {
  const start = Date.now();
  try {
    await page.goto(url, { waitUntil: 'networkidle', timeout });
  } catch (error) {
    consoleErrors.push(error.message || String(error));
  }
  loadTimeMs = Date.now() - start;

  if (selector) {
    try {
      await page.waitForSelector(selector, { timeout: Math.min(timeout, 10000) });
    } catch (_) {
      // ignore
    }

    const info = await page.evaluate((sel) => {
      const el = document.querySelector(sel);
      if (!el) {
        return { found: false, visible: false, clickable: false };
      }
      const rect = el.getBoundingClientRect();
      const style = window.getComputedStyle(el);
      const inViewport = rect.width > 0 && rect.height > 0 && rect.bottom > 0 && rect.right > 0;
      const visible = inViewport && style.visibility !== 'hidden' && style.display !== 'none';
      const clickable = visible && style.pointerEvents !== 'none' && !el.hasAttribute('disabled');
      return { found: true, visible, clickable };
    }, selector);

    widgetFound = info.found;
    widgetVisible = info.visible;
    widgetClickable = info.clickable;
  }

  if (waitMs > 0) {
    await page.waitForTimeout(waitMs);
  }

  try {
    await page.setViewportSize({ width: 1280, height: 720 });
    await page.screenshot({ path: desktopPath, fullPage: true });
  } catch (error) {
    consoleErrors.push(error.message || String(error));
  }

  try {
    await page.setViewportSize({ width: 390, height: 844 });
    await page.screenshot({ path: mobilePath, fullPage: true });
  } catch (error) {
    consoleErrors.push(error.message || String(error));
  }
} catch (error) {
  consoleErrors.push(error.message || String(error));
} finally {
  await browser.close();
}

const payload = {
  widget_found: widgetFound,
  widget_visible: widgetVisible,
  widget_clickable: widgetClickable,
  console_errors: consoleErrors,
  load_time_ms: loadTimeMs
};

console.log(JSON.stringify(payload));
