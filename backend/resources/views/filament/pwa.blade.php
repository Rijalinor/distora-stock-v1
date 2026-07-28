<link rel="manifest" href="{{ asset('manifest.webmanifest') }}">
<meta name="theme-color" content="#111827">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="Distora Stock">
<link rel="apple-touch-icon" href="{{ asset('pwa-icon-192.png') }}">

<script>
    window.addEventListener('beforeinstallprompt', (event) => {
        event.preventDefault();
        window.distoraInstallPrompt = event;
        window.dispatchEvent(new CustomEvent('distora-pwa-installable'));
    });

    window.addEventListener('appinstalled', () => {
        window.distoraInstallPrompt = null;
    });

    if ('serviceWorker' in navigator) {
        window.addEventListener('load', () => {
            navigator.serviceWorker.register("{{ asset('service-worker.js') }}");
        });
    }
</script>