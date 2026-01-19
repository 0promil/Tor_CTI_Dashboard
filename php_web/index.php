<?php
require_once 'config.php';
kullaniciGirisKontrol();

$db = dbBaglanti();

$ana_kategori_kolon_var = false;
$alt_kategori_kolon_var = false;
try {
    $test_sql = "SELECT ana_kategori, alt_kategori FROM cti_kayitlari LIMIT 1";
    $db->query($test_sql);
    $ana_kategori_kolon_var = true;
    $alt_kategori_kolon_var = true;
} catch (PDOException $e) {
    $ana_kategori_kolon_var = false;
    $alt_kategori_kolon_var = false;
}

$sayfa = max(1, intval($_GET['sayfa'] ?? 1));
$sayfa_basina = 20;
$offset = ($sayfa - 1) * $sayfa_basina;

$ana_kategori_filtre = $_GET['ana_kategori'] ?? '';
$alt_kategori_filtre = $_GET['alt_kategori'] ?? '';
$kategori_filtre = $_GET['kategori'] ?? ''; 
$kritiklik_filtre = $_GET['kritiklik'] ?? '';
$kaynak_filtre = $_GET['kaynak'] ?? '';
$tarih_baslangic = $_GET['tarih_baslangic'] ?? '';
$tarih_bitis = $_GET['tarih_bitis'] ?? '';

$where = [];
$params = [];

if ($ana_kategori_filtre) {
    if ($ana_kategori_kolon_var) {
        $where[] = "COALESCE(ana_kategori, kategori) = ?";
        $params[] = $ana_kategori_filtre;
    } else {
        $where[] = "kategori = ?";
        $params[] = $ana_kategori_filtre;
    }
}
if ($alt_kategori_filtre && $ana_kategori_kolon_var) {
    $where[] = "alt_kategori = ?";
    $params[] = $alt_kategori_filtre;
}
if ($kategori_filtre && !$ana_kategori_filtre && !$alt_kategori_filtre) {
    $where[] = "kategori = ?";
    $params[] = $kategori_filtre;
}
if ($kritiklik_filtre) {
    $where[] = "kritiklik = ?";
    $params[] = $kritiklik_filtre;
}
if ($kaynak_filtre) {
    $where[] = "c.kaynak_adi ILIKE ?";
    $params[] = "%$kaynak_filtre%";
}
if ($tarih_baslangic) {
    $where[] = "toplama_tarihi >= ?";
    $params[] = $tarih_baslangic . ' 00:00:00';
}
if ($tarih_bitis) {
    $where[] = "toplama_tarihi <= ?";
    $params[] = $tarih_bitis . ' 23:59:59';
}

$where_sql = $where ? "WHERE " . implode(" AND ", $where) : "";
$where_sql_for_stats = $where_sql; 

$count_sql = "SELECT COUNT(*) FROM cti_kayitlari c $where_sql";
$stmt = $db->prepare($count_sql);
$stmt->execute($params);
$toplam_kayit = $stmt->fetchColumn();
$toplam_sayfa = ceil($toplam_kayit / $sayfa_basina);

$sql = "SELECT c.*, o.adres as onion_adres 
        FROM cti_kayitlari c 
        LEFT JOIN onion_adresleri o ON c.onion_id = o.id 
        $where_sql 
        ORDER BY c.toplama_tarihi DESC 
        LIMIT ? OFFSET ?";
$params[] = $sayfa_basina;
$params[] = $offset;

$stmt = $db->prepare($sql);
$stmt->execute($params);
$kayitlar = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Istatistik sorgusu icin LIMIT/OFFSET parametrelerini cikartiyoruz
$params_stats = array_slice($params, 0, count($params) - 2);

$istatistik_sql = "SELECT 
    COUNT(*) as toplam,
    COUNT(DISTINCT kategori) as kategori_sayisi,
    COUNT(DISTINCT kaynak_adi) as kaynak_sayisi
    FROM cti_kayitlari c $where_sql_for_stats";
$stmt = $db->prepare($istatistik_sql);
$stmt->execute($params_stats); 
$istatistikler = $stmt->fetch(PDO::FETCH_ASSOC);

$son_24h_where = $where_sql ? $where_sql . " AND " : "WHERE ";
$son_24h_where .= "toplama_tarihi > NOW() - INTERVAL '24 hours'";
$son_24h_sql = "SELECT COUNT(*) FROM cti_kayitlari c $son_24h_where";
$stmt = $db->prepare($son_24h_sql);
$stmt->execute($params_stats);
$son_24h = $stmt->fetchColumn();

