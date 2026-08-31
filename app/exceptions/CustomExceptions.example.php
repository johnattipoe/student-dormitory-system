<?php
/** Application exceptions with status codes and safe structured context. */

namespace App\Exceptions;

abstract class ApplicationException extends \RuntimeException
{
    public function __construct(string $message, private readonly int $statusCode = 500, private readonly array $context = [], ?\Throwable $previous = null) { parent::__construct($message, $statusCode, $previous); }
    public function statusCode(): int { return $this->statusCode; }
    public function context(): array { return $this->context; }
    public function toArray(): array { return ['message' => $this->getMessage(), 'status' => $this->statusCode, 'context' => $this->context]; }
}

final class UnauthorizedException extends ApplicationException
{
    public function __construct(string $message = 'Authentication is required.', array $context = []) { parent::__construct($message, 401, $context); }
}

final class ForbiddenException extends ApplicationException
{
    public function __construct(string $message = 'You do not have permission to perform this action.', array $context = []) { parent::__construct($message, 403, $context); }
}

final class ResourceNotFoundException extends ApplicationException
{
    public function __construct(string $resource = 'Resource', string $id = '', array $context = []) { parent::__construct($resource . ' not found.', 404, ['resourceId' => $id] + $context); }
}

final class ValidationException extends ApplicationException
{
    public function __construct(private readonly array $errors, string $message = 'Validation failed.') { parent::__construct($message, 422, ['errors' => $errors]); }
    public function errors(): array { return $this->errors; }
}

final class ConflictException extends ApplicationException
{
    public function __construct(string $message = 'This action conflicts with the current record state.', array $context = []) { parent::__construct($message, 409, $context); }
}

final class ExternalServiceException extends ApplicationException
{
    public function __construct(string $service, string $message = 'An external service is unavailable.', ?\Throwable $previous = null) { parent::__construct($message, 502, ['service' => $service], $previous); }
}

// Example: throw new ValidationException(['guardianEmail' => 'A valid email is required.']);
