<?php

namespace eseperio\verifactu\tests\Unit;

use eseperio\verifactu\models\InvoiceCancellation;
use eseperio\verifactu\models\InvoiceResponse;
use eseperio\verifactu\models\InvoiceSubmission;
use eseperio\verifactu\Verifactu;
use PHPUnit\Framework\TestCase;

class VerifactuTest extends TestCase
{
    /**
     * Test para comprobar que existe la clase Verifactu
     */
    public function testVerifactuClassExists()
    {
        $this->assertTrue(class_exists(Verifactu::class));
    }

    /**
     * Test para verificar la estructura de la clase principal Verifactu
     */
    public function testVerifactuMethods()
    {
        $reflection = new \ReflectionClass(Verifactu::class);

        // Verificar que la clase Verifactu tiene los métodos principales
        // Nota: No probamos la funcionalidad, solo verificamos que los métodos existan
        $this->assertTrue($reflection->hasMethod('registerInvoice') ||
                        $reflection->hasMethod('submitInvoice') ||
                        $reflection->hasMethod('alta'),
                        'Debe existir un método para registrar facturas');

        $this->assertTrue($reflection->hasMethod('cancelInvoice') ||
                        $reflection->hasMethod('anulacion'),
                        'Debe existir un método para cancelar facturas');

        $this->assertTrue($reflection->hasMethod('queryInvoices') ||
                        $reflection->hasMethod('queryInvoice') ||
                        $reflection->hasMethod('consulta'),
                        'Debe existir un método para consultar facturas');

        $this->assertTrue($reflection->hasMethod('generateInvoiceQr'),
                        'Debe existir un método para generar códigos QR de facturas');
    }

    /**
     * Test para verificar que las clases principales de modelos existen
     */
    public function testModelsExist()
    {
        $this->assertTrue(class_exists(InvoiceSubmission::class), 'La clase InvoiceSubmission debe existir');
        $this->assertTrue(class_exists(InvoiceCancellation::class), 'La clase InvoiceCancellation debe existir');
        $this->assertTrue(class_exists(InvoiceResponse::class), 'La clase InvoiceResponse debe existir');
    }
}
