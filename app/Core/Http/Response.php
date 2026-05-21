<?php

declare(strict_types=1);

namespace Roostar\Core\Http;

use Roostar\Core\Security\SecurityHeaders;

final class Response
{
    public function __construct(
        private readonly string $body,
        private readonly int $status = 200,
        private readonly array $headers = [],
    ) {
    }

    public static function html(string $body, int $status = 200): self
    {
        return new self($body, $status, ['Content-Type' => 'text/html; charset=utf-8']);
    }

    public static function json(array $data, int $status = 200): self
    {
        return new self(
            json_encode($data, JSON_THROW_ON_ERROR),
            $status,
            ['Content-Type' => 'application/json; charset=utf-8'],
        );
    }

    public static function redirect(string $to, int $status = 302): self
    {
        return new self('', $status, ['Location' => $to]);
    }

    public function send(): void
    {
        http_response_code($this->status);
        header_remove('X-Powered-By');

        foreach (SecurityHeaders::mergeWith($this->headers) as $name => $value) {
            header($name . ': ' . $value);
        }

        echo $this->body;
    }
}
