<?php

declare(strict_types=1);

class LusopayApiClient
{
    private const SOAP_PROD_URL = 'https://services.lusopay.com/PaymentServices/PaymentServices.svc?wsdl';
    private const SOAP_TEST_URL = 'https://services.lusopay.com/PaymentServices_test/PaymentServices.svc?wsdl';

    private const PISP_PROD_BASE_URL = 'https://app.lusopay.com:8443/web/run/PISP';
    private const PISP_TEST_BASE_URL = 'http://185.15.20.221:8080/web_dev/run/PISP';

    private const COFIDIS_URL = 'https://services.lusopay.com/Cofidispay/cofidisweb.php';
    private const APPLEPAY_URL = 'https://services.lusopay.com/Applepay/zweb.php';

    private $clientGuid;
    private $vatNumber;
    private $timeout;
    private $verifySsl;

    public function __construct(string $clientGuid, string $vatNumber, array $options = [])
    {
        $this->clientGuid = $clientGuid;
        $this->vatNumber = $vatNumber;
        $this->timeout = isset($options['timeout']) ? (int) $options['timeout'] : 30;
        $this->verifySsl = isset($options['verify_ssl']) ? (bool) $options['verify_ssl'] : true;
    }

    public function generateMultibancoReference($orderId, $amount, bool $sendEmail = false): array
    {
        return $this->generateDynamicReference('MB', (string) $orderId, $amount, $sendEmail);
    }

    public function generatePayshopReference($orderId, $amount, bool $sendEmail = false): array
    {
        return $this->generateDynamicReference('PS', (string) $orderId, $amount, $sendEmail);
    }

    public function generateTimeLimitedMultibancoReference(
        $orderId,
        $amount,
        string $entity,
        string $paymentBeginDate,
        string $paymentLimitDate
    ): array {
        $formattedAmount = $this->formatAmount($amount);
        $soapUrl = $this->getSoapUrlByEntity($entity);

        $xml = '<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:tem="http://tempuri.org/">'
            . '<soapenv:Header/>'
            . '<soapenv:Body>'
            . '<tem:generateTimeLimitedDynamicReference>'
            . '<tem:clientToken>' . htmlspecialchars($this->clientGuid, ENT_XML1) . '</tem:clientToken>'
            . '<tem:entity>' . htmlspecialchars($entity, ENT_XML1) . '</tem:entity>'
            . '<tem:externalDescription>' . htmlspecialchars((string) $orderId, ENT_XML1) . '</tem:externalDescription>'
            . '<tem:paymentLimitDate>' . htmlspecialchars($paymentLimitDate, ENT_XML1) . '</tem:paymentLimitDate>'
            . '<tem:maximumAmount>' . $formattedAmount . '</tem:maximumAmount>'
            . '<tem:paymentBeginDate>' . htmlspecialchars($paymentBeginDate, ENT_XML1) . '</tem:paymentBeginDate>'
            . '<tem:minimumAmout>' . $formattedAmount . '</tem:minimumAmout>'
            . '<tem:externalDocumentNumber>' . htmlspecialchars((string) $orderId, ENT_XML1) . '</tem:externalDocumentNumber>'
            . '</tem:generateTimeLimitedDynamicReference>'
            . '</soapenv:Body>'
            . '</soapenv:Envelope>';

        $response = $this->requestSoap(
            $soapUrl,
            'http://tempuri.org/IPaymentServices/generateTimeLimitedDynamicReference',
            $xml,
            30
        );

        $body = $response['body'];

        return [
            'reference' => $this->extractXmlValue($body, 'GeneratedReference'),
            'entity' => $this->extractXmlValue($body, 'Entity'),
            'message' => $this->extractXmlValue($body, 'text'),
            'raw' => $body,
            'status_code' => $response['status_code'],
        ];
    }

