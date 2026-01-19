<?php
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_PORT', getenv('DB_PORT') ?: '5432');
define('DB_NAME', getenv('DB_NAME') ?: '0promil');
define('DB_USER', getenv('DB_USER') ?: '0promil');
define('DB_PASSWORD', getenv('DB_PASSWORD') ?: '0promil');

function dbBaglanti() {
    static $conn = null;
    
    if ($conn === null) {
        $dsn = sprintf(
            "pgsql:host=%s;port=%s;dbname=%s",
            DB_HOST,
            DB_PORT,
            DB_NAME
        );
        
        try {
            $conn = new PDO($dsn, DB_USER, DB_PASSWORD);
            $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $conn->exec("SET NAMES 'UTF8'");
        } catch (PDOException $e) {
            error_log("Veritabanı bağlantı hatası: " . $e->getMessage());
            die("Veritabanı bağlantı hatası");
        }
    }
    
    return $conn;
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function csrfTokenOlustur() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrfTokenDogrula($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function kullaniciGirisKontrol() {
    if (!isset($_SESSION['kullanici_id'])) {
        header('Location: login.php');
        exit;
    }
}

function rolKontrol($gerekliRol) {
    kullaniciGirisKontrol();
    
    if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== $gerekliRol) {
        header('Location: index.php');
        exit;
    }
}

function guvenliCikti($string) {
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}

function tarihFormatla($tarih) {
    return date('d.m.Y H:i', strtotime($tarih));
}

function logKaydet($mesaj, $seviye = 'INFO') {
    $logMesaj = sprintf(
        "[%s] [%s] %s\n",
        date('Y-m-d H:i:s'),
        $seviye,
        $mesaj
    );
    error_log($logMesaj, 3, __DIR__ . '/logs/app.log');
}


if (!file_exists(__DIR__ . '/logs')) {
    mkdir(__DIR__ . '/logs', 0755, true);
}

// Kaynak güven skoru yardımcı fonksiyonları
function guvenSkoruKolonVarMi() {
    static $kolon_var = null;
    
    if ($kolon_var === null) {
        try {
            $db = dbBaglanti();
            $test_sql = "SELECT kaynak_guven_skoru FROM onion_adresleri LIMIT 1";
            $db->query($test_sql);
            $kolon_var = true;
        } catch (PDOException $e) {
            $kolon_var = false;
        }
    }
    
    return $kolon_var;
}

function guvenSkoruGetir($kayit_veya_onion_id, $varsayilan = 3) {
    // Eğer array ise (kayıt verisi)
    if (is_array($kayit_veya_onion_id)) {
        return $kayit_veya_onion_id['kaynak_guven_skoru'] 
            ?? $kayit_veya_onion_id['kaynak_guven_skoru_onion'] 
            ?? $varsayilan;
    }
    
    // Eğer integer ise (onion_id)
    if (is_numeric($kayit_veya_onion_id) && guvenSkoruKolonVarMi()) {
        try {
            $db = dbBaglanti();
            $stmt = $db->prepare("SELECT kaynak_guven_skoru FROM onion_adresleri WHERE id = ?");
            $stmt->execute([$kayit_veya_onion_id]);
            $result = $stmt->fetchColumn();
            return $result !== false ? $result : $varsayilan;
        } catch (PDOException $e) {
            return $varsayilan;
        }
    }
    
    return $varsayilan;
}

function guvenSkoruYildizlar($skor) {
    $skor = max(1, min(5, intval($skor)));
    return str_repeat('★', $skor) . str_repeat('☆', 5 - $skor);
}

