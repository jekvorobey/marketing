<?php

namespace App\Services\Certificate;

class ActivatingStatus
{
    const SUCCESS = 200;
    const NOT_FOUND = 404;
    const UNEXPECTED = 500;
    const UNKNOWN_RECIPIENT = 501;
    const INVALID_PIN = 502;
    const EXPIRED = 505;

    public static $messages = [
        self::SUCCESS => 'Операция выполнена успешно',
        self::NOT_FOUND => 'Сертификат не найден',
        self::UNEXPECTED => 'Не удалось провести активацию',
        self::UNKNOWN_RECIPIENT => 'Не указан получатель сертификата',
        self::INVALID_PIN => 'Не верный ПИН',
        self::EXPIRED => 'Действие сертификата закончено',
    ];

    public $code;
    public $message;

    public function __construct($code)
    {
        $this->code = $code;
        $this->message = self::$messages[$code] ?? 'Не определен статус операции';
    }

    public function toArray()
    {
        return [
            'success' => $this->code === self::SUCCESS,
            'code' => $this->code,
            'message' => $this->message
        ];
    }
}
