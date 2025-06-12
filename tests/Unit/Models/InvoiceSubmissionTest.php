<?php

namespace eseperio\verifactu\tests\Unit\Models;

use eseperio\verifactu\models\InvoiceSubmission;
use PHPUnit\Framework\TestCase;

class InvoiceSubmissionTest extends TestCase
{
    /**
     * Test que la clase InvoiceSubmission existe y hereda de InvoiceRecord
     */
    public function testClassStructure()
    {
        $this->assertTrue(class_exists(InvoiceSubmission::class));

        $submission = new InvoiceSubmission();
        $this->assertInstanceOf('eseperio\verifactu\models\InvoiceRecord', $submission);

        // Verificar que tiene las propiedades específicas de registro de factura
        $reflection = new \ReflectionClass($submission);
        $this->assertTrue($reflection->hasProperty('issuerName'));
        $this->assertTrue($reflection->hasProperty('invoiceType'));
        $this->assertTrue($reflection->hasProperty('taxAmount'));
        $this->assertTrue($reflection->hasProperty('totalAmount'));
    }

    /**
     * Test que las propiedades heredadas de InvoiceRecord están presentes
     */
    public function testInheritedProperties()
    {
        $submission = new InvoiceSubmission();
        $reflection = new \ReflectionClass($submission);

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
        $submission = new InvoiceSubmission();
        $rules = $submission->rules();

        $this->assertIsArray($rules);
        $this->assertNotEmpty($rules);
    }
}
