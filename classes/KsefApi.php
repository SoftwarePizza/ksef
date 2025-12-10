<?php

class KsefApi
{
    private $baseUrl;
    private $nip;
    private $token;

    public function __construct($isLive, $nip, $token)
    {
        $this->baseUrl = $isLive ? 'https://ksef.mf.gov.pl/api' : 'https://ksef-demo.mf.gov.pl/api';
        $this->nip = $nip;
        $this->token = $token;
    }

    public function getSessionToken()
    {
        // 1. Get Challenge
        $challengeData = $this->getChallenge();
        $challenge = $challengeData['challenge'];
        $timestamp = $challengeData['timestamp'];

        // 2. Get Public Key
        $publicKey = $this->getPublicKey();

        // 3. Encrypt Token
        $timestampMs = strtotime($timestamp) * 1000;
        $message = $this->token . '|' . $timestampMs;
        
        $encryptedToken = $this->encryptToken($message, $publicKey);

        // 4. Authenticate (Init Session)
        return $this->initSession($challenge, $encryptedToken);
    }

    public function testConnection()
    {
        try {
            $sessionToken = $this->getSessionToken();
            return ['success' => true, 'message' => 'Connected successfully. Session Token: ' . substr($sessionToken, 0, 10) . '...'];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
        }
    }

    public function sendInvoice($xml, $sessionToken)
    {
        $url = $this->baseUrl . '/online/Invoice/Send';
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_PUT, true);
        
        // Prepare stream for PUT
        $stream = fopen('php://memory', 'r+');
        fwrite($stream, $xml);
        rewind($stream);
        
        curl_setopt($ch, CURLOPT_INFILE, $stream);
        curl_setopt($ch, CURLOPT_INFILESIZE, strlen($xml));
        
        $headers = [
            'Content-Type: application/octet-stream',
            'Accept: application/json',
            'SessionToken: ' . $sessionToken
        ];
        
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        if (curl_errno($ch)) {
            throw new Exception('Curl error: ' . curl_error($ch));
        }
        
        curl_close($ch);
        fclose($stream);

        $responseData = json_decode($response, true);

        if ($httpCode >= 400) {
            $msg = isset($responseData['exception']['serviceException']['message']) 
                ? $responseData['exception']['serviceException']['message'] 
                : (isset($responseData['exception']['exceptionDescription']) ? $responseData['exception']['exceptionDescription'] : 'Unknown error');
            throw new Exception('API Error (' . $httpCode . '): ' . $msg);
        }

        return $responseData;
    }

    private function getChallenge()
    {
        $url = $this->baseUrl . '/online/Session/AuthorisationChallenge';
        $data = [
            'contextIdentifier' => [
                'type' => 'onip',
                'identifier' => $this->nip
            ]
        ];

        $response = $this->request('POST', $url, $data);
        return [
            'challenge' => $response['challenge'],
            'timestamp' => $response['timestamp']
        ];
    }

    private function getPublicKey()
    {
        // Use the general public key endpoint
        $url = $this->baseUrl . '/online/General/PublicKey'; 
        // Note: If this fails, we might need to check if there's a specific one for the context, but this is standard.
        // Fallback to v2 if needed, but let's try online first.
        // Actually, let's use the one that worked before if we are unsure.
        // The previous one was /v2/security/public-key-certificates.
        // But since we are switching to /online/..., let's try to be consistent.
        // However, to be safe, I'll use the one I know exists in the docs or previous code.
        // Let's stick to /v2/security/public-key-certificates as it provides the key for encryption.
        
        $url = $this->baseUrl . '/v2/security/public-key-certificates';
        
        $response = $this->request('GET', $url);
        
        if (isset($response['publicKeyCertificates'])) {
             foreach ($response['publicKeyCertificates'] as $cert) {
                 return $cert['certificate'];
             }
        }
        
        if (is_array($response) && isset($response[0]['certificate'])) {
            return $response[0]['certificate'];
        }

        throw new Exception('Could not retrieve public key.');
    }

    private function encryptToken($message, $publicKeyContent)
    {
        if (strpos($publicKeyContent, 'BEGIN CERTIFICATE') === false) {
            $publicKeyContent = "-----BEGIN CERTIFICATE-----\n" . chunk_split($publicKeyContent, 64, "\n") . "-----END CERTIFICATE-----";
        }

        $publicKey = openssl_pkey_get_public($publicKeyContent);
        if (!$publicKey) {
            throw new Exception('Invalid public key.');
        }

        $encrypted = '';
        if (!openssl_public_encrypt($message, $encrypted, $publicKey, OPENSSL_PKCS1_OAEP_PADDING)) {
             throw new Exception('Encryption failed: ' . openssl_error_string());
        }

        return base64_encode($encrypted);
    }

    private function initSession($challenge, $encryptedToken)
    {
        $url = $this->baseUrl . '/online/Session/InitToken';
        $data = [
            'contextIdentifier' => [
                'type' => 'onip',
                'identifier' => $this->nip
            ],
            'challenge' => $challenge,
            'token' => $encryptedToken
        ];

        $response = $this->request('POST', $url, $data);
        
        if (isset($response['sessionToken']['token'])) {
            return $response['sessionToken']['token'];
        }
        
        throw new Exception('Could not retrieve Session Token from InitToken response.');
    }

    private function request($method, $url, $data = null, $customHeaders = [])
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        
        $headers = array_merge([
            'Content-Type: application/json',
            'Accept: application/json'
        ], $customHeaders);
        
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            if ($data) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            }
        } elseif ($method === 'GET') {
            curl_setopt($ch, CURLOPT_HTTPGET, true);
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        if (curl_errno($ch)) {
            throw new Exception('Curl error: ' . curl_error($ch));
        }
        
        curl_close($ch);

        $responseData = json_decode($response, true);

        if ($httpCode >= 400) {
            $msg = isset($responseData['exception']['serviceException']['message']) 
                ? $responseData['exception']['serviceException']['message'] 
                : (isset($responseData['exception']['exceptionDescription']) ? $responseData['exception']['exceptionDescription'] : 'Unknown error');
            throw new Exception('API Error (' . $httpCode . '): ' . $msg);
        }

        return $responseData;
    }
}
