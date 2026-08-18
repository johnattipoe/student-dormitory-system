<?php
/**
 * Example Exceptions - Custom Error Handling
 * 
 * Custom exceptions enable precise error handling and HTTP status mapping.
 */

namespace App\Exceptions;

class UnauthorizedException extends \Exception
{
    public function __construct(string $message = 'Unauthorized access', int $code = 403)
    {
        parent::__construct($message, $code);
    }
}

class ResourceNotFoundException extends \Exception
{
    public function __construct(string $resource = 'Resource', int $code = 404)
    {
        parent::__construct("$resource not found", $code);
    }
}

class ValidationException extends \Exception
{
    public function __construct(
        private array $errors,
        string $message = 'Validation failed',
        int $code = 422
    ) {
        parent::__construct($message, $code);
    }
    
    public function errors(): array
    {
        return $this->errors;
    }
}

class ConflictException extends \Exception
{
    public function __construct(string $message = 'Resource conflict', int $code = 409)
    {
        parent::__construct($message, $code);
    }
}

// Usage in services:
// throw new UnauthorizedException('User does not have permission');
// throw new ResourceNotFoundException('Student');
// throw new ValidationException(['email' => 'Email already exists']);
