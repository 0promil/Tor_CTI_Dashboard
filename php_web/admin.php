<?php
require_once 'config.php';
rolKontrol('admin');

$db = dbBaglanti();
$mesaj = '';
$hata = '';

// GET parametresinden mesaj kontrolü
if (isset($_GET['mesaj'])) {
    if ($_GET['mesaj'] === 'kullanici_eklendi') {
        $mesaj = 'Kullanıcı başarıyla eklendi.';
    } elseif ($_GET['mesaj'] === 'sifre_degisti') {
        $mesaj = 'Şifreniz başarıyla değiştirildi.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['islem']) && $_POST['islem'] === 'onion_ekle') {
    if (!csrfTokenDogrula($_POST['csrf_token'] ?? '')) {
        $hata = 'Güvenlik hatası.';
    } else {
        $adresler_text = trim($_POST['adresler'] ?? '');
        
        if (empty($adresler_text)) {
            $hata = 'En az bir onion adresi giriniz.';
        } else {
            // Her satırı ayrı adres olarak işle
            $adresler = array_filter(array_map('trim', explode("\n", $adresler_text)));
            $basarili = 0;
            $hatali = 0;
            $hata_mesajlari = [];
            
            foreach ($adresler as $adres) {
                // .onion kontrolü - path desteği ile
                if (!preg_match('/\.onion(\/|$)/i', $adres)) {
                    $hatali++;
                    $hata_mesajlari[] = "$adres - Geçerli bir .onion adresi değil";
                    continue;
                }
                
                // Domain adını kaynak adı olarak kullan
                $kaynak_adi = parse_url($adres, PHP_URL_HOST);
                if (!$kaynak_adi) {
                    // URL parse edilemezse, sadece domain'i al
                    $kaynak_adi = preg_replace('/^https?:\/\//', '', $adres);
                    $kaynak_adi = preg_replace('/\/.*$/', '', $kaynak_adi);
                }
                
                try {
                    // Güven skoru varsayılan 3 olarak veritabanında ayarlanacak
                    if (guvenSkoruKolonVarMi()) {
                        $stmt = $db->prepare("INSERT INTO onion_adresleri (adres, kaynak_adi, kaynak_guven_skoru) VALUES (?, ?, 3)");
                    } else {
                        $stmt = $db->prepare("INSERT INTO onion_adresleri (adres, kaynak_adi) VALUES (?, ?)");
                    }
                    $stmt->execute([$adres, $kaynak_adi]);
                    $basarili++;
                    logKaydet("Onion adresi eklendi: $adres (Kaynak: $kaynak_adi)");
                } catch (PDOException $e) {
                    $hatali++;
                    if (strpos($e->getMessage(), 'duplicate key') !== false) {
                        $hata_mesajlari[] = "$adres - Bu adres zaten kayıtlı";
                    } else {
                        $hata_mesajlari[] = "$adres - Veritabanı hatası";
                        logKaydet("Onion ekleme hatası: " . $e->getMessage(), 'ERROR');
                    }
                }
            }
            
            // Sonuç mesajı
            if ($basarili > 0) {
                $mesaj = "$basarili adres başarıyla eklendi.";
                if ($hatali > 0) {
                    $mesaj .= " $hatali adres eklenemedi.";
                }
            }
            if (!empty($hata_mesajlari)) {
                $hata = implode('<br>', $hata_mesajlari);
            }
        }
    }
}

// Kullanıcı ekleme işlemi
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['islem']) && $_POST['islem'] === 'kullanici_ekle') {
    if (!csrfTokenDogrula($_POST['csrf_token'] ?? '')) {
        $hata = 'Güvenlik hatası.';
    } else {
        $kullanici_adi = trim($_POST['kullanici_adi'] ?? '');
        $sifre = trim($_POST['sifre'] ?? '');
        $rol = trim($_POST['rol'] ?? 'analist');
        $aktif = isset($_POST['aktif']) ? 1 : 0;
        
        if (empty($kullanici_adi) || empty($sifre)) {
            $hata = 'Kullanıcı adı ve şifre gereklidir.';
        } elseif (strlen($sifre) < 8) {
            $hata = 'Şifre en az 8 karakter olmalıdır.';
        } elseif (!in_array($rol, ['admin', 'analist'])) {
            $hata = 'Geçersiz rol.';
        } else {
            try {
                // Kullanıcı adı kontrolü
                $stmt = $db->prepare("SELECT COUNT(*) FROM kullanicilar WHERE kullanici_adi = ?");
                $stmt->execute([$kullanici_adi]);
                if ($stmt->fetchColumn() > 0) {
                    $hata = 'Bu kullanıcı adı zaten kullanılıyor.';
                } else {
                    $sifre_hash = password_hash($sifre, PASSWORD_DEFAULT);
                    $stmt = $db->prepare("INSERT INTO kullanicilar (kullanici_adi, sifre_hash, rol, aktif) VALUES (?, ?, ?, ?)");
                    $stmt->execute([$kullanici_adi, $sifre_hash, $rol, $aktif]);
                    logKaydet("Yeni kullanıcı eklendi: " . $kullanici_adi . " (Rol: " . $rol . ")");
                    
                    // Redirect to prevent form resubmission
                    header("Location: admin.php?mesaj=kullanici_eklendi");
                    exit;
                }
            } catch (PDOException $e) {
                $hata = 'Kullanıcı eklenirken hata oluştu.';
                logKaydet("Kullanıcı ekleme hatası: " . $e->getMessage(), 'ERROR');
            }
        }
    }
}


