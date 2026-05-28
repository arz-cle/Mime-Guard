import Settings from './components/Settings.vue';

// Register Inertia page component for the settings page
Statamic.booting(() => {
    Statamic.$inertia.register('mime-guard::Settings', Settings);
});

// Client-side MIME validation helper (exposes window.MimeGuard)
import './cp';
