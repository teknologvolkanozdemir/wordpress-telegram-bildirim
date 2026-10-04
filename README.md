# WordPress Telegram Bildirim

WordPress yazı ve sayfa işlemleri ile yorum ve yönetici hesabı olaylarında Telegram'a bildirim gönderir. Bildirimler yalnızca ayarlarda belirtilen yönetici sohbetine gönderilir; site kullanıcılarına mesaj gönderilmez.

## Kurulum

1. Bu depodaki `wordpress-telegram-bildirim.php` dosyasını `wp-content/plugins/wordpress-telegram-bildirim/` klasörüne yükleyin.
2. WordPress yönetim panelinde **Eklentiler** bölümünden **WordPress Telegram Bildirim** eklentisini etkinleştirin.
3. Telegram'da bir bot oluşturup bot tokenını ve yönetici sohbet kimliğini **Ayarlar → Telegram Bildirimleri** sayfasına girin. Yönetici hesabı önce botla sohbet başlatmalıdır.

## Bildirimler

- Yazı veya sayfa yayınlama, düzenleme ve silme
- Yorum ekleme ve düzenleme; yorumun onaylanması, reddedilmesi, spam veya çöp olarak işaretlenmesi
- Yönetici girişi, çıkışı, şifre sıfırlama isteği ve başarısız giriş denemesi

Bot tokenı ve sohbet kimliği yalnızca WordPress yöneticileri tarafından değiştirilebilir. Bildirim gönderimi için sunucunun Telegram Bot API'ye HTTPS erişimi olmalıdır.
