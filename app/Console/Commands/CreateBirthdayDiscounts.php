<?php

namespace App\Console\Commands;

use App\Models\Discount\Discount;
use App\Models\Discount\DiscountCondition;
use App\Models\Discount\DiscountConditionGroup;
use App\Models\Discount\LogicalOperator;
use Carbon\Carbon;
use Greensight\CommonMsa\Rest\RestQuery;
use Greensight\Message\Services\ServiceNotificationService\ServiceNotificationService;
use Greensight\Customer\Dto\CustomerDto;
use Greensight\Customer\Services\CustomerService\CustomerService;
use Greensight\Oms\Services\OrderService\OrderService;
use Illuminate\Console\Command;
use App\Models\PromoCode\PromoCode;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CreateBirthdayDiscounts extends Command
{
    protected const DAYS_BEFORE_BITHDAY = 7;
    protected const DAYS_AFTER_BITHDAY = 14;
    protected const CHECK_DISCOUNTS_LAST_DAYS_COUNT = 357;
    protected const CREATOR_USER_ID = 19435; //Талалов

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'discount:birthday';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Создает скидки ко дню рождения';

    private PromoCode $birthdayPromocode;
    private CustomerService $customerService;
    private ServiceNotificationService $notificationService;
    private OrderService $orderService;

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();

        $this->customerService = app(CustomerService::class);
        $this->notificationService = app(ServiceNotificationService::class);
        $this->orderService = app(OrderService::class);

        $this->preloadData();
    }

    protected function preloadData(): void
    {
        $this->birthdayPromocode = PromoCode::where(['code' => PromoCode::HAPPY2U_PROMOCODE])->first();

        if (!$this->birthdayPromocode) {
            throw new \LogicException('Promocode HAPPY2U not found');
        }

        if ($this->birthdayPromocode->status !== PromoCode::STATUS_ACTIVE) {
            throw new \LogicException('Promocode HAPPY2U is not active');
        }
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->createBirthdayDiscounts();
        $this->sendBithdayNotifications();

        return 0;
    }

    protected function sendBithdayNotifications(): void
    {
        $customers = $this->getCusotmers();

        /** @var CustomerDto $customer */
        foreach ($customers as $customer) {
            $createdBithdayDiscounts = $this->getBirthdayDiscountsForLastDays($customer, static::DAYS_BEFORE_BITHDAY + 1);
            $activeBithdayDiscount = $createdBithdayDiscounts->filter(fn($discount) => $discount->status === Discount::STATUS_ACTIVE)->first();

            $orders = $createdBithdayDiscounts->isNotEmpty() ?
                $this->getOrdersByDiscountIds($customer, $createdBithdayDiscounts->pluck('id')->toArray()) :
                collect();

            if ($orders->isEmpty() && $activeBithdayDiscount) {
                $this->notificationService->send($customer->user_id, 'birthday_congratulation_has_not_used_promocode', [
                    'EXPIRES_AT' => Carbon::parse($activeBithdayDiscount->end_date)->format('d.m.Y')
                ]);
            }

            if ($orders->isNotEmpty()) {
                $this->notificationService->send($customer->user_id, 'birthday_congratulation_has_used_promocode');
            }
        }
    }

    protected function getOrdersByDiscountIds(CustomerDto $customer, array $discountIds): Collection
    {
        return $this->orderService->orders(
            (new RestQuery())
                ->include('discounts')
                ->setFilter('discount_id', $discountIds)
                ->setFilter('customer_id', $customer->id)
        );
    }

    protected function createBirthdayDiscounts(): void
    {
        $customers = $this->getCusotmers(static::DAYS_BEFORE_BITHDAY);

        /** @var CustomerDto $customer */
        foreach ($customers as $customer) {
            $alreadyCreatedDiscounts = $this->getBirthdayDiscountsForLastDays($customer, static::CHECK_DISCOUNTS_LAST_DAYS_COUNT);
            if ($alreadyCreatedDiscounts->isNotEmpty()) {
                continue;
            }

            try {
                $discount = $this->createBithdayDiscount($customer);

                $this->notificationService->send($customer->user_id, 'birthday_discount_created', [
                    'EXPIRES_AT' => $discount->end_date->format('d.m.Y')
                ]);

            } catch (\Throwable $exception) {
                report($exception);
            }
        }
    }

    protected function createBithdayDiscount(CustomerDto $customer): Discount
    {
        try {
            DB::beginTransaction();

            $discount = new Discount([
                'user_id' => static::CREATOR_USER_ID,
                'type' => Discount::DISCOUNT_TYPE_ANY_OFFER,
                'name' => $this->generateDiscountName($customer),
                'value_type' => Discount::DISCOUNT_VALUE_TYPE_PERCENT,
                'value' => 10,
                'status' => Discount::STATUS_ACTIVE,
                'start_date' => now(),
                'end_date' => now()->addDays(static::DAYS_BEFORE_BITHDAY + static::DAYS_AFTER_BITHDAY),
                'promo_code_only' => true,
                'summarizable_with_all' => true,
            ]);
            $discount->save();

            $discount->promoCodes()->save($this->birthdayPromocode);

            $conditionGroup = new DiscountConditionGroup([
                'discount_id' => $discount->id,
                'logical_operator' => LogicalOperator::AND,
            ]);
            $conditionGroup->save();

            $condition = new DiscountCondition([
                'discount_id' => $discount->id,
                'type' => DiscountCondition::CUSTOMER,
                'condition' => [DiscountCondition::FIELD_CUSTOMER_IDS => [$customer->id]],
                'discount_condition_group_id' => $conditionGroup->id,
            ]);
            $condition->save();

            DB::commit();

        } catch (\Throwable $exception) {
            DB::rollBack();
            throw $exception;
        }

        return $discount;
    }

    protected function generateDiscountName(CustomerDto $customer): string
    {
        return join('_', [PromoCode::HAPPY2U_PROMOCODE, $customer->id, now()->toDateString()]);
    }

    protected function getBirthdayDiscountsForLastDays(CustomerDto $customer, int $lastDaysCount): \Illuminate\Database\Eloquent\Collection
    {
        return Discount::whereHas('promoCodes', function ($promoCodeQuery) {
                $promoCodeQuery->where('code', PromoCode::HAPPY2U_PROMOCODE);
            })
            ->whereDate('created_at', '>=', now()->subDays($lastDaysCount)->toDateString())
            ->whereHas('conditions', function ($conditionQuery) use ($customer) {
                $conditionQuery->where('type', DiscountCondition::CUSTOMER)
                    ->whereJsonContains('condition->customerIds', [$customer->id]);
            })
            ->get();
    }

    protected function getCusotmers(int $daysBeforeBirthday = 0): Collection
    {
        return $this->customerService->customers(
            $this->customerService->newQuery()->setFilter('birthday', now()->addDays($daysBeforeBirthday)->toDateString())
        );
    }
}
