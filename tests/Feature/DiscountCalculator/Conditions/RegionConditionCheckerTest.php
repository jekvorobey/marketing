<?php

namespace Tests\Feature\DiscountCalculator\Conditions;

use App\Models\Discount\DiscountCondition;
use App\Services\Calculator\Discount\Checker\ConditionCheckers\RegionConditionChecker;
use App\Services\Calculator\InputCalculator;
use Greensight\CommonMsa\Rest\RestQuery;
use Greensight\Logistics\Dto\Lists\RegionDto;
use Greensight\Logistics\Services\ListsService\ListsService;
use Pim\Core\PimException;
use Tests\CreatesApplication;
use Tests\Feature\DiscountCalculator\Mocks\Discounts\Conditions\DiscountConditionMock;
use Tests\Feature\DiscountCalculator\Mocks\InputParamsBuilder;
use Tests\TestCase;

/**
 * @property RegionDto|null $region
 * @property DiscountCondition $condition
 * @property InputParamsBuilder $inputBuilder
 */
class RegionConditionCheckerTest extends TestCase
{
    use CreatesApplication;

    /**
     * @return void
     */
    public function setUp(): void
    {
        parent::setUp();

        $query = (new RestQuery())->addFields(RegionDto::entity(), 'id', 'guid');
        $this->region = app(ListsService::class)->regions($query)->first() ?? null;

        $this->condition = DiscountConditionMock::create(
            DiscountCondition::REGION,
            [DiscountCondition::FIELD_REGIONS => [$this->region->id]]
        );

        $this->inputBuilder = new InputParamsBuilder();
    }

    /**
     * @throws PimException
     */
    public function test_region_valid()
    {
        $this->inputBuilder->setRegionFiasId($this->region->guid);
        $input = new InputCalculator(
            $this->inputBuilder->build()
        );

        $checker = new RegionConditionChecker($input, $this->condition);
        $this->assertTrue($checker->check());
    }

    /**
     * @throws PimException
     */
    public function test_region_invalid()
    {
        $this->inputBuilder->setRegionFiasId('something');
        $input = new InputCalculator(
            $this->inputBuilder->build()
        );

        $checker = new RegionConditionChecker($input, $this->condition);
        $this->assertFalse($checker->check());
    }
}
