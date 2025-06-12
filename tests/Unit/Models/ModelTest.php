<?php

namespace eseperio\verifactu\tests\Unit\Models;

use eseperio\verifactu\models\Model;
use PHPUnit\Framework\TestCase;

class ModelTest extends TestCase
{
    /**
     * Test for model validation
     */
    public function testValidation()
    {
        // Use a concrete implementation of the abstract Model class
        $model = new class extends Model {
            public $requiredField;
            public $optionalField;

            public function rules()
            {
                return [
                    [['requiredField'], 'required'],
                    [['optionalField'], function($value) {
                        return is_null($value) || is_string($value) ? true : 'Must be string or null.';
                    }]
                ];
            }
        };

        // Test validation fails for missing required field
        $result = $model->validate();
        $this->assertNotTrue($result);
        $this->assertArrayHasKey('requiredField', $result);

        // Test validation passes when required fields are set
        $model->requiredField = 'test';
        $result = $model->validate();
        $this->assertTrue($result);

        // Test validation with custom validator function
        $model->optionalField = 123; // Not a string
        $result = $model->validate();
        $this->assertNotTrue($result);
        $this->assertArrayHasKey('optionalField', $result);

        // Fix the field and test again
        $model->optionalField = 'valid string';
        $result = $model->validate();
        $this->assertTrue($result);
    }
}
