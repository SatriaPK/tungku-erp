import { defineConfig } from '@playwright/test';
export default defineConfig({
  testDir:'./tests/browser', fullyParallel:true, workers:1,
  timeout:60000, expect:{timeout:15000},
  use:{baseURL:'http://localhost:4174',headless:true,channel:process.env.PLAYWRIGHT_CHANNEL || undefined,viewport:{width:1440,height:1000}},
  webServer:{command:'npm run preview -- --strictPort',url:'http://localhost:4174',reuseExistingServer:!process.env.CI},
  reporter:'list',
});
