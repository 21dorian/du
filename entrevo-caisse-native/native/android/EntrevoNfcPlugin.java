package fr.entrevo.caisse;

import android.app.Activity;
import android.nfc.NfcAdapter;
import android.nfc.Tag;
import com.getcapacitor.JSObject;
import com.getcapacitor.Plugin;
import com.getcapacitor.PluginCall;
import com.getcapacitor.PluginMethod;
import com.getcapacitor.annotation.CapacitorPlugin;

/**
 * Lecture des bracelets et cartes NFC : renvoie l'identifiant unique de la puce (UID, en hexadécimal) à chaque contact.
 * Mode « lecteur » d'Android : l'appli reste au premier plan, aucun autre programme ne s'ouvre au contact d'un bracelet.
 */
@CapacitorPlugin(name = "EntrevoNfc")
public class EntrevoNfcPlugin extends Plugin {
    private boolean reading = false;

    @PluginMethod
    public void isAvailable(PluginCall call) {
        NfcAdapter a = NfcAdapter.getDefaultAdapter(getContext());
        JSObject r = new JSObject();
        r.put("available", a != null);
        r.put("enabled", a != null && a.isEnabled());
        call.resolve(r);
    }

    @PluginMethod
    public void startReading(PluginCall call) {
        NfcAdapter a = NfcAdapter.getDefaultAdapter(getContext());
        if (a == null) { call.reject("Cet appareil n'a pas de lecteur NFC."); return; }
        if (!a.isEnabled()) { call.reject("Active le NFC dans les réglages Android."); return; }
        reading = true;
        enable(a);
        call.resolve();
    }

    @PluginMethod
    public void stopReading(PluginCall call) {
        reading = false;
        NfcAdapter a = NfcAdapter.getDefaultAdapter(getContext());
        Activity act = getActivity();
        if (a != null && act != null) act.runOnUiThread(() -> a.disableReaderMode(act));
        call.resolve();
    }

    private void enable(NfcAdapter a) {
        Activity act = getActivity();
        if (act == null) return;
        int flags = NfcAdapter.FLAG_READER_NFC_A | NfcAdapter.FLAG_READER_NFC_B | NfcAdapter.FLAG_READER_NFC_F
            | NfcAdapter.FLAG_READER_NFC_V | NfcAdapter.FLAG_READER_SKIP_NDEF_CHECK;
        act.runOnUiThread(() -> a.enableReaderMode(act, this::onTag, flags, null));
    }

    private void onTag(Tag tag) {
        byte[] id = tag.getId();
        StringBuilder sb = new StringBuilder();
        for (byte b : id) sb.append(String.format("%02X", b));
        JSObject o = new JSObject();
        o.put("uid", sb.toString());
        notifyListeners("tag", o);
    }

    @Override
    protected void handleOnResume() {
        super.handleOnResume();
        NfcAdapter a = NfcAdapter.getDefaultAdapter(getContext());
        if (reading && a != null && a.isEnabled()) enable(a);
    }

    @Override
    protected void handleOnPause() {
        NfcAdapter a = NfcAdapter.getDefaultAdapter(getContext());
        Activity act = getActivity();
        if (a != null && act != null) a.disableReaderMode(act);
        super.handleOnPause();
    }
}
