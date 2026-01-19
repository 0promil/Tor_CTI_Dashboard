<?php
require_once 'config.php';
kullaniciGirisKontrol();

$id = intval($_GET['id'] ?? 0);

if (!$id) {
    header('Location: index.php');
    exit;
}

$db = dbBaglanti();

$mesaj = '';
$hata = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['analist_notu_ekle'])) {
    if (!csrfTokenDogrula($_POST['csrf_token'] ?? '')) {
        $hata = 'Güvenlik hatası.';
    } else {
        $not = trim($_POST['analist_notu'] ?? '');
        if (!empty($not)) {
            try {
                $stmt = $db->prepare("UPDATE cti_kayitlari SET analist_notu = ?, analist_not_tarihi = CURRENT_TIMESTAMP, analist_not_kullanici_id = ? WHERE id = ?");
                $stmt->execute([$not, $_SESSION['kullanici_id'], $id]);
                $mesaj = 'Analist notu başarıyla kaydedildi.';
                logKaydet("Analist notu eklendi: Kayıt ID $id");
            } catch (PDOException $e) {
                $hata = 'Not kaydedilirken hata oluştu.';
                logKaydet("Analist notu hatası: " . $e->getMessage(), 'ERROR');
            }
        }
    }
}


$kaynak_guven_skoru_kolon_var = guvenSkoruKolonVarMi();

