=== DLA Medical Trust ===
Contributors: drleylaarvas
Requires at least: 6.0
Tested up to: 6.7
Requires PHP: 8.0
Stable tag: 0.3.0-M3
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

= Bu sürümde (M3 — tıbbi inceleme alanı ve iş akışı çekirdeği) =

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

= Bu sürümde YOK (bilinçli) =

* Ön yüz render, shortcode, otomatik enjeksiyon — M4: uzman, uzmanlık alanı,
  profil bağlantısı, inceleme bilgisi, isteğe bağlı uzman yorumu ve seçilmiş
  kaynakları içeren, premium author-box kalitesinde Medical Author / Reviewer
  Trust Box. Birincil kullanım, global Avada Content Layout'da Post Content
  altına bir kez eklenen `[dla_medical_trust]` shortcode'udur; bu shortcode
  geçerli tekil sayfayı çözümler. Otomatik `the_content` enjeksiyonu isteğe
  bağlıdır ve varsayılan olarak kapalıdır. Avada yalnızca yerleşim tercihidir;
  render katmanı tüm uyumlu WordPress temalarında çalışır. Varsayılan görünüm
  premium uzman kartıdır; `[dla_medical_trust display="compact"]` aynı
  çözülmüş verinin metin odaklı compact görünümünü seçer.
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
