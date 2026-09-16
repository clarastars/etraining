/**
 * Thin bridge to the Capacitor MaqsamDialer plugin when running inside the Android APK.
 * Desktop/browser keeps using window.open — this module is a no-op there.
 */
export function isNativeMaqsamDialerAvailable() {
    try {
        const capacitor = typeof window !== 'undefined' ? window.Capacitor : null;
        if (!capacitor || typeof capacitor.isNativePlatform !== 'function' || !capacitor.isNativePlatform()) {
            return false;
        }

        const plugins = capacitor.Plugins || {};
        return !!(plugins.MaqsamDialer && typeof plugins.MaqsamDialer.open === 'function');
    } catch (error) {
        return false;
    }
}

function dialerPlugin() {
    return window.Capacitor.Plugins.MaqsamDialer;
}

export async function nativeDialerOpen(url, options = {}) {
    return dialerPlugin().open({
        url,
        forceReload: !!options.forceReload,
    });
}

export async function nativeDialerFocus() {
    return dialerPlugin().focus();
}

export async function nativeDialerMinimize() {
    return dialerPlugin().minimize();
}

export async function nativeDialerIsOpen() {
    const result = await dialerPlugin().isOpen();
    return !!(result && result.open);
}
