package fr.entrevo.pro;

import android.Manifest;
import android.app.NotificationChannel;
import android.app.NotificationManager;
import android.content.pm.PackageManager;
import android.nfc.NfcAdapter;
import android.nfc.Tag;
import android.os.Build;
import android.os.Bundle;
import android.webkit.WebView;
import androidx.core.app.ActivityCompat;
import androidx.core.content.ContextCompat;
import com.getcapacitor.BridgeActivity;

/**
 * Entrevo Pro : l'appli charge la billetterie de l'organisateur (appli organisateur ou contrôle d'accès)
 * et lui donne accès au lecteur NFC du téléphone et aux notifications, via l'objet JavaScript « EntrevoNative ».
 */
public class MainActivity extends BridgeActivity {
    static final String CHANNEL = "entrevo-alertes";
    private NfcAdapter nfc;
    private EntrevoBridge native_;

    @Override
    public void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        WebView wv = getBridge().getWebView();
        native_ = new EntrevoBridge(this, wv);
        wv.addJavascriptInterface(native_, "EntrevoNative");
        nfc = NfcAdapter.getDefaultAdapter(this);
        if (Build.VERSION.SDK_INT >= 26) {
            NotificationChannel ch = new NotificationChannel(CHANNEL, "Ventes et alertes", NotificationManager.IMPORTANCE_HIGH);
            ch.setDescription("Ventes, paliers, jauge et messages du PC sécurité");
            getSystemService(NotificationManager.class).createNotificationChannel(ch);
        }
        if (Build.VERSION.SDK_INT >= 33 && ContextCompat.checkSelfPermission(this, Manifest.permission.POST_NOTIFICATIONS) != PackageManager.PERMISSION_GRANTED) {
            ActivityCompat.requestPermissions(this, new String[]{Manifest.permission.POST_NOTIFICATIONS}, 42);
        }
    }

    @Override
    public void onResume() {
        super.onResume();
        if (nfc != null && nfc.isEnabled()) {
            int flags = NfcAdapter.FLAG_READER_NFC_A | NfcAdapter.FLAG_READER_NFC_B | NfcAdapter.FLAG_READER_NFC_F | NfcAdapter.FLAG_READER_NFC_V;
            nfc.enableReaderMode(this, this::onTag, flags, null);
        }
    }

    @Override
    public void onPause() {
        super.onPause();
        if (nfc != null) nfc.disableReaderMode(this);
    }

    private void onTag(Tag tag) {
        byte[] id = tag.getId();
        if (id == null || id.length == 0) return;
        StringBuilder hex = new StringBuilder();
        for (byte b : id) hex.append(String.format("%02X", b));
        native_.dispatchNfc(hex.toString());
    }
}
