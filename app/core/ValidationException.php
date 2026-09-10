<?php
/**
 * Carries field-level validation messages back to a controller.
 *
 * Module : Shared validation support
 */
class ValidationException extends InvalidArgumentException
{
    public function __construct(private array $errors)
    {
        parent::__construct('The submitted data is invalid.');
    }

    public function getErrors(): array
    {
        return $this->errors;
    }
}
