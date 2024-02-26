<?php

namespace App\Console\Commands\OneTime;

use App\Models\Basket\BasketItem;
use App\Services\Calculator\Bonus\BonusCalculator;
use App\Services\Calculator\InputCalculator;
use App\Services\Calculator\OutputCalculator;
use Greensight\Customer\Dto\CustomerBonusDto;
use Greensight\Customer\Dto\CustomerDto;
use Greensight\Customer\Services\CustomerService\CustomerService;
use Greensight\Oms\Dto\BasketItemDto;
use Greensight\Oms\Dto\Order\OrderType;
use Greensight\Oms\Dto\OrderDto;
use Greensight\Oms\Dto\OrderStatus;
use Greensight\Oms\Dto\Payment\PaymentStatus;
use Greensight\Oms\Services\OrderService\OrderService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Class RecalculateMissedOrderBonuses
 * @package App\Console\Commands\OneTime
 */
class RecalculateMissedOrderBonuses extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'bonus:recalculate';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Пересчитать бонусы для заказов, за которые не начислились бонусы';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $orders = $this->fetchOrders();

        $orders->each(function (OrderDto $order) {
            dump("Recalculating bonuses for order id = {$order->id}");
            $this->recalculateOrderBonuses($order);
            dump('done');
        });
    }

    private function fetchOrders(): Collection
    {
        $orderService = resolve(OrderService::class);

        $dateTo = Carbon::now()->subDay(7);
        $dateFrom = $dateTo->subDay(90);

        $ordersQuery  = $orderService->newQuery()
            ->setFilter('type', OrderType::PRODUCT)
            ->setFilter('created_at', '>=', $dateFrom->format('Y-m-d H:i:s'))
            ->setFilter('created_at', '<=', $dateTo->format('Y-m-d H:i:s'))
            ->setFilter('added_bonus', 0)
            ->setFilter('is_canceled', false)
            ->setFilter('is_returned', false)
            ->setFilter('payment_status', PaymentStatus::PAID)
            ->setFilter('status', OrderStatus::DONE)
            ->include('basket.items');

        return $orderService->orders($ordersQuery);
    }

    protected function recalculateOrderBonuses(OrderDto $order): void
    {
        $customer = $this->getCustomer($order->customer_id);
        $basketItems = $order->basket->items->map(function (BasketItemDto $item) {
            return new BasketItem(
                $item->id,
                $item->qty,
                $item->offer_id,
                0,
                0,
                $item->bundle_id
            );
        });

        $input = new InputCalculator([
            'basketItems' => $basketItems->toArray(),
            'customer' => $customer->toArray()
        ]);
        $output = new OutputCalculator();

        $bonusCalculator = new BonusCalculator($input, $output);
        $bonusCalculator->calculate();

        $addedBonuses = $bonusCalculator->getOutput()->appliedBonuses;

        $this->saveOrderAddedBonus($order, $addedBonuses);
        $this->addBonusToCustomer($customer, $addedBonuses, $order);
    }

    protected function getCustomer(int $customerId): CustomerDto
    {
        $service = resolve(CustomerService::class);

        return $service->customers(
            $service
                ->newQuery()
                ->setFilter('id', $customerId)
        )
        ->first();
    }

    protected function saveOrderAddedBonus(OrderDto $order, Collection $addedBonuses): void
    {
        $orderService = resolve(OrderService::class);

        $order->added_bonus = $addedBonuses->sum('bonus');
        unset($order->basket);

        $orderService->updateOrder($order->id, $order);
    }

    protected function addBonusToCustomer(CustomerDto $customer, Collection $addedBonuses, OrderDto $order): void
    {
        $service = resolve(CustomerService::class);

        $addedBonuses->each(function ($bonus) use ($customer, $order, $service) {
            $customerBonusDto = new CustomerBonusDto();
            $customerBonusDto->customer_id = $customer->id;
            $customerBonusDto->name = "{$order->id}";
            $customerBonusDto->value = $bonus['bonus'];
            $customerBonusDto->status = CustomerBonusDto::STATUS_ACTIVE;
            $customerBonusDto->type = CustomerBonusDto::TYPE_ORDER;
            $customerBonusDto->expiration_date = $bonus['valid_period'] ? now()->addDays($bonus['valid_period'])->format('Y-m-d H:i:s') : null;;
            $customerBonusDto->message = '';
            $customerBonusDto->order_id = $order->id;

            $service->createBonus($customerBonusDto);
        });
    }
}
