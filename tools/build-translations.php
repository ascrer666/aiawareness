<?php
/**
 * Build release PO files from the checked-in POT.
 *
 * The catalog intentionally contains interface strings only. Editorial data
 * (expert biographies, commentary and medical page content) is post meta or
 * post content and is never introduced here.
 *
 * Run: php tools/build-translations.php
 */

declare( strict_types = 1 );

$root = dirname( __DIR__ );
$pot  = $root . '/languages/dla-medical-trust.pot';

if ( ! is_file( $pot ) ) {
	fwrite( STDERR, "Generate the POT first.\n" );
	exit( 1 );
}

$catalogs = [
	'en_US' => [
		'İçerik Sorumlusu ve Tıbbi Denetim' => 'Content Responsibility and Medical Review',
		'Tıbbi uzman' => 'Medical expert', 'Hakkımda' => 'About',
		'İçeriği hazırlayan ve tıbbi olarak inceleyen: %s' => 'Prepared and medically reviewed by: %s',
		'İçeriği hazırlayan: %s' => 'Prepared by: %s', 'Tıbbi olarak inceleyen: %s' => 'Medically reviewed by: %s',
		'%1$s içeriğinin tıbbi sorumlusu: %2$s' => 'Medical content lead for %1$s: %2$s',
		'Bu sayfanın tıbbi içerik sorumlusu: %s' => 'Medical content lead for this page: %s',
		'Tıbbi inceleme tarihi:' => 'Last medical review:', 'Tıbbi incelemenin güncellenmesi planlanmıştır.' => 'Medical review update is due.',
		'Son güncelleme:' => 'Last updated:', 'Uzman değerlendirmesi' => 'Expert commentary',
		'Seçilmiş tıbbi kaynaklar' => 'Selected medical sources',
		'Bu bölüm, içeriğin tıbbi sorumluluk ve inceleme bilgilerini sunar; kişisel tıbbi değerlendirme yerine geçmez.' => 'This section presents the content responsibility and medical review information; it does not replace individual medical advice.',
		'Akademik / Bilimsel Kaynak' => 'Academic / Scientific Source', 'Tıbbi Otorite / Mesleki Kuruluş' => 'Medical Authority / Professional Organisation',
		'Bilimsel Yayın' => 'Scientific Publication', 'Hakem denetimi' => 'Peer review', 'Yeniden inceleme zamanı geldi' => 'Medical review update due',
	],
	'de_DE' => [
		'İçerik Sorumlusu ve Tıbbi Denetim' => 'Inhaltsverantwortung und medizinische Prüfung',
		'Tıbbi uzman' => 'Medizinische Fachperson', 'Hakkımda' => 'Über mich',
		'İçeriği hazırlayan ve tıbbi olarak inceleyen: %s' => 'Erstellt und medizinisch geprüft von: %s',
		'İçeriği hazırlayan: %s' => 'Erstellt von: %s', 'Tıbbi olarak inceleyen: %s' => 'Medizinisch geprüft von: %s',
		'%1$s içeriğinin tıbbi sorumlusu: %2$s' => 'Medizinisch verantwortlich für %1$s: %2$s',
		'Bu sayfanın tıbbi içerik sorumlusu: %s' => 'Medizinisch verantwortlich für diese Seite: %s',
		'Tıbbi inceleme tarihi:' => 'Datum der letzten medizinischen Prüfung:', 'Tıbbi incelemenin güncellenmesi planlanmıştır.' => 'Eine Aktualisierung der medizinischen Prüfung ist vorgesehen.',
		'Son güncelleme:' => 'Zuletzt aktualisiert:', 'Uzman değerlendirmesi' => 'Fachliche Einschätzung',
		'Seçilmiş tıbbi kaynaklar' => 'Ausgewählte medizinische Quellen',
		'Bu bölüm, içeriğin tıbbi sorumluluk ve inceleme bilgilerini sunar; kişisel tıbbi değerlendirme yerine geçmez.' => 'Dieser Abschnitt enthält Angaben zur medizinischen Verantwortung und Prüfung und ersetzt keine individuelle medizinische Beratung.',
		'Akademik / Bilimsel Kaynak' => 'Akademische / wissenschaftliche Quelle', 'Tıbbi Otorite / Mesleki Kuruluş' => 'Medizinische Fachgesellschaft / Gesundheitsbehörde',
		'Bilimsel Yayın' => 'Wissenschaftliche Publikation', 'Hakem denetimi' => 'Begutachtung', 'Yeniden inceleme zamanı geldi' => 'Medizinische Überprüfung fällig',
	],
	'fr_FR' => [
		'İçerik Sorumlusu ve Tıbbi Denetim' => 'Responsabilité éditoriale et revue médicale',
		'Tıbbi uzman' => 'Expert médical', 'Hakkımda' => 'À propos',
		'İçeriği hazırlayan ve tıbbi olarak inceleyen: %s' => 'Contenu rédigé et relu médicalement par : %s',
		'İçeriği hazırlayan: %s' => 'Contenu rédigé par : %s', 'Tıbbi olarak inceleyen: %s' => 'Relecture médicale : %s',
		'%1$s içeriğinin tıbbi sorumlusu: %2$s' => 'Responsable médical du contenu « %1$s » : %2$s',
		'Bu sayfanın tıbbi içerik sorumlusu: %s' => 'Responsable médical de cette page : %s',
		'Tıbbi inceleme tarihi:' => 'Dernière revue médicale :', 'Tıbbi incelemenin güncellenmesi planlanmıştır.' => 'Une mise à jour de la revue médicale est prévue.',
		'Son güncelleme:' => 'Dernière mise à jour :', 'Uzman değerlendirmesi' => 'Avis de l’expert',
		'Seçilmiş tıbbi kaynaklar' => 'Sources médicales sélectionnées',
		'Bu bölüm, içeriğin tıbbi sorumluluk ve inceleme bilgilerini sunar; kişisel tıbbi değerlendirme yerine geçmez.' => 'Cette section présente les informations de responsabilité et de revue médicale ; elle ne remplace pas un avis médical personnalisé.',
		'Akademik / Bilimsel Kaynak' => 'Source académique / scientifique', 'Tıbbi Otorite / Mesleki Kuruluş' => 'Autorité médicale / organisme professionnel',
		'Bilimsel Yayın' => 'Publication scientifique', 'Hakem denetimi' => 'Évaluation par les pairs', 'Yeniden inceleme zamanı geldi' => 'Mise à jour de la revue médicale requise',
	],
	'ru_RU' => [
		'İçerik Sorumlusu ve Tıbbi Denetim' => 'Ответственность за содержание и медицинская проверка',
		'Tıbbi uzman' => 'Медицинский эксперт', 'Hakkımda' => 'О специалисте',
		'İçeriği hazırlayan ve tıbbi olarak inceleyen: %s' => 'Материал подготовлен и проверен с медицинской точки зрения: %s',
		'İçeriği hazırlayan: %s' => 'Материал подготовил: %s', 'Tıbbi olarak inceleyen: %s' => 'Медицинскую проверку провёл: %s',
		'%1$s içeriğinin tıbbi sorumlusu: %2$s' => 'Медицинский ответственный за материал «%1$s»: %2$s',
		'Bu sayfanın tıbbi içerik sorumlusu: %s' => 'Медицинский ответственный за эту страницу: %s',
		'Tıbbi inceleme tarihi:' => 'Дата последней медицинской проверки:', 'Tıbbi incelemenin güncellenmesi planlanmıştır.' => 'Запланировано обновление медицинской проверки.',
		'Son güncelleme:' => 'Последнее обновление:', 'Uzman değerlendirmesi' => 'Комментарий эксперта',
		'Seçilmiş tıbbi kaynaklar' => 'Отобранные медицинские источники',
		'Bu bölüm, içeriğin tıbbi sorumluluk ve inceleme bilgilerini sunar; kişisel tıbbi değerlendirme yerine geçmez.' => 'Этот раздел содержит сведения о медицинской ответственности и проверке материала и не заменяет персональную медицинскую консультацию.',
		'Akademik / Bilimsel Kaynak' => 'Академический / научный источник', 'Tıbbi Otorite / Mesleki Kuruluş' => 'Медицинский орган / профессиональная организация',
		'Bilimsel Yayın' => 'Научная публикация', 'Hakem denetimi' => 'Рецензирование', 'Yeniden inceleme zamanı geldi' => 'Требуется обновление медицинской проверки',
	],
];

