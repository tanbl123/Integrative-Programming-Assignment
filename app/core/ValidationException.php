<?php
/**
 * Carries field-level validation messages back to a controller.
 *
 * Author : Group D - Ng Zi Zhang (2406898), Ong Kar Heng (2408830),
 *          Tan Boon Leong (2402865), Phang Jun Hong (2406646)
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
