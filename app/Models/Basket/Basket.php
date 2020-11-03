<?php

namespace App\Models\Basket;

use App\Models\Option\Option;
use App\Services\Calculator\Checkout\CheckoutCalculatorBuilder;
use Illuminate\Support\Collection;
use Exception;

class Basket implements \JsonSerializable
{
    private const CERTS = [
        'CERT2020-500' => ['id' => 1, 'code' => 'CERT2020-500', 'amount' => 500],
        'CERT2019-1000' => ['id' => 2, 'code' => 'CERT2019-1000', 'amount' => 1000],
    ];

    /**
     * Стоимость корзины без скидок.
     * @var float
     */
    public $cost;
    /**
     * Итоговая цена всей корзины (cost - discount).
     * @var float
     */
    public $price;
    /**
     * Размер скидки.
     * @var float
     */
    public $discount;

    /** @var int */
    public $user;
    /** @var int */
    public $userRegionFiasId;
    /** @var string */
    public $referalCode;
    /** @var Collection|array */
    public $deliveries;
    /** @var int */
    public $payMethod;
    /** @var int */
    public $bonus;
    /** @var string */
    public $promoCode;
    /** @var array */
    public $certificates;

    /** @var int */
    private $bonusSpent = 0;
    /** @var int */
    private $bonusDiscount = 0;
    /** @var int */
    private $bonusPerRub;

    /** @var array */
    private $appliedCertificates;
    private $discountByCertificates = 0;

    /** @var array */
    private $appliedDiscounts;
    /** @var array */
    private $appliedBonuses;
    /** @var array */
    private $appliedPromoCodes = [];
    private $maxSpendableBonus;

    /** @var BasketItem[] */
    public $items;

    public static function fromRequestData(array $data): self
    {
        $basket = new self($data['user'], $data['userRegionFiasId']);

        @([
            'referal_code' => $basket->referalCode,
            'deliveries'   => $basket->deliveries,
            'pay_method'   => $basket->payMethod,
            'marketing'    => $marketing,
            'items'        => $items
        ] = $data);

        $basket->promoCode    = $marketing['promoCode'] ?? '';
        $basket->bonus        = $marketing['bonus'] ?? 0;
        $basket->certificates = $marketing['certificates'] ?? [];

        if (!$items) {
            $basket->items = [];
            return $basket;
        }

        foreach ($items as $itemData) {
            [
                'id'          => $id,
                'qty'         => $qty,
                'offer_id'    => $offerId,
                'category_id' => $categoryId,
                'brand_id'    => $brandId,
                'bundle_id'    => $bundleId,
            ] = $itemData;
            $basket->items[] = new BasketItem($id, $qty, $offerId, $categoryId, $brandId, $bundleId);
        }

        return $basket;
    }

    public function __construct(int $userId, $userRegionFiasId=null)
    {
        $this->user             = $userId;
        $this->userRegionFiasId = $userRegionFiasId;

        $option = Option::query()->where('key', Option::KEY_BONUS_PER_RUBLES)->first();
        $this->bonusPerRub = $option ? $option->value['value'] : Option::DEFAULT_BONUS_PER_RUBLES;
    }

