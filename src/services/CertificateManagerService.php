<?php
namespace eseperio\verifactu\services;

/**
 * Service responsible for securely loading, validating, and exposing
 * the digital certificate and private key used for signing and authentication.
 */
class CertificateManagerService
{
    /**
     * Loads and returns the X.509 certificate contents.
     *
     * @param string $certPath Path to the certificate file (PEM or PFX)
     * @param string $certPassword Certificate password (if required)
     * @return string Certificate contents (PEM format)
     * @throws \RuntimeException
     */
    public static function getCertificate($certPath, $certPassword = '')
    {
        if (!file_exists($certPath)) {
            throw new \RuntimeException("Certificate file not found: $certPath");
        }

        $ext = strtolower(pathinfo($certPath, PATHINFO_EXTENSION));
        $certContent = file_get_contents($certPath);

        if ($ext === 'pem') {
            // Return as is
            return $certContent;
        } elseif ($ext === 'pfx' || $ext === 'p12') {
            // Convert PFX to PEM
            $certs = [];
            if (!openssl_pkcs12_read($certContent, $certs, $certPassword)) {
                throw new \RuntimeException("Unable to read PFX/P12 certificate. Wrong password?");
            }
            // Return certificate and private key in PEM format
            return $certs['cert'] . (isset($certs['pkey']) ? $certs['pkey'] : '');
        } else {
            throw new \RuntimeException("Unsupported certificate format: $ext");
        }
    }

    /**
     * Loads and returns the private key from the certificate.
     *
     * @param string $certPath Path to the certificate file (PEM or PFX)
     * @param string $certPassword Certificate password (if required)
     * @return string Private key in PEM format
     * @throws \RuntimeException
     */
    public static function getPrivateKey($certPath, $certPassword = '')
    {
        $ext = strtolower(pathinfo($certPath, PATHINFO_EXTENSION));
        $certContent = file_get_contents($certPath);

        if ($ext === 'pem') {
            // Extract private key from PEM
            $key = openssl_pkey_get_private($certContent, $certPassword);
            if (!$key) {
                throw new \RuntimeException("Unable to extract private key from PEM.");
            }
            // Export private key to string
            openssl_pkey_export($key, $outKey, $certPassword);
            return $outKey;
        } elseif ($ext === 'pfx' || $ext === 'p12') {
            $certs = [];
            if (!openssl_pkcs12_read($certContent, $certs, $certPassword)) {
                throw new \RuntimeException("Unable to read PFX/P12 certificate. Wrong password?");
            }
            return $certs['pkey'];
        } else {
            throw new \RuntimeException("Unsupported certificate format: $ext");
        }
    }

    /**
     * Checks if a certificate is valid (by expiration).
     *
     * @param string $certPath Path to the certificate file
     * @param string $certPassword Certificate password
     * @return bool
     */
    public static function isValid($certPath, $certPassword = '')
    {
        $cert = self::getCertificate($certPath, $certPassword);
        $parsed = openssl_x509_parse($cert);
        if (!$parsed) {
            return false;
        }
        $now = time();
        return $parsed['validFrom_time_t'] <= $now && $now <= $parsed['validTo_time_t'];
    }
}
