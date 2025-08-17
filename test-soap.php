<?php

$wsdl = __DIR__ . '/docs/aeat/esquemas/SistemaFacturacion.wsdl';
$certPath = getenv('VERIFACTU_CERT_PATH') ?: '/ruta/al/certificado.p12';
$wrongPassword = 'contraseña_incorrecta';

$options = [
    'trace' => 1,
    'exceptions' => true,
    'local_cert' => $certPath,
    'passphrase' => $wrongPassword,
    'cache_wsdl' => WSDL_CACHE_NONE,
];

try {
    // Si el WSDL es local, usa file://
    $wsdlPath = file_exists($wsdl) ? 'file://' . realpath($wsdl) : $wsdl;
    $client = new SoapClient($wsdlPath, $options);
    echo "SoapClient creado correctamente (esto NO debería ocurrir con contraseña incorrecta)\n";
} catch (Exception $e) {
    echo "Excepción capturada al crear SoapClient con contraseña incorrecta:\n";
    echo get_class($e) . ': ' . $e->getMessage() . "\n";
}
