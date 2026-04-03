<?php

namespace Saleh7\Zatca\Helpers;

use phpseclib3\Crypt\Common\PrivateKey;
use phpseclib3\Crypt\EC;
use phpseclib3\File\X509;

/**
 * Certificate helper class.
 *
 * Provides methods to manage and use X509 certificates.
 *
 * @mixin X509
 */
class Certificate
{
    /**
     * The raw certificate content.
     */
    protected string $rawCertificate;

    /**
     * The X509 certificate object.
     */
    protected X509 $x509;

    /**
     * The private key for this certificate.
     */
    protected PrivateKey $privateKey;

    /**
     * The secret key used for authentication.
     */
    protected string $secretKey;

    /**
     * Constructor.
     *
     * @param  string  $rawCert  The raw certificate string.
     * @param  string  $privateKeyStr  The private key string.
     * @param  string  $secretKey  The secret key.
     */
    public function __construct(string $rawCert, string $privateKeyStr, string $secretKey)
    {
        $this->secretKey = $secretKey;
        $this->rawCertificate = $rawCert;
        $this->x509 = new X509;
        $this->x509->loadX509($rawCert);
        $this->privateKey = EC::loadPrivateKey($privateKeyStr);
    }

    /**
     * Delegate method calls to the underlying X509 object.
     *
     * @param  string  $name  The method name.
     * @param  array  $arguments  The method arguments.
     * @return mixed
     */
    public function __call($name, $arguments)
    {
        return $this->x509->{$name}(...$arguments);
    }

    /**
     * Get the private key.
     */
    public function getPrivateKey(): PrivateKey
    {
        return $this->privateKey;
    }

    /**
     * Get the raw certificate content.
     */
    public function getRawCertificate(): string
    {
        return $this->rawCertificate;
    }

    public function getCleanBase64Content(): string
    {
        if (strpos($this->rawCertificate, '-----BEGIN CERTIFICATE-----') !== false) {
            if (preg_match('/-----BEGIN CERTIFICATE-----\s*(.*?)\s*-----END CERTIFICATE-----/s', $this->rawCertificate, $matches)) {
                return str_replace(["\n", "\r", " ", "\t"], '', $matches[1]);
            }
        }

        return str_replace(["\n", "\r", " ", "\t"], '', $this->rawCertificate);
    }

    /**
     * Get the X509 certificate object.
     */
    public function getX509(): X509
    {
        return $this->x509;
    }

    /**
     * Create the authorization header using the raw certificate and secret key.
     */
    public function getAuthHeader(): string
    {
        return 'Basic '.base64_encode(base64_encode($this->getRawCertificate()).':'.$this->getSecretKey());
    }

    /**
     * Get the secret key.
     */
    public function getSecretKey(): ?string
    {
        return $this->secretKey;
    }

    /**
     * Generate a hash of the certificate.
     * Historical ZATCA-compatible behavior: hash the same clean base64 certificate
     * content embedded in ds:X509Certificate, then base64-encode the hex digest.
     */
    public function getCertHash(): string
    {
        return base64_encode(hash('sha256', $this->getCleanBase64Content()));
    }

    /**
     * Get the formatted issuer details.
     *
     * ZATCA expects format: CN=..., DC=..., DC=..., DC=...
     * Example: CN=PRZEINVOICESCA4-CA, DC=extgazt, DC=gov, DC=local
     */
    public function getFormattedIssuer(): string
    {
        $issuerDN = $this->x509->getIssuerDN(X509::DN_STRING);

        // Replace OID with DC and normalize separators
        $normalized = str_replace(
            ['0.9.2342.19200300.100.1.25', '/', ', '],
            ['DC', ',', ','],
            $issuerDN
        );

        // Split into components
        $dnArray = explode(',', $normalized);

        // Trim whitespace from each component
        $dnArray = array_map('trim', $dnArray);

        // Filter out empty components
        $dnArray = array_filter($dnArray, function($v) { return !empty($v); });

        // ZATCA expects: CN first, then DC fields
        // The phpseclib returns DN in reverse order (leaf to root), so we need to reverse
        return implode(', ', array_reverse($dnArray));
    }

    /**
     * Get the raw public key in base64 format.
     */
    public function getRawPublicKey(): string
    {
        return str_replace(
            ["-----BEGIN PUBLIC KEY-----\r\n", "\r\n-----END PUBLIC KEY-----", "\r\n"],
            '',
            $this->x509->getPublicKey()->toString('PKCS8')
        );
    }

    /**
     * Get the certificate signature.
     *
     * Note: Removes an extra prefix byte from the signature.
     */
    public function getCertSignature(): string
    {
        return substr($this->getCurrentCert()['signature'], 1);
    }
}