$onceki_24h_where = $where_sql ? $where_sql . " AND " : "WHERE ";
$onceki_24h_where .= "toplama_tarihi > NOW() - INTERVAL '48 hours' AND toplama_tarihi <= NOW() - INTERVAL '24 hours'";
$onceki_24h_sql = "SELECT COUNT(*) FROM cti_kayitlari c $onceki_24h_where";
$stmt = $db->prepare($onceki_24h_sql);
$stmt->execute($params_stats);
$onceki_24h = $stmt->fetchColumn();

$degisim_yuzde = 0;
$degisim_yon = '';
if ($onceki_24h > 0) {
    $degisim_yuzde = (($son_24h - $onceki_24h) / $onceki_24h) * 100;
    $degisim_yon = $degisim_yuzde >= 0 ? 'YUKARI' : 'ASAGI';
} elseif ($son_24h > 0) {
    $degisim_yuzde = 100;
    $degisim_yon = 'YUKARI';
}

if ($ana_kategori_kolon_var) {
    $where_clause = $where_sql_for_stats ? $where_sql_for_stats . " AND COALESCE(ana_kategori, kategori) IS NOT NULL" : "WHERE COALESCE(ana_kategori, kategori) IS NOT NULL";
    $ana_kategoriler_sql = "SELECT DISTINCT COALESCE(ana_kategori, kategori) as ana_kategori FROM cti_kayitlari c $where_clause ORDER BY ana_kategori";
    $stmt = $db->prepare($ana_kategoriler_sql);
    $stmt->execute($params_stats);
    $ana_kategoriler = $stmt->fetchAll(PDO::FETCH_COLUMN);
} else {
    $where_clause = $where_sql_for_stats ? $where_sql_for_stats . " AND kategori IS NOT NULL" : "WHERE kategori IS NOT NULL";
    $ana_kategoriler_sql = "SELECT DISTINCT kategori as ana_kategori FROM cti_kayitlari c $where_clause ORDER BY kategori";
    $stmt = $db->prepare($ana_kategoriler_sql);
    $stmt->execute($params_stats);
    $ana_kategoriler = $stmt->fetchAll(PDO::FETCH_COLUMN);
}

