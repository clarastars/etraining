package com.clarastars.etraining.chat;

import android.Manifest;
import android.annotation.SuppressLint;
import android.app.Activity;
import android.content.pm.PackageManager;
import android.graphics.Color;
import android.os.Build;
import android.view.Gravity;
import android.view.View;
import android.view.ViewGroup;
import android.webkit.CookieManager;
import android.webkit.PermissionRequest;
import android.webkit.WebChromeClient;
import android.webkit.WebSettings;
import android.webkit.WebView;
import android.webkit.WebViewClient;
import android.widget.FrameLayout;
import android.widget.LinearLayout;
import android.widget.TextView;

import androidx.core.content.ContextCompat;

import com.getcapacitor.JSObject;
import com.getcapacitor.Plugin;
import com.getcapacitor.PluginCall;
import com.getcapacitor.PluginMethod;
import com.getcapacitor.annotation.CapacitorPlugin;
import com.getcapacitor.annotation.Permission;
import com.getcapacitor.annotation.PermissionCallback;

@CapacitorPlugin(
    name = "MaqsamDialer",
    permissions = {
        @Permission(
            alias = "microphone",
            strings = { Manifest.permission.RECORD_AUDIO }
        )
    }
)
public class MaqsamDialerPlugin extends Plugin {
    private FrameLayout overlayRoot;
    private WebView dialerWebView;
    private String loadedUrl = "";
    private boolean dialerVisible = false;

    @PluginMethod
    public void open(PluginCall call) {
        String url = call.getString("url", "");
        if (url == null || url.trim().isEmpty()) {
            call.reject("url is required");
            return;
        }

        if (!hasMicrophonePermission()) {
            requestPermissionForAlias("microphone", call, "microphonePermissionCallback");
            return;
        }

        openDialer(call, url);
    }

    @PermissionCallback
    private void microphonePermissionCallback(PluginCall call) {
        if (!hasMicrophonePermission()) {
            call.reject("Microphone permission is required for Maqsam calls");
            return;
        }

        String url = call.getString("url", "");
        if (url == null || url.trim().isEmpty()) {
            call.reject("url is required");
            return;
        }

        openDialer(call, url);
    }

    private void openDialer(PluginCall call, String url) {
        getActivity().runOnUiThread(() -> {
            try {
                ensureOverlay();
                boolean forceReload = Boolean.TRUE.equals(call.getBoolean("forceReload", false));
                if (forceReload || loadedUrl.isEmpty() || !loadedUrl.equals(url)) {
                    dialerWebView.loadUrl(url);
                    loadedUrl = url;
                }
                showOverlay();
                JSObject result = new JSObject();
                result.put("open", true);
                call.resolve(result);
            } catch (Exception e) {
                call.reject("Failed to open Maqsam dialer: " + e.getMessage(), e);
            }
        });
    }

    @PluginMethod
    public void focus(PluginCall call) {
        getActivity().runOnUiThread(() -> {
            if (dialerWebView == null) {
                JSObject result = new JSObject();
                result.put("open", false);
                call.resolve(result);
                return;
            }
            showOverlay();
            JSObject result = new JSObject();
            result.put("open", true);
            call.resolve(result);
        });
    }

    @PluginMethod
    public void minimize(PluginCall call) {
        getActivity().runOnUiThread(() -> {
            hideOverlay();
            JSObject result = new JSObject();
            result.put("open", isDialerAlive());
            call.resolve(result);
        });
    }

    @PluginMethod
    public void close(PluginCall call) {
        getActivity().runOnUiThread(() -> {
            destroyOverlay();
            JSObject result = new JSObject();
            result.put("open", false);
            call.resolve(result);
        });
    }

    @PluginMethod
    public void isOpen(PluginCall call) {
        JSObject result = new JSObject();
        result.put("open", isDialerAlive());
        result.put("visible", dialerVisible);
        call.resolve(result);
    }

    private boolean isDialerAlive() {
        return dialerWebView != null && loadedUrl != null && !loadedUrl.isEmpty();
    }

    private boolean hasMicrophonePermission() {
        return ContextCompat.checkSelfPermission(getContext(), Manifest.permission.RECORD_AUDIO)
            == PackageManager.PERMISSION_GRANTED;
    }

