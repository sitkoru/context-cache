<?php
declare(strict_types=1);

namespace sitkoru\contextcache\tests\unit;


use directapi\services\adgroups\models\AdGroupGetItem;
use directapi\services\ads\models\AdGetItem;
use directapi\services\campaigns\models\CampaignGetItem;
use directapi\services\keywords\models\KeywordGetItem;
use directapi\services\retargetinglists\models\RetargetingListGetItem;
use Monolog\Handler\ErrorLogHandler;
use Monolog\Logger;
use PHPUnit\Framework\TestCase;
use sitkoru\contextcache\common\cache\MongoDbCacheProvider;
use sitkoru\contextcache\ContextEntitiesProvider;
use sitkoru\contextcache\direct\DirectAdGroupsProvider;
use sitkoru\contextcache\direct\DirectAdsProvider;
use sitkoru\contextcache\direct\DirectCampaignsProvider;
use sitkoru\contextcache\direct\DirectKeywordsProvider;
use sitkoru\contextcache\direct\DirectRetargetingProvider;

class DirectTest extends TestCase
{
    private $provider;

    public function __construct($name = null, array $data = [], $dataName = '')
    {
        parent::__construct($name, $data, $dataName);
        $cacheProvider = new MongoDbCacheProvider('mongodb://mongodb');
        $logger = new Logger('directLogger');
        $logger->pushHandler(new ErrorLogHandler());
        $contextEntitiesProvider = new ContextEntitiesProvider($cacheProvider, $logger);
        $this->provider = $contextEntitiesProvider->getDirectProvider(DIRECT_ACCESS_TOKEN, DIRECT_LOGIN);
    }

    /**
     * @dataProvider cacheProvider
     * @param bool $clearCache
     */
    public function testLoadCampaigns(bool $clearCache): void
    {
        $this->assertInstanceOf(DirectCampaignsProvider::class, $this->provider->campaigns);
        if ($clearCache) {
            $this->provider->campaigns->clearCache();
        }
        $campaigns = $this->provider->campaigns->getAll([YDCampaignId]);
        $this->assertCount(1, $campaigns);
        foreach ($campaigns as $campaign) {
            $this->assertInstanceOf(CampaignGetItem::class, $campaign);
        }
    }

    /**
     * @dataProvider cacheProvider
     * @param bool $clearCache
     */
    public function testLoadGroups(bool $clearCache): void
    {
        $this->assertInstanceOf(DirectAdGroupsProvider::class, $this->provider->adGroups);
        if ($clearCache) {
            $this->provider->adGroups->clearCache();
        }
        $adGroups = $this->provider->adGroups->getAll([YDAdGroupId]);
        $this->assertCount(1, $adGroups);
        foreach ($adGroups as $adGroup) {
            $this->assertInstanceOf(AdGroupGetItem::class, $adGroup);
        }
    }

    /**
     * @dataProvider cacheProvider
     * @param bool $clearCache
     */
    public function testLoadGroupsByCampaignIds($clearCache): void
    {
        $this->assertInstanceOf(DirectAdGroupsProvider::class, $this->provider->adGroups);
        if ($clearCache) {
            $this->provider->adGroups->clearCache();
        }
        $adGroups = $this->provider->adGroups->getByCampaignIds([YDCampaignId]);
        $this->assertTrue(count($adGroups) > 0);
        foreach ($adGroups as $adGroup) {
            $this->assertInstanceOf(AdGroupGetItem::class, $adGroup);
        }
    }

    /**
     * @dataProvider cacheProvider
     * @param bool $clearCache
     */
    public function testLoadAds($clearCache): void
    {
        $this->assertInstanceOf(DirectAdsProvider::class, $this->provider->ads);
        if ($clearCache) {
            $this->provider->ads->clearCache();
        }
        $ads = $this->provider->ads->getAll([YDAdId]);
        $this->assertCount(1, $ads);
        foreach ($ads as $ad) {
            $this->assertInstanceOf(AdGetItem::class, $ad);
        }
    }

    /**
     * @dataProvider cacheProvider
     * @param bool $clearCache
     */
    public function testLoadAdsByGroupIds($clearCache): void
    {
        $this->assertInstanceOf(DirectAdsProvider::class, $this->provider->ads);
        if ($clearCache) {
            $this->provider->ads->clearCache();
        }
        $ads = $this->provider->ads->getByAdGroupIds([YDAdGroupId]);
        $this->assertTrue(count($ads) > 0);
        foreach ($ads as $ad) {
            $this->assertInstanceOf(AdGetItem::class, $ad);
        }
    }

    /**
     * @dataProvider cacheProvider
     * @param bool $clearCache
     */
    public function testLoadAdsByCampaignIds($clearCache): void
    {
        $this->assertInstanceOf(DirectAdsProvider::class, $this->provider->ads);
        if ($clearCache) {
            $this->provider->ads->clearCache();
        }
        $ads = $this->provider->ads->getByCampaignIds([YDCampaignId]);
        $this->assertTrue(count($ads) > 0);
        foreach ($ads as $ad) {
            $this->assertInstanceOf(AdGetItem::class, $ad);
        }
    }

    /**
     * @dataProvider cacheProvider
     * @param bool $clearCache
     */
    public function testLoadCriterions($clearCache): void
    {
        $this->assertInstanceOf(DirectKeywordsProvider::class, $this->provider->keywords);
        if ($clearCache) {
            $this->provider->keywords->clearCache();
        }
        $keywords = $this->provider->keywords->getAll([YDCriterionId]);
        $this->assertCount(1, $keywords);
        foreach ($keywords as $keyword) {
            $this->assertInstanceOf(KeywordGetItem::class, $keyword);
        }
    }

    /**
     * @dataProvider cacheProvider
     * @param bool $clearCache
     */
    public function testLoadCriterionsByGroupIds($clearCache): void
    {
        $this->assertInstanceOf(DirectKeywordsProvider::class, $this->provider->keywords);
        if ($clearCache) {
            $this->provider->keywords->clearCache();
        }
        $keywords = $this->provider->keywords->getByAdGroupIds([YDAdGroupId]);
        $this->assertTrue(count($keywords) > 0);
        foreach ($keywords as $keyword) {
            $this->assertInstanceOf(KeywordGetItem::class, $keyword);
        }
    }

    public function cacheProvider()
    {
        return [
            [true],
            [false]
        ];
    }

    /**
     * @dataProvider cacheProvider
     * @param bool $clearCache
     */
    public function testLoadRetargetingLists($clearCache): void
    {
        $this->assertInstanceOf(DirectRetargetingProvider::class, $this->provider->retargetingLists);
        if ($clearCache) {
            $this->provider->keywords->clearCache();
        }
        $lists = $this->provider->retargetingLists->getAll([YDRetargetingListId]);
        $this->assertCount(1, $lists);
        foreach ($lists as $list) {
            $this->assertInstanceOf(RetargetingListGetItem::class, $list);
        }
    }
}