=== DLA Medical Trust ===
Contributors: drleylaarvas
Requires at least: 6.0
Tested up to: 6.7
Requires PHP: 8.0
Stable tag: 0.6.0-M6
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Tıbbi içerik sorumluluk katmanı: uzman varlığı, tıbbi konu hiyerarşisi,
küratörlü kaynak kütüphanesi ve tıbbi inceleme kayıtları.

== Description ==

This plugin extends the traditional WordPress author-box concept into a
medical content responsibility and review system. Sağlık içeriklerinde
içeriğin arkasındaki tıbbi uzmanı, içeriğin hangi konuya ait olduğunu, ne
zaman gerçekten incelendiğini ve hangi doğrulanabilir kaynaklara dayandığını
yönetir.

Tasarım ilkesi: yanlış bir güven sinyali üretmektense hiçbir sinyal üretme.

= Bu sürümde (M6 — Read-only Medical Trust Contract) =

* Uzman içerik türü (dla_expert), herkese açık değil
* Tıbbi konu taksonomisi (dla_medical_topic), hiyerarşik, herkese açık değil
* Kaynak içerik türü (dla_source) ve kontrollü tür taksonomisi
* Tipli meta kaydı: sanitize + auth callback, REST'e kapalı
* Dil-nötr kalıcı kimlikler (entity_uid, topic_uid, source_uid)
* Yetenekler ve kullanıcı bazlı tıbbi inceleme yetkisi
* Adlandırılmış inceleme politikaları
* Bağlantı politikası doğrulaması (kanonik hedef, yasak desenler)
* Polylang çeviri grubu kimlik senkronizasyonu ve idempotent onarım
* Domain nesneleri, WordPress repository katmanı ve deterministik kaynak resolver'ı
* Skorlama, top-tier çeşitlilik bandı, rendezvous hashing ve sürüm damgalı seçim cache'i
* Salt-okunur resolver açıklama paneli
* Korumalı tıbbi inceleme kaydı: reviewed/valid durumu, uzman ve kaydeden kullanıcı ayrımı,
  geçmiş tarih, imza referansı ve içerik hash'i tek yetkili servis yoluyla yazılır
* Append-only, son 25 olayla sınırlı inceleme geçmişi; supersede olayları önceki kayda bağlanır
* Tıbbi konu politikasından çalışma anında türetilen current/due freshness; cron veya kalıcı freshness alanı yoktur
* Açık değişiklik sınıflandırma API'si: minor_edit geçerliliği korur, medical_content_update kaydı supersede eder
* Premium, semantik Medical Trust Box: `[dla_medical_trust]`; metin odaklı aynı-fact varyantı:
  `[dla_medical_trust display="compact"]`
* Global Avada Content Layout içindeki shortcode, layout postu yerine queried tekil tıbbi içeriği çözümler;
  klasik ve block theme kullanımında da aynı sunucu tarafı HTML'yi verir
* Tema geliştiricileri için `dla_medical_trust()` ve `dla_medical_trust_get_html()` template tag'leri;
  tema override yolu: `yourtheme/dla-medical-trust/trust-block.php`
* Varsayılan kapalı, ayarlardan açılabilen `the_content` enjeksiyonu; çift render koruması
* JS'siz, responsive ve `dla-mt-*` kapsamlı CSS; `--dla-mt-accent`, `--dla-mt-border`,
  `--dla-mt-radius`, `--dla-mt-surface` tasarım token'ları
* Sayfa düzenleme ekranında Tıbbi İçerik Bilgileri paneli: açık birincil konu, yazar modu,
  sayfaya özgü uzman değerlendirmesi, görünürlük ve slot bazlı güvenli kaynak override'ları
* ReviewService'i bypass etmeyen tarihli inceleme action'ı, doğrudan kullanıcı yetkisi ve
  bilinçli içerik-değişikliği sınıflandırması
* Hazırlık uyarıları ile içerik listesi Medical Topic / Reviewer / Review State / Source Coverage sütunları
* Schema veya başka entegrasyonlar için salt-okunur, sürümlü `dla_medical_trust_get_contract()` API'si;
  M4 ile aynı kanonik review/source gerçeklerini verir, JSON-LD üretmez

= Bu sürümde YOK (bilinçli) =

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

= 0.6.0-M6 =
* `dla-medical-trust/v1` read-only trust data contract added for downstream schema consumers.
* Contract reuses M2 final source resolution and M4 review applicability; presentation flags do not erase canonical facts.
* No JSON-LD or second schema engine was added.

= 0.5.0-M5 =
* Medical Content paneli, kaynak override doğrulaması ve sayfaya özgü commentary yönetimi.
* Güvenli review-record action, açık content-change sınıflandırması, readiness uyarıları ve içerik listesi sütunları.
* Gerçek WordPress admin save/capability/Polylang commentary akışlarıyla genişletilmiş testler.

= 0.4.0-M4 =
* Premium ve compact Medical Trust Box, shortcode, template tag ve tema override desteği.
* Avada Global Layout queried-post çözümü, varsayılan kapalı otomatik enjeksiyon ve çift-render koruması.
* JS'siz responsive scoped CSS; gerçek WordPress klasik, block ve Avada bağlam testleri.

= 0.3.0-M3 =
* Medical review domain: deterministic content hash and runtime freshness evaluator.
* ReviewService is the sole review-record write path; protected metadata blocks direct updates.
* Append-only 25-event review history, explicit change classification, validity superseding, and `dla_mt/v1/review_recorded` hook.
* Pure tests and isolated WordPress integration coverage were extended for the review workflow.

= 0.2.0-M2 =
* Polylang kimlik senkronizasyonu ve idempotent onarım.
* Deterministik kaynak çözümleme: konu yakınlığı, skor, top-tier bandı ve rendezvous hashing.
* Sürüm damgalı seçim cache'i ve salt-okunur çözümleme açıklama paneli.
* Saf resolver testleri ile izole WordPress entegrasyon koşumu.

= 0.1.0-M1 =
* İlk sürüm: depolama iskeleti.