// Log indirme işlemi
if (isset($_GET['islem']) && $_GET['islem'] === 'log_indir') {
    $log_dosya = __DIR__ . '/logs/app.log';
    if (file_exists($log_dosya)) {
        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="system_logs_' . date('Y-m-d_H-i') . '.log"');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($log_dosya));
        readfile($log_dosya);
        exit;
    }
}

if (isset($_GET['islem']) && isset($_GET['id'])) {
    $islem = $_GET['islem'];
    $id = intval($_GET['id']);
    
    if ($islem === 'sil') {
        try {
            $stmt = $db->prepare("DELETE FROM onion_adresleri WHERE id = ?");
            $stmt->execute([$id]);
            $mesaj = 'Onion adresi silindi.';
            logKaydet("Onion adresi silindi: ID $id");
        } catch (PDOException $e) {
            $hata = 'Silme işlemi başarısız.';
        }
    } elseif ($islem === 'aktif') {
        try {
            $stmt = $db->prepare("UPDATE onion_adresleri SET aktif = TRUE WHERE id = ?");
            $stmt->execute([$id]);
            $mesaj = 'Onion adresi aktif edildi.';
        } catch (PDOException $e) {
            $hata = 'Güncelleme başarısız.';
        }
    } elseif ($islem === 'pasif') {
        try {
            $stmt = $db->prepare("UPDATE onion_adresleri SET aktif = FALSE WHERE id = ?");
            $stmt->execute([$id]);
            $mesaj = 'Onion adresi pasif edildi.';
        } catch (PDOException $e) {
            $hata = 'Güncelleme başarısız.';
        }
    } elseif ($islem === 'kullanici_sil') {
        // Kendi hesabını silemesin
        if ($id == $_SESSION['kullanici_id']) {
            $hata = 'Kendi hesabınızı silemezsiniz.';
        } else {
            try {
                $stmt = $db->prepare("SELECT kullanici_adi FROM kullanicilar WHERE id = ?");
                $stmt->execute([$id]);
                $kullanici_adi = $stmt->fetchColumn();
                
                $stmt = $db->prepare("DELETE FROM kullanicilar WHERE id = ?");
                $stmt->execute([$id]);
                $mesaj = 'Kullanıcı silindi.';
                logKaydet("Kullanıcı silindi: $kullanici_adi (ID: $id)");
            } catch (PDOException $e) {
                $hata = 'Kullanıcı silinirken hata oluştu.';
                logKaydet("Kullanıcı silme hatası: " . $e->getMessage(), 'ERROR');
            }
        }
    }
}

