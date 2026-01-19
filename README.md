# 0Promil CTI - Siber Tehdit İstihbarat Platformu
**Uyarı sistem açıldıktan sonra TXT dosyasının içindeki linkleri toplu yada tek tek ekleyebilirsiniz sonrasında sistemi kullanmaya başlayabilirsiniz**



0Promil CTI, onion sitelerinden ve diğer kaynaklardan siber tehdit verilerini taramak, analiz etmek ve görselleştirmek için tasarlanmış kapsamlı bir platformdur. Güvenlik analistlerine kritik tehditleri izlemek, trendleri analiz etmek ve istihbarat kaynaklarını etkili bir şekilde yönetmek için merkezi bir panel sunar.

## Özellikler

### Gösterge Paneli ve Analitik
*   **Gerçek Zamanlı İstatistikler:** Toplam kayıtları, günlük veri giriş hızlarını ve kritik tehdit sayılarını izleyin.
*   **Kategori Analizi:** Tehditlerin kategorilere göre görsel dağılımı (örn. Fidye Yazılımı, Kimlik Avı, Botnet).
*   **Trend Analizi:** Son 24 saat ve 7 günlük tehdit aktivitelerini görüntüleyin.
*   **Kritik Uyarılar:** Yüksek önem seviyesindeki tehditleri anında fark edin.
*   **Gelişmiş Arama:** Verileri tarih aralığı, kritiklik, kategori ve kaynak adına göre filtreleyin.
*   **PDF Dışa Aktarma:** Belirli tehdit detayları için temiz, yazdırmaya hazır raporlar oluşturun.

### Yönetim Paneli
*   **Kaynak Yönetimi:** Çoklu onion adreslerini topluca ekleyin (textarea desteği) ve otomatik doğrulama sağlayın.
*   **Kullanıcı Yönetimi:** Rol tabanlı erişim ile analist hesapları oluşturun, silin ve yönetin.
*   **Sistem Logları:** Sistem izleme için ham metin tabanlı log görüntüleyici. Tüm logları indirme özelliği dahildir.
*   **Bütünleşik Tasarım:** Ana gösterge paneliyle uyumlu, tutarlı ve responsive arayüz.

### Backend ve Tarayıcı (Crawler)
*   **Go Collector:** Tor proxy üzerinden güvenli `.onion` site taraması için yüksek performanslı crawler.
*   **Otomatik Kaynak Tespiti:** Site başlıklarından kaynak isimlerini otomatik olarak çıkarır ve günceller.
*   **İçerik Analizi:** Anahtar kelimelere dayalı güven skoru ve kritiklik seviyesi atamak için otomatik puanlama sistemi.

## Teknoloji Stack

*   **Backend:** Go (Golang)
*   **Frontend:** PHP 8.x, HTML5, CSS3, Chart.js
*   **Veritabanı:** PostgreSQL 15
*   **Ağ:** Tor Proxy (Socks5)
*   **Konteynerizasyon:** Docker & Docker Compose

## Kurulum

### Gereksinimler
*   Docker
*   Docker Compose

### Hızlı Başlangıç
1.  **Depoyu Klonlayın**
    ```bash
    git clone https://github.com/0promil/Tor_CTI_Dashboard
    cd Tor_CTI_Dashboard
    ```

2.  **Servisleri Başlatın**
    Tüm yapıyı derlemek ve başlatmak için aşağıdaki komutu çalıştırın:
    ```bash
    docker-compose up -d --build
    ```
    Bu komut PostgreSQL veritabanını, Tor proxy'sini, Go collector'ı ve PHP web sunucusunu başlatacaktır.

3.  **Uygulamaya Erişim**
    *   **Web Arayüzü:** http://localhost:8080
    *   **Go Collector:** http://localhost:8081 (Dahili API)

### Varsayılan Giriş Bilgileri
*   **Kullanıcı Adı:** `admin`
*   **Şifre:** `admin123`

*Not: Varsayılan şifreyi Yönetim Paneli üzerinden hemen değiştirmeniz önerilir.*

## Proje Yapısı

```
0promil-cti/
├── go_collector/       # Go crawler kaynak kodu ve Dockerfile
├── php_web/           # PHP web uygulaması, admin paneli ve stiller
│   ├── output/        # Taranan içerik dizini
│   ├── admin.php      # Yönetim arayüzü
│   ├── index.php      # Ana gösterge paneli
│   └── style.css      # Merkezi stil dosyası
├── sql/               # Veritabanı başlatma scriptleri
└── docker-compose.yml # Servis orkestrasyon konfigürasyonu
```

