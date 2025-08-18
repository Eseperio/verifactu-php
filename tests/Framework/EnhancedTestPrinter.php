<?php

declare(strict_types=1);

namespace eseperio\verifactu\tests\Framework;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\TestResult;
use PHPUnit\Framework\TestFailure;
use PHPUnit\TextUI\DefaultResultPrinter;

/**
 * Enhanced test printer for PHPUnit that provides more detailed diagnostics.
 * 
 * This printer adds additional information when tests fail, including:
 * - More detailed error messages
 * - XML parsing error information
 * - SOAP request/response details when available
 */
class EnhancedTestPrinter extends DefaultResultPrinter
{
    /**
     * Print detailed information about a test failure.
     */
    protected function printDefect(TestFailure $defect, int $count): void
    {
        parent::printDefect($defect, $count);
        
        // Get additional diagnostics
        $this->printEnhancedDiagnostics($defect);
    }
    
    /**
     * Print enhanced diagnostics for test failures.
     */
    protected function printEnhancedDiagnostics(TestFailure $defect): void
    {
        $e = $defect->thrownException();
        $test = $defect->failedTest();
        
        $this->write("\n----- Enhanced Diagnostics -----\n");
        
        // Print the exception class
        $this->write("Exception Class: " . get_class($e) . "\n");
        
        // Handle specific types of failures differently
        if ($e instanceof \DOMException || $e instanceof \LibXMLError || strpos($e->getMessage(), 'XML') !== false) {
            $this->printXmlParsingDiagnostics($e);
        } elseif (strpos($e->getMessage(), 'SOAP') !== false || $e instanceof \SoapFault) {
            $this->printSoapDiagnostics($e);
        }
        
        // Print test class properties if available (for data inspection)
        if ($test instanceof TestCase) {
            $this->printTestProperties($test);
        }
        
        $this->write("\n----- End Enhanced Diagnostics -----\n");
    }
    
    /**
     * Print diagnostics specific to XML parsing errors.
     */
    protected function printXmlParsingDiagnostics(\Throwable $e): void
    {
        $this->write("XML Parsing Error Information:\n");
        
        // Get the last libxml error if available
        $errors = libxml_get_errors();
        if (!empty($errors)) {
            foreach ($errors as $error) {
                $this->write(sprintf(
                    "  Line %d, Column %d: %s (Code: %d)\n",
                    $error->line,
                    $error->column,
                    trim($error->message),
                    $error->code
                ));
            }
        } else {
            // Try to extract line/column information from the exception message
            if (preg_match('/line (\d+).*column (\d+)/i', $e->getMessage(), $matches)) {
                $this->write("  Error at line {$matches[1]}, column {$matches[2]}\n");
            }
        }
        
        // Advice for XML issues
        $this->write("\nSuggestions for XML issues:\n");
        $this->write("  - Check for well-formedness: XML must be properly nested and closed\n");
        $this->write("  - Ensure namespaces are properly declared\n");
        $this->write("  - Validate against the AEAT schema specifications\n");
        $this->write("  - Use an XML validator tool to identify specific issues\n");
    }
    
    /**
     * Print diagnostics specific to SOAP errors.
     */
    protected function printSoapDiagnostics(\Throwable $e): void
    {
        $this->write("SOAP Error Information:\n");
        
        if ($e instanceof \SoapFault) {
            $this->write("  Fault Code: " . $e->faultcode . "\n");
            $this->write("  Fault String: " . $e->faultstring . "\n");
            
            if (isset($e->detail)) {
                $this->write("  Detail:\n");
                $this->write("  " . print_r($e->detail, true) . "\n");
            }
        }
        
        // Try to get the last request/response if available from SoapClient
        // This would require storing these in a accessible property
        // in your SoapClientFactoryService or a debug handler
        
        // Advice for SOAP issues
        $this->write("\nSuggestions for SOAP issues:\n");
        $this->write("  - Check WSDL location and accessibility\n");
        $this->write("  - Verify certificate configuration for secure connections\n");
        $this->write("  - Ensure request payload matches the expected format\n");
        $this->write("  - Check environment settings (production vs. sandbox)\n");
    }
    
    /**
     * Print relevant properties of the test class for inspection.
     */
    protected function printTestProperties(TestCase $test): void
    {
        $this->write("\nTest Object Properties:\n");
        
        // Use reflection to access protected/private properties
        $reflection = new \ReflectionObject($test);
        $properties = $reflection->getProperties();
        
        foreach ($properties as $property) {
            $property->setAccessible(true);
            $value = $property->getValue($test);
            
            // Skip empty arrays and null values
            if (is_array($value) && empty($value) || $value === null) {
                continue;
            }
            
            // Skip the default PHPUnit properties
            if (in_array($property->getName(), ['name', 'data', 'dataName'])) {
                continue;
            }
            
            $this->write("  " . $property->getName() . ": ");
            
            // Format output based on value type
            if (is_scalar($value)) {
                $this->write(var_export($value, true) . "\n");
            } elseif (is_array($value)) {
                $this->write("Array with " . count($value) . " items\n");
                // Print first few items if it's a small array
                if (count($value) <= 5) {
                    $this->write("    " . print_r($value, true) . "\n");
                }
            } elseif (is_object($value)) {
                $this->write("Object of class " . get_class($value) . "\n");
            } else {
                $this->write(gettype($value) . "\n");
            }
        }
    }
}
