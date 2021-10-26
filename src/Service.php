<?php namespace AAD\Fatura;

use AAD\Fatura\Exceptions\UnexpectedValueException;
use NumberToWords\NumberToWords;
use Ramsey\Uuid\Uuid;

class Service
{
    private $config = [
        "base_url"      => "https://earsivportaltest.efatura.gov.tr",
        "language"      => "tr",
        "currency"      => "TRY",
        "username"      => "",
        "password"      => "",
        "token"         => "",
        "service_type"  => "test",
    ];

    protected $curl_http_headers = [
        "accept: */*",
        "accept-language: tr,en-US;q=0.9,en;q=0.8",
        "cache-control: no-cache",
        "content-type: application/x-www-form-urlencoded;charset=UTF-8",
        "pragma: no-cache",
        "sec-fetch-mode: cors",
        "sec-fetch-site: same-origin",
        "connection: keep-alive"
    ];

    const COMMANDS = [
        "create_draft_invoice"                  => ["EARSIV_PORTAL_FATURA_OLUSTUR","RG_BASITFATURA"],
        "get_all_invoices_by_date_range"        => ["EARSIV_PORTAL_TASLAKLARI_GETIR", "RG_BASITTASLAKLAR"],
        "sign_draft_invoice"                    => ["EARSIV_PORTAL_FATURA_HSM_CIHAZI_ILE_IMZALA", "RG_BASITTASLAKLAR"],
        "get_invoice_html"                      => ["EARSIV_PORTAL_FATURA_GOSTER", "RG_BASITTASLAKLAR"],
        "cancel_draft_invoice"                  => ["EARSIV_PORTAL_FATURA_SIL", "RG_BASITTASLAKLAR"],
        "get_recipient_data_by_tax_id_or_tr_id" => ["SICIL_VEYA_MERNISTEN_BILGILERI_GETIR", "RG_BASITFATURA"],
        "send_sign_sms_code"                    => ["EARSIV_PORTAL_SMSSIFRE_GONDER", "RG_SMSONAY"],
        "verify_sms_code"                       => ["EARSIV_PORTAL_SMSSIFRE_DOGRULA", "RG_SMSONAY"],
        "get_user_data"                         => ["EARSIV_PORTAL_KULLANICI_BILGILERI_GETIR", "RG_KULLANICI"],
        "update_user_data"                      => ["EARSIV_PORTAL_KULLANICI_BILGILERI_KAYDET", "RG_KULLANICI"]
    ];

    private $uuid;

    public function __construct(array $config = [])
    {
        $this->config = array_merge($this->config, $config);
        $this->getToken();
    }

    public function setConfig($key, $val)
    {
        $this->config[$key] = $val;
        return $this->config[$key];
    }

    public function getConfig($key)
    {
        return isset($this->config[$key]) ? $this->config[$key] : null;
    }

    public function setUuid($uuid)
    {
        if (!Uuid::isValid($uuid)) {
            throw new UnexpectedValueException("Belirttiğiniz uuid geçerli değil.");
        }
        $this->uuid = $uuid;
        return $uuid;
    }

    public function getUuid()
    {
        if (!isset($this->uuid)) {
            return Uuid::uuid1()->toString();
        }
        return $this->uuid;
    }

    public function currencyTransformerToWords($amount)
    {
        $amount = (string) str_replace(".", "", $amount);
        $number_to_words = new NumberToWords();
        $currency_transformer = $number_to_words->getCurrencyTransformer($this->config['language']);
        return mb_strtoupper($currency_transformer->toWords($amount, $this->config['currency']), 'utf-8');
    }

