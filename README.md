# 🧾 Fatura

### Bu paket [Fatih Kadir Akın](https://github.com/f)'ın hazırlamış olduğu [fatura](https://github.com/f/fatura) paketinin PHP dili ile yazılmış versiyonudur.

eFatura sistemi üzerinde fatura oluşturmanızı sağlar.

> Bu sistem **https://earsivportal.efatura.gov.tr/** adresini kullanarak bu sistem üzerinden fatura oluşturmanızı sağlar.

> Bu sistem GİB'e tabi **şahış şirketi** ya da **şirket** hesapları ile çalışır ve bu kişilikler adına resmi fatura oluşturur. Kesilen faturaları https://earsivportal.efatura.gov.tr/ adresinden görüntüleyebilir ya da bu kütüphane ile indirebilirsiniz.

#### Kullanıcı Adı ve Parola Bilgileri

> [https://earsivportal.efatura.gov.tr/intragiris.html](https://earsivportal.efatura.gov.tr/intragiris.html) adresindeki parola ekranında kullanılan kullanıcı kodu ve parola ile bu paketi kullanabilirsiniz.
> ℹ️ Bu **kullanıcı kodu ve parola bilgilerini** muhasebecinizden ya da **GİB - İnteraktif Vergi Dairesi**'nden edinebilirsiniz.

---

## Kurulum
```
composer require aad/fatura
```
---

## Kullanım

Service sınıfının constructor ına konfigürasyon bilgilerini vererek kullanabilirsiniz.

### Örnek fatura bilgileri ve servis ayarları

> Aşağıdaki şekilde fatura detaylarını kullanacağınız metoda parametre olarak verip faturanın bu bilgiler ile oluşmasını sağlayabilirsiniz. Bu bilgiler anlatılan örneklerde kullanılacaktır.

```php
$fatura_detaylari = [
    'date' => "08/02/2020",
    'time' => "15:03:00",
    'taxIDOrTRID' => "11111111111",
    'taxOffice' => "Cankaya",
    'title' => "ADEM ALI'DEN FKA'YA SELAMLAR",
    'name' => "",
    'surname' => "",
    'fullAddress' => "X Sok. Y Cad. No: 3 Z T",
    'items' => [
        [
            'name' => "Ornek",
            'quantity' => 1,
            'unitPrice' => 0.01,
            'price' => 0.01,
            'VATRate' => 18,
            'VATAmount' => 0.0
        ]
    ],
    'totalVAT' => 0.0,
    'grandTotal' => 0.01,
    'grandTotalInclVAT' => 0.01,
    'paymentTotal' => 0.01
];
```

> Aşağıda servis ayarlarına ilişkin olması gereken bilgiler örnek olarak belirtilmiştir. `base_url` ve `service_type` bilgileri gönderilmediği durumda e-arşiv portalı test ortam bilgileri baz alınacaktır. Bu bilgiler anlatılan örneklerde kullanılacaktır.

```php
$ayarlar = [
    'username'      => 'GIB Kullanıcı Adı',
    'password'      => 'GIB Kullanıcı Parolası',
    'base_url'      => "https://earsivportal.efatura.gov.tr",
    "service_type"  => "prod"
];
```

### createInvoiceAndGetDownloadURL

Bu metod imzalanmış faturayı oluşturur ve indirme adresi döner.


```php
use AAD\Fatura\Service;

$service = new Service($ayarlar);
$fatura_url = $service->createInvoiceAndGetDownloadURL(['invoice_details' => $fatura_detaylari]);
```

### createInvoiceAndGetHTML

Bu metod imzalanmış faturayı oluşturur ve fatura çıktısını HTML formatta döner. Bu HTML'i `iframe` içerisinde gösterip yazdırılmasını sağlayabilirsiniz.

```php
use AAD\Fatura\Service;

$service = new Service($ayarlar);
$fatura_html = $service->createInvoiceAndGetHTML(['invoice_details' => $fatura_detaylari]);
```

---

## Diğer Kullanım Örnekleri

### getToken

eFatura Portal'ını kullanabileceğiniz `token`'ı döner.

```php
use AAD\Fatura\Service;

$service = new Service($ayarlar);
$token = $service->getToken();
```

### createDraftInvoice

eFatura.gov.tr'de fatura direkt oluşmaz. Önce Taslak fatura oluşturmak gerekir. createDraftInvoice size taslak bir fatura oluşturacaktır. `$fatura_detaylari` değişkeninde olması gereken bilgiler `kullanım` başlığı altında belirtilmiştir.

```php
use AAD\Fatura\Service;

$service = new Service($ayarlar);
$taslak = $service->createDraftInvoice($fatura_detaylari);
```

### findDraftInvoice

Taslak olarak oluşturulan her fatura içerisinde `uuid` ve `date` bilgisi yer alır. Bu metod aracılığı ile belirtilen tarih aralığındaki taslak faturalar aranır. Arama sonuçlarında belirtilen `uuid` yi içeren fatura bilgisi var ise detaylarını döner. Şayet belirtilen `uuid` ve `date` bilgileriyle eşleşen bir taslak fatura bulunamaz ise boş `array` döner.

```php
use AAD\Fatura\Service;

$service = new Service($ayarlar);
$bulunan_taslak = $service->findDraftInvoice(['date' => 'Taslak durumdaki faturanın tarihi', 'uuid' => 'Taslak durumdaki faturanın uuid bilgisi']);
```

> Belirtilen `uuid` ve `date` bilgisi ile eşleşen kayıt sonucu aşağıdaki şekilde dönmektedir.

```
Array
(
    [belgeNumarasi] => GIB2020000000430
    [aliciVknTckn] => 11111111111
    [aliciUnvanAdSoyad] =>  
    [belgeTarihi] => 08-02-2020
    [belgeTuru] => FATURA
    [onayDurumu] => Onaylanmadı
    [ettn] => Fatura uuid
)
```

> Dönen bilgiler arasındaki GIB Belge Numarası (`ettn`) bilgisi taslak durumdaki faturanın `imzalanması` için kullanılacaktır.

## signDraftInvoice

☢️ Fatura imzalama faturanın kesilmesi işlemidir ve vergi sisteminde mali veri oluşturur. Bu nedenle dikkatli kullanınız.

`findDraftInvoice()` metodundan dönen bilgi doğrudan `signDraftInvoice()` metoduna parametre olarak verilip imzalanması sağlanabilir.

```php
use AAD\Fatura\Service;

$service = new Service($ayarlar);
$bulunan_taslak = $service->findDraftInvoice(['date' => 'Fatura tarihi', 'uuid' => 'Fatura uuid']);
$imzalanmis_fatura = $service->signDraftInvoice($bulunan_taslak);
```

> İmzalama işleminin başarılı olması durumunda aşağıdaki şekilde yanıt dönmektedir.

```
Array
(
    [data] => İmzalama işlemi başarı ile tamamlandı.
    [metadata] => Array
        (
            [optime] => 20200208175608+0300
        )

)
```

## getDownloadURL

İmzalanmış faturanın indirme bağlantısını bu metod aracılığı ile oluşturabilirsiniz.

```php
use AAD\Fatura\Service;

$service = new Service($ayarlar);
$fatura_url = $service->getDownloadURL('Fatura uuid');
```

Henüz imzalanmamış bir faturanın indirme bağlantısına erişmek için `getDownloadURL` metodunun ikinci parametresine `false` değerini gönderebilirsiniz.

```php
use AAD\Fatura\Service;

$service = new Service($ayarlar);
$fatura_url = $service->getDownloadURL('Fatura uuid', false);
```

## getInvoiceHTML

İmzalanmış faturanın HTML çıktısını bu metod aracılığı ile oluşturabilirsiniz.

```php
use AAD\Fatura\Service;

$service = new Service($ayarlar);
$fatura_html = $service->getInvoiceHTML('Fatura uuid');
```

Henüz imzalanmamış bir faturanın HTML çıktısını oluşturmak için `getInvoiceHTML` metodunun ikinci parametresine `false` değerini gönderebilirsiniz.

```php
use AAD\Fatura\Service;

$service = new Service($ayarlar);
$fatura_html = $service->getInvoiceHTML('Fatura uuid', false);
```

## cancelDraftInvoice

Taslak durumdaki faturanın iptalini bu metod ile gerçekleştirebilirsiniz.

```php
use AAD\Fatura\Service;

$service = new Service($ayarlar);
$fatura_html = $service->getInvoiceHTML('İptal sebebi', $bulunan_taslak);
```

## setUuid

Fatura işlemlerinde özelleştirilmiş `uuid` tanımlamak için bu metodu kullanabilirsiniz.

```php
use AAD\Fatura\Service;

$service = new Service($ayarlar);
$service->setUuid('590e1a3e-4aaf-11ea-b085-8434976ef848');
```

> Kullanım örneklerine examples/index.php dosyasından da erişebilirsiniz.

---

## Lisans
MIT

----

> ☢️ **BU PAKET VERGİYE TABİ OLAN MALİ VERİ OLUŞTURUR.** BU PAKET NEDENİYLE OLUŞABİLECEK SORUNLARDAN BU PAKET SORUMLU TUTULAMAZ, RİSK KULLANANA AİTTİR. RİSKLİ GÖRÜYORSANIZ KULLANMAYINIZ.