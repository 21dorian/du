package fr.entrevo.caisse;

import com.getcapacitor.JSObject;
import com.getcapacitor.Plugin;
import com.getcapacitor.PluginCall;
import com.getcapacitor.PluginMethod;
import com.getcapacitor.annotation.CapacitorPlugin;
import java.io.ByteArrayOutputStream;
import java.io.InputStream;
import java.io.OutputStream;
import java.net.InetSocketAddress;
import java.net.Socket;
import java.net.SocketTimeoutException;
import java.nio.charset.StandardCharsets;

/**
 * Dialogue avec un terminal de paiement Nepting en protocole Caisse-AP (connexion caisse, TCP sur le réseau local).
 * La trame TLV est construite et lue côté JavaScript (caisse/app.js) ; ce module ouvre la connexion, envoie la demande
 * et rend la réponse du terminal dès qu'elle est complète (fin de ligne, fermeture de la connexion, ou trame TLV
 * entière contenant le résultat AE).
 */
@CapacitorPlugin(name = "EntrevoTpe")
public class EntrevoTpePlugin extends Plugin {

    @PluginMethod
    public void exchange(PluginCall call) {
        final String host = call.getString("host", "");
        final int port = call.getInt("port", 8888);
        final String data = call.getString("data", "");
        final int timeout = call.getInt("timeout", 120000);
        if (host == null || host.isEmpty()) { call.reject("Adresse du terminal non renseignée."); return; }
        new Thread(() -> {
            try (Socket s = new Socket()) {
                s.connect(new InetSocketAddress(host, port), 5000);
                s.setSoTimeout(timeout);
                OutputStream out = s.getOutputStream();
                out.write(data.getBytes(StandardCharsets.ISO_8859_1));
                out.flush();
                InputStream in = s.getInputStream();
                ByteArrayOutputStream buf = new ByteArrayOutputStream();
                byte[] chunk = new byte[2048];
                int n;
                while ((n = in.read(chunk)) != -1) {
                    buf.write(chunk, 0, n);
                    String cur = new String(buf.toByteArray(), StandardCharsets.ISO_8859_1);
                    if (cur.endsWith("\n") || complete(cur)) break;
                }
                JSObject r = new JSObject();
                r.put("data", new String(buf.toByteArray(), StandardCharsets.ISO_8859_1));
                call.resolve(r);
            } catch (SocketTimeoutException e) {
                call.reject("Le terminal n'a pas répondu à temps.");
            } catch (Exception e) {
                call.reject("Terminal injoignable (" + host + ":" + port + ") : " + e.getMessage());
            }
        }).start();
    }

    /** La réponse se découpe exactement en balises TLV (2 lettres, longueur sur 3 chiffres, valeur) et contient le résultat AE. */
    static boolean complete(String s) {
        int i = 0;
        boolean result = false;
        String t = s.replaceAll("[\\r\\n]+$", "");
        while (i + 5 <= t.length()) {
            String tag = t.substring(i, i + 2);
            if (!tag.matches("[A-Z]{2}")) return false;
            int len;
            try { len = Integer.parseInt(t.substring(i + 2, i + 5)); } catch (NumberFormatException e) { return false; }
            if (i + 5 + len > t.length()) return false;
            if (tag.equals("AE")) result = true;
            i += 5 + len;
        }
        return i == t.length() && result;
    }
}
