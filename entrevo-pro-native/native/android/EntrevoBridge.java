package fr.entrevo.pro;

import android.app.Activity;
import android.content.SharedPreferences;
import android.net.Uri;
import android.os.Handler;
import android.os.Looper;
import android.webkit.JavascriptInterface;
import android.webkit.WebView;
import androidx.core.app.NotificationCompat;
import androidx.core.app.NotificationManagerCompat;
import java.util.concurrent.CountDownLatch;
import java.util.concurrent.TimeUnit;
import java.util.concurrent.atomic.AtomicReference;

/** Fonctions natives offertes aux pages de la billetterie enregistrée (et seulement à elle). */
public class EntrevoBridge {
    private final Activity act;
    private final WebView wv;
    private final SharedPreferences prefs;
    private final Handler ui = new Handler(Looper.getMainLooper());
    private int nid = 1;

    EntrevoBridge(Activity act, WebView wv) {
        this.act = act; this.wv = wv;
        this.prefs = act.getSharedPreferences("entrevo", Activity.MODE_PRIVATE);
    }

    private String currentUrl() {
        AtomicReference<String> u = new AtomicReference<>("");
        CountDownLatch l = new CountDownLatch(1);
        ui.post(() -> { u.set(wv.getUrl() == null ? "" : wv.getUrl()); l.countDown(); });
        try { l.await(800, TimeUnit.MILLISECONDS); } catch (InterruptedException e) { return ""; }
        return u.get();
    }

    private boolean isLocal(String url) { return url.startsWith("https://localhost"); }

    /** Seule la billetterie choisie au premier lancement peut utiliser le NFC et les notifications. */
    private boolean allowed() {
        String url = currentUrl();
        if (isLocal(url)) return true;
        String host = prefs.getString("host", "");
        return !host.isEmpty() && host.equalsIgnoreCase(Uri.parse(url).getHost());
    }

    @JavascriptInterface
    public void setSite(String base) {
        if (!isLocal(currentUrl())) return;
        String host = Uri.parse(base).getHost();
        if (host != null) prefs.edit().putString("host", host).apply();
    }

    @JavascriptInterface
    public boolean nfc(boolean on) { return allowed(); }

    @JavascriptInterface
    public void notify(String title, String body) {
        if (!allowed()) return;
        NotificationCompat.Builder b = new NotificationCompat.Builder(act, MainActivity.CHANNEL)
            .setSmallIcon(act.getApplicationInfo().icon)
            .setContentTitle(title == null ? "Entrevo" : title)
            .setContentText(body == null ? "" : body)
            .setStyle(new NotificationCompat.BigTextStyle().bigText(body == null ? "" : body))
            .setPriority(NotificationCompat.PRIORITY_HIGH)
            .setAutoCancel(true);
        try { NotificationManagerCompat.from(act).notify(nid++, b.build()); } catch (SecurityException e) { /* autorisation refusée */ }
    }

    @JavascriptInterface
    public void home() { ui.post(() -> wv.loadUrl("https://localhost/?reglages=1")); }

    void dispatchNfc(String hex) {
        if (!hex.matches("[0-9A-F]{6,28}")) return;
        ui.post(() -> {
            String url = wv.getUrl() == null ? "" : wv.getUrl();
            String host = prefs.getString("host", "");
            if (host.isEmpty() || !host.equalsIgnoreCase(Uri.parse(url).getHost())) return;
            wv.evaluateJavascript("window.dispatchEvent(new CustomEvent('entrevo-nfc',{detail:'" + hex + "'}))", null);
        });
    }
}
