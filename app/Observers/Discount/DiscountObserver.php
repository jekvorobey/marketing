<?php

namespace App\Observers\Discount;

use App\Models\Discount\Discount;
use App\Models\Discount\DiscountCondition;
use App\Models\Discount\DiscountUserRole;
use Greensight\CommonMsa\Dto\RoleDto;
use Greensight\CommonMsa\Dto\UserDto;
use Greensight\CommonMsa\Rest\RestQuery;
use Greensight\CommonMsa\Services\AuthService\UserService;
use Greensight\Customer\Services\CustomerService\CustomerService;
use Greensight\Message\Services\ServiceNotificationService\ServiceNotificationService;
use Illuminate\Support\Facades\Log;
use MerchantManagement\Dto\OperatorDto;
use MerchantManagement\Services\OperatorService\OperatorService;

class DiscountObserver
{
    private OperatorService $operatorService;
    private ServiceNotificationService $notificationService;
    private UserService $userService;
    private CustomerService $customerService;

    public function __construct(
        OperatorService $operatorService,
        ServiceNotificationService $notificationService,
        UserService $userService,
        CustomerService $customerService
    ) {
        $this->operatorService = $operatorService;
        $this->notificationService = $notificationService;
        $this->userService = $userService;
        $this->customerService = $customerService;
    }

    public function saving(Discount $discount)
    {
        // Было перенесено из обзервера модели на всякий случай, возможно это можно удалить
        /*if (in_array(
            $discount->type,
            [Discount::DISCOUNT_TYPE_BUNDLE_OFFER, Discount::DISCOUNT_TYPE_BUNDLE_MASTERCLASS],
            true
        )) {
            $discount->summarizable_with_all = true;
        }*/
    }

    public function saved(Discount $discount): void
    {
        if ($discount->isDirty() || $discount->relationsWasRecentlyUpdated) {
            $discount->updatePimContents();
        }

        $operators = $this->operatorService->operators(
            (new RestQuery())->setFilter('merchant_id', '=', $discount->merchant_id)
        )->filter(
            function (OperatorDto $operator) {
                return $this->userService->userRoles($operator->user_id)
                    ->where('id', RoleDto::ROLE_MAS_MERCHANT_ADMIN)
                    ->isNotEmpty();
            }
        );

        [$type, $data] = (function () use ($discount) {
            return match ($discount->status) {
                Discount::STATUS_CREATED => ['marketingskidka_sozdana', []],
                Discount::STATUS_SENT => ['marketingskidka_otpravlena_na_soglasovanie', []],
                Discount::STATUS_ON_CHECKING => ['marketingskidka_na_soglasovanii', []],
                Discount::STATUS_ACTIVE => ['marketingskidka_aktivna', [
                    'NAME_DISCOUNT' => $discount->name,
                ],
                ],
                Discount::STATUS_REJECTED => ['marketingskidka_otklonena', [
                    'NAME_DISCOUNT' => $discount->name,
                ],
                ],
                Discount::STATUS_PAUSED => ['marketingskidka_priostanovlena', [
                    'NAME_DISCOUNT' => $discount->name,
                ],
                ],
                Discount::STATUS_EXPIRED => ['marketingskidka_zavershena', [
                    'NAME_DISCOUNT' => $discount->name,
                ],
                ],
                default => ['', []],
            };
        })();

        if ($discount->status == Discount::STATUS_CREATED) {
            $this->notificationService->sendToAdmin('aozskidkaskidka_sozdana');
        } else {
            $this->notificationService->sendToAdmin('aozskidkaskidka_izmenena');
        }

        if ($discount->status != $discount->getOriginal('status')) {
            foreach ($operators as $operator) {
                $this->notificationService->send($operator->user_id, $type, $data);
            }

            foreach ($discount->childDiscounts as $childDiscount) {
                $childDiscount->status = $discount->status;
                $childDiscount->save();
            }
        }

        if ($discount->value != $discount->getOriginal('value') || $discount->wasRecentlyCreated) {
            $sentIds = [];

            $discount
                ->roles()
                ->get()
                ->map(function (DiscountUserRole $discountUserRole) {
                    return $this->userService->users(
                        $this->userService->newQuery()
                            ->setFilter('role', $discountUserRole->role_id)
                    );
                })
                ->each(function ($role) use ($discount, &$sentIds) {
                    $role->each(function ($user) use ($discount, &$sentIds) {
                        $sentIds[] = $user->id;

                        if ($discount->value_type == Discount::DISCOUNT_VALUE_TYPE_PERCENT) {
                            $type = '%';
                        } else {
                            $type = ' руб.';
                        }

                        $this->notificationService->send($user->id, 'sotrudnichestvouroven_personalnoy_skidki_izmenen', [
                            'LVL_DISCOUNT' => sprintf('%s%s', $discount->value, $type),
                            'CUSTOMER_NAME' => $user->first_name,
                        ]);
                    });
                });

            $discount
                ->conditions() // deprecated relation
                ->whereJsonLength('condition->customerIds', '>=', 1)
                ->get()
                ->map(function (DiscountCondition $discountCondition) {
                    return $discountCondition->condition['customerIds'];
                })
                ->flatten()
                ->unique()
                ->map(function ($customer) {
                    return $this->customerService->customers(
                        $this->customerService->newQuery()
                            ->setFilter('id', $customer)
                    )->first();
                })
                ->filter()
                ->map(function ($user) {
                    return $this->userService->users(
                        $this->userService->newQuery()
                            ->setFilter('id', $user->user_id)
                    )->first();
                })
                ->filter()
                ->filter(function (UserDto $userDto) {
                    return array_key_exists(RoleDto::ROLE_SHOWCASE_REFERRAL_PARTNER, $userDto->roles);
                })
                ->filter(function (UserDto $userDto) use ($sentIds) {
                    return !in_array($userDto->id, $sentIds);
                })
                ->each(function (UserDto $userDto) use ($discount) {
                    if ($discount->value_type == Discount::DISCOUNT_VALUE_TYPE_PERCENT) {
                        $type = '%';
                    } else {
                        $type = ' руб.';
                    }

                    $this->notificationService->send($userDto->id, 'sotrudnichestvouroven_personalnoy_skidki_izmenen', [
                        'LVL_DISCOUNT' => sprintf('%s%s', $discount->value, $type),
                        'CUSTOMER_NAME' => $userDto->first_name,
                    ]);
                });
        }
    }

    public function deleting(Discount $discount)
    {
        try {
            $discount->offers()->delete();
            $discount->promoCodes()->detach();
            $discount->bundleItems()->delete();
            $discount->brands()->delete();
            $discount->categories()->delete();
            $discount->roles()->delete();
            $discount->segments()->delete();
            $discount->conditions()->delete();
            $discount->conditionGroups()->delete();
            $discount->publicEvents()->delete();
            $discount->bundles()->delete();
            $discount->childDiscounts()->delete();
            $discount->merchants()->delete();
            $discount->productProperties()->delete();
            $discount->childDiscounts()->delete();

            $discount->updatePimContents();
        } catch (\Exception $e) {
            Log::error("Error deleting related data for Discount ID {$discount->id}: {$e->getMessage()}");
            return false;
        }

        $this->notificationService->sendToAdmin('aozskidkaskidka_udalena');
    }
}
