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
     * @param string $wsdl The WSDL URL for the AEAT service (production or test)
     * @param string $certPath Path to the X.509 certificate (PEM or PFX)
     * @param string $certPassword Certificate password (if any)
     * @param array $options Additional SoapClient options (optional)
     * @throws \RuntimeException
     */
    public static function createSoapClient(string $wsdl, string $certPath, string $certPassword = '', array $options = []): \SoapClient
    {
        if (!file_exists($certPath)) {
            throw new \RuntimeException("Certificate file not found: $certPath");
        }

        $defaultOptions = [
            'trace' => 1,
            'exceptions' => true,
            'local_cert' => $certPath,
            'passphrase' => $certPassword,
            'cache_wsdl' => WSDL_CACHE_NONE,
        ];

        // Merge with user-provided options
        $soapOptions = array_merge($defaultOptions, $options);

        try {
            // If WSDL is a local file path, prefix with file:// to help libxml resolve relative imports
            $wsdlPath = $wsdl;
            if (is_string($wsdl) && file_exists($wsdl) && !str_starts_with($wsdl, 'file://')) {
                $real = realpath($wsdl);
                if ($real !== false) {
                    $wsdlPath = 'file://' . $real;
                }
            }
            return new \SoapClient($wsdlPath, $soapOptions);
        } catch (\Exception $e) {
            throw new \RuntimeException('Unable to create SoapClient: ' . $e->getMessage(), 0, $e);
        }
    }
}
