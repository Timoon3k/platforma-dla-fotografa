/**
 * Skrypt startowy podglądu ekranów uwierzytelniania.
 *
 * Kolejność importów ma znaczenie: atrapa sieci musi być zainstalowana,
 * zanim formularz zdąży cokolwiek wysłać. Moduły ES wykonują zależności
 * w kolejności importów, więc `./preview-auth-net.js` idzie pierwszy
 * i nie wolno tego zamienić miejscami.
 */
import './preview-auth-net.js';
import './auth.js';