## Kullanım Kılavuzu

1.  **Kaynak Ekleme:** Yönetim Paneline giriş yapın ve yeni .onion linkleri eklemek için "Onion Adresleri" bölümünü kullanın. Her satıra bir adres gelecek şekilde çoklu ekleme yapabilirsiniz.
2.  **İzleme:** Gelen verileri görüntülemek için Gösterge Panelini kullanın. Go collector eklenen kaynakları periyodik olarak tarayacaktır.
3.  **Loglar:** Tarama durumunu veya sistem hatalarını incelemek için Yönetim Panelindeki "Sistem Logları" bölümünü kontrol edin. Loglar kolay okuma için ham metin formatında görüntülenir.
4.  **Raporlama:** Herhangi bir tehdit kaydının detaylarını görmek için "İncele"ye tıklayın ve rapor almak için "PDF İndir" butonunu kullanın.

## Lisans
MIT License

---

# 0Promil CTI - Cyber Threat Intelligence Platform

0Promil CTI is a comprehensive platform designed to crawl, analyze, and visualize cyber threat data from onion sites and other sources. It provides security analysts with a centralized dashboard to monitor critical threats, analyze trends, and manage intelligence sources effectively.

## Features

### Dashboard & Analytics
*   **Real-time Statistics:** Monitor total records, daily ingestion rates, and critical threat counts.
*   **Category Analysis:** Visual breakdown of threats by category (e.g., Ransomware, Phishing, Botnet).
*   **Trend Analysis:** View threat activity over the last 24 hours and 7 days.
*   **Critical Alerts:** Immediate visibility of high-severity threats.
*   **Advanced Search:** Filter data by date range, criticality, category, and source name.
*   **PDF Export:** Generate clean, print-ready reports for specific threat details.

### Admin Panel
*   **Source Management:** Add multiple onion addresses in bulk (textarea support) with automatic validation.
*   **User Management:** Specific Create, delete, and manage analyst accounts with role-based access.
*   **System Logs:** Raw text-based log viewer for real-time system monitoring. Includes full log download capability.
*   **Unified Design:** Consistent, responsive interface matching the main dashboard.

### Backend & Crawler
*   **Go Collector:** High-performance crawler for safe `.onion` site scraping via Tor proxy.
*   **Automated Source Detection:** Automatically extracts and updates source names from site titles.
*   **Content Analysis:** Automated scoring system to assign confidence scores and criticality levels based on keywords.

## Tech Stack

*   **Backend:** Go (Golang)
*   **Frontend:** PHP 8.x, HTML5, CSS3, Chart.js
*   **Database:** PostgreSQL 15
*   **Network:** Tor Proxy (Socks5)
*   **Containerization:** Docker & Docker Compose

## Installation

### Prerequisites
*   Docker
*   Docker Compose

### Quick Start
1.  **Clone the Repository**
    ```bash
    git clone https://github.com/username/0promil-cti.git
    cd 0promil-cti
    ```

2.  **Start Services**
    Run the following command to build and start the entire stack:
    ```bash
    docker-compose up -d --build
    ```
    This will initialize the PostgreSQL database, Tor proxy, Go collector, and PHP web server.

3.  **Access the Application**
    *   **Web Interface:** http://localhost:8080
    *   **Go Collector:** http://localhost:8081 (Internal API)

### Default Credentials
*   **Username:** `admin`
*   **Password:** `admin123`

*Note: It is highly recommended to change the default password immediately via the Admin Panel.*

## Project Structure

```
0promil-cti/
├── go_collector/       # Go crawler source code and Dockerfile
├── php_web/           # PHP web application, admin panel, and styles
│   ├── output/        # Directory for crawled content
│   ├── admin.php      # Administration interface
│   ├── index.php      # Main dashboard
│   └── style.css      # Centralized stylesheet
├── sql/               # Database initialization scripts
└── docker-compose.yml # Service orchestration configuration
```

## Usage Guide

1.  **Adding Sources:** Log in to the Admin Panel and use the "Onion Adresleri" section to add new .onion links. You can paste multiple addresses, one per line.
2.  **Monitoring:** Use the Dashboard to view incoming data. The Go collector will periodically scan added sources.
3.  **Logs:** Check the "Sistem Logları" section in the Admin Panel to troubleshoot crawling status or system errors. Logs are displayed in a raw text format for easy reading.
4.  **Reporting:** Click "İncele" on any threat record to view details and use the "PDF İndir" button to export a report.

## License
MIT License