    public function sendMbWayRequest(
        $orderId,
        $amount,
        string $phoneNumber,
        bool $sendEmail = false
    ): array {
        $formattedAmount = $this->formatAmount($amount);
        $soapUrl = $this->getSoapUrlByVatNumber();

        $xml = '<?xml version="1.0" encoding="utf-8"?>'
            . '<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:tem="http://tempuri.org/" xmlns:pay="http://schemas.datacontract.org/2004/07/PaymentServices">'
            . '<soapenv:Body>'
            . '<tem:sendMBWayRequest>'
            . '<tem:clientGuid>' . htmlspecialchars($this->clientGuid, ENT_XML1) . '</tem:clientGuid>'
            . '<tem:vatNumber>' . htmlspecialchars($this->vatNumber, ENT_XML1) . '</tem:vatNumber>'
            . '<tem:cellPhoneNumber>' . htmlspecialchars($phoneNumber, ENT_XML1) . '</tem:cellPhoneNumber>'
            . '<tem:amount>' . $formattedAmount . '</tem:amount>'
            . '<tem:externalReference>' . htmlspecialchars((string) $orderId, ENT_XML1) . '</tem:externalReference>'
            . '<tem:sendEmail>' . ($sendEmail ? 'true' : 'false') . '</tem:sendEmail>'
            . '</tem:sendMBWayRequest>'
            . '</soapenv:Body>'
            . '</soapenv:Envelope>';

        $response = $this->requestSoap(
            $soapUrl,
            'http://tempuri.org/IPaymentServices/sendMBWayRequest',
            $xml,
            30
        );

        $body = $response['body'];

        return [
            'merchantOperationID' => $this->extractXmlValue($body, 'merchantOperationID'),
            'statusCode' => $this->extractXmlValue($body, 'statusCode'),
            'statusMessage' => $this->extractXmlValue($body, 'statusMessage'),
            'timeStamp' => $this->extractXmlValue($body, 'timeStamp'),
            'token' => $this->extractXmlValue($body, 'token'),
            'message' => $this->extractXmlValue($body, 'message'),
            'raw' => $body,
            'status_code' => $response['status_code'],
        ];
    }

    public function createPispPayment(
        $orderId,
        $amount,
        string $currency = 'EUR',
        string $language = 'pt',
        bool $retryWithNull = true
    ): array {
        $formattedAmount = $this->formatAmount($amount);
        $base = $this->getPispBaseUrlByVatNumber();

        $segments = [
            rawurlencode($this->clientGuid),
            rawurlencode($formattedAmount),
            rawurlencode($currency),
            rawurlencode((string) $orderId),
            rawurlencode(strtolower(substr($language, 0, 2))),
        ];

        $url = $base . '/' . implode('/', $segments);
        $response = $this->requestHttp('POST', $url, null, 30);

        if ($retryWithNull && $response['status_code'] === 404) {
            $url .= '/null';
            $response = $this->requestHttp('POST', $url, null, 30);
        }

        return [
            'url' => $url,
            'status_code' => $response['status_code'],
            'body' => $response['body'],
        ];
    }

    public function createCofidisPayment(
        $orderId,
        $amount,
        string $username,
        string $password,
        string $reference,
        string $paidRequest,
        string $returnUrl,
        string $callbackUrl
    ): array {
        $formattedAmount = $this->formatAmount($amount);

        $query = http_build_query([
            'username' => $username,
            'password' => $password,
            'orderid' => (string) $orderId,
            'value' => $formattedAmount,
            'returnURL' => preg_replace('/^https?:\/\//', '', $returnUrl),
            'paidrequest' => $paidRequest,
            'clientguid' => $this->clientGuid,
            'callbackurl' => $callbackUrl,
            'reference' => $reference,
        ]);

        $url = self::COFIDIS_URL . '?' . $query;
        $response = $this->requestHttp('GET', $url, null, 30);
        $decoded = json_decode($response['body'], true);

        return [
            'url' => $url,
            'status_code' => $response['status_code'],
            'resposta' => is_array($decoded) ? ($decoded['resposta'] ?? null) : null,
            'raw' => $response['body'],
        ];
    }

    public function createApplePayPayment(
        $orderId,
        $amount,
        string $username,
        string $password,
        string $reference,
        string $paidRequest,
        string $returnUrl,
        string $callbackUrl
    ): array {
        $formattedAmount = $this->formatAmount($amount);

        $query = http_build_query([
            'username' => $username,
            'password' => $password,
            'orderid' => (string) $orderId,
            'value' => $formattedAmount,
            'returnURL' => preg_replace('/^https?:\/\//', '', $returnUrl),
            'paidrequest' => $paidRequest,
            'clientguid' => $this->clientGuid,
            'callbackurl' => $callbackUrl,
            'reference' => $reference,
        ]);

        $url = self::APPLEPAY_URL . '?' . $query;
        $response = $this->requestHttp('GET', $url, null, 30);
        $decoded = json_decode($response['body'], true);

        return [
            'url' => $url,
            'status_code' => $response['status_code'],
            'resposta' => is_array($decoded) ? ($decoded['resposta'] ?? null) : null,
            'raw' => $response['body'],
        ];
    }

