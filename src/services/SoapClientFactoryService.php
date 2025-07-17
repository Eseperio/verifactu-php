<?php

declare(strict_types=1);

namespace eseperio\verifactu\services;

/**
 * Service responsible for creating and configuring SOAP clients
 * for communication with AEAT VERI*FACTU endpoints.
 */
class SoapClientFactoryService
{
    /**
     * Creates a configured SoapClient instance for a given endpoint and certificate.
     *
     * @param string $wsdl   The WSDL URL for the AEAT service (production or test)
     * @param string $certPath Path to the X.509 certificate (PEM or PFX)
     * @param string $certPassword Certificate password (if any)
     * @param array $options  Additional SoapClient options (optional)
     * @throws \RuntimeException
     */
    public static function createSoapClient($wsdl, $certPath, $certPassword = '', $options = []): \SoapClient
    {
        // Detect sandbox mode by checking if $wsdl is a local file
        $isLocalWsdl = file_exists($wsdl);
        if ($isLocalWsdl) {
            // En sandbox, forzamos el uso del WSDL local
            $wsdl = realpath($wsdl);
        }

        if (!file_exists($certPath)) {
            throw new \RuntimeException("Certificate file not found: $certPath");
        }

        $defaultOptions = [
            'trace' => 1,
            'exceptions' => true,
            'local_cert' => $certPath,
            'passphrase' => $certPassword,
            'cache_wsdl' => WSDL_CACHE_NONE,
            // 'connection_timeout' => 30, // uncomment if needed
        ];

        // Merge with user-provided options
        $soapOptions = array_merge($defaultOptions, $options);

        try {
            return new \SoapClient($wsdl, $soapOptions);
        } catch (\Exception $e) {
            throw new \RuntimeException('Unable to create SoapClient: ' . $e->getMessage(), 0, $e);
        }
    }
}
