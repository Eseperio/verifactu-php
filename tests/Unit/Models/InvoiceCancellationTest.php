<?php

namespace eseperio\verifactu\tests\Unit\Models;

use eseperio\verifactu\models\InvoiceCancellation;
use PHPUnit\Framework\TestCase;

class InvoiceCancellationTest extends TestCase
{
    /**
     * Test que la clase InvoiceCancellation existe y hereda de InvoiceRecord
     */
    public function testClassStructure()
    {
        $this->assertTrue(class_exists(InvoiceCancellation::class));

        $cancellation = new InvoiceCancellation();
        $this->assertInstanceOf('eseperio\verifactu\models\InvoiceRecord', $cancellation);

        // Verificar que tiene las propiedades específicas de cancelación
        $reflection = new \ReflectionClass($cancellation);
        $this->assertTrue($reflection->hasProperty('noPreviousRecord'));
        $this->assertTrue($reflection->hasProperty('previousRejection'));
        $this->assertTrue($reflection->hasProperty('generator'));
    }

    /**
     * Test que las propiedades heredadas de InvoiceRecord están presentes
     */
    public function testInheritedProperties()
    {
        $cancellation = new InvoiceCancellation();
        $reflection = new \ReflectionClass($cancellation);

        // Comprobar propiedades heredadas
        $this->assertTrue($reflection->hasProperty('versionId'));
        $this->assertTrue($reflection->hasProperty('invoiceId'));
        $this->assertTrue($reflection->hasProperty('recordTimestamp'));
    }

    /**
     * Test que el método rules() existe y devuelve un array
     */
    public function testRulesMethod()
    {
        $cancellation = new InvoiceCancellation();
        $rules = $cancellation->rules();

        $this->assertIsArray($rules);
        $this->assertNotEmpty($rules);
    }
}
