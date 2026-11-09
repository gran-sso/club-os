<!-- Progressive Web App (PWA) Manifest & Mobile Meta Tags -->
<link rel="manifest" href="/manifest-admin.json">
<meta name="theme-color" content="#0f172a">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="Malabar Admin">
<meta name="format-detection" content="telephone=no">

<!-- Apple Touch Icons & Favicons -->
<link rel="apple-touch-icon" sizes="180x180" href="/icons/apple-touch-icon.png">
<link rel="icon" type="image/png" sizes="192x192" href="/icons/icon-192.png">
<link rel="icon" type="image/png" sizes="512x512" href="/icons/icon-512.png">
<link rel="icon" type="image/svg+xml" href="/icons/icon-192.svg">

<!-- Service Worker Registration -->
<script>
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', function () {
            navigator.serviceWorker.register('/sw.js', { scope: '/' })
                .then(function (registration) {
                    // Service worker registered successfully
                })
                .catch(function (error) {
                    console.warn('Malabar Admin ServiceWorker registration failed:', error);
                });
        });
    }
</script>

