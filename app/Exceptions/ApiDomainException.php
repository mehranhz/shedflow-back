<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ApiDomainException extends Exception
{
    /**
     * @param  array<string, mixed>  $details
     */
    public function __construct(
        public readonly string $errorCode,
        string $message,
        public readonly int $httpStatus = 422,
        public readonly array $details = [],
    ) {
        parent::__construct($message);
    }

    public function render(Request $request): JsonResponse
    {
        $payload = [
            'error' => [
                'code' => $this->errorCode,
                'message' => $this->getMessage(),
            ],
        ];

        if ($this->details !== []) {
            $payload['error']['details'] = $this->details;
        }

        return response()->json($payload, $this->httpStatus);
    }
}
