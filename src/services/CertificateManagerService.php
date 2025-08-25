<?php

declare(strict_types=1);

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
    public static function getCertificate($certPath, $certPassword = ''): string|false
    {
        if (!file_exists($certPath)) {
            throw new \RuntimeException("Certificate file not found: $certPath");
        }

        $ext = strtolower(pathinfo($certPath, PATHINFO_EXTENSION));
        $certContent = file_get_contents($certPath);
        if ($ext === 'pem') {
            // Only extract the X.509 certificate block from PEM
            if (preg_match('/-----BEGIN CERTIFICATE-----.*?-----END CERTIFICATE-----/s', $certContent, $m)) {
                return $m[0] . (str_ends_with($m[0], "\n") ? '' : "\n");
            }
            throw new \RuntimeException('No X.509 certificate found in PEM file.');
        }

        if ($ext === 'pfx' || $ext === 'p12') {
            // Convertir PFX a PEM (intenta PHP, hace fallback a CLI con -legacy si hace falta)
            $certs = self::readPkcs12($certPath, $certPassword);
            if (empty($certs['cert']) && empty($certs['pkey'])) {
                throw new \RuntimeException('Unable to read PFX/P12 certificate. Check password or OpenSSL legacy support for RC2-40.');
            }
            return $certs['cert'];
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
            // Detect if PEM private key is encrypted
            $isEncrypted =
                (strpos($certContent, '-----BEGIN ENCRYPTED PRIVATE KEY-----') !== false) ||
                (preg_match('/-----BEGIN RSA PRIVATE KEY-----.*?DEK-Info:/s', $certContent) === 1) ||
                (preg_match('/Proc-Type:\s*4,ENCRYPTED/i', $certContent) === 1);

            if ($isEncrypted && ($certPassword === '' || $certPassword === null)) {
                throw new \RuntimeException('PEM private key is encrypted. A password is required.');
            }

            // Extraer la clave privada (soporta cifrado con $certPassword)
            $key = @openssl_pkey_get_private($certContent, (string)$certPassword);
            if (!$key) {
                $err = openssl_error_string() ?: 'Unable to extract private key from PEM.';
                throw new \RuntimeException($err);
            }
            // Exportar la clave privada a string (si $certPassword vacío => sin cifrar)
            $outKey = null;
            if (!@openssl_pkey_export($key, $outKey, $certPassword ?: null)) {
                $err = openssl_error_string() ?: 'Unable to export private key.';
                throw new \RuntimeException($err);
            }
            return $outKey;
        }

        if ($ext === 'pfx' || $ext === 'p12') {
            // PHP primero, fallback a CLI si hace falta (legacy/RC2-40)
            $certs = self::readPkcs12($certPath, $certPassword);
            return $certs['pkey'] ?? null;
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

    /**
     * Try to read PKCS#12 (PFX/P12) using PHP OpenSSL; if it fails (e.g. OpenSSL 3 without legacy),
     * fallback to system openssl CLI adding -legacy when the CLI is >= 3.x.
     * Returns leaf certificate, private key, and optional chain certificates.
     *
     * @param string $certPath
     * @param string $password
     * @return array{cert?: string, pkey?: string, chain?: string[]}
     */
    private static function readPkcs12(string $certPath, string $password): array
    {
        $certContent = file_get_contents($certPath);

        // 1) First try with PHP OpenSSL
        $phpResult = [];
        if (self::pkcs12ReadWithPhp($certContent, $password, $phpResult)) {
            return $phpResult;
        }

        // 2) Fallback to CLI openssl (with -legacy when CLI is 3.x)
        $cliResult = self::pkcs12ReadWithCli($certPath, $password);
        if (!empty($cliResult)) {
            return $cliResult;
        }

        throw new \RuntimeException('Unable to read PFX/P12 certificate. Check password or OpenSSL legacy support for RC2-40.');
    }

    /**
     * Read PKCS#12 via PHP OpenSSL.
     */
    private static function pkcs12ReadWithPhp(string $certContent, string $password, array &$out): bool
    {
        // Clear previous OpenSSL errors
        while (openssl_error_string() !== false) {
        }

        $certs = [];
        if (@openssl_pkcs12_read($certContent, $certs, $password)) {
            // Normalize to expected keys: cert, pkey, chain[]
            $out = [];
            if (!empty($certs['cert'])) {
                $out['cert'] = is_string($certs['cert']) ? $certs['cert'] : (string)$certs['cert'];
            }
            if (!empty($certs['pkey'])) {
                $out['pkey'] = is_string($certs['pkey']) ? $certs['pkey'] : (string)$certs['pkey'];
            }
            if (!empty($certs['extracerts']) && is_array($certs['extracerts'])) {
                $chain = [];
                foreach ($certs['extracerts'] as $c) {
                    if (is_string($c)) {
                        $chain[] = $c;
                    }
                }
                if ($chain) {
                    $out['chain'] = $chain;
                }
            }
            return true;
        }

        // Common failure on OpenSSL 3 when legacy ciphers (RC2-40) are disabled
        // Keep it silent here; the caller will try CLI fallback.
        return false;
    }

    /**
     * Read PKCS#12 via openssl CLI, using -legacy if CLI is >= 3.
     * Password is supplied via env to avoid leaking it via argv.
     * Returns leaf cert + pkey + chain (other certs) when available.
     *
     * @return array{cert?: string, pkey?: string, chain?: string[]}
     */
    private static function pkcs12ReadWithCli(string $certPath, string $password): array
    {
        $opensslBin = getenv('OPENSSL_BIN') ?: 'openssl';
        $version = self::getOpenSSLCLIMajorVersion($opensslBin);
        $useLegacy = ($version !== null && $version >= 3);

        $args = [
            $opensslBin, 'pkcs12',
            '-in', $certPath,
            '-nodes',
        ];
        if ($useLegacy) {
            $args[] = '-legacy';
        }

        // Preferir -passin env: para compatibilidad; si la contraseña es vacía, usar pass:
        $env = is_array($_ENV) ? $_ENV : [];
        if ($password === '' || $password === null) {
            $args[] = '-passin';
            $args[] = 'pass:'; // contraseña vacía explícita
        } else {
            $args[] = '-passin';
            $args[] = 'env:OPENSSL_PASS';
            $env['OPENSSL_PASS'] = $password;
        }

        $cmd = '';
        foreach ($args as $a) {
            $cmd .= escapeshellarg($a) . ' ';
        }

        $descriptorSpec = [
            0 => ['pipe', 'w'], // stdin (no se usará)
            1 => ['pipe', 'w'], // stdout
            2 => ['pipe', 'w'], // stderr
        ];
        $proc = @proc_open($cmd, $descriptorSpec, $pipes, null, $env);
        if (!\is_resource($proc)) {
            return [];
        }

        try {
            // No escribimos nada a stdin; cerramos para no bloquear
            fclose($pipes[0]);

            $stdout = stream_get_contents($pipes[1]);
            fclose($pipes[1]);
            $stderr = stream_get_contents($pipes[2]);
            fclose($pipes[2]);

            $exitCode = proc_close($proc);

            if ($exitCode !== 0) {
                // Si -legacy no es reconocido, reintentar sin él
                if ($useLegacy && (str_contains($stderr, 'unknown option') || str_contains($stderr, 'invalid option') || str_contains($stderr, 'unrecognized option'))) {
                    return self::pkcs12ReadWithCliNoLegacy($certPath, $password, $opensslBin);
                }
                return [];
            }

            return self::parsePemBundle($stdout);
        } finally {
            if (\is_resource($proc)) {
                @proc_close($proc);
            }
        }
    }

    /**
     * Retry without -legacy (para OpenSSL 1.1.1/LibreSSL).
     */
    private static function pkcs12ReadWithCliNoLegacy(string $certPath, string $password, string $opensslBin): array
    {
        $args = [
            $opensslBin, 'pkcs12',
            '-in', $certPath,
            '-nodes',
        ];

        $env = is_array($_ENV) ? $_ENV : [];
        if ($password === '' || $password === null) {
            $args[] = '-passin';
            $args[] = 'pass:';
        } else {
            $args[] = '-passin';
            $args[] = 'env:OPENSSL_PASS';
            $env['OPENSSL_PASS'] = $password;
        }

        $cmd = '';
        foreach ($args as $a) {
            $cmd .= escapeshellarg($a) . ' ';
        }

        $descriptorSpec = [
            0 => ['pipe', 'w'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];
        $proc = @proc_open($cmd, $descriptorSpec, $pipes, null, $env);
        if (!\is_resource($proc)) {
            return [];
        }
        try {
            fclose($pipes[0]);

            $stdout = stream_get_contents($pipes[1]);
            fclose($pipes[1]);
            $stderr = stream_get_contents($pipes[2]);
            fclose($pipes[2]);

            $exitCode = proc_close($proc);
            if ($exitCode !== 0) {
                return [];
            }
            return self::parsePemBundle($stdout);
        } finally {
            if (\is_resource($proc)) {
                @proc_close($proc);
            }
        }
    }

    /**
     * Parse PEM bundle to extract leaf certificate, private key, and chain certificates.
     *
     * @param string $pem
     * @return array{cert?: string, pkey?: string, chain?: string[]}
     */
    private static function parsePemBundle(string $pem): array
    {
        $out = [];

        // Extract all certificate blocks as-is (first one assumed to be leaf cert)
        if (preg_match_all('/-----BEGIN CERTIFICATE-----.*?-----END CERTIFICATE-----/s', $pem, $m)) {
            $certs = $m[0];
            if (!empty($certs)) {
                $leaf = array_shift($certs);
                $out['cert'] = $leaf . (str_ends_with($leaf, "\n") ? '' : "\n");
                if (!empty($certs)) {
                    $chain = [];
                    foreach ($certs as $c) {
                        $chain[] = $c . (str_ends_with($c, "\n") ? '' : "\n");
                    }
                    $out['chain'] = $chain;
                }
            }
        }

        // Extract private key block, preserving ENCRYPTED PRIVATE KEY header if present
        if (preg_match('/-----BEGIN (?:ENCRYPTED )?PRIVATE KEY-----.*?-----END (?:ENCRYPTED )?PRIVATE KEY-----/s', $pem, $m)) {
            $out['pkey'] = $m[0] . (str_ends_with($m[0], "\n") ? '' : "\n");
        } elseif (preg_match('/-----BEGIN RSA PRIVATE KEY-----.*?-----END RSA PRIVATE KEY-----/s', $pem, $m)) {
            $out['pkey'] = $m[0] . (str_ends_with($m[0], "\n") ? '' : "\n");
        }

    return $out;
    }

    /**
     * Get openssl CLI major version (e.g., 1, 3) or null if unknown.
     */
    private static function getOpenSSLCLIMajorVersion(string $opensslBin): ?int
    {
        $cmd = escapeshellarg($opensslBin) . ' version';
        $out = @shell_exec($cmd);
        if (!\is_string($out) || $out === '') {
            return null;
        }
        if (preg_match('/OpenSSL\s+(\d+)(?:\.(\d+)\.\d+)?/i', $out, $m)) {
            return (int)$m[1];
        }
        return null;
    }

    /**
     * Crea un archivo PEM temporal combinando certificado, cadena (CA) y clave privada, compatible con SoapClient.
     * Devuelve la ruta absoluta al archivo temporal (con permisos 0600) y programa su eliminación al shutdown.
     *
     * @param string $certPath Ruta al certificado original (PEM o PFX/P12)
     * @param string $certPassword Contraseña del certificado (si aplica)
     * @return string Ruta al archivo PEM temporal
     * @throws \RuntimeException
     */
    public static function createSoapCompatiblePemTemp(string $certPath, string $certPassword = ''): string
    {
        if (!file_exists($certPath)) {
            throw new \RuntimeException("Certificate file not found: $certPath");
        }

        $ext = strtolower(pathinfo($certPath, PATHINFO_EXTENSION));
        $certPem = '';
        $keyPem = '';
        $chainPems = [];

        if ($ext === 'p12' || $ext === 'pfx') {
            // Leer todo del contenedor PKCS#12 en una sola pasada
            $bundle = self::readPkcs12($certPath, $certPassword);
            $certPem = $bundle['cert'] ?? '';
            $keyPem = $bundle['pkey'] ?? '';
            $chainPems = isset($bundle['chain']) && is_array($bundle['chain']) ? $bundle['chain'] : [];
        } else {
            // PEM: extraer certs (leaf + chain) y la clave
            $pemContent = file_get_contents($certPath);
            if (preg_match_all('/-----BEGIN CERTIFICATE-----.*?-----END CERTIFICATE-----/s', $pemContent, $m)) {
                $certs = $m[0];
                $certPem = array_shift($certs);
                $chainPems = $certs;
            } else {
                $certPem = self::getCertificate($certPath, $certPassword);
            }
            $keyPem = self::getPrivateKey($certPath, $certPassword);
        }

        if (!\is_string($certPem) || $certPem === '') {
            throw new \RuntimeException('Failed to extract certificate in PEM format.');
        }
        if (!\is_string($keyPem) || $keyPem === '') {
            throw new \RuntimeException('Failed to extract private key in PEM format.');
        }

        // Asegurar salto de línea final
        if (!str_ends_with($certPem, "\n")) {
            $certPem .= "\n";
        }
        foreach ($chainPems as &$c) {
            if (!str_ends_with($c, "\n")) {
                $c .= "\n";
            }
        }
        unset($c);
        if (!str_ends_with($keyPem, "\n")) {
            $keyPem .= "\n";
        }

        // Orden recomendado: leaf cert, cadena (CA) y por último clave
        $combined = $certPem . implode('', $chainPems) . $keyPem;

        // Crear archivo temporal seguro
        $base = tempnam(sys_get_temp_dir(), 'verifactu_cert_');
        if ($base === false) {
            throw new \RuntimeException('Unable to create temporary file for certificate.');
        }

        // Preferimos extensión .pem para compatibilidad
        $tmpPemPath = $base . '.pem';
        // Renombrar el archivo temporal base al .pem; si falla, usamos el base sin extensión
        if (!@rename($base, $tmpPemPath)) {
            $tmpPemPath = $base; // fallback
        }

        // Escribir contenido y fijar permisos 0600
        $bytes = @file_put_contents($tmpPemPath, $combined);
        if ($bytes === false || $bytes < strlen($combined)) {
            @unlink($tmpPemPath);
            throw new \RuntimeException('Failed to write combined PEM to temporary file.');
        }
        @chmod($tmpPemPath, 0600);

        // Programar eliminación en shutdown
        register_shutdown_function(static function () use ($tmpPemPath): void {
            if (is_file($tmpPemPath)) {
                @unlink($tmpPemPath);
            }
        });

        return $tmpPemPath;
    }
}
