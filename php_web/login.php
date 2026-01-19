<?php
require_once 'config.php';

$hata = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $kullanici_adi = trim($_POST['kullanici_adi'] ?? '');
    $sifre = $_POST['sifre'] ?? '';
    $csrf_token = $_POST['csrf_token'] ?? '';
    
    if (!csrfTokenDogrula($csrf_token)) {
        $hata = 'Guvenlik hatasi. Lutfen tekrar deneyin.';
    } elseif (empty($kullanici_adi) || empty($sifre)) {
        $hata = 'Kullanici adi ve sifre gereklidir.';
    } else {
        try {
            $db = dbBaglanti();
            $stmt = $db->prepare("SELECT id, kullanici_adi, sifre_hash, rol, aktif FROM kullanicilar WHERE kullanici_adi = ?");
            $stmt->execute([$kullanici_adi]);
            $kullanici = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($kullanici && $kullanici['aktif'] && password_verify($sifre, $kullanici['sifre_hash'])) {
                $_SESSION['kullanici_id'] = $kullanici['id'];
                $_SESSION['kullanici_adi'] = $kullanici['kullanici_adi'];
                $_SESSION['rol'] = $kullanici['rol'];
                
                $stmt = $db->prepare("UPDATE kullanicilar SET son_giris = CURRENT_TIMESTAMP WHERE id = ?");
                $stmt->execute([$kullanici['id']]);
                
                logKaydet("Kullanici girisi: " . $kullanici_adi);
                
                header('Location: index.php');
                exit;
            } else {
                $hata = 'Kullanici adi veya sifre hatali.';
                logKaydet("Basarisiz giris denemesi: " . $kullanici_adi, 'WARNING');
            }
        } catch (PDOException $e) {
            $hata = 'Giris sirasinda bir hata olustu.';
            logKaydet("Giris hatasi: " . $e->getMessage(), 'ERROR');
        }
    }
}

if (isset($_SESSION['kullanici_id'])) {
    header('Location: index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>0Promil CTI - Giriş</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .login-container {
            background: white;
            padding: 40px;
            border-radius: 10px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            width: 100%;
            max-width: 400px;
        }
        h1 {
            text-align: center;
            color: #333;
            margin-bottom: 30px;
        }
        .form-group {
            margin-bottom: 20px;
        }
        label {
            display: block;
            margin-bottom: 5px;
            color: #555;
            font-weight: 500;
        }
        input[type="text"],
        input[type="password"] {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
        }
        input[type="text"]:focus,
        input[type="password"]:focus {
            outline: none;
            border-color: #667eea;
        }
        button {
            width: 100%;
            padding: 12px;
            background: #667eea;
            color: white;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            cursor: pointer;
            transition: background 0.3s;
        }
        button:hover {
            background: #5568d3;
        }
        .hata {
            background: #fee;
            color: #c33;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 20px;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <h1>0Promil CTI</h1>
        <?php if ($hata): ?>
            <div class="hata"><?= guvenliCikti($hata) ?></div>
        <?php endif; ?>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= csrfTokenOlustur() ?>">
            <div class="form-group">
                <label>Kullanıcı Adı</label>
                <input type="text" name="kullanici_adi" required autofocus>
            </div>
            <div class="form-group">
                <label>Şifre</label>
                <input type="password" name="sifre" required>
            </div>
            <button type="submit">Giriş Yap</button>
        </form>
        <div style="margin-top: 20px; text-align: center; color: #999; font-size: 12px;">
            Varsayılan: admin / admin123 /// analiz / analiz123 /// 
            yedek admin : promil / promil00 
        </div>
    </div>
</body>
</html>
