import axios from 'axios';

export const API_ORIGIN = (import.meta.env.VITE_API_ORIGIN ||
  import.meta.env.VITE_API_BASE_URL?.replace(/\/api(?:\/v1)?\/?$/, '') ||
  (import.meta.env.DEV ? 'http://localhost:8001' : '')).replace(/\/$/, '');

export const api = axios.create({
  baseURL: `${API_ORIGIN}/api/v1`, withCredentials: true, withXSRFToken: true, timeout:20000,
  headers: { Accept: 'application/json' },
});

let csrfRequest;
export function refreshCsrf() {
  if (!csrfRequest) {
    csrfRequest = axios.get(`${API_ORIGIN}/sanctum/csrf-cookie`, {
      withCredentials: true, timeout:20000, headers: { Accept: 'application/json' },
    }).finally(() => { csrfRequest = undefined; });
  }
  return csrfRequest;
}

api.interceptors.response.use(response => response, async error => {
  const status = error.response?.status;
  const payload = error.response?.data;
  error.status = status;
  error.errors = payload?.errors || {};
  error.retryAfter = Number(error.response?.headers?.['retry-after'] || 0);
  error.message = payload?.message || (status === 403 ? 'Akun atau tindakan ini tidak diizinkan.' :
    status === 404 ? 'Data tidak tersedia.' : status === 429 ? 'Terlalu banyak permintaan. Tunggu sebelum mencoba kembali.' :
      status === 419 ? 'Session kedaluwarsa. Silakan masuk kembali.' :
        status ? 'Permintaan gagal. Muat ulang data sebelum mencoba kembali.' :
          'Backend tidak dapat dihubungi. Periksa koneksi dan alamat API.');
  if (status === 419) {
    // Recheck the actual session after refreshing CSRF. Never replay a mutation.
    await refreshCsrf().catch(() => {});
    if (error.config?.url !== '/auth/me') {
      try {
        const response=await api.get('/auth/me');
        window.dispatchEvent(new CustomEvent('session-restored',{detail:response.data.data}));
        error.message='Cookie keamanan sudah diperbarui. Periksa data terbaru sebelum mengulangi tindakan.';
      } catch(checkError) {
        if(checkError.status===403) window.dispatchEvent(new CustomEvent('session-restricted',{detail:checkError}));
        else window.dispatchEvent(new Event('session-expired'));
      }
    }
  } else if (status === 401 && error.config?.url !== '/auth/me') window.dispatchEvent(new Event('session-expired'));
  return Promise.reject(error);
});

export async function request(path, { method = 'GET', body, params, signal } = {}) {
  const response = await api.request({ url: path, method, data: body, params, signal });
  return response.status === 204 ? null : response.data;
}
export async function login(body) {
  await refreshCsrf();
  await request('/auth/login', { method: 'POST', body });
  return (await request('/auth/me')).data;
}
export async function register(body) {
  await refreshCsrf();
  await request('/auth/register', { method: 'POST', body });
  return (await request('/auth/me')).data;
}