    /**
     * @throws Exception
     */
    public function addPricesAndBonuses()
    {
        $offers = collect($this->items)
            ->groupBy('offerId')
            ->map(function (Collection $items, $offerId) {
                $bundleQty = $items->keyBy('bundleId')
                    ->map(function ($item) {
                        return collect([
                            'qty' => $item->qty,
                        ]);
                    });

                $qty = $bundleQty->pluck('qty')->sum();

                return [
                    'id' => $offerId,
                    'qty' => $qty,
                    'bundles' => $bundleQty,
                ];
        });

        $calculation = (new CheckoutCalculatorBuilder())
            ->customer(['id' => $this->user])
            ->payment(['method' => $this->payMethod])
            ->regionFiasId($this->userRegionFiasId)
            ->deliveries($this->deliveries)
            ->offers($offers)
            ->promoCode($this->promoCode)
            ->bonus($this->bonus)
            ->calculate();

        $this->appliedPromoCodes = $calculation['promoCodes'];
        $this->appliedDiscounts  = $calculation['discounts'];
        $this->appliedBonuses    = $calculation['bonuses'];
        $this->deliveries        = $calculation['deliveries'];
        $this->maxSpendableBonus        = $calculation['maxSpendableBonus'];

        $totalCost = 0;
        $totalItemDiscount = 0;
        $totalBonusSpent = 0;
        $totalBonusDiscount = 0;
        foreach ($this->items as $item) {
            if (!$calculation['offers']->has($item->offerId)) {
                throw new Exception("basket item offer {$item->offerId} without price");
            }

            if (!$calculation['offers'][$item->offerId]['bundles']->has($item->bundleId)) {
                throw new Exception("basket item offer {$item->offerId} from bundle {$item->bundleId} without price");
            }

            $offer = $calculation['offers'][$item->offerId];

            if ($item->bundleId) {
                $qty = $offer['bundles'][$item->bundleId]['qty'];
                $discount = $offer['bundles'][$item->bundleId]['discount'] ?? 0;
                $discounts = $offer['bundles'][$item->bundleId]['discounts'] ?? [];
                $price = $offer['bundles'][$item->bundleId]['price'] ?? 0;
                $bonusSpent = ($offer['bundles'][$item->bundleId]['bonusSpent'] ?? 0) * $qty;
                $bonusDiscount = ($offer['bundles'][$item->bundleId]['bonusDiscount'] ?? 0) * $qty;
            } else {
                $qty = $offer['bundles'][0]['qty'];
                $discount = $offer['discount'] ?? 0;
                $discounts = $offer['discounts'] ?? [];
                $price = $offer['price'];
                $bonusSpent = ($offer['bonusSpent'] ?? 0) * $qty;
                $bonusDiscount = ($offer['bonusDiscount'] ?? 0) * $qty;
            }

            $offer['cost'] = $offer['cost'] ?? $price;
            $item->cost = $offer['cost'];
            $item->totalCost = $offer['cost'] * $qty;
            $item->discount = $discount * $qty;
            $item->discounts = $discounts;
            $item->unitPrice = $price;
            $item->price = $price * $qty;
            $item->bonus = $offer['bonus'];
            $item->bonuses = $offer['bonuses']->toArray();
            $item->bonusSpent = $bonusSpent;
            $item->bonusDiscount = $bonusDiscount;

            $totalCost += $item->totalCost;
            $totalItemDiscount += $item->discount;
            $totalBonusSpent += $item->bonusSpent;
            $totalBonusDiscount += $item->bonusDiscount;
        }

        $this->applyCertificates();

        $basketDiscount = 0;
        $this->cost = $totalCost;
        $this->discount = $totalItemDiscount + $basketDiscount;
        $this->price = $totalCost - $this->discount;
        $this->bonusSpent = $totalBonusSpent;
        $this->bonusDiscount = $totalBonusDiscount;
    }

    private function applyCertificates(): void
    {
        if (!$this->certificates) {
            return;
        }
        foreach ($this->certificates as $code) {
            if (isset($this->appliedCertificates[$code])) {
                continue;
            }
            $cert = self::CERTS[$code] ?? null;
            if ($cert) {
                $this->appliedCertificates[] = $cert;
                $this->discountByCertificates += $cert['amount'];
            }
        }
    }

    public function jsonSerialize()
    {
        return [
            'cost' => $this->cost,
            'price' => $this->price,
            'discounts' => $this->appliedDiscounts,
            'bonuses' => $this->appliedBonuses,
            'bonusSpent' => $this->bonusSpent,
            'maxSpendableBonus' => $this->maxSpendableBonus,
            'bonusDiscount' => $this->bonusDiscount,
            'bonusPerRub' => $this->bonusPerRub,
            'promoCodes' => $this->appliedPromoCodes,
            'items' => $this->items,
            'deliveries' => $this->deliveries,
        ];
    }
}
