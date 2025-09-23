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
        $class = static::class;
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

                $errorKey = $class . '::$' . $property;

                if ($validator === 'required') {
                    if ($value === null || $value === '' || (is_array($value) && empty($value))) {
                        $errors[$errorKey][] = "This field is required.";
                    }
                    continue;
                }

                // Si el valor está vacío, ignorar el resto de reglas
                if ($value === null || $value === '' || (is_array($value) && empty($value))) {
                    continue;
                }

                if (is_callable($validator)) {
                    $result = call_user_func($validator, $value, $this);
                    if ($result !== true) {
                        $errors[$errorKey][] = is_string($result) ? $result : "Validation failed for $property.";
                    }
                } else {
                    switch ($validator) {
                        case 'string':
                            if (!is_string($value)) {
                                $errors[$errorKey][] = "Must be a string.";
                            }
                            break;
                        case 'integer':
                            if (!is_int($value)) {
                                $errors[$errorKey][] = "Must be an integer.";
                            }
                            break;
                        case 'float':
                            if (!is_float($value) && !is_int($value)) {
                                $errors[$errorKey][] = "Must be a float.";
                            }
                            break;
                        case 'email':
                            if (!is_string($value) || !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                                $errors[$errorKey][] = "Must be a valid email address.";
                            }
                            break;
                        case 'array':
                            if (!is_array($value)) {
                                $errors[$errorKey][] = "Must be an array.";
                            }
                            break;
                        default:
                            $errors[$errorKey][] = "Unknown validator: $validator";
                    }
                }
            }
        }

        return $errors ;
    }
}
