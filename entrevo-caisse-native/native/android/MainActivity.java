package fr.entrevo.caisse;

import android.os.Bundle;
import com.getcapacitor.BridgeActivity;

/** Point d'entrée de l'application : enregistre les modules natifs Entrevo avant le démarrage du pont Capacitor. */
public class MainActivity extends BridgeActivity {
    @Override
    public void onCreate(Bundle savedInstanceState) {
        registerPlugin(EntrevoTpePlugin.class);
        registerPlugin(EntrevoNfcPlugin.class);
        super.onCreate(savedInstanceState);
    }
}
