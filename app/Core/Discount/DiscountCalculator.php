<?php

namespace App\Core\Discount;

use Illuminate\Http\Request;
use App\Models\Discount\Discount;

/**
 * Class DiscountCalculator
 * @package App\Core\Discount
 */
class DiscountCalculator
{
    /**
     * Информация о пользователе
     * @var array
     */
    protected $user;

    /**
     * Список офферов в корзине
     * @var array
     */
    protected $offers;

    /**
     * Список введенных промокодов
     * @var array
     */
    protected $promoCode;

    /**
     * Информация о выбранной доставке
     * @var array
     */
    protected $delivery;

    /**
     * Информация о выбранной оплате
     * @var array
     */
    protected $payment;

    /**
     * Скидки, которые активированы с помощью промокода
     * @var array
     */
    protected $appliedDiscounts;

    /**
     * DiscountCalculator constructor.
     * @param Request $request
     */
    public function __construct(Request $request)
    {
        $this->user = (array) $request->post('user', []);
        $this->offers = (array) $request->post('offers', []);
        $this->promoCode = (array) $request->post('promo_code', []);
        $this->delivery = (array) $request->post('delivery', []);
        $this->payment = (array)  $request->post('payment', []);

        $this->appliedDiscounts = []; // todo
    }

    /**
     * Возвращает данные о примененных скидках
     *
     * @return array
     */
    public function calculate()
    {
        $discounts = $this->getCorrectDiscount();

        return [
            'discounts' => $discounts,
        ];
    }

    /**
     * Можно ли применить данную скидку (независимо от других скидок)
     *
     * @param Discount $discount
     * @return bool
     */
    protected function checkDiscount(Discount $discount): bool
    {
        // Если скидку можно получить только по промокоду, но промокод не был введен
        if ($discount->promo_code_only && !in_array($discount->id, $this->appliedDiscounts)) {
            return false;
        }

        return true;
    }

    /**
     * Получает все скидки, которые можно применить
     *
     * @return mixed
     */
    protected function getCorrectDiscount()
    {
        return $this->getActiveDiscounts()
            ->filter(function (Discount $discount) {
                return $this->checkDiscount($discount);
            });
    }

    /**
     * Получить все активные скидки
     *
     * @return mixed
     */
    protected function getActiveDiscounts()
    {
        return Discount::select(['id', 'type', 'value', 'value_type'])
            ->active()
            ->orderBy('type')
            ->get();
    }
}