    public function getToken()
    {
        if (isset($this->config['token']) && !empty($this->config['token'])) {
            return $this->config['token'];
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "{$this->config['base_url']}/earsiv-services/assos-login");
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $this->curl_http_headers);
        curl_setopt($ch, CURLOPT_REFERER, "{$this->config['base_url']}/intragiris.html");
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
            "assoscmd" => $this->config['service_type'] == 'prod' ? "anologin" : "login",
            "rtype" => "json",
            "userid" => $this->config['username'],
            "sifre" => $this->config['password'],
            "sifre2" => $this->config['password'],
            "parola" => 1,
        ]));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $server_response = curl_exec($ch);
        $response = json_decode($server_response, true);
        curl_close($ch);

        $this->setConfig("token", $response['token']);
        return $response['token'];
    }

    public function runCommand($command, $page_name, $data = null, $url_encode = false)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "{$this->config['base_url']}/earsiv-services/dispatch");
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $this->curl_http_headers);
        curl_setopt($ch, CURLOPT_REFERER, "{$this->config['base_url']}/login.jsp");
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
            "callid" => $this->getUuid(),
            "token" => $this->config['token'],
            "cmd" => $command,
            "pageName" => $page_name,
            "jp" => $url_encode ? urlencode(json_encode($data)) : json_encode($data),
        ]));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $server_response = curl_exec($ch);
        curl_close($ch);

        return json_decode($server_response, true);
    }

    public function createDraftInvoice($invoice_details = [])
    {
        $invoice_data = [
            "faturaUuid" => $this->getUuid(),
            "belgeNumarasi" => "",
            "faturaTarihi" => $invoice_details['date'],
            "saat" => $invoice_details['time'],
            "paraBirimi" => $this->config['currency'],
            "dovzTLkur" => "0",
            "faturaTipi" => "SATIS",
            "vknTckn" => $invoice_details['taxIDOrTRID'] ?? "11111111111",
            "aliciUnvan" => $invoice_details['title'] ?? "",
            "aliciAdi" => $invoice_details['name'],
            "aliciSoyadi" => $invoice_details['surname'],
            "binaAdi" => "",
            "binaNo" => "",
            "kapiNo" => "",
            "kasabaKoy" => "",
            "vergiDairesi" => $invoice_details['taxOffice'],
            "ulke" => "Türkiye",
            "bulvarcaddesokak" => $invoice_details['fullAddress'],
            "mahalleSemtIlce" => "",
            "sehir" => " ",
            "postaKodu" => "",
            "tel" => "",
            "fax" => "",
            "eposta" => "",
            "websitesi" => "",
            "iadeTable" => [],
            "ozelMatrahTutari" => "0",
            "ozelMatrahOrani" => 0,
            "ozelMatrahVergiTutari" => "0",
            "vergiCesidi" => " ",
            "malHizmetTable" => [],
            "tip" => "İskonto",
            "matrah" => round($invoice_details['grandTotal'], 2),
            "malhizmetToplamTutari" => round($invoice_details['grandTotal'], 2),
            "toplamIskonto" => "0",
            "hesaplanankdv" => round($invoice_details['totalVAT'], 2),
            "vergilerToplami" => round($invoice_details['totalVAT'], 2),
            "vergilerDahilToplamTutar" => round($invoice_details['grandTotalInclVAT'], 2),
            "odenecekTutar" => round($invoice_details['paymentTotal'], 2),
            "not" => $this->currencyTransformerToWords($invoice_details['paymentTotal']),
            "siparisNumarasi" => "",
            "siparisTarihi" => "",
            "irsaliyeNumarasi" => "",
            "irsaliyeTarihi" => "",
            "fisNo" => "",
            "fisTarihi" => "",
            "fisSaati" => " ",
            "fisTipi" => " ",
            "zRaporNo" => "",
            "okcSeriNo" => ""
        ];

        foreach ($invoice_details['items'] as $item) {
            $invoice_data['malHizmetTable'][] = [
                "malHizmet" => $item['name'],
                "miktar" => $item['quantity'] ?? 1,
                "birim" => "C62",
                "birimFiyat" => round($item['unitPrice'], 2),
                "fiyat" => round($item['price'], 2),
                "iskontoOrani" => 0,
                "iskontoTutari" => "0",
                "iskontoNedeni" => "",
                "malHizmetTutari" => round(($item['quantity'] * $item['unitPrice']), 2),
                "kdvOrani" => round($item['VATRate'], 0),
                "vergiOrani" => 0,
                "kdvTutari" => round($item['VATAmount'], 2),
                "vergininKdvTutari" => "0"
            ];
        }
      
        $invoice = $this->runCommand(
            self::COMMANDS['create_draft_invoice'][0],
            self::COMMANDS['create_draft_invoice'][1],
            $invoice_data
        );

        return array_merge([
            "date" => $invoice_data['faturaTarihi'],
            "uuid" => $invoice_data['faturaUuid'],
        ], $invoice);
    }

    public function getAllInvoicesByDateRange($start_date, $end_date)
    {
        $invoices = $this->runCommand(
            self::COMMANDS['get_all_invoices_by_date_range'][0],
            self::COMMANDS['get_all_invoices_by_date_range'][1],
            [
                "baslangic" => $start_date,
                "bitis" => $end_date,
                "table" => []
            ]
        );
        return $invoices['data'];
    }

    public function findDraftInvoice($draft_invoice)
    {
        $drafts = $this->runCommand(
            self::COMMANDS['get_all_invoices_by_date_range'][0],
            self::COMMANDS['get_all_invoices_by_date_range'][1],
            [
                "baslangic" => $draft_invoice['date'],
                "bitis" => $draft_invoice['date'],
                "table" => []
            ]
        );

        foreach ($drafts['data'] as $item) {
            if ($item['ettn'] === $draft_invoice['uuid']) {
                return $item;
            }
        }

        return [];
    }

    public function signDraftInvoice($draft_invoice)
    {
        return $this->runCommand(
            self::COMMANDS['sign_draft_invoice'][0],
            self::COMMANDS['sign_draft_invoice'][1],
            [
                'imzalanacaklar' => [$draft_invoice]
            ]
        );
    }

    public function getInvoiceHTML($uuid, $signed = true)
    {
        $invoice = $this->runCommand(
            self::COMMANDS['get_invoice_html'][0],
            self::COMMANDS['get_invoice_html'][1],
            [
                'ettn' => $uuid,
                'onayDurumu' => $signed ? "Onaylandı" : "Onaylanmadı"
            ]
        );
        return $invoice['data'];
    }

    public function getDownloadURL($invoiceUUID, $signed = true)
    {
        $sign_status = urlencode($signed ? "Onaylandı" : "Onaylanmadı");

        return "{$this->config['base_url']}/earsiv-services/download?token={$this->config['token']}&ettn={$invoiceUUID}&belgeTip=FATURA&onayDurumu={$sign_status}&cmd=downloadResource";
    }

    public function createInvoice($invoice_details, $sign = true)
    {
        if (!isset($this->config['token']) || empty($this->config['token'])) {
            $this->getToken();
        }

        $draft_invoice = $this->createDraftInvoice($invoice_details);
        $draft_invoice_details = $this->findDraftInvoice($draft_invoice);

        if ($sign) {
            $this->signDraftInvoice($draft_invoice_details);
        }

        return [
          'uuid' => $draft_invoice['uuid'],
          'signed' => $sign
        ];
    }

    public function createInvoiceAndGetDownloadURL($args)
    {
        $invoice = $this->createInvoice($args['invoice_details'], $args['sign'] ?? true);
        return $this->getDownloadURL($invoice['uuid'], $invoice['signed']);
    }

    public function createInvoiceAndGetHTML($args)
    {
        $invoice = $this->createInvoice($args['invoice_details'], $args['sign'] ?? true);
        return $this->getInvoiceHTML($invoice['uuid'], $invoice['signed']);
    }

    public function cancelDraftInvoice($reason, $draft_invoice)
    {
        $cancel = $this->runCommand(
            self::COMMANDS['cancel_draft_invoice'][0],
            self::COMMANDS['cancel_draft_invoice'][1],
            [
                'silinecekler' => [$draft_invoice],
                'aciklama' => $reason
            ]
        );
        
        return $cancel['data'];
    }

    public function getRecipientDataByTaxIDOrTRID($tax_id_or_tr_id)
    {
        $recipient = $this->runCommand(
            self::COMMANDS['get_recipient_data_by_tax_id_or_tr_id'][0],
            self::COMMANDS['get_recipient_data_by_tax_id_or_tr_id'][1],
            [
                'vknTcknn' => $tax_id_or_tr_id
            ]
        );

        return $recipient['data'];
    }

    public function sendSignSMSCode($phone)
    {
        $sms = $this->runCommand(
            self::COMMANDS['send_sign_sms_code'][0],
            self::COMMANDS['send_sign_sms_code'][1],
            [
                "CEPTEL" => $phone,
                "KCEPTEL" => false,
                "TIP" => ""
            ]
        );

        return $sms['oid'];
    }

    public function verifySignSMSCode($sms_code, $operation_id)
    {
        $sms = $this->runCommand(
            self::COMMANDS['verify_sms_code'][0],
            self::COMMANDS['verify_sms_code'][1],
            [
                "SIFRE" => $sms_code,
                "OID" => $operation_id
            ]
        );

        return $sms['oid'];
    }

    public function getUserData()
    {
        $user = $this->runCommand(
            self::COMMANDS['get_user_data'][0],
            self::COMMANDS['get_user_data'][1],
            new \stdClass()
        );

        return [
            "taxIDOrTRID" => $user['data']['vknTckn'],
            "title" => $user['data']['unvan'],
            "name" => $user['data']['ad'],
            "surname" => $user['data']['soyad'],
            "registryNo" => $user['data']['sicilNo'],
            "mersisNo" => $user['data']['mersisNo'],
            "taxOffice" => $user['data']['vergiDairesi'],
            "fullAddress" => $user['data']['cadde'],
            "buildingName" => $user['data']['apartmanAdi'],
            "buildingNumber" => $user['data']['apartmanNo'],
            "doorNumber" => $user['data']['kapiNo'],
            "town" => $user['data']['kasaba'],
            "district" => $user['data']['ilce'],
            "city" => $user['data']['il'],
            "zipCode" => $user['data']['postaKodu'],
            "country" => $user['data']['ulke'],
            "phoneNumber" => $user['data']['telNo'],
            "faxNumber" => $user['data']['faksNo'],
            "email" => $user['data']['ePostaAdresi'],
            "webSite" => $user['data']['webSitesiAdresi'],
            "businessCenter" => $user['data']['isMerkezi']
        ];
    }

    public function updateUserData(array $user_data)
    {
        $fields = [
            "taxIDOrTRID" => 'vknTckn',
            "title" => 'unvan',
            "name" => 'ad',
            "surname" => 'soyad',
            "registryNo" => 'sicilNo',
            "mersisNo" => 'mersisNo',
            "taxOffice" => 'vergiDairesi',
            "fullAddress" => 'cadde',
            "buildingName" => 'apartmanAdi',
            "buildingNumber" => 'apartmanNo',
            "doorNumber" => 'kapiNo',
            "town" => 'kasaba',
            "district" => 'ilce',
            "city" => 'il',
            "zipCode" => 'postaKodu',
            "country" => 'ulke',
            "phoneNumber" => 'telNo',
            "faxNumber" => 'faksNo',
            "email" => 'ePostaAdresi',
            "webSite" => 'webSitesiAdresi',
            "businessCenter" => 'isMerkezi',
        ];

        $update_data = [];
        foreach ($fields as $source => $target) {
            if (isset($user_data[$source])) {
                $update_data[$target] = $user_data[$source];
            }
        }

        if (count($update_data) < 1) {
            return;
        }
        
        $user = $this->runCommand(
            self::COMMANDS['update_user_data'][0],
            self::COMMANDS['update_user_data'][1],
            $update_data
        );

        return $user['data'];
    }
}
