<?php
namespace eseperio\verifactu\models;

/**
 * Abstract base model with validation support.
 * All models must implement the rules() method and toXml() method.
 */
abstract class Model
{
    /**
     * Returns validation rules for model properties.
     * Each rule must be an array: [propertyName, validator]
     * Validator can be a string ('required', 'integer', 'string', 'email', etc) or a callable.
     *
     * Example:
     *  return [
     *      ['systemInfo', 'string'],
     *      ['email', 'email'],
     *      ['amount', 'integer'],
     *      ['customField', function($value) { return is_numeric($value); }]
     *      ['issuerName', 'required']
     *  ];
     *
     * @return array
     */
    abstract public function rules();


    /**
     * Validates model properties based on rules().
     * Returns true if all validations pass, otherwise returns an array of error messages.
     *
     * @return true|array
     */
    public function validate()
    {
        $errors = [];
        foreach ($this->rules() as $rule) {
            $properties = $rule[0];
            $validator = $rule[1];

            // Permitir que $properties sea string o array
            $properties = is_array($properties) ? $properties : [$properties];

            foreach ($properties as $property) {
                // Try to get value using getter method first
                $getter = 'get' . ucfirst($property);
                if (method_exists($this, $getter)) {
                    $value = $this->$getter();
                } else {
                    $value = isset($this->$property) ? $this->$property : null;
                }

                if ($validator === 'required') {
                    if ($value === null || $value === '' || (is_array($value) && empty($value))) {
                        $errors[$property][] = "This field is required.";
                    }
                    continue;
                }

                if (is_callable($validator)) {
                    $result = call_user_func($validator, $value, $this);
                    if ($result !== true) {
                        $errors[$property][] = is_string($result) ? $result : "Validation failed for $property.";
                    }
                } else {
                    // Skip validation for string/integer/float validators if value is null (unless marked as required)
                    if ($value === null && in_array($validator, ['string', 'integer', 'float', 'email'])) {
                        continue;
                    }
                    
                    switch ($validator) {
                        case 'string':
                            if (!is_string($value)) {
                                $errors[$property][] = "Must be a string.";
                            }
                            break;
                        case 'integer':
                            if (!is_int($value)) {
                                $errors[$property][] = "Must be an integer.";
                            }
                            break;
                        case 'float':
                            if (!is_float($value) && !is_int($value)) {
                                $errors[$property][] = "Must be a float.";
                            }
                            break;
                        case 'email':
                            if (!is_string($value) || !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                                $errors[$property][] = "Must be a valid email address.";
                            }
                            break;
                        default:
                            $errors[$property][] = "Unknown validator: $validator";
                    }
                }
            }
        }

        return empty($errors) ? true : $errors;
    }

    /**
     * Validates model properties based on rules(), excluding the hash field.
     * This is useful for validating before hash generation.
     * Returns true if all validations pass, otherwise returns an array of error messages.
     *
     * @return true|array
     */
    public function validateExceptHash()
    {
        $errors = [];
        foreach ($this->rules() as $rule) {
            $properties = $rule[0];
            $validator = $rule[1];

            // Allow $properties to be string or array
            $properties = is_array($properties) ? $properties : [$properties];

            foreach ($properties as $property) {
                // Skip hash field validation
                if ($property === 'hash') {
                    continue;
                }
                
                // Try to get value using getter method first
                $getter = 'get' . ucfirst($property);
                if (method_exists($this, $getter)) {
                    $value = $this->$getter();
                } else {
                    $value = isset($this->$property) ? $this->$property : null;
                }

                if ($validator === 'required') {
                    if ($value === null || $value === '' || (is_array($value) && empty($value))) {
                        $errors[$property][] = "This field is required.";
                    }
                    continue;
                }

                if (is_callable($validator)) {
                    $result = call_user_func($validator, $value, $this);
                    if ($result !== true) {
                        $errors[$property][] = is_string($result) ? $result : "Validation failed for $property.";
                    }
                } else {
                    // Skip validation for string/integer/float validators if value is null (unless marked as required)
                    if ($value === null && in_array($validator, ['string', 'integer', 'float', 'email'])) {
                        continue;
                    }
                    
                    switch ($validator) {
                        case 'string':
                            if (!is_string($value)) {
                                $errors[$property][] = "Must be a string.";
                            }
                            break;
                        case 'integer':
                            if (!is_int($value)) {
                                $errors[$property][] = "Must be an integer.";
                            }
                            break;
                        case 'float':
                            if (!is_float($value) && !is_int($value)) {
                                $errors[$property][] = "Must be a float.";
                            }
                            break;
                        case 'email':
                            if (!is_string($value) || !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                                $errors[$property][] = "Must be a valid email address.";
                            }
                            break;
                        default:
                            $errors[$property][] = "Unknown validator: $validator";
                    }
                }
            }
        }

        return empty($errors) ? true : $errors;
    }
}