    private function generateDynamicReference(string $serviceType, string $orderId, $amount, bool $sendEmail): array
    {
        $formattedAmount = $this->formatAmount($amount);
        $soapUrl = $this->getSoapUrlByVatNumber();

        $xml = '<?xml version="1.0" encoding="utf-8"?>'
            . '<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:tem="http://tempuri.org/" xmlns:pay="http://schemas.datacontract.org/2004/07/PaymentServices">'
            . '<soapenv:Body>'
            . '<tem:getNewDynamicReference>'
            . '<tem:clientGuid>' . htmlspecialchars($this->clientGuid, ENT_XML1) . '</tem:clientGuid>'
            . '<tem:vatNumber>' . htmlspecialchars($this->vatNumber, ENT_XML1) . '</tem:vatNumber>'
            . '<tem:valueList>'
            . '<pay:References>'
            . '<pay:amount>' . $formattedAmount . '</pay:amount>'
            . '<pay:description>' . htmlspecialchars($orderId, ENT_XML1) . '</pay:description>'
            . '<pay:serviceType>' . $serviceType . '</pay:serviceType>'
            . '</pay:References>'
            . '</tem:valueList>'
            . '<tem:sendEmail>' . ($sendEmail ? 'true' : 'false') . '</tem:sendEmail>'
            . '</tem:getNewDynamicReference>'
            . '</soapenv:Body>'
            . '</soapenv:Envelope>';

        $response = $this->requestSoap(
            $soapUrl,
            'http://tempuri.org/IPaymentServices/getNewDynamicReference',
            $xml,
            60
        );

        $body = $response['body'];

        return [
            'referenceMB' => $this->extractXmlValue($body, 'referenceMB'),
            'entityMB' => $this->extractXmlValue($body, 'entityMB'),
            'referencePS' => $this->extractXmlValue($body, 'referencePS'),
            'message' => $this->extractXmlValue($body, 'message'),
            'raw' => $body,
            'status_code' => $response['status_code'],
        ];
    }

    private function getSoapUrlByVatNumber(): string
    {
        return $this->vatNumber === '999999999' ? self::SOAP_TEST_URL : self::SOAP_PROD_URL;
    }

    private function getSoapUrlByEntity(string $entity): string
    {
        return $entity === '12345' ? self::SOAP_TEST_URL : self::SOAP_PROD_URL;
    }

    private function getPispBaseUrlByVatNumber(): string
    {
        return $this->vatNumber === '999999999' ? self::PISP_TEST_BASE_URL : self::PISP_PROD_BASE_URL;
    }

    private function formatAmount($amount): string
    {
        return number_format((float) $amount, 2, '.', '');
    }

    private function extractXmlValue(string $xml, string $tag): ?string
    {
        $pattern = '/<(?:[A-Za-z0-9_]+:)?' . preg_quote($tag, '/') . '>(.*?)<\/(?:[A-Za-z0-9_]+:)?' . preg_quote($tag, '/') . '>/s';
        if (preg_match($pattern, $xml, $matches) === 1) {
            return trim(html_entity_decode($matches[1], ENT_QUOTES | ENT_XML1, 'UTF-8'));
        }

        return null;
    }

    private function requestSoap(string $url, string $soapAction, string $xmlBody, int $timeout): array
    {
        $headers = [
            'Content-Type: text/xml;charset="utf-8"',
            'Accept: text/xml',
            'Cache-Control: no-cache',
            'Pragma: no-cache',
            'SOAPAction: ' . $soapAction,
            'Content-Length: ' . strlen($xmlBody),
        ];

        return $this->requestHttp('POST', $url, $xmlBody, $timeout, $headers);
    }

    private function requestHttp(string $method, string $url, ?string $body, int $timeout, array $headers = []): array
    {
        $ch = curl_init();
        if ($ch === false) {
            throw new RuntimeException('Não foi possível inicializar cURL.');
        }

        $effectiveTimeout = $timeout > 0 ? $timeout : $this->timeout;
        $connectTimeout = $effectiveTimeout > 15 ? 15 : $effectiveTimeout;

        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => $effectiveTimeout,
            CURLOPT_CONNECTTIMEOUT => $connectTimeout,
            CURLOPT_SSL_VERIFYPEER => $this->verifySsl,
            CURLOPT_SSL_VERIFYHOST => $this->verifySsl ? 2 : 0,
            CURLOPT_CUSTOMREQUEST => strtoupper($method),
        ]);

        if (defined('CURLOPT_IPRESOLVE') && defined('CURL_IPRESOLVE_V4')) {
            curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
        }

        if (!empty($headers)) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        }

        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }

        $rawBody = curl_exec($ch);
        $errorNumber = curl_errno($ch);
        $errorMessage = curl_error($ch);
        $statusCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($rawBody === false) {
            if ($errorNumber === 28) {
                throw new RuntimeException(
                    'Timeout na chamada Lusopay (' . $effectiveTimeout . 's). URL: ' . $url
                );
            }
            throw new RuntimeException('Erro na chamada Lusopay: ' . $errorMessage);
        }

        return [
            'status_code' => $statusCode,
            'body' => (string) $rawBody,
        ];
    }
}
