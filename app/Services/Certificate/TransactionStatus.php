<?php

namespace App\Services\Certificate;

class TransactionStatus
{
    const SUCCESS = 200;
    const NOT_FOUND = 404;
    const UNEXPECTED = 500;
    const NOT_PAID = 501;
    const INACTIVE = 502;
    const EMPTY_SUM = 503;
    const INSUFFICIENT_FUNDS = 504;
    const EXPIRED = 505;
    const LIMIT_EXCEEDED = 506;

    public static $messages = [
        self::SUCCESS => 'Операция выполнена успешно',
        self::NOT_FOUND => 'Сертификат не найден',
        self::UNEXPECTED => 'Не удалось провести транзакцию',
        self::NOT_PAID => 'Сертификат не оплачен',
        self::INACTIVE => 'Сертификат не активен, операция невозможна',
        self::EMPTY_SUM => 'Не указана сумма операции',
        self::INSUFFICIENT_FUNDS => 'Не достаточно средств',
        self::EXPIRED => 'Действие сертификата закончено',
        self::LIMIT_EXCEEDED => 'Сумма возврата средств превышает стоимость сертификата',
    ];

    public $code;
    public $message;

    public function __construct($code)
    {
        $this->code = $code;
        $this->message = self::$messages[$code] ?? 'Не определен статус операции';
    }

    public function toArray(): array
    {
        return [
            'success' => $this->code === self::SUCCESS,
            'code' => $this->code,
            'message' => $this->message
        ];
    }
}
