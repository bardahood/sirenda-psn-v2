import '@fontsource-variable/plus-jakarta-sans';
import './bootstrap';
import Alpine from 'alpinejs';
import { filterStore, multiPilih } from './filter';
import { halamanDashboard } from './dashboard/halaman';

window.Alpine = Alpine;
Alpine.store('filter', filterStore());
Alpine.data('multiPilih', multiPilih);
Alpine.data('halamanDashboard', halamanDashboard);
Alpine.start();
