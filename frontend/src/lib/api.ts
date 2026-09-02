import axios from 'axios';

const apiUrl = import.meta.env.VITE_API_URL ?? 'http://localhost:8081';

export const api = axios.create({
  baseURL: apiUrl,
  withCredentials: true,
  withXSRFToken: true,
  headers: {
    Accept: 'application/json',
  },
});

export async function ensureCsrfCookie(): Promise<void> {
  await api.get('/sanctum/csrf-cookie');
}
