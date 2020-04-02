<?php

use Illuminate\Database\Seeder;
use App\Models\PromoCode\PromoCode;
use Greensight\CommonMsa\Services\AuthService\UserService;
use Greensight\Customer\Services\CustomerService\CustomerService;
use MerchantManagement\Services\MerchantService\MerchantService;
use App\Models\Discount\Discount;
use Greensight\CommonMsa\Dto\UserDto;

class PromoCodesTableSeeder extends Seeder
{
    const FAKER_SEED = 123456;

    /** @var \Faker\Generator */
    protected $faker;

    /** @var array */
    protected $userIds;

    /** @var array */
    protected $customerIds;

    /** @var array */
    protected $ownerIds;

    /** @var array */
    protected $segmentIds;

    /** @var array */
    protected $merchantsIds;

    /** @var array */
    protected $userRoles;

    /** @var array */
    protected $discountIds;

    /** @var array */
    protected $names;

    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $this->faker = Faker\Factory::create('ru_RU');
        $this->faker->seed(self::FAKER_SEED);

        $this->loadData();

        for ($i = 0; $i < 100; $i++) {
            $promo = new PromoCode();
            $promo->creator_id = $this->faker->randomElement($this->userIds);

            $promo->merchant_id = $this->faker->boolean(10)
                ? $this->faker->randomElement($this->merchantsIds)
                : null;

            $promo->owner_id = ($this->faker->boolean() && !empty($this->ownerIds) )
                ? $this->faker->randomElement($this->ownerIds)
                : null;

            $promo->name = $this->faker->numerify($this->faker->randomElement($this->names));
            $promo->code = PromoCode::generate();
            $promo->counter = $promo->owner_id ? null : $this->faker->numberBetween(1, 10);

            $promo->start_date = $this->faker->boolean()
                ? $this->faker->dateTimeBetween($startDate = '-6 month', $endDate = '+1 month')
                : null;
            $promo->end_date = $this->faker->boolean()
                ? $this->faker->dateTimeBetween($startDate = '+1 month', $endDate = '+5 month')
                : null;

            $promo->status = $this->faker->randomElement(PromoCode::availableStatuses());
            $promo->type = $promo->merchant_id
                ? $this->faker->randomElement(PromoCode::availableTypesForMerchant())
                : $this->faker->randomElement(PromoCode::availableTypes());

            switch ($promo->type) {
                case PromoCode::TYPE_DISCOUNT:
                    $promo->discount_id = $this->faker->randomElement($this->discountIds);
                    break;
                case PromoCode::TYPE_GIFT:
                    $promo->gift_id = $this->faker->numberBetween(1, 10); // todo: ID подарка
                    break;
                case PromoCode::TYPE_BONUS:
                    $promo->bonus_id = $this->faker->numberBetween(1, 10); // todo: ID бонуса
                    break;
                case PromoCode::TYPE_DELIVERY:
                    break;
            }

            if (!$promo->owner_id) {
                if ($this->faker->boolean(33)) {
                    $promo->setCustomerIds($this->faker->randomElements(
                        $this->customerIds,
                        $this->faker->numberBetween(1, 3)
                    ));
                } elseif ($this->faker->boolean(33)) {
                    $promo->setSegmentIds($this->faker->randomElements(
                        $this->segmentIds,
                        $this->faker->numberBetween(1, 3)
                    ));
                } elseif ($this->faker->boolean(33)) {
                    $promo->setRoleIds($this->faker->randomElements(
                        $this->userRoles,
                        $this->faker->numberBetween(1, 2)
                    ));
                }
            }

            $promo->save();
        }
    }

    public function loadData()
    {
        /** @var UserService $userService */
        $userService = resolve(UserService::class);
        $query = $userService->newQuery()->setFilter('front', [1, 2]);
        $this->userIds = $userService->users($query)->pluck('id')->toArray();

        /** @var CustomerService $customerService */
        $customerService = resolve(CustomerService::class);
        $customers = $customerService->customers($customerService->newQuery());
        $this->customerIds = $customers->pluck('id')->values()->toArray();
        $this->ownerIds = $customers->filter(function ($customer) {
                return isset($customer['referral_code']);
            })->pluck('id')
            ->values()
            ->toArray();

        /** @var MerchantService $merchantService */
        $merchantService = resolve(MerchantService::class);
        $this->merchantsIds = $merchantService->merchants($merchantService->newQuery())->pluck('id')->toArray();

        $this->segmentIds = [1, 2, 3]; // todo: ID сегментов

        $this->userRoles = [UserDto::SHOWCASE__PROFESSIONAL, UserDto::SHOWCASE__REFERRAL_PARTNER];

        $this->discountIds = Discount::select('id')->get()->pluck('id')->toArray();

        $this->names = [
            'Распродажа – ###',
            'Успей все скупить – ###',
            'Летний промокод – ###',
            'Акция – ###',
        ];
    }
}