// Şifre değiştirme işlemi
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['islem']) && $_POST['islem'] === 'sifre_degistir') {
    if (!csrfTokenDogrula($_POST['csrf_token'] ?? '')) {
        $hata = 'Güvenlik hatası.';
    } else {
        $eski_sifre = trim($_POST['eski_sifre'] ?? '');
        $yeni_sifre = trim($_POST['yeni_sifre'] ?? '');
        $yeni_sifre_tekrar = trim($_POST['yeni_sifre_tekrar'] ?? '');
        
        if (empty($eski_sifre) || empty($yeni_sifre) || empty($yeni_sifre_tekrar)) {
            $hata = 'Tüm alanları doldurun.';
        } elseif ($yeni_sifre !== $yeni_sifre_tekrar) {
            $hata = 'Yeni şifreler eşleşmiyor.';
        } elseif (strlen($yeni_sifre) < 8) {
            $hata = 'Yeni şifre en az 8 karakter olmalıdır.';
        } else {
            try {
                $stmt = $db->prepare("SELECT sifre_hash FROM kullanicilar WHERE id = ?");
                $stmt->execute([$_SESSION['kullanici_id']]);
                $mevcut_sifre_hash = $stmt->fetchColumn();
                
                if (!password_verify($eski_sifre, $mevcut_sifre_hash)) {
                    $hata = 'Eski şifre yanlış.';
                } else {
                    $yeni_sifre_hash = password_hash($yeni_sifre, PASSWORD_DEFAULT);
                    $stmt = $db->prepare("UPDATE kullanicilar SET sifre_hash = ? WHERE id = ?");
                    $stmt->execute([$yeni_sifre_hash, $_SESSION['kullanici_id']]);
                    logKaydet("Kullanıcı şifresini değiştirdi: " . $_SESSION['kullanici_adi']);
                    
                    // Redirect to prevent form resubmission
                    header("Location: admin.php?mesaj=sifre_degisti");
                    exit;
                }
            } catch (PDOException $e) {
                $hata = 'Şifre değiştirilirken hata oluştu.';
                logKaydet("Şifre değiştirme hatası: " . $e->getMessage(), 'ERROR');
            }
        }
    }
}


