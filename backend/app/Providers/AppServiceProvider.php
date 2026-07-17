<?php

namespace App\Providers;

use Filament\Support\Facades\FilamentView;
use Filament\View\PanelsRenderHook;
use Illuminate\Support\Facades\URL;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if (str_contains(request()->url(), 'ngrok-free.dev')) {
            URL::forceScheme('https');
        }

        FilamentView::registerRenderHook(
            'panels::head.end',
            fn (): string => <<<'HTML'
<link rel="manifest" href="/manifest.webmanifest">
<link rel="icon" href="/pwa-icon.svg" sizes="any" type="image/svg+xml">
<link rel="apple-touch-icon" href="/pwa-icon.svg">
<meta name="theme-color" content="#111827">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="Distora Stock">
<script>
if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/service-worker.js').catch(() => {});
    });
}
</script>
HTML
        );

        FilamentView::registerRenderHook(
            PanelsRenderHook::TOPBAR_AFTER,
            function (): string {
                if (! request()->routeIs('filament.admin.pages.dashboard')) {
                    return '';
                }

                return <<<'HTML'
<div
    x-data="{
        deferredPrompt: null,
        canInstall: false,
        init() {
            window.addEventListener('beforeinstallprompt', (event) => {
                event.preventDefault();
                this.deferredPrompt = event;
                this.canInstall = true;
            });
        },
        install() {
            if (! this.deferredPrompt) {
                return;
            }

            this.deferredPrompt.prompt();
            this.deferredPrompt.userChoice.finally(() => {
                this.deferredPrompt = null;
                this.canInstall = false;
            });
        },
    }"
    x-init="init()"
    x-show="canInstall"
    x-cloak
    class="ml-3 flex items-center gap-2 rounded-xl border border-primary-500/30 bg-primary-500/10 px-3 py-2 shadow-sm"
>
    <span class="text-sm font-semibold text-gray-900 dark:text-white">Install PWA</span>
    <button
        type="button"
        x-on:click="install()"
        class="inline-flex items-center justify-center rounded-lg bg-primary-500 px-3 py-2 text-sm font-semibold text-white transition hover:bg-primary-600"
    >
        Install
    </button>
</div>
HTML;
            }
        );
    }
}
