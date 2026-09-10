package com.clarastars.etraining.chat;

import android.os.Bundle;
import android.view.WindowManager;
import android.webkit.WebSettings;
import android.webkit.WebView;

import com.getcapacitor.BridgeActivity;

public class MainActivity extends BridgeActivity {
    @Override
    public void onCreate(Bundle savedInstanceState) {
        registerPlugin(MaqsamDialerPlugin.class);
        super.onCreate(savedInstanceState);

        // Block screenshots, Recents thumbnails, and screen recording of app content.
        getWindow().setFlags(
            WindowManager.LayoutParams.FLAG_SECURE,
            WindowManager.LayoutParams.FLAG_SECURE
        );
    }

    @Override
    public void onStart() {
        super.onStart();
        markChatAppUserAgent();
    }

    private void markChatAppUserAgent() {
        if (this.bridge == null) {
            return;
        }

        WebView webView = this.bridge.getWebView();
        if (webView == null) {
            return;
        }

        WebSettings settings = webView.getSettings();
        String current = settings.getUserAgentString();
        if (current == null) {
            current = "";
        }
        if (!current.contains("eTrainingChatApp")) {
            settings.setUserAgentString(current + " eTrainingChatApp/1.0");
        }
    }
}