$alt_kategoriler = [];
if ($ana_kategori_kolon_var) {
    if ($ana_kategori_filtre) {
        $alt_kategoriler_sql = "SELECT DISTINCT alt_kategori FROM cti_kayitlari c WHERE COALESCE(ana_kategori, kategori) = ? AND alt_kategori IS NOT NULL ORDER BY alt_kategori";
        $stmt = $db->prepare($alt_kategoriler_sql);
        $stmt->execute([$ana_kategori_filtre]);
        $alt_kategoriler = $stmt->fetchAll(PDO::FETCH_COLUMN);
    } else {
        $where_clause = $where_sql_for_stats ? $where_sql_for_stats . " AND alt_kategori IS NOT NULL" : "WHERE alt_kategori IS NOT NULL";
        $alt_kategoriler_sql = "SELECT DISTINCT alt_kategori FROM cti_kayitlari c $where_clause ORDER BY alt_kategori";
        $stmt = $db->prepare($alt_kategoriler_sql);
        $stmt->execute($params_stats);
        $alt_kategoriler = $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
}

$kategoriler_sql = "SELECT DISTINCT kategori FROM cti_kayitlari c $where_sql_for_stats ORDER BY kategori";
$stmt = $db->prepare($kategoriler_sql);
$stmt->execute($params_stats);
$kategoriler = $stmt->fetchAll(PDO::FETCH_COLUMN);

$kritiklik_sql = "SELECT kritiklik, COUNT(*) as sayi FROM cti_kayitlari c $where_sql_for_stats GROUP BY kritiklik";
$stmt = $db->prepare($kritiklik_sql);
$stmt->execute($params_stats);
$kritiklik_dagilim = $stmt->fetchAll(PDO::FETCH_ASSOC);

$kritiklik_toplam = array_sum(array_column($kritiklik_dagilim, 'sayi'));
$kritiklik_analiz = [];
foreach ($kritiklik_dagilim as $item) {
    $kritiklik_analiz[$item['kritiklik']] = [
        'sayi' => $item['sayi'],
        'yuzde' => $kritiklik_toplam > 0 ? round(($item['sayi'] / $kritiklik_toplam) * 100, 1) : 0
    ];
}

if ($ana_kategori_kolon_var) {
    $ana_kategori_dagilim_sql = "SELECT COALESCE(ana_kategori, kategori) as ana_kategori, COUNT(*) as sayi FROM cti_kayitlari c $where_sql_for_stats GROUP BY COALESCE(ana_kategori, kategori) ORDER BY sayi DESC";
    $stmt = $db->prepare($ana_kategori_dagilim_sql);
    $stmt->execute($params_stats);
    $ana_kategori_dagilim = $stmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    $ana_kategori_dagilim_sql = "SELECT kategori as ana_kategori, COUNT(*) as sayi FROM cti_kayitlari c $where_sql_for_stats GROUP BY kategori ORDER BY sayi DESC";
    $stmt = $db->prepare($ana_kategori_dagilim_sql);
    $stmt->execute($params_stats);
    $ana_kategori_dagilim = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$kategori_dagilim_sql = "SELECT kategori, COUNT(*) as sayi FROM cti_kayitlari c $where_sql_for_stats GROUP BY kategori ORDER BY sayi DESC";
$stmt = $db->prepare($kategori_dagilim_sql);
$stmt->execute($params_stats);
$kategori_dagilim = $stmt->fetchAll(PDO::FETCH_ASSOC);

$zaman_trend_24h_where = $where_sql ? $where_sql . " AND " : "WHERE ";
$zaman_trend_24h_where .= "toplama_tarihi > NOW() - INTERVAL '24 hours'";
$zaman_trend_24h_sql = "SELECT DATE_TRUNC('hour', toplama_tarihi) as saat, COUNT(*) as sayi 
    FROM cti_kayitlari c 
    $zaman_trend_24h_where
    GROUP BY saat 
    ORDER BY saat";
$stmt = $db->prepare($zaman_trend_24h_sql);
$stmt->execute($params_stats);
$zaman_trend_24h = $stmt->fetchAll(PDO::FETCH_ASSOC);

$zaman_trend_7g_where = $where_sql ? $where_sql . " AND " : "WHERE ";
$zaman_trend_7g_where .= "toplama_tarihi > NOW() - INTERVAL '7 days'";
$zaman_trend_7g_sql = "SELECT DATE_TRUNC('day', toplama_tarihi) as gun, COUNT(*) as sayi 
    FROM cti_kayitlari c 
    $zaman_trend_7g_where
    GROUP BY gun 
    ORDER BY gun";
$stmt = $db->prepare($zaman_trend_7g_sql);
$stmt->execute($params_stats);
$zaman_trend_7g = $stmt->fetchAll(PDO::FETCH_ASSOC);

$zaman_trend_24h_ortalama = count($zaman_trend_24h) > 0 ? array_sum(array_column($zaman_trend_24h, 'sayi')) / count($zaman_trend_24h) : 0;
$zaman_trend_24h_max = count($zaman_trend_24h) > 0 ? max(array_column($zaman_trend_24h, 'sayi')) : 0;
$zaman_trend_24h_anomali = $zaman_trend_24h_max > ($zaman_trend_24h_ortalama * 2);

$kaynaklar_sql = "SELECT DISTINCT kaynak_adi FROM cti_kayitlari c $where_sql_for_stats ORDER BY kaynak_adi";
$stmt = $db->prepare($kaynaklar_sql);
$stmt->execute($params_stats);
$kaynaklar = $stmt->fetchAll(PDO::FETCH_COLUMN);

$kritik_seviye_where = $where_sql_for_stats ? $where_sql_for_stats . " AND " : "WHERE ";
$kritik_seviye_where .= "(kritiklik = 'kritik' OR kritiklik = 'yuksek')";
$kritik_seviye_sql = "SELECT COUNT(*) FROM cti_kayitlari c $kritik_seviye_where";
$stmt = $db->prepare($kritik_seviye_sql);
$stmt->execute($params_stats);
$kritik_seviye_sayisi = $stmt->fetchColumn();

$en_kritik_where = $where_sql_for_stats ? $where_sql_for_stats . " AND " : "WHERE ";
$en_kritik_where .= "(kritiklik = 'kritik' OR kritiklik = 'yuksek') AND toplama_tarihi > NOW() - INTERVAL '24 hours'";
$en_kritik_sql = "SELECT c.*, o.adres as onion_adres 
                  FROM cti_kayitlari c 
                  LEFT JOIN onion_adresleri o ON c.onion_id = o.id 
                  $en_kritik_where 
                  ORDER BY c.toplama_tarihi DESC 
                  LIMIT 10";
$stmt = $db->prepare($en_kritik_sql);
$stmt->execute($params_stats);
$en_kritik_kayitlar = $stmt->fetchAll(PDO::FETCH_ASSOC);

$son_24h_kritik = $db->query("SELECT COUNT(*) FROM cti_kayitlari WHERE (kritiklik = 'kritik' OR kritiklik = 'yuksek') AND toplama_tarihi > NOW() - INTERVAL '24 hours'")->fetchColumn();
$onceki_24h_kritik = $db->query("SELECT COUNT(*) FROM cti_kayitlari WHERE (kritiklik = 'kritik' OR kritiklik = 'yuksek') AND toplama_tarihi > NOW() - INTERVAL '48 hours' AND toplama_tarihi <= NOW() - INTERVAL '24 hours'")->fetchColumn();
$kritik_artis = $onceki_24h_kritik > 0 ? (($son_24h_kritik - $onceki_24h_kritik) / $onceki_24h_kritik) * 100 : ($son_24h_kritik > 0 ? 100 : 0);

$kaynak_yogunluk_sql = "SELECT kaynak_adi, COUNT(*) as sayi 
                        FROM cti_kayitlari c 
                        WHERE toplama_tarihi > NOW() - INTERVAL '24 hours' 
                        GROUP BY kaynak_adi 
                        HAVING COUNT(*) > 10 
                        ORDER BY sayi DESC 
                        LIMIT 5";
$kaynak_yogunluk = $db->query($kaynak_yogunluk_sql)->fetchAll(PDO::FETCH_ASSOC);

if ($ana_kategori_kolon_var) {
    $yeni_kategori_sql = "SELECT DISTINCT COALESCE(ana_kategori, kategori) as ana_kategori 
                          FROM cti_kayitlari c 
                          WHERE toplama_tarihi > NOW() - INTERVAL '24 hours' 
                          AND COALESCE(ana_kategori, kategori) NOT IN (
                              SELECT DISTINCT COALESCE(ana_kategori, kategori) 
                              FROM cti_kayitlari c 
                              WHERE toplama_tarihi <= NOW() - INTERVAL '24 hours'
                          )
                          LIMIT 5";
} else {
    $yeni_kategori_sql = "SELECT DISTINCT kategori as ana_kategori 
                          FROM cti_kayitlari c 
                          WHERE toplama_tarihi > NOW() - INTERVAL '24 hours' 
                          AND kategori NOT IN (
                              SELECT DISTINCT kategori 
                              FROM cti_kayitlari c 
                              WHERE toplama_tarihi <= NOW() - INTERVAL '24 hours'
                          )
                          LIMIT 5";
}
$yeni_kategoriler = $db->query($yeni_kategori_sql)->fetchAll(PDO::FETCH_COLUMN);
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>0Promil CTI - Cyber Threat Intelligence Analiz Platformu</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="header">
        <h1>0Promil CTI - Siber Tehdit İstihbarat Paneli</h1>
        
        <div class="header-info">
            <?php if (isset($_SESSION['kullanici_adi'])): ?>
                <span>Kullanıcı: <strong><?= guvenliCikti($_SESSION['kullanici_adi']) ?></strong> (<?= guvenliCikti($_SESSION['rol']) ?>)</span>
            <?php endif; ?>
            
            <?php if (isset($_SESSION['rol']) && $_SESSION['rol'] === 'admin'): ?>
                <a href="admin.php">Yönetim Paneli</a>
            <?php endif; ?>
            
            <a href="logout.php">Çıkış Yap</a>
        </div>
    </div>

    <div class="container">
        <div class="uyari-paneli">
            <h3>Sistem Analizleri ve Öngörüler</h3>
            <div class="panel-aciklama">
                Bu alan, sistem tarafından tespit edilen anormallikleri ve önemli gelişmeleri özetler.
            </div>
            
            <div class="uyari-liste">
                <?php if ($kritik_artis > 20): ?>
                <div class="uyari-item uyari-kritik">
                    <div class="uyari-icerik">
                        <strong>Kritik Tehdit Artışı</strong>
                        <p>Son 24 saat içerisinde kritik seviyeli tehdit kayıtlarında <strong>%<?= round($kritik_artis) ?></strong> oranında artış gözlemlendi. Bu durum, devam eden bir saldırı kampanyasının göstergesi olabilir.</p>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($kaynak_yogunluk)): ?>
                <div class="uyari-item uyari-orta">
                    <div class="uyari-icerik">
                        <strong>Kaynak Aktivite Yoğunluğu</strong>
                        <p>Aşağıdaki kaynaklardan son 24 saatte normalin üzerinde veri girişi sağlandı:</p>
                        <ul>
                            <?php foreach ($kaynak_yogunluk as $kaynak): ?>
                                <li><?= guvenliCikti($kaynak['kaynak_adi']) ?>: <?= $kaynak['sayi'] ?> kayıt</li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($yeni_kategoriler)): ?>
                <div class="uyari-item uyari-bilgi">
                    <div class="uyari-icerik">
                        <strong>Yeni Tehdit Kategorileri</strong>
                        <p>Son 24 saatte sistemde ilk kez aşağıdaki kategorilerde kayıtlar tespit edildi:</p>
                        <ul>
                            <?php foreach ($yeni_kategoriler as $kat): ?>
                                <li><?= guvenliCikti($kat) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
                <?php endif; ?>
                
                
                <?php if ($kritik_artis <= 20 && empty($kaynak_yogunluk) && empty($yeni_kategoriler)): ?>
                <div class="uyari-item uyari-normal">
                    <div class="uyari-icerik">
                        <strong>Sistem Normal</strong>
                        <p>Son 24 saat içerisinde kayda değer kritik bir anormallik tespit edilmedi.</p>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <?php if (!empty($en_kritik_kayitlar)): ?>
        <div class="kritik-kayitlar">
            <h3>Son 24 Saat: En Kritik Tehditler</h3>
            <table>
                <thead>
                    <tr>
                        <th style="width: 15%">Tarih</th>
                        <th style="width: 15%">Kaynak</th>
                        <th style="width: 40%">Baslik</th>
                        <th style="width: 15%">Kategori</th>
                        <th style="width: 15%">Kritiklik</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($en_kritik_kayitlar as $kayit): ?>
                        <tr>
                            <td><?= tarihFormatla($kayit['toplama_tarihi']) ?></td>
                            <td><?= guvenliCikti($kayit['kaynak_adi']) ?></td>
                            <td>
                                <a href="detay.php?id=<?= $kayit['id'] ?>" style="color: #e74c3c; text-decoration: none; font-weight: 600;">
                                    <?= guvenliCikti(substr($kayit['baslik'], 0, 80)) . (strlen($kayit['baslik']) > 80 ? '...' : '') ?>
                                </a>
                            </td>
                            <td><?= guvenliCikti($kayit['kategori']) ?></td>
                            <td>
                                <span class="kritiklik-badge kritiklik-<?= guvenliCikti($kayit['kritiklik']) ?>">
                                    <?= ucfirst(guvenliCikti($kayit['kritiklik'])) ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

        <div class="istatistikler">
            <div class="istatistik-kart">
                <h3>Günlük Kayıt</h3>
                <div class="deger"><?= number_format($son_24h) ?></div>
                <div class="degisim">
                    <span class="<?= $degisim_yon === 'YUKARI' ? 'degisim-artis' : 'degisim-azalis' ?>">
                        <?= $degisim_yon === 'YUKARI' ? '↑' : '↓' ?> %<?= number_format(abs($degisim_yuzde), 1) ?>
                    </span>
                    <span class="degisim-sifir">düne göre</span>
                </div>
            </div>
            
            <div class="istatistik-kart">
                <h3>Toplam Kayıt</h3>
                <div class="deger"><?= number_format($toplam_kayit) ?></div>
                <div class="aciklama">Sistemdeki toplam CTI verisi</div>
            </div>
            
            <div class="istatistik-kart">
                <h3>Kritik Seviye</h3>
                <div class="deger" style="color: #e74c3c;"><?= number_format($kritik_seviye_sayisi) ?></div>
                <div class="aciklama">Kritik ve Yüksek seviyeli kayıtlar</div>
            </div>
            
            <div class="istatistik-kart">
                <h3>Aktif Kaynak</h3>
                <div class="deger"><?= count($kaynaklar) ?></div>
                <div class="aciklama">Veri toplanan benzersiz kaynaklar</div>
            </div>
            
            <div class="istatistik-kart">
                <h3>
                    Izlenen Kaynak
                </h3>
                <div class="deger"><?= number_format($istatistikler['kaynak_sayisi']) ?></div>
                <div class="aciklama">Aktif veri toplanan kaynak sayisi</div>
            </div>
        </div>

        <div class="grafikler">
            <div class="grafik-kart">
                <h3>Tehdit Zaman Analizi (Son 7 Gun)</h3>
                <div class="grafik-aciklama">Tehditlerin gunlere gore dagilimi ve yogunluk trendi.</div>
                <canvas id="zamanGrafigi"></canvas>
                <div class="analiz-yorumu">
                    <?php
                    $trend_sonuc = "Veri yetersiz.";
                    if (count($zaman_trend_7g) >= 2) {
                        $son_gun = end($zaman_trend_7g)['sayi'];
                        $ilk_gun = reset($zaman_trend_7g)['sayi'];
                        if ($son_gun > $ilk_gun * 1.5) {
                            $trend_sonuc = "<strong>Yukselis Trendi:</strong> Son bir haftada tehdit aktivitesinde belirgin bir artis var.";
                        } elseif ($ilk_gun > $son_gun * 1.5) {
                            $trend_sonuc = "<strong>Dusus Trendi:</strong> Tehdit aktivitesi son gunlerde azalma egiliminde.";
                        } else {
                            $trend_sonuc = "<strong>Yatay Seyir:</strong> Tehdit aktivitesi stabil bir seyir izliyor.";
                        }
                    }
                    echo $trend_sonuc;
                    ?>
                </div>
            </div>

            <div class="grafik-kart">
                <h3>Kategori Dagilimi (Ana Kategoriler)</h3>
                <div class="grafik-aciklama">Tehditlerin ana kategorilere gore oransal dagilimi.</div>
                <canvas id="kategoriGrafigi"></canvas>
                <div class="analiz-yorumu">
                    <?php
                    if (!empty($ana_kategori_dagilim)) {
                        $en_cok = $ana_kategori_dagilim[0];
                        echo "<strong>En Yaygin Tehdit:</strong> " . guvenliCikti($en_cok['ana_kategori']) . " (" . $en_cok['sayi'] . " kayit)";
                        echo "<br>Bu kategori toplam tehditlerin onemli bir kismini olusturmaktadir.";
                    }
                    ?>
                </div>
            </div>
            
            <div class="grafik-kart">
                <h3>Kritiklik Seviyesi Dagilimi</h3>
                <div class="grafik-aciklama">Tespit edilen tehditlerin onem derecesine gore dagilimi.</div>
                <canvas id="kritiklikGrafigi"></canvas>
            </div>
            
            <div class="grafik-kart">
                <h3>Son 24 Saat Aktivitesi</h3>
                <div class="grafik-aciklama">Saatlik bazda tehdit tespit sayilari.</div>
                <canvas id="saatlikGrafik"></canvas>
            </div>
        </div>
        
        <div class="filtreler">
            <h3>Detayli Filtreleme</h3>
            <form method="GET" action="">
                <div class="filtre-grup">
                    <label>Ana Kategori</label>
                    <select name="ana_kategori" onchange="this.form.submit()">
                        <option value="">Tumu</option>
                        <?php foreach ($ana_kategoriler as $kat): ?>
                            <option value="<?= guvenliCikti($kat) ?>" <?= $ana_kategori_filtre === $kat ? 'selected' : '' ?>>
                                <?= guvenliCikti($kat) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <?php if ($ana_kategori_filtre && !empty($alt_kategoriler)): ?>
                <div class="filtre-grup">
                    <label>Alt Kategori</label>
                    <select name="alt_kategori">
                        <option value="">Tumu</option>
                        <?php foreach ($alt_kategoriler as $kat): ?>
                            <option value="<?= guvenliCikti($kat) ?>" <?= $alt_kategori_filtre === $kat ? 'selected' : '' ?>>
                                <?= guvenliCikti($kat) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>

                <div class="filtre-grup">
                    <label>Kritiklik</label>
                    <select name="kritiklik">
                        <option value="">Tumu</option>
                        <option value="dusuk" <?= $kritiklik_filtre === 'dusuk' ? 'selected' : '' ?>>Dusuk</option>
                        <option value="orta" <?= $kritiklik_filtre === 'orta' ? 'selected' : '' ?>>Orta</option>
                        <option value="yuksek" <?= $kritiklik_filtre === 'yuksek' ? 'selected' : '' ?>>Yuksek</option>
                        <option value="kritik" <?= $kritiklik_filtre === 'kritik' ? 'selected' : '' ?>>Kritik</option>
                    </select>
                </div>
                
                <div class="filtre-grup">
                    <label>Kaynak</label>
                    <input type="text" name="kaynak" value="<?= guvenliCikti($kaynak_filtre) ?>" placeholder="Orn: forum...">
                </div>
                
                <div class="filtre-grup">
                    <label>Baslangic Tarihi</label>
                    <input type="date" name="tarih_baslangic" value="<?= guvenliCikti($tarih_baslangic) ?>">
                </div>
                
                <div class="filtre-grup">
                    <label>Bitis Tarihi</label>
                    <input type="date" name="tarih_bitis" value="<?= guvenliCikti($tarih_bitis) ?>">
                </div>
                
                <div class="filtre-grup">
                    <button type="submit">Filtrele</button>
                    <button type="button" class="filtre-temizle" onclick="window.location.href='index.php'">Temizle</button>
                </div>
            </form>
        </div>
        
        <div class="kayit-tablosu">
            <table>
                <thead>
                    <tr>
                        <th style="width: 5%">ID</th>
                        <th style="width: 10%">Tarih</th>
                        <th style="width: 10%">Kaynak</th>
                        <th style="width: 38%">Baslik / Icerik Ozeti</th>
                        <th style="width: 15%">Kategori</th>
                        <th style="width: 10%">Kritiklik</th>
                        <th style="width: 12%">Islem</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($kayitlar)): ?>
                        <tr>
                            <td colspan="7" style="text-align: center;">Kayit bulunamadi.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($kayitlar as $kayit): ?>
                            <tr>
                                <td>#<?= $kayit['id'] ?></td>
                                <td><?= tarihFormatla($kayit['toplama_tarihi']) ?></td>
                                <td><?= guvenliCikti($kayit['kaynak_adi']) ?></td>
                                <td>
                                    <strong><?= guvenliCikti(substr($kayit['baslik'], 0, 80)) . (strlen($kayit['baslik']) > 80 ? '...' : '') ?></strong>
                                    <br>
                                    <small style="color: #666;"><?= guvenliCikti(substr($kayit['ozet'], 0, 120)) . '...' ?></small>
                                </td>
                                <td>
                                    <?php if (isset($kayit['ana_kategori']) && $kayit['ana_kategori']): ?>
                                        <?= guvenliCikti($kayit['ana_kategori']) ?>
                                        <?php if (isset($kayit['alt_kategori']) && $kayit['alt_kategori']): ?>
                                            <br><small style="color: #666;"> - <?= guvenliCikti($kayit['alt_kategori']) ?></small>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <?= guvenliCikti($kayit['kategori']) ?>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="kritiklik-badge kritiklik-<?= guvenliCikti($kayit['kritiklik']) ?>">
                                        <?= ucfirst(guvenliCikti($kayit['kritiklik'])) ?>
                                    </span>
                                </td>
                                <td style="text-align: center;">
                                    <a href="detay.php?id=<?= $kayit['id'] ?>" class="detay-link" style="display: inline-block; padding: 8px 16px; background: #e74c3c; color: white !important; text-decoration: none; font-weight: 600; border-radius: 4px;">İNCELE</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <?php if ($toplam_sayfa > 1): ?>
            <div class="sayfalama">
                <?php if ($sayfa > 1): ?>
                    <a href="?sayfa=1&ana_kategori=<?= urlencode($ana_kategori_filtre) ?>&alt_kategori=<?= urlencode($alt_kategori_filtre) ?>&kritiklik=<?= urlencode($kritiklik_filtre) ?>&kaynak=<?= urlencode($kaynak_filtre) ?>&tarih_baslangic=<?= urlencode($tarih_baslangic) ?>&tarih_bitis=<?= urlencode($tarih_bitis) ?>"><< Ilk</a>
                    <a href="?sayfa=<?= $sayfa - 1 ?>&ana_kategori=<?= urlencode($ana_kategori_filtre) ?>&alt_kategori=<?= urlencode($alt_kategori_filtre) ?>&kritiklik=<?= urlencode($kritiklik_filtre) ?>&kaynak=<?= urlencode($kaynak_filtre) ?>&tarih_baslangic=<?= urlencode($tarih_baslangic) ?>&tarih_bitis=<?= urlencode($tarih_bitis) ?>">< Onceki</a>
                <?php endif; ?>
                
                <?php
                $baslangic = max(1, $sayfa - 2);
                $bitis = min($toplam_sayfa, $sayfa + 2);
                
                for ($i = $baslangic; $i <= $bitis; $i++):
                ?>
                    <a href="?sayfa=<?= $i ?>&ana_kategori=<?= urlencode($ana_kategori_filtre) ?>&alt_kategori=<?= urlencode($alt_kategori_filtre) ?>&kritiklik=<?= urlencode($kritiklik_filtre) ?>&kaynak=<?= urlencode($kaynak_filtre) ?>&tarih_baslangic=<?= urlencode($tarih_baslangic) ?>&tarih_bitis=<?= urlencode($tarih_bitis) ?>" class="<?= $i == $sayfa ? 'aktif' : '' ?>"><?= $i ?></a>
                <?php endfor; ?>
                
                <?php if ($sayfa < $toplam_sayfa): ?>
                    <a href="?sayfa=<?= $sayfa + 1 ?>&ana_kategori=<?= urlencode($ana_kategori_filtre) ?>&alt_kategori=<?= urlencode($alt_kategori_filtre) ?>&kritiklik=<?= urlencode($kritiklik_filtre) ?>&kaynak=<?= urlencode($kaynak_filtre) ?>&tarih_baslangic=<?= urlencode($tarih_baslangic) ?>&tarih_bitis=<?= urlencode($tarih_bitis) ?>">Sonraki ></a>
                    <a href="?sayfa=<?= $toplam_sayfa ?>&ana_kategori=<?= urlencode($ana_kategori_filtre) ?>&alt_kategori=<?= urlencode($alt_kategori_filtre) ?>&kritiklik=<?= urlencode($kritiklik_filtre) ?>&kaynak=<?= urlencode($kaynak_filtre) ?>&tarih_baslangic=<?= urlencode($tarih_baslangic) ?>&tarih_bitis=<?= urlencode($tarih_bitis) ?>">Son >></a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <script>
        const zamanTrendData = {
            labels: [<?php foreach ($zaman_trend_7g as $item) echo "'" . date('d.m', strtotime($item['gun'])) . "',"; ?>],
            datasets: [{
                label: 'Gunluk Tespit Sayisi',
                data: [<?php foreach ($zaman_trend_7g as $item) echo $item['sayi'] . ","; ?>],
                borderColor: '#3498db',
                backgroundColor: 'rgba(52, 152, 219, 0.1)',
                tension: 0.3,
                fill: true
            }]
        };

        const anaKategoriData = {
            labels: [<?php foreach ($ana_kategori_dagilim as $item) echo "'" . guvenliCikti($item['ana_kategori']) . "',"; ?>],
            datasets: [{
                data: [<?php foreach ($ana_kategori_dagilim as $item) echo $item['sayi'] . ","; ?>],
                backgroundColor: [
                    '#e74c3c', '#3498db', '#f1c40f', '#2ecc71', '#9b59b6', 
                    '#e67e22', '#1abc9c', '#34495e', '#7f8c8d', '#d35400'
                ]
            }]
        };
        
        const kritiklikData = {
            labels: ['Düşük', 'Orta', 'Yüksek', 'Kritik'],
            datasets: [{
                label: 'Kritiklik Seviyesi',
                data: [
                    <?= $kritiklik_analiz['dusuk']['sayi'] ?? 0 ?>, 
                    <?= $kritiklik_analiz['orta']['sayi'] ?? 0 ?>, 
                    <?= $kritiklik_analiz['yuksek']['sayi'] ?? 0 ?>, 
                    <?= $kritiklik_analiz['kritik']['sayi'] ?? 0 ?>
                ],
                backgroundColor: [
                    '#27ae60', '#f39c12', '#e67e22', '#e74c3c'
                ]
            }]
        };

        const saatlikData = {
            labels: [<?php foreach ($zaman_trend_24h as $item) echo "'" . date('H:i', strtotime($item['saat'])) . "',"; ?>],
            datasets: [{
                label: 'Saatlik Aktivite',
                data: [<?php foreach ($zaman_trend_24h as $item) echo $item['sayi'] . ","; ?>],
                backgroundColor: '#3498db',
                barPercentage: 0.6
            }]
        };

        new Chart(document.getElementById('zamanGrafigi'), {
            type: 'line',
            data: zamanTrendData,
            options: { responsive: true }
        });

        new Chart(document.getElementById('kategoriGrafigi'), {
            type: 'doughnut',
            data: anaKategoriData,
            options: { responsive: true, plugins: { legend: { position: 'right' } } }
        });
        
        new Chart(document.getElementById('kritiklikGrafigi'), {
            type: 'bar',
            data: kritiklikData,
            options: { 
                responsive: true,
                plugins: { legend: { display: false } },
                scales: { y: { beginAtZero: true } }
            }
        });
        
        new Chart(document.getElementById('saatlikGrafik'), {
            type: 'bar',
            data: saatlikData,
            options: { 
                responsive: true,
                plugins: { legend: { display: false } },
                scales: { y: { beginAtZero: true } }
            }
        });
    </script>
    
    <div class="footer-banner">
        <a href="https://www.linkedin.com/in/bydurmus" target="_blank">www.linkedin.com/in/bydurmus</a>
    </div>
</body>
</html>