$po_quote = static function ( string $value ): string {
	return '"' . addcslashes( $value, "\\\"\n\r\t" ) . '"';
};

$entries = preg_split( '/(?=^#:)/m', (string) file_get_contents( $pot ) );
foreach ( $catalogs as $locale => $translations ) {
	$out = "msgid \"\"\nmsgstr \"\"\n\"Project-Id-Version: DLA Medical Trust 0.6.1\\n\"\n\"Language: {$locale}\\n\"\n\"Content-Type: text/plain; charset=UTF-8\\n\"\n\"Content-Transfer-Encoding: 8bit\\n\"\n\"Plural-Forms: nplurals=2; plural=(n != 1);\\n\"\n\n";
	foreach ( $entries as $entry ) {
		if ( ! preg_match( '/^msgid ("(?:[^"\\\\]|\\\\.)*")$/m', $entry, $match ) || '""' === $match[1] ) {
			continue;
		}
		$msgid = stripcslashes( substr( $match[1], 1, -1 ) );
		$translation = $translations[ $msgid ] ?? '';
		$refs = preg_match_all( '/^#: .+$/m', $entry, $refs_match ) ? implode( "\n", $refs_match[0] ) . "\n" : '';
		$out .= $refs . 'msgid ' . $po_quote( $msgid ) . "\nmsgstr " . $po_quote( $translation ) . "\n\n";
	}
	file_put_contents( $root . '/languages/dla-medical-trust-' . $locale . '.po', rtrim( $out ) . "\n" );
}
