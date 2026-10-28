import { copyFile } from 'node:fs/promises';
import { constants } from 'node:fs';

// COPYFILE_EXCL preserves existing local configuration.
try {
  await copyFile(new URL('../.env.example', import.meta.url), new URL('../.env.local', import.meta.url), constants.COPYFILE_EXCL);
  console.log('.env.local dibuat dari .env.example. API: http://localhost:8001');
} catch (error) {
  if (error.code !== 'EEXIST') throw error;
  console.log('.env.local sudah ada; konfigurasi dipertahankan.');
}