if ($kaynak_guven_skoru_kolon_var) {
    $stmt = $db->prepare("SELECT c.*, o.adres as onion_adres, o.kaynak_guven_skoru as kaynak_guven_skoru_onion,
                          k.kullanici_adi as analist_not_kullanici
                          FROM cti_kayitlari c 
                          LEFT JOIN onion_adresleri o ON c.onion_id = o.id 
                          LEFT JOIN kullanicilar k ON c.analist_not_kullanici_id = k.id
                          WHERE c.id = ?");
} else {
    $stmt = $db->prepare("SELECT c.*, o.adres as onion_adres,
                          k.kullanici_adi as analist_not_kullanici
                          FROM cti_kayitlari c 
                          LEFT JOIN onion_adresleri o ON c.onion_id = o.id 
                          LEFT JOIN kullanicilar k ON c.analist_not_kullanici_id = k.id
                          WHERE c.id = ?");
}
$stmt->execute([$id]);
$kayit = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$kayit) {
    header('Location: index.php');
    exit;
}

$kaynak_guven_skoru = guvenSkoruGetir($kayit);


$kaynak_istatistik_sql = "SELECT 
    COUNT(*) as toplam_kayit,
    COUNT(DISTINCT kategori) as kategori_sayisi,
    AVG(kritiklik_skor) as ortalama_kritiklik_skor,
    MAX(toplama_tarihi) as son_kayit_tarihi
    FROM cti_kayitlari 
    WHERE kaynak_adi = ?";
$stmt = $db->prepare($kaynak_istatistik_sql);
$stmt->execute([$kayit['kaynak_adi']]);
$kaynak_istatistik = $stmt->fetch(PDO::FETCH_ASSOC);

$kategori_aciklamalari = [
    'Zararlı Yazılım (Malware)' => [
        'aciklama' => 'İçerikte zararlı yazılım, virüs ve saldırı araçlarına dair teknik detaylar veya dağıtım faaliyetleri tespit edilmiştir.',
        'neden' => 'Analiz motoru, "malware", "virus", "ransomware" gibi tehdit göstergelerini (IoC) belirlemiştir.'
    ],
    'Kimlik Avı (Phishing)' => [
        'aciklama' => 'Kullanıcı kimlik bilgilerini ele geçirmeyi hedefleyen sahte giriş sayfaları veya oltalama kampanyaları ile ilgili veriler bulunmuştur.',
        'neden' => 'Metin içerisinde "phishing", "fake login", "spoofing" vb. oltalama terminolojisi saptanmıştır.'
    ],
    'Sosyal Mühendislik' => [
        'aciklama' => 'İnsan hatalarını istismar etmeye yönelik manipülasyon teknikleri ve dolandırıcılık senaryoları tespit edilmiştir.',
        'neden' => 'İçerikte güven istismarı ve kandırma odaklı (scam, social engineering) ifadeler yer almaktadır.'
    ],
    'Yetkisiz Erişim / Sızma' => [
        'aciklama' => 'Sistemlere izinsiz giriş, şifre kırma girişimleri veya hesap ele geçirme faaliyetlerine dair bulgular mevcuttur.',
        'neden' => '"Brute-force", "hack", "access denied" gibi yetkisiz erişim teşebbüslerini işaret eden terimler bulunmuştur.'
    ],
    'Veri İhlali / Veri Sızıntısı' => [
        'aciklama' => 'Hassas verilerin (kişisel bilgiler, kurumsal veriler, veritabanı dökümleri) yetkisiz paylaşımı veya sızıntısı tespit edilmiştir.',
        'neden' => '"Data breach", "leak", "veritabanı" gibi veri sızıntısını doğrulayan anahtar kelimeler yoğunluktadır.'
    ],
    'Dark Web Pazarları' => [
        'aciklama' => 'Yasa dışı ürün veya hizmetlerin (silah, uyuşturucu, sahte belge vb.) ticaretinin yapıldığı darknet pazar yeri aktivitesi görülmüştür.',
        'neden' => 'Marketplace yapısına özgü "vendor", "escrow", "price" ve yasa dışı ürün isimleri analiz edilmiştir.'
    ],
    'Exploit / Zafiyet Paylaşımları' => [
        'aciklama' => 'Sistem güvenlik açıklarını (CVE) istismar eden kodlar (exploit) veya teknik zafiyet analizleri paylaşılmıştır.',
        'neden' => 'İçerik, "exploit", "PoC", "vulnerability", "RCE" gibi teknik zafiyet terimlerini içermektedir.'
    ],
    'APT (Gelişmiş Kalıcı Tehditler)' => [
        'aciklama' => 'Devlet destekli veya organize siber casusluk gruplarının (APT) faaliyetlerine dair istihbarat verisi içermektedir.',
        'neden' => 'Belirli tehdit aktörleri veya sofistike saldırı teknikleri (APT, state-sponsored) ile eşleşmiştir.'
    ],
    'Finansal Dolandırıcılık' => [
        'aciklama' => 'Kredi kartı dolandırıcılığı, kripto para hırsızlığı veya finansal sahtekarlık girişimleri ile ilgili veriler tespit edilmiştir.',
        'neden' => '"Carding", "cc dump", "fraud" gibi finansal suç terminolojisi belirlenmiştir.'
    ],
    'Hacktivizm' => [
        'aciklama' => 'Politik veya ideolojik motivasyonla gerçekleştirilen siber saldırı (site tahrifatı, DDoS) çağrıları veya eylemleri bulunmuştur.',
        'neden' => '"Hacked by", "defaced", "dava", "boykot" gibi hacktivist jargon kullanımı saptanmıştır.'
    ],
    'İç Tehdit (Insider Threat)' => [
        'aciklama' => 'Kurum çalışanları tarafından gerçekleştirilen yetki kötüye kullanımı veya kasıtlı veri sızıntısı şüphesi taşıyan içerik.',
        'neden' => 'Kurumsal yapı içerisinden dışarıya bilgi aktarımı veya yetki aşımı belirten ifadeler analiz edilmiştir.'
    ],
    'Yeni Teknolojiler Kaynaklı Tehditler' => [
        'aciklama' => 'Yapay zeka, blockchain veya IoT gibi gelişmekte olan teknolojileri hedef alan veya bu teknolojileri kullanan tehditler.',
        'neden' => '"AI attack", "smart contract exploit", "deepfake" gibi modern teknoloji tehditleri tespit edilmiştir.'
    ]
];

$ana_kategori = isset($kayit['ana_kategori']) && $kayit['ana_kategori'] ? $kayit['ana_kategori'] : $kayit['kategori'];
$alt_kategori = isset($kayit['alt_kategori']) && $kayit['alt_kategori'] ? $kayit['alt_kategori'] : '';

$kategori_bilgi = $kategori_aciklamalari[$ana_kategori] ?? [
    'aciklama' => 'İçerik analizi sonucunda sistem tarafından otomatik sınıflandırma yapılmıştır.',
    'neden' => 'İçerikteki anahtar kelime yoğunluğuna göre en uygun kategori belirlenmiştir.'
];

$guven_skoru_aciklamalari = [
    1 => [
        'aciklama' => 'Güvenilmez',
        'renk' => '#c0392b',
        'detay' => 'Bu kaynak, genelde yanıltıcı veya güvenilir olmayan bilgiler paylaşmıştır. Analistler bu kaynaktan gelen bilgileri çok dikkatli değerlendirmelidir.'
    ],
    2 => [
        'aciklama' => 'Düşük Güvenilirlik',
        'renk' => '#e67e22',
        'detay' => 'Bu kaynağın güvenilirliği sınırlıdır. Bilgiler doğrulanmadan kullanılmamalıdır.'
    ],
    3 => [
        'aciklama' => 'Orta Güvenilirlik',
        'renk' => '#f1c40f',
        'detay' => 'Bu kaynak bazen doğru bazen yanlış bilgiler paylaşabilir. Çapraz doğrulama önerilir.'
    ],
    4 => [
        'aciklama' => 'Yüksek Güvenilirlik',
        'renk' => '#27ae60',
        'detay' => 'Bu kaynak, genelde güvenilir ve doğru bilgiler paylaşmıştır. Bilgiler genellikle doğrulanabilir niteliktedir.'
    ],
    5 => [
        'aciklama' => 'Doğrulanmış Kaynak',
        'renk' => '#27ae60',
        'detay' => 'Bu kaynak geçmişte tutarlı ve teyit edilmiş istihbarat sağlamıştır. Oldukça güvenilirdir.'
    ]
];

$guven_skoru_bilgi = $guven_skoru_aciklamalari[$kaynak_guven_skoru] ?? $guven_skoru_aciklamalari[3];
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>0Promil CTI - Kayit Detayi - <?= guvenliCikti(substr($kayit['baslik'], 0, 50)) ?></title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="header">
        <h1>0Promil CTI - Kayıt Detay Analizi</h1>
        <div class="header-info">
            <button type="button" class="pdf-buton" onclick="printDetailCard()">PDF Olarak Kaydet</button>
            <a href="index.php">← Ana Sayfaya Dön</a>
        </div>
    </div>
    <div class="container">
        <div class="detay-kart">
            <div class="detay-baslik"><?= guvenliCikti($kayit['baslik']) ?></div>

            <div class="bolum-baslik">Temel Bilgiler</div>
            
            <div class="detay-alan">
                <label>Toplama Tarihi</label>
                <div class="deger"><?= tarihFormatla($kayit['toplama_tarihi']) ?></div>
            </div>

            <div class="detay-alan">
                <label>Kaynak Adı</label>
                <div class="deger"><?= guvenliCikti($kayit['kaynak_adi']) ?></div>
            </div>

            <div class="detay-alan">
                <label>Kaynak Güven Skoru</label>
                <div class="guven-skoru" style="border-left-color: <?= $guven_skoru_bilgi['renk'] ?>">
                    <div class="guven-skoru-yildizlar">
                        <?php for ($i = 1; $i <= 5; $i++): ?>
                            <span class="guven-skoru-yildiz <?= $i <= $kaynak_guven_skoru ? 'aktif' : '' ?>">★</span>
                        <?php endfor; ?>
                    </div>
                    <div>
                        <strong><?= $kaynak_guven_skoru ?>/5</strong> - <?= $guven_skoru_bilgi['aciklama'] ?>
                    </div>
                </div>
            </div>

            <div class="detay-alan">
                <label>Kaynak URL</label>
                <div class="deger" style="word-break: break-all; color: #3498db; font-family: monospace; font-size: 13px;">
                    <?= guvenliCikti($kayit['kaynak_url']) ?>
                </div>
            </div>

            <div class="detay-alan">
                <label>Onion Adres</label>
                <div class="deger" style="font-family: monospace; color: #7f8c8d;">
                    <?= guvenliCikti($kayit['onion_adres'] ?? 'Bilinmiyor') ?>
                </div>
            </div>

            <?php
            $onion_id = $kayit['onion_id'] ?? null;
            $tarama_sonucu_yolu = null;
            if ($onion_id) {
                try {
                    $kolon_kontrol = $db->query("SELECT COUNT(*) FROM information_schema.columns WHERE table_name='onion_adresleri' AND column_name='tarama_sonucu_yolu'")->fetchColumn();
                    if ($kolon_kontrol > 0) {
                        $stmt = $db->prepare("SELECT tarama_sonucu_yolu FROM onion_adresleri WHERE id = ?");
                        $stmt->execute([$onion_id]);
                        $onion_data = $stmt->fetch(PDO::FETCH_ASSOC);
                        $tarama_sonucu_yolu = $onion_data['tarama_sonucu_yolu'] ?? null;
                    }
                } catch (PDOException $e) {
                    $tarama_sonucu_yolu = null;
                }
            }
            ?>
            <?php 
            $host_tarama_yolu = null;
            $web_path = null;
            
            if ($tarama_sonucu_yolu) {
                // Remove the container base path to get the relative path part
                // DB has: /root/output/onion_hash/timestamp_folder
                // We want: /onion_hash/timestamp_folder
                $relative_path = str_replace('/root/output', '', $tarama_sonucu_yolu);
                
                 // Check relative to current SCRIPT directory
                $local_output_path = __DIR__ . '/output' . $relative_path;
                
                if (is_dir($local_output_path)) {
                    $host_tarama_yolu = $local_output_path;
                    // Web path should be relative to index.php (which is in php_web root)
                    // So path is output/onion_hash/timestamp_folder
                    $web_path = 'output' . $relative_path;
                } else {
                    // Fallback for manual check or direct mapping
                    $host_tarama_yolu = str_replace('/root/output', '/var/www/html/output', $tarama_sonucu_yolu);
                    $web_path = str_replace('/var/www/html/', '', $host_tarama_yolu);
                }
            }
            ?>
            <?php if ($host_tarama_yolu && is_dir($host_tarama_yolu)): ?>
            <div class="bolum-baslik">Onion Tarama Sonuçları</div>
            
            <?php 
            // Debug check for files
            $ss_path = $host_tarama_yolu . '/screenshot.png';
            $has_ss = file_exists($ss_path);
            ?>
            
            <?php if ($has_ss): ?>
            <div class="detay-alan">
                <label>Sayfa Ekran Görüntüsü (PNG)</label>
                <div style="margin-top: 15px; background: #f8f9fa; padding: 20px; border-radius: 8px; text-align: center;">
                    <?php
                    $screenshot_url = $web_path . '/screenshot.png';
                    ?>
                    <img src="<?= htmlspecialchars($screenshot_url) ?>" 
                         alt="Sayfa Ekran Görüntüsü" 
                         style="max-width: 100%; max-height: 600px; border: 2px solid #ddd; border-radius: 5px; cursor: pointer; box-shadow: 0 2px 8px rgba(0,0,0,0.1);"
                         onclick="window.open(this.src, '_blank')"
                         title="Tam boyut için tıklayın">
                    <div style="margin-top: 15px;">
                        <a href="<?= htmlspecialchars($screenshot_url) ?>" 
                           download="screenshot_<?= $id ?>.png" 
                           class="btn btn-primary"
                           style="display: inline-block; padding: 10px 20px; background: #3498db; color: white; text-decoration: none; border-radius: 5px; font-weight: 600;">
                           📥 Ekran Görüntüsünü İndir (PNG)
                        </a>
                        <div style="margin-top: 5px; color: #7f8c8d; font-size: 12px;">
                            Tam boyut görüntülemek için resme tıklayın
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <?php if (file_exists($host_tarama_yolu . '/links.txt')): ?>
            <div class="detay-alan">
                <label>Çıkarılan Linkler</label>
                <div style="margin-top: 15px; background: #f8f9fa; padding: 20px; border-radius: 8px;">
                    <?php
                    $links = file($host_tarama_yolu . '/links.txt', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                    $link_count = count($links);
                    ?>
                    <div style="margin-bottom: 15px; padding: 10px; background: white; border-radius: 5px; border-left: 4px solid #3498db;">
                        <strong style="color: #2c3e50; font-size: 16px;"><?= number_format($link_count) ?></strong> 
                        <span style="color: #7f8c8d;">link bulundu</span>
                    </div>
                    <?php if ($link_count > 0): ?>
                        <div style="max-height: 400px; overflow-y: auto; background: white; padding: 15px; border-radius: 5px;">
                            <?php foreach ($links as $index => $link): ?>
                                <div style="padding: 10px 0; border-bottom: 1px solid #eee; display: flex; align-items: center; gap: 10px;">
                                    <span style="color: #95a5a6; font-size: 12px; min-width: 30px;"><?= $index + 1 ?>.</span>
                                    <a href="<?= htmlspecialchars($link) ?>" 
                                       target="_blank" 
                                       style="color: #3498db; text-decoration: none; word-break: break-all; flex: 1;"
                                       onmouseover="this.style.textDecoration='underline'"
                                       onmouseout="this.style.textDecoration='none'">
                                        <?= htmlspecialchars($link) ?>
                                    </a>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div style="padding: 20px; text-align: center; color: #7f8c8d;">
                            Bu sayfada link bulunamadı.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
            
            <?php if (file_exists($host_tarama_yolu . '/site_snapshot.mhtml')): ?>
            <div class="detay-alan">
                <label>MHTML Snapshot (Tam Sayfa Görünümü)</label>
                <div style="margin-top: 15px; background: #f8f9fa; padding: 20px; border-radius: 8px;">
                    <div style="padding: 15px; background: white; border-radius: 5px; border-left: 4px solid #27ae60;">
                        <p style="margin: 0 0 15px 0; color: #2c3e50;">
                            MHTML formatı, sayfanın tam görünümünü ve tüm kaynaklarını içeren bir arşiv dosyasıdır. 
                            Bu dosyayı indirip tarayıcınızda açarak sayfanın tam halini offline olarak görüntüleyebilirsiniz.
                        </p>
                        <?php
                        $mhtml_url = $web_path . '/site_snapshot.mhtml';
                        ?>
                        <a href="<?= htmlspecialchars($mhtml_url) ?>" 
                           download="site_snapshot_<?= $id ?>.mhtml"
                           class="btn btn-success"
                           style="display: inline-block; padding: 12px 25px; background: #27ae60; color: white; text-decoration: none; border-radius: 5px; font-weight: 500; transition: background 0.3s;"
                           onmouseover="this.style.background='#229954'"
                           onmouseout="this.style.background='#27ae60'">
                           📥 MHTML Dosyasını İndir
                        </a>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <?php if (file_exists($host_tarama_yolu . '/site_data.html')): ?>
            <div class="detay-alan">
                <label>HTML İçerik (Temizlenmiş)</label>
                <div style="margin-top: 15px;">
                    <?php
                    $html_url = $web_path . '/site_data.html';
                    ?>
                    <a href="<?= htmlspecialchars($html_url) ?>" 
                       target="_blank" 
                       style="display: inline-block; padding: 10px 20px; background: #3498db; color: white; text-decoration: none; border-radius: 5px; font-weight: 500;">
                        HTML Dosyasını Görüntüle
                    </a>
                    <span style="margin-left: 10px; color: #7f8c8d; font-size: 12px;">
                        (Linkler ve kaynaklar temizlenmiş offline versiyon)
                    </span>
                </div>
            </div>
            <?php endif; ?>
            
            <?php else: ?>
            <div class="detay-alan">
                <div style="padding: 20px; background: #fff3cd; border-left: 4px solid #ffc107; border-radius: 5px; color: #856404;">
                    <strong>Tarama Sonuçları Henüz Mevcut Değil</strong><br>
                    Bu onion adresi için henüz tarama yapılmamış veya tarama sonuçları hazır değil. 
                    Admin panelinden "Tara" butonuna tıklayarak tarama başlatabilirsiniz.
                </div>
            </div>
            <?php endif; ?>

            <div class="bolum-baslik">Kategori Analizi</div>
            
            <div class="detay-alan">
                <label>Ana Kategori</label>
                <div class="deger"><?= guvenliCikti($ana_kategori) ?></div>
                <?php if ($alt_kategori): ?>
                    <div class="detay-alan" style="margin-top: 15px;">
                        <label>Alt Kategori</label>
                        <div class="deger"><?= guvenliCikti($alt_kategori) ?></div>
                    </div>
                <?php endif; ?>
            </div>

            <div class="bolum-baslik">Kritiklik Analizi</div>
            
            <div class="detay-alan">
                <label>Kritiklik Seviyesi</label>
                <div class="deger">
                    <span class="kritiklik-badge kritiklik-<?= guvenliCikti($kayit['kritiklik']) ?>">
                        <?= ucfirst(guvenliCikti($kayit['kritiklik'])) ?>
                    </span>
                    <?php if (!empty($kayit['kritiklik_skor'])): ?>
                        <span style="margin-left: 15px; color: #7f8c8d; font-size: 14px;">
                            (Skor: <strong><?= $kayit['kritiklik_skor'] ?>/100</strong>)
                        </span>
                    <?php endif; ?>
                </div>
                <?php if (!empty($kayit['kritiklik_aciklama'])): ?>
                    <div class="aciklama-kutu" style="margin-top: 10px; background: #fff3cd; border-left-color: #f39c12;">
                        <?= nl2br(guvenliCikti($kayit['kritiklik_aciklama'])) ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="bolum-baslik">İçerik Özeti</div>
            
            <div class="detay-alan">
                <label>Özet</label>
                <div class="ozet-kutu">
                    <?= nl2br(guvenliCikti($kayit['ozet'])) ?>
                </div>
            </div>

            <?php if ($kaynak_istatistik): ?>
            <div class="bolum-baslik">Kaynak İstatistikleri</div>
            <div class="kaynak-istatistik">
                <div class="kaynak-istatistik-item">
                    <div class="deger"><?= number_format($kaynak_istatistik['toplam_kayit']) ?></div>
                    <div class="label">Toplam Kayıt</div>
                </div>
                <div class="kaynak-istatistik-item">
                    <div class="deger"><?= $kaynak_istatistik['kategori_sayisi'] ?></div>
                    <div class="label">Kategori Sayısı</div>
                </div>
                <div class="kaynak-istatistik-item">
                    <div class="deger"><?= number_format($kaynak_istatistik['ortalama_kritiklik_skor'] ?? 0, 1) ?></div>
                    <div class="label">Ort. Kritiklik Skoru</div>
                </div>
                <?php if ($kaynak_istatistik['son_kayit_tarihi']): ?>
                <div class="kaynak-istatistik-item">
                    <div class="deger" style="font-size: 14px;"><?= tarihFormatla($kaynak_istatistik['son_kayit_tarihi']) ?></div>
                    <div class="label">Son Kayıt Tarihi</div>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <div class="bolum-baslik">Analist Notu</div>
            
            <div class="detay-alan">
                <?php if ($mesaj): ?>
                    <div class="mesaj mesaj-basarili"><?= guvenliCikti($mesaj) ?></div>
                <?php endif; ?>
                <?php if ($hata): ?>
                    <div class="mesaj mesaj-hata"><?= guvenliCikti($hata) ?></div>
                <?php endif; ?>
                
                <?php if (!empty($kayit['analist_notu'])): ?>
                    <div class="analist-notu-kutu">
                        <div class="baslik">Mevcut Analist Notu:</div>
                        <div><?= nl2br(guvenliCikti($kayit['analist_notu'])) ?></div>
                        <?php if ($kayit['analist_not_kullanici']): ?>
                            <div class="tarih">
                                Not: <strong><?= guvenliCikti($kayit['analist_not_kullanici']) ?></strong> tarafından 
                                <?= $kayit['analist_not_tarihi'] ? tarihFormatla($kayit['analist_not_tarihi']) : '' ?> tarihinde eklendi.
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
                
                <form method="POST" style="margin-top: 15px;">
                    <input type="hidden" name="csrf_token" value="<?= csrfTokenOlustur() ?>">
                    <label for="analist_notu">Yeni Analist Notu:</label>
                    <textarea name="analist_notu" id="analist_notu" 
                              placeholder="Bu kayıt hakkında analist yorumunuzu buraya yazabilirsiniz..."><?= !empty($kayit['analist_notu']) ? guvenliCikti($kayit['analist_notu']) : '' ?></textarea>
                    <button type="submit" name="analist_notu_ekle">
                        <?= !empty($kayit['analist_notu']) ? 'Notu Güncelle' : 'Not Ekle' ?>
                    </button>
                </form>
            </div>

            <a href="index.php" class="geri-buton">← Ana Sayfaya Dön</a>
        </div>
    </div>
    
    <div class="footer-banner">
        <a href="https://www.linkedin.com/in/bydurmus" target="_blank">www.linkedin.com/in/bydurmus</a>
    </div>
    
    <script>
    function printDetailCard() {
        // Detay kartı içeriğini al
        const detayKart = document.querySelector('.detay-kart');
        const baslik = document.querySelector('.header h1').textContent;
        
        // Yeni pencere aç
        const printWindow = window.open('', '', 'height=600,width=800');
        
        // HTML içeriği oluştur
        printWindow.document.write('<html><head><title>' + baslik + '</title>');
        printWindow.document.write('<style>');
        printWindow.document.write('@page { margin: 1.5cm; size: A4; }');
        printWindow.document.write('body { font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif; margin: 0; padding: 20px; background: white; color: #000; }');
        printWindow.document.write('h1 { font-size: 18pt; margin-bottom: 15px; padding-bottom: 10px; border-bottom: 2px solid #3498db; }');
        printWindow.document.write('.bolum-baslik { font-size: 14pt; margin: 15px 0 10px 0; padding-bottom: 5px; border-bottom: 1px solid #ddd; font-weight: 600; }');
        printWindow.document.write('.detay-alan { margin-bottom: 12px; }');
        printWindow.document.write('.detay-alan label { display: block; font-size: 9pt; text-transform: uppercase; margin-bottom: 4px; font-weight: 600; color: #666; }');
        printWindow.document.write('.detay-alan .deger { font-size: 10pt; color: #000; }');
        printWindow.document.write('.ozet-kutu, .aciklama-kutu { background: #f9f9f9; padding: 10px; border-left: 3px solid #3498db; margin: 8px 0; font-size: 9pt; }');
        printWindow.document.write('.kritiklik-badge { display: inline-block; padding: 4px 10px; border-radius: 15px; font-size: 9pt; font-weight: 600; }');
        printWindow.document.write('.kritiklik-dusuk { background: #27ae60; color: white; }');
        printWindow.document.write('.kritiklik-orta { background: #f39c12; color: white; }');
        printWindow.document.write('.kritiklik-yuksek { background: #e67e22; color: white; }');
        printWindow.document.write('.kritiklik-kritik { background: #e74c3c; color: white; }');
        printWindow.document.write('.guven-skoru { display: inline-flex; align-items: center; gap: 8px; padding: 8px 12px; background: #f5f5f5; border-radius: 5px; border-left: 3px solid #3498db; }');
        printWindow.document.write('.guven-skoru-yildiz { font-size: 14px; color: #ddd; }');
        printWindow.document.write('.guven-skoru-yildiz.aktif { color: #f39c12; }');
        printWindow.document.write('.kaynak-istatistik { display: grid; grid-template-columns: repeat(4, 1fr); gap: 8px; margin: 10px 0; }');
        printWindow.document.write('.kaynak-istatistik-item { padding: 10px; background: #f5f5f5; border-radius: 5px; text-align: center; }');
        printWindow.document.write('.kaynak-istatistik-item .deger { font-size: 16pt; font-weight: 700; }');
        printWindow.document.write('.kaynak-istatistik-item .label { font-size: 8pt; color: #666; margin-top: 4px; }');
        printWindow.document.write('.analist-notu-kutu { background: #fff3cd; padding: 12px; border-left: 3px solid #ffc107; margin: 10px 0; }');
        printWindow.document.write('.analist-notu-kutu .baslik { font-weight: 600; margin-bottom: 8px; color: #856404; }');
        printWindow.document.write('.analist-notu-kutu .tarih { font-size: 8pt; color: #666; margin-top: 8px; }');
        printWindow.document.write('img { max-width: 100%; height: auto; }');
        printWindow.document.write('</style>');
        printWindow.document.write('</head><body>');
        printWindow.document.write('<h1>' + baslik + '</h1>');
        printWindow.document.write(detayKart.innerHTML);
        printWindow.document.write('</body></html>');
        
        printWindow.document.close();
        printWindow.focus();
        
        // Biraz bekle ve yazdır
        setTimeout(function() {
            printWindow.print();
            printWindow.close();
        }, 250);
    }
    </script>
</body>
</html>