    @SuppressLint("SetJavaScriptEnabled")
    private void ensureOverlay() {
        Activity activity = getActivity();
        if (activity == null) {
            throw new IllegalStateException("Activity is null");
        }

        if (overlayRoot != null && dialerWebView != null) {
            return;
        }

        ViewGroup content = activity.findViewById(android.R.id.content);

        overlayRoot = new FrameLayout(activity);
        overlayRoot.setLayoutParams(new FrameLayout.LayoutParams(
            ViewGroup.LayoutParams.MATCH_PARENT,
            ViewGroup.LayoutParams.MATCH_PARENT
        ));
        overlayRoot.setClickable(true);
        overlayRoot.setFocusable(true);
        overlayRoot.setBackgroundColor(Color.WHITE);
        overlayRoot.setVisibility(View.GONE);

        LinearLayout column = new LinearLayout(activity);
        column.setOrientation(LinearLayout.VERTICAL);
        column.setLayoutParams(new FrameLayout.LayoutParams(
            ViewGroup.LayoutParams.MATCH_PARENT,
            ViewGroup.LayoutParams.MATCH_PARENT
        ));

        LinearLayout toolbar = new LinearLayout(activity);
        toolbar.setOrientation(LinearLayout.HORIZONTAL);
        toolbar.setGravity(Gravity.CENTER_VERTICAL);
        toolbar.setPadding(dp(12), dp(10), dp(12), dp(10));
        toolbar.setBackgroundColor(Color.parseColor("#0f172a"));
        toolbar.setLayoutParams(new LinearLayout.LayoutParams(
            ViewGroup.LayoutParams.MATCH_PARENT,
            ViewGroup.LayoutParams.WRAP_CONTENT
        ));

        TextView title = new TextView(activity);
        title.setText("Maqsam Dialer");
        title.setTextColor(Color.WHITE);
        title.setTextSize(16);
        LinearLayout.LayoutParams titleParams = new LinearLayout.LayoutParams(
            0,
            ViewGroup.LayoutParams.WRAP_CONTENT,
            1f
        );
        title.setLayoutParams(titleParams);

        TextView minimize = new TextView(activity);
        minimize.setText("Minimize");
        minimize.setTextColor(Color.WHITE);
        minimize.setTextSize(14);
        minimize.setPadding(dp(12), dp(8), dp(12), dp(8));
        minimize.setOnClickListener(v -> hideOverlay());

        toolbar.addView(title);
        toolbar.addView(minimize);

        dialerWebView = new WebView(activity);
        LinearLayout.LayoutParams webParams = new LinearLayout.LayoutParams(
            ViewGroup.LayoutParams.MATCH_PARENT,
            0,
            1f
        );
        dialerWebView.setLayoutParams(webParams);

        WebSettings settings = dialerWebView.getSettings();
        settings.setJavaScriptEnabled(true);
        settings.setDomStorageEnabled(true);
        settings.setMediaPlaybackRequiresUserGesture(false);
        settings.setAllowFileAccess(false);
        settings.setAllowContentAccess(false);
        // Maqsam's web dialer blocks mobile/tablet UAs; present as desktop Chrome.
        settings.setUserAgentString(
            "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 "
                + "(KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36"
        );
        settings.setUseWideViewPort(true);
        settings.setLoadWithOverviewMode(true);
        settings.setSupportZoom(true);
        settings.setBuiltInZoomControls(true);
        settings.setDisplayZoomControls(false);

        CookieManager cookieManager = CookieManager.getInstance();
        cookieManager.setAcceptCookie(true);
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.LOLLIPOP) {
            cookieManager.setAcceptThirdPartyCookies(dialerWebView, true);
        }

        dialerWebView.setWebViewClient(new WebViewClient() {
            @Override
            public void onPageFinished(WebView view, String url) {
                // Force a desktop-like viewport so Maqsam does not treat the shell as a phone.
                view.evaluateJavascript(
                    "(function(){"
                        + "var m=document.querySelector('meta[name=viewport]');"
                        + "if(!m){m=document.createElement('meta');m.name='viewport';document.head.appendChild(m);}"
                        + "m.setAttribute('content','width=1280, initial-scale=0.5, maximum-scale=3, user-scalable=yes');"
                        + "})();",
                    null
                );
            }
        });
        dialerWebView.setWebChromeClient(new WebChromeClient() {
            @Override
            public void onPermissionRequest(final PermissionRequest request) {
                activity.runOnUiThread(() -> {
                    if (request != null) {
                        request.grant(request.getResources());
                    }
                });
            }
        });

        column.addView(toolbar);
        column.addView(dialerWebView);
        overlayRoot.addView(column);
        content.addView(overlayRoot);
    }

    private void showOverlay() {
        if (overlayRoot != null) {
            overlayRoot.setVisibility(View.VISIBLE);
            overlayRoot.bringToFront();
            dialerVisible = true;
        }
    }

    private void hideOverlay() {
        if (overlayRoot != null) {
            overlayRoot.setVisibility(View.GONE);
            dialerVisible = false;
        }
    }

    private void destroyOverlay() {
        Activity activity = getActivity();
        if (dialerWebView != null) {
            dialerWebView.loadUrl("about:blank");
            dialerWebView.stopLoading();
            dialerWebView.clearHistory();
            dialerWebView.removeAllViews();
            dialerWebView.destroy();
            dialerWebView = null;
        }
        if (overlayRoot != null && activity != null) {
            ViewGroup content = activity.findViewById(android.R.id.content);
            if (content != null) {
                content.removeView(overlayRoot);
            }
        }
        overlayRoot = null;
        loadedUrl = "";
        dialerVisible = false;
    }

    private int dp(int value) {
        float density = getContext().getResources().getDisplayMetrics().density;
        return Math.round(value * density);
    }

    @Override
    protected void handleOnDestroy() {
        destroyOverlay();
        super.handleOnDestroy();
    }
}