$onion_adresleri = $db->query("SELECT * FROM onion_adresleri ORDER BY ekleme_tarihi DESC")->fetchAll(PDO::FETCH_ASSOC);
$kullanicilar = $db->query("SELECT id, kullanici_adi, rol, aktif, son_giris FROM kullanicilar ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
$kategoriler = $db->query("SELECT * FROM kategoriler ORDER BY kategori_adi")->fetchAll(PDO::FETCH_ASSOC);
$kritiklik_esikleri = $db->query("SELECT * FROM kritiklik_esikleri ORDER BY min_skor")->fetchAll(PDO::FETCH_ASSOC);

// Log dosyasını oku
$log_kayitlari = [];
$log_dosya = __DIR__ . '/logs/app.log';
if (file_exists($log_dosya)) {
    $log_lines = file($log_dosya, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $log_lines = array_reverse($log_lines); // En yeni önce
    $log_kayitlari = array_slice($log_lines, 0, 100); // Son 100 kayıt
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>0Promil CTI - Yönetim Paneli</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="header">
        <h1>0Promil CTI - Yönetim Paneli</h1>
        <div class="header-info">
            <a href="index.php">Ana Sayfa</a>
            <a href="logout.php">Çıkış</a>
        </div>
    </div>

    <div class="container">
        <?php if ($mesaj): ?>
            <div class="alert alert-success">
                <div style="flex-shrink: 0">✓</div>
                <div><?= guvenliCikti($mesaj) ?></div>
            </div>
        <?php endif; ?>
        <?php if ($hata): ?>
            <div class="alert alert-danger">
                <div style="flex-shrink: 0">⚠</div>
                <div><?= guvenliCikti($hata) ?></div>
            </div>
        <?php endif; ?>

        <div class="admin-panel-card">
            <h2>Onion Adresi Ekle</h2>
            <form method="POST">
                <input type="hidden" name="islem" value="onion_ekle">
                <input type="hidden" name="csrf_token" value="<?= csrfTokenOlustur() ?>">
                <div class="form-group">
                    <label for="adresler">
                        Onion Adresleri
                        <span style="font-size:12px;color: var(--secondary-light);">(Her satıra bir adres)</span>
                    </label>

                    <textarea
                        id="adresler"
                        name="adresler"
                        rows="8"
                        placeholder="http://falanfilancartcurtcartcurt.onion&#10;falanfalanfilanfilancartcartcurtcurt.onion&#10;falancurtfilanfalancartcurt123456789.onion/forum"
                        required
                        spellcheck="false"
                        autocomplete="off"
                        class="form-control onion-textarea"
                    ></textarea>

                    <div class="adres-info">
                        <span id="adresSayisi">0 adres</span>
                        <span id="gecersizSayisi" class="error-text"></span>
                    </div>

                    <small class="form-help">
                        -> v3 Onion adresleri otomatik doğrulanır<br>
                        -> http/https yazmanız zorunlu değildir<br>
                        -> Path desteklenir (örn: /index, /forum)<br>
                        -> Kopyala-yapıştır
                    </small>
                </div>
                <div class="form-group">
                    <button type="submit" class="btn btn-primary">Ekle</button>
                </div>
            </form>
        </div>

        <div class="admin-panel-card">
            <h2>Yeni Kullanıcı Ekle</h2>
            <form method="POST">
                <input type="hidden" name="islem" value="kullanici_ekle">
                <input type="hidden" name="csrf_token" value="<?= csrfTokenOlustur() ?>">
                <div class="form-group">
                    <label>Kullanıcı Adı</label>
                    <input type="text" name="kullanici_adi" class="form-control" placeholder="Kullanıcı adı" required>
                </div>
                <div class="form-group">
                    <label>Şifre (min 8 karakter)</label>
                    <input type="password" name="sifre" class="form-control" placeholder="Şifre" required minlength="8">
                </div>
                <div class="form-group">
                    <label>Rol</label>
                    <select name="rol" class="form-control">
                        <option value="analist">Analist</option>
                        <option value="admin">Admin</option>
                    </select>
                </div>
                <div class="form-group">
                    <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                        <input type="checkbox" name="aktif" checked style="width: auto;">
                        Aktif
                    </label>
                </div>
                <div class="form-group">
                    <button type="submit" class="btn btn-primary">Kullanıcı Ekle</button>
                </div>
            </form>
        </div>

        <div class="admin-panel-card">
            <h2>Onion Adresleri</h2>
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Adres</th>
                        <th>Kaynak Adı</th>
                        <th>Güven Skoru</th>
                        <th>Durum</th>
                        <th>Son Tarama</th>
                        <th>Toplam Kayıt</th>
                        <th>İşlemler</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($onion_adresleri)): ?>
                        <tr>
                            <td colspan="8" style="text-align: center; padding: 40px;">
                                Henüz onion adresi eklenmemiş.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($onion_adresleri as $onion): ?>
                            <tr>
                                <td><?= $onion['id'] ?></td>
                                <td style="font-family: monospace;"><?= guvenliCikti($onion['adres']) ?></td>
                                <td><?= guvenliCikti($onion['kaynak_adi']) ?></td>
                                <td>
                                    <?php 
                                    $guven_skoru = guvenSkoruGetir($onion);
                                    echo guvenSkoruYildizlar($guven_skoru) . ' (' . $guven_skoru . '/5)';
                                    ?>
                                </td>
                                <td>
                                    <span class="badge badge-<?= $onion['aktif'] ? 'aktif' : 'pasif' ?>">
                                        <?= $onion['aktif'] ? 'Aktif' : 'Pasif' ?>
                                    </span>
                                </td>
                                <td><?= $onion['son_tarama'] ? tarihFormatla($onion['son_tarama']) : 'Henüz taranmadı' ?></td>
                                <td><?= $onion['toplam_kayit'] ?></td>
                                <td>
                                    <a href="#" class="action-btn edit tara-buton" data-id="<?= $onion['id'] ?>" 
                                       style="background: #27ae60; color: white; padding: 5px 10px; border-radius: 3px;">
                                        Tara
                                    </a>
                                    <?php if ($onion['aktif']): ?>
                                        <a href="?islem=pasif&id=<?= $onion['id'] ?>" class="action-btn edit">Pasif Et</a>
                                    <?php else: ?>
                                        <a href="?islem=aktif&id=<?= $onion['id'] ?>" class="action-btn edit">Aktif Et</a>
                                    <?php endif; ?>
                                    <a href="?islem=sil&id=<?= $onion['id'] ?>" class="action-btn delete" 
                                       onclick="return confirm('Bu onion adresini silmek istediğinizden emin misiniz?')">Sil</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="admin-panel-card">
            <h2>Kullanıcılar</h2>
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Kullanıcı Adı</th>
                        <th>Rol</th>
                        <th>Durum</th>
                        <th>Son Giriş</th>
                        <th>İşlemler</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($kullanicilar as $kullanici): ?>
                        <tr>
                            <td><?= $kullanici['id'] ?></td>
                            <td><?= guvenliCikti($kullanici['kullanici_adi']) ?></td>
                            <td><?= guvenliCikti($kullanici['rol']) ?></td>
                            <td>
                                <span class="badge badge-<?= $kullanici['aktif'] ? 'aktif' : 'pasif' ?>">
                                    <?= $kullanici['aktif'] ? 'Aktif' : 'Pasif' ?>
                                </span>
                            </td>
                            <td><?= $kullanici['son_giris'] ? tarihFormatla($kullanici['son_giris']) : 'Henüz giriş yapmadı' ?></td>
                            <td>
                                <?php if ($kullanici['id'] != $_SESSION['kullanici_id']): ?>
                                    <a href="?islem=kullanici_sil&id=<?= $kullanici['id'] ?>" class="action-btn delete" 
                                       onclick="return confirm('Bu kullanıcıyı silmek istediğinizden emin misiniz?')">Sil</a>
                                <?php else: ?>
                                    <span style="color: #999;">-</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="admin-panel-card">
            <h2>Kategoriler</h2>
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Kategori Adı</th>
                        <th>Açıklama</th>
                        <th>Renk</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($kategoriler as $kategori): ?>
                        <tr>
                            <td><?= guvenliCikti($kategori['kategori_adi']) ?></td>
                            <td><?= guvenliCikti($kategori['aciklama'] ?? '') ?></td>
                            <td>
                                <span style="display: inline-block; width: 30px; height: 20px; background: <?= guvenliCikti($kategori['renk']) ?>; border: 1px solid #ddd;"></span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="admin-panel-card">
            <h2>Kritiklik Eşikleri</h2>
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Eşik Adı</th>
                        <th>Min Skor</th>
                        <th>Max Skor</th>
                        <th>Renk</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($kritiklik_esikleri as $esik): ?>
                        <tr>
                            <td><?= ucfirst(guvenliCikti($esik['esik_adi'])) ?></td>
                            <td><?= $esik['min_skor'] ?></td>
                            <td><?= $esik['max_skor'] ?></td>
                            <td>
                                <span style="display: inline-block; width: 30px; height: 20px; background: <?= guvenliCikti($esik['renk']) ?>; border: 1px solid #ddd;"></span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="admin-panel-card">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; border-bottom: 2px solid #eee; padding-bottom: 12px;">
                <h2 style="margin: 0; border: none; padding: 0;">Sistem Logları (Son 100)</h2>
                <div style="display: flex; gap: 10px; align-items: center;">
                    <div class="log-controls" style="display: flex; gap: 2px;">
                        <button type="button" class="btn btn-sm btn-light" onclick="setFontSize('small')" title="Küçük Yazı">A</button>
                        <button type="button" class="btn btn-sm btn-light" onclick="setFontSize('medium')" title="Orta Yazı">A</button>
                        <button type="button" class="btn btn-sm btn-light" onclick="setFontSize('large')" title="Büyük Yazı">A</button>
                    </div>
                    <a href="?islem=log_indir" class="btn btn-sm btn-primary">
                        Tüm Logları İndir
                    </a>
                </div>
            </div>

            <?php if (empty($log_kayitlari)): ?>
                <p style="text-align: center; padding: 40px; color: #999;">
                    Henüz log kaydı bulunmuyor.
                </p>
            <?php else: ?>
                <div id="logViewer" class="log-content log-medium">
                    <?php foreach ($log_kayitlari as $log): ?>
                        <?php
                        // Log seviyesini belirle ve class ata
                        $log_class = '';
                        if (strpos($log, '[ERROR]') !== false) {
                            $log_class = 'log-error';
                        } elseif (strpos($log, '[WARNING]') !== false) {
                            $log_class = 'log-warning';
                        } elseif (strpos($log, '[INFO]') !== false) {
                            $log_class = 'log-info';
                        }
                        ?>
                        <div class="log-entry <?= $log_class ?>"><?= guvenliCikti($log) ?></div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        function setFontSize(size) {
            const viewer = document.getElementById('logViewer');
            viewer.classList.remove('log-small', 'log-medium', 'log-large');
            viewer.classList.add('log-' + size);
            
            // Tercihi kaydet (localStorage)
            localStorage.setItem('logFontSize', size);
        }

        // Sayfa yüklendiğinde tercihi hatırla
        document.addEventListener('DOMContentLoaded', function() {
            const savedSize = localStorage.getItem('logFontSize');
            if (savedSize) {
                setFontSize(savedSize);
            }
        });
    </script>
    
    <script>
        document.querySelectorAll('.tara-buton').forEach(function(buton) {
            buton.addEventListener('click', function(e) {
                e.preventDefault();
                const onionId = this.getAttribute('data-id');
                const buton = this;
                const orijinalText = buton.textContent;
                
                buton.style.opacity = '0.6';
                buton.style.pointerEvents = 'none';
                buton.textContent = 'Taranıyor...';
                
                fetch('api_tarama.php?id=' + onionId, {
                    method: 'GET'
                })
                .then(response => response.json())
                .then(data => {
                    if (data.basarili) {
                        buton.textContent = 'Tarama Başlatıldı ✓';
                        buton.style.background = '#27ae60';
                        
                        setTimeout(() => {
                            buton.textContent = orijinalText;
                            buton.style.opacity = '1';
                            buton.style.pointerEvents = 'auto';
                        }, 3000);
                        
                        const mesajDiv = document.createElement('div');
                        mesajDiv.className = 'mesaj basarili';
                        mesajDiv.textContent = 'Tarama başlatıldı: ' + data.mesaj;
                        document.querySelector('.container').insertBefore(mesajDiv, document.querySelector('.container').firstChild);
                        
                        setTimeout(() => {
                            mesajDiv.remove();
                        }, 5000);
                    } else {
                        buton.textContent = 'Hata!';
                        buton.style.background = '#e74c3c';
                        alert('Tarama başlatılamadı: ' + (data.mesaj || 'Bilinmeyen hata'));
                    }
                })
                .catch(error => {
                    console.error('Hata:', error);
                    buton.textContent = 'Hata!';
                    buton.style.background = '#e74c3c';
                    alert('Tarama başlatılamadı. Go collector servisinin çalıştığından emin olun.');
                    
                    setTimeout(() => {
                        buton.textContent = orijinalText;
                        buton.style.opacity = '1';
                        buton.style.pointerEvents = 'auto';
                        buton.style.background = '#27ae60';
                    }, 3000);
                });
            });
        });
    </script>
    
    <div class="footer-banner">
        <a href="https://www.linkedin.com/in/bydurmus" target="_blank">www.linkedin.com/in/bydurmus</a>
    </div>

    <script>
    const textarea = document.getElementById('adresler');
    const adresSayisi = document.getElementById('adresSayisi');
    const gecersizSayisi = document.getElementById('gecersizSayisi');

    // v3 onion adresleri + path desteği (örn: /index, /forum)
    const onionRegex = /^((http|https):\/\/)?[a-z2-7]{56}\.onion(\/.*)?$/;

    textarea.addEventListener('input', () => {
        const satirlar = textarea.value
            .split('\n')
            .map(s => s.trim())
            .filter(Boolean);

        let gecersiz = 0;

        satirlar.forEach(adres => {
            if (!onionRegex.test(adres)) {
                gecersiz++;
            }
        });

        adresSayisi.textContent = `${satirlar.length} adres`;
        gecersizSayisi.textContent = gecersiz
            ? `${gecersiz} geçersiz adres`
            : '';
    });
    </script>
</body>
</html>
