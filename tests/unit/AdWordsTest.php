<?php
declare(strict_types=1);

namespace sitkoru\contextcache\tests\unit;


use Google\AdsApi\AdWords\v201809\cm\AdGroup;
use Google\AdsApi\AdWords\v201809\cm\AdGroupAd;
use Google\AdsApi\AdWords\v201809\cm\AdGroupCriterion;
use Google\AdsApi\AdWords\v201809\cm\Campaign;
use Monolog\Handler\ErrorLogHandler;
use Monolog\Logger;
use PHPUnit\Framework\TestCase;
use sitkoru\contextcache\adwords\AdWordsAdGroupCriterionsProvider;
use sitkoru\contextcache\adwords\AdWordsAdGroupsProvider;
use sitkoru\contextcache\adwords\AdWordsAdsProvider;
use sitkoru\contextcache\adwords\AdWordsCampaignsProvider;
use sitkoru\contextcache\common\cache\MongoDbCacheProvider;
use sitkoru\contextcache\ContextEntitiesProvider;

class AdWordsTest extends TestCase
{
    private $provider;

    public function __construct($name = null, array $data = [], $dataName = '')
    {
        parent::__construct($name, $data, $dataName);
        $cacheProvider = new MongoDbCacheProvider('mongodb://mongodb');
        $logger = new Logger('adWordsLogger');
        $logger->pushHandler(new ErrorLogHandler());
        $contextEntitiesProvider = new ContextEntitiesProvider($cacheProvider, $logger);
        $this->provider = $contextEntitiesProvider->getAdWordsProvider(ADWORDS_CUSTOMER_ID, ADWORDS_AUTH_FILE_PATH);
    }

    /**
     * @dataProvider cacheProvider
     * @param bool $clearCache
     */
    public function testLoadCampaigns(bool $clearCache): void
    {
        $this->assertInstanceOf(AdWordsCampaignsProvider::class, $this->provider->campaigns);
        if ($clearCache) {
            $this->provider->campaigns->clearCache();
        }
        $campaigns = $this->provider->campaigns->getAll([ADWCampaignId]);
        $this->assertCount(1, $campaigns);
        foreach ($campaigns as $campaign) {
            $this->assertInstanceOf(Campaign::class, $campaign);
        }
    }

    /**
     * @dataProvider cacheProvider
     * @param bool $clearCache
     */
    public function testLoadGroups(bool $clearCache): void
    {
        $this->assertInstanceOf(AdWordsAdGroupsProvider::class, $this->provider->adGroups);
        if ($clearCache) {
            $this->provider->adGroups->clearCache();
        }
        $adGroups = $this->provider->adGroups->getAll([ADWAdGroupId]);
        $this->assertCount(1, $adGroups);
        foreach ($adGroups as $adGroup) {
            $this->assertInstanceOf(AdGroup::class, $adGroup);
        }
    }

    /**
     * @dataProvider cacheProvider
     * @param bool $clearCache
     */
    public function testLoadGroupsByCampaignIds($clearCache): void
    {
        $this->assertInstanceOf(AdWordsAdGroupsProvider::class, $this->provider->adGroups);
        if ($clearCache) {
            $this->provider->adGroups->clearCache();
        }
        $adGroups = $this->provider->adGroups->getByCampaignIds([ADWCampaignId]);
        $this->assertTrue(count($adGroups) > 0);
        foreach ($adGroups as $adGroup) {
            $this->assertInstanceOf(AdGroup::class, $adGroup);
        }
    }

    /**
     * @dataProvider cacheProvider
     * @param bool $clearCache
     */
    public function testLoadAds($clearCache): void
    {
        $this->assertInstanceOf(AdWordsAdsProvider::class, $this->provider->ads);
        if ($clearCache) {
            $this->provider->ads->clearCache();
        }
        $ads = $this->provider->ads->getAll([ADWAdId]);
        $this->assertCount(1, $ads);
        foreach ($ads as $ad) {
            $this->assertInstanceOf(AdGroupAd::class, $ad);
        }
    }

    /**
     * @dataProvider cacheProvider
     * @param bool $clearCache
     */
    public function testLoadAdsByGroupIds($clearCache): void
    {
        $this->assertInstanceOf(AdWordsAdsProvider::class, $this->provider->ads);
        if ($clearCache) {
            $this->provider->ads->clearCache();
        }
        $ads = $this->provider->ads->getByAdGroupIds([ADWAdGroupId]);
        $this->assertTrue(count($ads) > 0);
        foreach ($ads as $ad) {
            $this->assertInstanceOf(AdGroupAd::class, $ad);
        }
    }

    /**
     * @dataProvider cacheProvider
     * @param bool $clearCache
     */
    public function testLoadCriterions($clearCache): void
    {
        $this->assertInstanceOf(AdWordsAdGroupCriterionsProvider::class, $this->provider->criterions);
        if ($clearCache) {
            $this->provider->criterions->clearCache();
        }
        $criterions = $this->provider->criterions->getAll([ADWCriterionId]);
        $this->assertCount(1, $criterions);
        foreach ($criterions as $criterion) {
            $this->assertInstanceOf(AdGroupCriterion::class, $criterion);
        }
    }

    /**
     * @dataProvider cacheProvider
     * @param bool $clearCache
     */
    public function testLoadCriterionsByGroupIds($clearCache): void
    {
        $this->assertInstanceOf(AdWordsAdGroupCriterionsProvider::class, $this->provider->criterions);
        if ($clearCache) {
            $this->provider->criterions->clearCache();
        }
        $criterions = $this->provider->criterions->getByAdGroupIds([ADWAdGroupId]);
        $this->assertTrue(count($criterions) > 0);
        foreach ($criterions as $criterion) {
            $this->assertInstanceOf(AdGroupCriterion::class, $criterion);
        }
    }

    public function cacheProvider()
    {
        return [
            [true],
            [false]
        ];
    }
}