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
    public static function createSoapClient($wsdl, $certPath, $certPassword = '', $options = [], $environment = null): \SoapClient
    {
        // Inicializamos $environment si no está definido
        if (!isset($environment)) {
            $environment = null;
        }
        // Detect environment from argument or config
        if ($environment === null && class_exists('eseperio\\verifactu\\services\\VerifactuService')) {
            // Intentamos obtener el entorno de la configuración global
            $configClass = 'eseperio\\verifactu\\services\\VerifactuService';
            if (method_exists($configClass, 'getConfig')) {
                $env = null;
                try {
                    $env = $configClass::getConfig('environment');
                } catch (\Throwable $e) {
                    // Si no está definido, seguimos con null
                }
                if ($env !== null) {
                    $environment = $env;
                }
            }
        }

        // Selección del WSDL según entorno
        if ($environment === 'sandbox') {
            // Forzamos el WSDL local para sandbox
            $wsdl = realpath(__DIR__ . '/../../docs/aeat/SistemaFacturacion.wsdl.xml');
        }

        // Si el certificado es una ruta válida, comprobar existencia. Si es contenido, permitir string.
        $isFile = is_string($certPath) && file_exists($certPath);
        $isString = is_string($certPath) && !file_exists($certPath);
        if (!$isFile && !$isString) {
            throw new \RuntimeException("Certificate not found or invalid: $certPath");
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

    /**
     * Returns the WSDL path or URL that would be used for the given environment.
     * For testing purposes only.
     */
    public static function getWsdlForTest($wsdl, $environment)
    {
        if ($environment === 'sandbox') {
            return realpath(__DIR__ . '/../../docs/aeat/SistemaFacturacion.wsdl.xml');
        }
        return $wsdl;
    }
}
