<?php

declare(strict_types=1);

namespace eseperio\verifactu\tests\Unit\Services;

use eseperio\verifactu\services\CertificateManagerService;
use PHPUnit\Framework\TestCase;

class CertificateManagerServiceTest extends TestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tmpDir = sys_get_temp_dir();
    }

    private function generateKeyAndCert(int $days = 1): array
    {
        $privKey = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);
        $csr = openssl_csr_new([
            'countryName' => 'ES',
            'stateOrProvinceName' => 'Madrid',
            'localityName' => 'Madrid',
            'organizationName' => 'Test Org',
            'organizationalUnitName' => 'IT',
            'commonName' => 'example.test',
            'emailAddress' => 'test@example.test',
        ], $privKey, ['digest_alg' => 'sha256']);
        $x509 = openssl_csr_sign($csr, null, $privKey, $days, ['digest_alg' => 'sha256']);

        $certPem = '';
        $keyPem = '';
        openssl_x509_export($x509, $certPem);
        openssl_pkey_export($privKey, $keyPem);

        return [$keyPem, $certPem];
    }

    public function testGetCertificateFromPemReturnsLeafOnly(): void
    {
        [$keyPem, $leafCert] = $this->generateKeyAndCert();
        // second (chain) cert
        [, $chainCert] = $this->generateKeyAndCert();

        $pemPath = $this->tmpDir . '/leaf_chain_' . uniqid() . '.pem';
        file_put_contents($pemPath, $leafCert . $chainCert . $keyPem);

    $cert = CertificateManagerService::getCertificate($pemPath);

    $this->assertIsString($cert);
    $this->assertStringContainsString('BEGIN CERTIFICATE', $cert);
    // Only the first cert should be returned and match exactly the leaf cert block
    $this->assertSame(1, substr_count($cert, 'BEGIN CERTIFICATE'));
    $expectedLeaf = rtrim($leafCert) . "\n";
    $this->assertSame($expectedLeaf, $cert);
    }

    public function testGetPrivateKeyFromPem(): void
    {
        [$keyPem, $certPem] = $this->generateKeyAndCert();
        $pemPath = $this->tmpDir . '/key_cert_' . uniqid() . '.pem';
        file_put_contents($pemPath, $certPem . $keyPem);

        $key = CertificateManagerService::getPrivateKey($pemPath);
        $this->assertIsString($key);
        $this->assertStringContainsString('BEGIN PRIVATE KEY', $key);
    }

    public function testCreateSoapCompatiblePemTempCombinesInOrder(): void
    {
        [$keyPem, $leafCert] = $this->generateKeyAndCert();
        [, $chainCert] = $this->generateKeyAndCert();

        $pemPath = $this->tmpDir . '/combo_' . uniqid() . '.pem';
        file_put_contents($pemPath, $leafCert . $chainCert . $keyPem);

        $combinedPath = CertificateManagerService::createSoapCompatiblePemTemp($pemPath);
        $this->assertFileExists($combinedPath);
        $content = file_get_contents($combinedPath);
        $this->assertIsString($content);

        // Order: leaf, chain, key
        $posLeaf = strpos($content, $leafCert);
        $posChain = strpos($content, $chainCert);
        $posKey = strpos($content, $keyPem);
        $this->assertNotFalse($posLeaf);
        $this->assertNotFalse($posChain);
        $this->assertNotFalse($posKey);
        $this->assertTrue($posLeaf < $posChain && $posChain < $posKey);

        // Permissions 0600
        $perms = fileperms($combinedPath) & 0777;
        $this->assertSame(0600, $perms, 'Combined PEM should have 0600 permissions');
    }

    public function testIsValidForFreshSelfSignedCert(): void
    {
        [, $certPem] = $this->generateKeyAndCert(1);
        $path = $this->tmpDir . '/cert_' . uniqid() . '.pem';
        file_put_contents($path, $certPem);
        $this->assertTrue(CertificateManagerService::isValid($path));
    }

    public function testGetCertificateAndKeyFromPkcs12(): void
    {
        [$keyPem, $certPem] = $this->generateKeyAndCert();
        $pkcs12 = '';
        $password = 'secret123';
        $ok = openssl_pkcs12_export($certPem, $pkcs12, openssl_pkey_get_private($keyPem), $password);
        $this->assertTrue($ok, 'PKCS#12 export should succeed');

        $p12Path = $this->tmpDir . '/bundle_' . uniqid() . '.p12';
        file_put_contents($p12Path, $pkcs12);

        $outCert = CertificateManagerService::getCertificate($p12Path, $password);
        $outKey = CertificateManagerService::getPrivateKey($p12Path, $password);

        $this->assertIsString($outCert);
        $this->assertStringContainsString('BEGIN CERTIFICATE', $outCert);
        $this->assertIsString($outKey);
        $this->assertStringContainsString('BEGIN', $outKey);
    }

    public function testPkcs12CliFallbackViaFakeOpenssl(): void
    {
        // Prepare fake openssl that outputs known PEM content
        [$keyPem, $certPem] = $this->generateKeyAndCert();
        $stdout = $certPem . $keyPem;
        $scriptPath = $this->tmpDir . '/fake_openssl_' . uniqid();
        $script = "#!/bin/sh\n" .
            "# ignore args and print PEM\n" .
            "cat <<'PEM'\n" .
            $this->heredocEscape($stdout) .
            "\nPEM\n" .
            "exit 0\n";
        file_put_contents($scriptPath, $script);
        @chmod($scriptPath, 0755);

        // Force CLI path by making php pkcs12 read fail with non-pkcs12 input
        $fakePfx = $this->tmpDir . '/not_a_pfx_' . uniqid() . '.p12';
        file_put_contents($fakePfx, 'NOT A PFX');

        $prev = getenv('OPENSSL_BIN');
        putenv('OPENSSL_BIN=' . $scriptPath);
        try {
            $outCert = CertificateManagerService::getCertificate($fakePfx, 'any');
            $outKey = CertificateManagerService::getPrivateKey($fakePfx, 'any');
            $this->assertStringContainsString('BEGIN CERTIFICATE', $outCert);
            $this->assertStringContainsString('BEGIN', (string)$outKey);
        } finally {
            // restore env
            if ($prev === false) {
                putenv('OPENSSL_BIN');
            } else {
                putenv('OPENSSL_BIN=' . $prev);
            }
        }
    }

    private function heredocEscape(string $s): string
    {
        // Ensure no accidental heredoc terminator in content
        return str_replace("'", "'\\''", $s);
    }
}
