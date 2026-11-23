<?php // Suivi d'audience de l'organisateur (Google Analytics 4, Google Tag Manager, Pixel Meta)
$ga = preg_replace('~[^A-Z0-9-]~', '', strtoupper((string)($o['ga4_id'] ?? '')));
$gtm = preg_replace('~[^A-Z0-9-]~', '', strtoupper((string)($o['gtm_id'] ?? '')));
$px = preg_replace('~[^0-9]~', '', (string)($o['fb_pixel'] ?? ''));
if ($ga): ?><script async src="https://www.googletagmanager.com/gtag/js?id=<?= $ga ?>"></script><script>window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments)}gtag('js',new Date());gtag('config','<?= $ga ?>');</script>
<?php endif; if ($gtm): ?><script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],j=d.createElement(s);j.async=true;j.src='https://www.googletagmanager.com/gtm.js?id='+i;f.parentNode.insertBefore(j,f)})(window,document,'script','dataLayer','<?= $gtm ?>');</script>
<?php endif; if ($px): ?><script>!function(f,b,e,v,n,t,s){if(f.fbq)return;n=f.fbq=function(){n.callMethod?n.callMethod.apply(n,arguments):n.queue.push(arguments)};if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';n.queue=[];t=b.createElement(e);t.async=!0;t.src=v;s=b.getElementsByTagName(e)[0];s.parentNode.insertBefore(t,s)}(window,document,'script','https://connect.facebook.net/en_US/fbevents.js');fbq('init','<?= $px ?>');fbq('track','PageView');</script>
<?php endif;
