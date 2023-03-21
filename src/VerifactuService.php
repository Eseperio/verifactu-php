<?php

namespace Eseperio\VerifactuPhp;

use SoapClient;
use SoapFault;

/**
 *
 */
class VerifactuService {

    const WSDL_PROD = 'https://www2.agenciatributaria.gob.es/static_files/common/internet/dep/aplicaciones/es/aeat/tike/cont/ws/SistemaFacturacion.wsdl';
    const WSDL_TEST = 'https://www2.agenciatributaria.gob.es/static_files/common/internet/dep/aplicaciones/es/aeat/tike/cont/ws/SistemaFacturacionPruebas.wsdl';

    private $isTestEnvironment;

    public function __construct($isTestEnvironment) {
        $this->isTestEnvironment = $isTestEnvironment;
    }

    /**
     * Calls the "AltaFactuSistemaFacturacion" operation on the AEAT web service
     *
     * @param mixed $data The data to send in the "AltaFactuSistemaFacturacion" element of the SOAP request
     *
     * @return mixed The response from the web service, or a SoapFault object if an error occurred
     * @throws \SoapFault
     */
    public function registerInvoice($data) {
        $client = new SoapClient($this->getWsdlUrl(), array('soap_version' => SOAP_1_2, 'trace' => 1));
        $params = array('AltaFactuSistemaFacturacion' => $data);
        try {
            return $client->__soapCall('AltaFactuSistemaFacturacion', array($params));
        } catch (SoapFault $fault) {
            return $fault;
        }
    }

    /**
     * Calls the "BajaFactuSistemaFacturacion" operation on the AEAT web service
     *
     * @param mixed $data The data to send in the "BajaFactuSistemaFacturacion" element of the SOAP request
     *
     * @return mixed The response from the web service, or a SoapFault object if an error occurred
     */
    public function unregisterInvoice($data) {
        $client = new SoapClient($this->getWsdlUrl(), array('soap_version' => SOAP_1_2, 'trace' => 1));
        $params = array('BajaFactuSistemaFacturacion' => $data);
        try {
            $response = $client->__soapCall('BajaFactuSistemaFacturacion', array($params));
            return $response;
        } catch (SoapFault $fault) {
            return $fault;
        }
    }

    /**
     * Gets the WSDL URL based on the environment
     *
     * @return string The URL of the WSDL file for the appropriate environment
     */
    private function getWsdlUrl() {
        return $this->isTestEnvironment ? self::WSDL_TEST : self::WSDL_PROD;
    }

}

