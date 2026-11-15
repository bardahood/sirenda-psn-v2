import axios from 'axios';
window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

// Token CSRF untuk permintaan tulis (PATCH isian sel kertas kerja); cadangan selain cookie XSRF-TOKEN.
const token = document.head.querySelector('meta[name="csrf-token"]');
if (token) window.axios.defaults.headers.common['X-CSRF-TOKEN'] = token.content;
