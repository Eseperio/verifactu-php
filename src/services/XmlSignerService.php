<?php
namespace eseperio\verifactu\services;

/**
 * Service responsible for digitally signing XML blocks using XAdES Enveloped
 * with the provided digital certificate, as required by AEAT.
 */
class XmlSignerService
{
    /**
     * Signs a given XML string with the provided certificate and private key.
     * The signature is embedded in the XML (enveloped).
     *
     * @param string $xml Original XML to be signed
     * @param string $certPath Path to certificate (PEM or PFX)
     * @param string $certPassword Password for the certificate
     * @return string Signed XML
     * @throws \RuntimeException
     */
    public static function signXml($xml, $certPath, $certPassword = '')
    {
        // --- 1. Load certificate and private key ---
        $pemCert = CertificateManagerService::getCertificate($certPath, $certPassword);
        $privateKey = CertificateManagerService::getPrivateKey($certPath, $certPassword);

        // --- 2. Use XMLSecurityDSig and XMLSecurityKey from the xmlseclibs library ---
        // xmlseclibs: https://github.com/robrichards/xmlseclibs
        if (!class_exists('XMLSecurityDSig')) {
            throw new \RuntimeException('xmlseclibs library is required for XML signing.');
        }

        $doc = new \DOMDocument();
        $doc->loadXML($xml);

        $objDSig = new \XMLSecurityDSig();
        $objDSig->setCanonicalMethod(\XMLSecurityDSig::EXC_C14N);
        $objDSig->addReference(
            $doc,
            \XMLSecurityDSig::SHA256,
            ['http://www.w3.org/2000/09/xmldsig#enveloped-signature'],
            ['force_uri' => true]
        );

        $objKey = new \XMLSecurityKey(\XMLSecurityKey::RSA_SHA256, ['type' => 'private']);
        $objKey->loadKey($privateKey, false);

        // Attach the certificate (public) to the KeyInfo
        $objDSig->add509Cert($pemCert, true, false, ['subjectName' => true]);

        $objDSig->sign($objKey);
        $objDSig->appendSignature($doc->documentElement);

        // --- 3. Return signed XML as string ---
        return $doc->saveXML();
    }
}
