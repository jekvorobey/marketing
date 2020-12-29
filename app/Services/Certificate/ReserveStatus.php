<?php

namespace App\Services\Certificate;

class ReserveStatus
{
    const SUCCESS = 200;
    const INVALID_SUM = 503;
    const INSUFFICIENT_FUNDS = 504;

    public static $messages = [
        self::SUCCESS => 'Операция выполнена успешно',
        self::INSUFFICIENT_FUNDS => 'Не достаточно средств',
        self::INVALID_SUM => 'Не верно указана сумма операции',
    ];

    public $code;
    public $message;
    public $certificates;

    public function __construct($code, array $certificates = [])
    {
        $this->code = $code;
        $this->message = self::$messages[$code] ?? 'Не определен статус операции';
        $this->certificates = $certificates;
    }

    public function toArray(): array
    {
        $amount = 0;
        foreach ($this->certificates as $certificate)
            $amount += $certificate['amount'];

        return [
            'success' => $this->code === self::SUCCESS,
            'code' => $this->code,
            'message' => $this->message,
            'amount' => $amount,
            'certificates' => $this->certificates
        ];
    }
}
