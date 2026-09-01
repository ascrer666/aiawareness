=== DLA Medical Trust ===
Contributors: drleylaarvas
Requires at least: 6.0
Tested up to: 6.7
Requires PHP: 8.0
Stable tag: 0.1.0-M1
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Tıbbi içerik sorumluluk katmanı: uzman varlığı, tıbbi konu hiyerarşisi,
küratörlü kaynak kütüphanesi ve tıbbi inceleme kayıtları.

== Description ==

Bu eklenti bir "yazar kutusu" değildir. Sağlık içeriklerinde içeriğin
arkasındaki tıbbi uzmanı, içeriğin hangi konuya ait olduğunu, ne zaman
gerçekten incelendiğini ve hangi doğrulanabilir kaynaklara dayandığını
yönetir.

Tasarım ilkesi: yanlış bir güven sinyali üretmektense hiçbir sinyal üretme.

= Bu sürümde (M1 — depolama iskeleti) =

* Uzman içerik türü (dla_expert), herkese açık değil
* Tıbbi konu taksonomisi (dla_medical_topic), hiyerarşik, herkese açık değil
* Kaynak içerik türü (dla_source) ve kontrollü tür taksonomisi
* Tipli meta kaydı: sanitize + auth callback, REST'e kapalı
* Dil-nötr kalıcı kimlikler (entity_uid, topic_uid, source_uid)
* Yetenekler ve kullanıcı bazlı tıbbi inceleme yetkisi
* Adlandırılmış inceleme politikaları
* Bağlantı politikası doğrulaması (kanonik hedef, yasak desenler)

= Bu sürümde YOK (bilinçli) =

* Kaynak çözümleyici ve çeşitlilik mekanizması — M2
* Ön yüz render, shortcode, otomatik enjeksiyon — M4
* Schema veri kontratı — M6
* JSON-LD üretimi — hiçbir zaman; bu eklentinin işi değildir
* PubMed entegrasyonu, denetim panosu, kaynak sağlık kontrolü — Phase 2
* Herhangi bir LLM / yapay zekâ özetleme özelliği — kapsam dışı

== Frequently Asked Questions ==

= Neden inceleme yetkisi Administrator'de bile yok? =

"İçerik editi" ile "tıbbi inceleme" farklı iddialardır. Yetki kullanıcı
bazında verilir; böylece içeriği yazan kişi kendi yazdığını tıbben
onaylayamaz. Kullanıcı profili ekranından atanır.

= PubMed ve Google Scholar neden kaynak türü değil? =

Onlar indeks/keşif servisleridir, kaynak değil. Bir yayını PubMed'de bulmuş
olmak o yayının niteliği hakkında bir şey söylemez. Köken "discovered_via"
alanında idari bilgi olarak saklanır ve sıralamayı etkilemez.

= Eklentiyi silersem verilerim ne olur? =

Varsayılan olarak korunur. Silme, ayarlardan açıkça istenmedikçe yapılmaz.

== Changelog ==

= 0.1.0-M1 =
* İlk sürüm: depolama iskeleti.
