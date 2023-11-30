<?php

namespace App\Console\Commands;

use App\Models\Discount\Discount;
use App\Models\Discount\DiscountCondition;
use App\Models\Discount\DiscountConditionGroup;
use App\Models\Discount\LogicalOperator;
use Greensight\CommonMsa\Rest\RestQuery;
use Greensight\Message\Services\ServiceNotificationService\ServiceNotificationService;
use Greensight\Customer\Dto\CustomerDto;
use Greensight\Customer\Services\CustomerService\CustomerService;
use Greensight\Oms\Services\OrderService\OrderService;
use Illuminate\Console\Command;
use App\Models\PromoCode\PromoCode;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class BirthdayDiscounts extends Command
{
    protected const DAYS_BEFORE_BITHDAY = 7;
    protected const DAYS_AFTER_BITHDAY = 14;
    protected const CHECK_DISCOUNTS_LAST_DAYS_COUNT = 357;
    protected const CREATOR_USER_ID = 19435; //Талалов

    protected const DISCOUNT_1_COPY_FROM_ID = 2229;
    protected const DISCOUNT_2_COPY_FROM_ID = 2230;
    protected const DISCOUNT_FREE_DELIVERY_COPY_FROM_ID = 2447;

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
    protected $description = 'Создает скидки ко дню рождения и отправляет уведомления';

    private ?PromoCode $birthdayPromocode;
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
        $this->preloadData();

        $this->createBirthdayDiscounts();
        $this->sendBithdayNotifications();

        return 0;
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
                $discount1 = $this->copyDiscountFrom(static::DISCOUNT_1_COPY_FROM_ID, $customer);
                $discount2 = $this->copyDiscountFrom(static::DISCOUNT_2_COPY_FROM_ID, $customer);
                $discount3 = $this->copyDiscountFrom(static::DISCOUNT_FREE_DELIVERY_COPY_FROM_ID, $customer);

                $this->notificationService->send($customer->user_id, 'birthday_discount_created', $this->getEmailData([
                    'TITLE' => 'Есть догадки!',
                    'TEXT' => 'Привет, Бессовестно Талантливый!<br><br>
                        Мы знаем, что у тебя скоро день рождения. Именно поэтому очень хочется сделать тебе приятно. Так сказать, выразить всю нашу любовь…<br><br>
                        По промокоду HAPPY2U будет доступна дополнительная скидка на всё! Она будет настолько максимальной, насколько это возможно. Все-таки такой день, да и только раз в году.<br><br>
                        Скидкой можно воспользоваться за 7 дней до праздничной даты и в течение 14 дней после. Сможешь тщательно подумать и повыбирать. Всё для тебя!<br><br>
                        Спасибо за то, что ты с нами! Любим и ценим ❤️<br><br>',
                    'BUTTON' => [
                        'text' => 'К ПОКУПКАМ',
                        'link' => config('app.showcase_host') . '/yo',
                    ],
                ]));

            } catch (\Throwable $exception) {
                report($exception);
            }
        }
    }

    private function getEmailData(array $data): array
    {
        return array_merge([
            'finisher_text' => 'Если вы получили это письмо по ошибке,<br>просто проигнорируйте его',
        ], $data);
    }

    protected function copyDiscountFrom(int $discountId, CustomerDto $customer): Discount
    {
        $originalDiscount = Discount::whereId($discountId)->firstOrFail();

        try {
            DB::beginTransaction();

            $discount = $originalDiscount->replicate();
            $discount->name = $this->generateDiscountName($customer);
            $discount->status = Discount::STATUS_ACTIVE;
            $discount->start_date = now();
            $discount->end_date = now()->addDays(static::DAYS_BEFORE_BITHDAY + static::DAYS_AFTER_BITHDAY)->setTime(23,59,59);
            $discount->promo_code_only = true;
            $discount->push();

            $this->replicateRelations($originalDiscount, $discount, 'brands');
            $this->replicateRelations($originalDiscount, $discount, 'categories');

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

    protected function replicateRelations(Discount $originalDiscount, Discount $newDiscount, string $relationName): void
    {
        $originalDiscount->load($relationName);

        if (!$originalDiscount->{$relationName}) {
            return;
        }

        foreach ($originalDiscount->{$relationName} as $relationModel) {
            $newDiscount->{$relationName}()->save($relationModel->replicate());
        }
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
                $this->notificationService->send($customer->user_id, 'birthday_congratulation_has_not_used_promocode', $this->getEmailData([
                        'TITLE' => 'Помнишь про подарок?',
                        'TEXT' => 'Привет, Бессовестно Талантливый! <br><br>
                            Спешим от всей души поздравить тебя с днем рождения! Искренне желаем оставаться таким же бессовестным, но в то же время таким же талантливым. У тебя это отлично получается. <br><br>
                            Хотели бы напомнить, что тебе все еще доступен подарок от нас — промокод HAPPY2U, который действует НА ВСЁ! <br><br>
                            Скидкой можно воспользоваться еще в течение 14 дней.<br><br>
                            Еще раз с днем рождения, Бессовестно Талантливый! ❤️<br><br>',
                        'BUTTON' => [
                            'text' => 'ВОСПОЛЬЗОВАТЬСЯ ПОДАРКОМ',
                            'link' => config('app.showcase_host') . '/yo',
                        ],
                ]));
            }

            if ($orders->isNotEmpty()) {
                $this->notificationService->send($customer->user_id, 'birthday_congratulation_has_used_promocode', $this->getEmailData([
                    'TITLE' => 'Скорее открывай поздравление',
                    'TEXT' => 'Привет, Бессовестно Талантливый! <br><br>
                        Спешим от всей души поздравить тебя с днем рождения! Искренне желаем оставаться таким же бессовестным, но в то же время таким же талантливым. У тебя это отлично получается. <br><br>
                        И спасибо, что воспользовался праздничным промокодом! Мы хотели, чтобы ты мог порадовать себя с максимальной выгодой. <br><br>
                        Этот день для тебя. Каждый день для тебя. Каждая наша акция тоже для тебя. Только на <a href="https://ibt.ru/promo/">https://ibt.ru/promo/</a><br><br>
                        Будь счастлив! Еще раз с днем рождения! ❤️<br><br>',
                    'BUTTON' => [
                        'text' => 'К ВЫГОДНЫМ ПРЕДЛОЖЕНИЯМ',
                        'link' => config('app.showcase_host') . '/wow',
                    ],
                ]));
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
        $dateOfBirth = now()->addDays($daysBeforeBirthday);

        return $this->customerService->customers(
            $this->customerService->newQuery()
                ->setFilter('birthday_by_month_and_day', now()->addDays($daysBeforeBirthday)->toDateString())
                ->setFilter('birthday', '<=', (clone $dateOfBirth)->subYears(14)->toDateString())    //старше 14 и младше 100 лет
                ->setFilter('birthday', '>=', (clone $dateOfBirth)->subYears(100)->toDateString())
                ->setFilter('status', CustomerDto::STATUS_ACTIVE)
        );
    }
}
