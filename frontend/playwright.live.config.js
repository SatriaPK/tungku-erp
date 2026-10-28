import {defineConfig} from '@playwright/test';
export default defineConfig({
  testDir:'./tests/live',outputDir:'test-results-live',workers:1,timeout:180000,expect:{timeout:20000},
  use:{baseURL:'http://localhost:5174',headless:true,channel:process.env.PLAYWRIGHT_CHANNEL || undefined,viewport:{width:1440,height:1000}},
  webServer:{command:'npm run dev -- --strictPort',url:'http://localhost:5174',reuseExistingServer:true},
  reporter:'list',
});
