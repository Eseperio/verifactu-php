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
            // Return as is
            return $certContent;
        }

        if ($ext === 'pfx' || $ext === 'p12') {
            // Convert PFX to PEM (try PHP first, fallback to CLI with -legacy when needed)
            $certs = self::readPkcs12($certPath, $certPassword);
            return ($certs['cert'] ?? '') . ($certs['pkey'] ?? '');
        }
        else {
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
                throw new \RuntimeException('Unable to extract private key from PEM.');
            }
            // Export private key to string
            openssl_pkey_export($key, $outKey, $certPassword);
            return $outKey;
        }

        if ($ext === 'pfx' || $ext === 'p12') {
            // Try PHP first, fallback to CLI if needed (legacy/RC2-40)
            $certs = self::readPkcs12($certPath, $certPassword);
            return $certs['pkey'] ?? null;
        }
        else {
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
     *
     * @param string $certPath
     * @param string $password
     * @return array{cert?: string, pkey?: string}
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
        while (openssl_error_string() !== false) {}

        $certs = [];
        if (@openssl_pkcs12_read($certContent, $certs, $password)) {
            $out = $certs;
            return true;
        }

        // Common failure on OpenSSL 3 when legacy ciphers (RC2-40) are disabled
        // Keep it silent here; the caller will try CLI fallback.
        return false;
    }

    /**
     * Read PKCS#12 via openssl CLI, using -legacy if CLI is >= 3.
     * Password is supplied via stdin (fd:0) to avoid leaking it via argv.
     *
     * @return array{cert?: string, pkey?: string}
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
            '-clcerts',
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

            $stdout = stream_get_contents($pipes[1]); fclose($pipes[1]);
            $stderr = stream_get_contents($pipes[2]); fclose($pipes[2]);

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
            '-clcerts',
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

            $stdout = stream_get_contents($pipes[1]); fclose($pipes[1]);
            $stderr = stream_get_contents($pipes[2]); fclose($pipes[2]);

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
     * Parse PEM bundle to extract certificate and private key.
     *
     * @param string $pem
     * @return array{cert?: string, pkey?: string}
     */
    private static function parsePemBundle(string $pem): array
    {
        $out = [];

        // Capture first certificate
        if (preg_match('/-----BEGIN CERTIFICATE-----(.*?)-----END CERTIFICATE-----/s', $pem, $m)) {
            $out['cert'] = "-----BEGIN CERTIFICATE-----" . $m[1] . "-----END CERTIFICATE-----\n";
        }

        // Capture private key (PKCS#8 or traditional)
        if (preg_match('/-----BEGIN (?:ENCRYPTED )?PRIVATE KEY-----(.*?)-----END (?:ENCRYPTED )?PRIVATE KEY-----/s', $pem, $m)) {
            $out['pkey'] = "-----BEGIN PRIVATE KEY-----" . $m[1] . "-----END PRIVATE KEY-----\n";
        } elseif (preg_match('/-----BEGIN RSA PRIVATE KEY-----(.*?)-----END RSA PRIVATE KEY-----/s', $pem, $m)) {
            $out['pkey'] = "-----BEGIN RSA PRIVATE KEY-----" . $m[1] . "-----END RSA PRIVATE KEY-----\n";
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
}
