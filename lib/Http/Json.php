<?php
namespace NGN\Lib\Http;

class Json
{
    public static function envelope(mixed $data = null, array $meta = [], array $errors = []): array
    {
        return [
            'data' => $data,
            'meta' => $meta,
            'errors' => $errors,
        ];
    }
}
