<?php

namespace sitkoru\contextcache\tests\unit;


use Google\AdsApi\AdWords\v201802\cm\ExpandedTextAd;
use Google\AdsApi\AdWords\v201802\cm\Keyword;
use Monolog\Handler\ErrorLogHandler;
use Monolog\Logger;
use PHPUnit\Framework\TestCase;
use sitkoru\contextcache\common\cache\MongoDbCacheProvider;
use sitkoru\contextcache\ContextEntitiesProvider;

class AdWordsUpdateTest extends TestCase
{
    private $provider;

    public function __construct($name = null, array $data = [], $dataName = '')
    {
        parent::__construct($name, $data, $dataName);
        $cacheProvider = new MongoDbCacheProvider('mongodb://mongodb');
        $logger = new Logger('adWordsUpdateLogger');
        $logger->pushHandler(new ErrorLogHandler());
        $contextEntitiesProvider = new ContextEntitiesProvider($cacheProvider, $logger);
        $this->provider = $contextEntitiesProvider->getAdWordsProvider(ADWORDS_UPDATE_CUSTOMER_ID,
            ADWORDS_AUTH_FILE_PATH);
    }

    public function testUpdateCampaign()
    {
        $campaign = $this->provider->campaigns->getOne(GAUpdateCampaignId);
        $this->assertNotNull($campaign);
        $oldTitle = $campaign->getName();
        $campaign->setName('Updated campaign title');
        $result = $this->provider->campaigns->update([$campaign]);
        $this->assertTrue($result->success, json_encode($result->errors));

        $updCampaign = $this->provider->campaigns->getOne(GAUpdateCampaignId);
        $this->assertEquals('Updated campaign title', $updCampaign->getName());

        $campaign->setName($oldTitle);
        $result = $this->provider->campaigns->update([$campaign]);
        $this->assertTrue($result->success);
    }

    public function testUpdateGroup()
    {
        $adGroup = $this->provider->adGroups->getOne(GAUpdateAdGroupId);
        $this->assertNotNull($adGroup);
        $oldTitle = $adGroup->getName();
        $adGroup->setName('Updated group title');
        $result = $this->provider->adGroups->update([$adGroup]);
        $this->assertTrue($result->success);

        $updAdGroup = $this->provider->adGroups->getOne(GAUpdateAdGroupId);
        $this->assertEquals('Updated group title', $updAdGroup->getName());

        $adGroup->setName($oldTitle);
        $result = $this->provider->adGroups->update([$adGroup]);
        $this->assertTrue($result->success);
    }

    public function testUpdateAd()
    {
        $groupAds = $this->provider->ads->getByAdGroupIds([GAUpdateAdGroupId]);
        $this->assertNotEmpty($groupAds);
        $groupAd = reset($groupAds);
        /**
         * @var ExpandedTextAd $textAd
         */
        $textAd = $groupAd->getAd();
        $oldTitle = $textAd->getHeadlinePart1();
        $newTitle = 'Updated ad title';
        $textAd->setHeadlinePart1($newTitle);
        $groupAd->setAd($textAd);
        $result = $this->provider->ads->update([$groupAd]);
        $this->assertTrue($result->success);

        $newGroupAds = $this->provider->ads->getByAdGroupIds([GAUpdateAdGroupId]);
        $updGroupAd = null;
        foreach ($newGroupAds as $newGroupAd) {
            $textAd = $newGroupAd->getAd();
            if ($textAd->getHeadlinePart1() === $newTitle) {
                $updGroupAd = $newGroupAd;
                break;
            }
        }
        $this->assertNotNull($updGroupAd);

        $textAd->setHeadlinePart1($oldTitle);
        $updGroupAd->setAd($textAd);
        $result = $this->provider->ads->update([$updGroupAd]);
        $this->assertTrue($result->success);
    }

    public function testUpdateKeyword()
    {
        $criterions = $this->provider->criterions->getByAdGroupIds([GAUpdateAdGroupId]);
        $this->assertNotEmpty($criterions);
        $criterion = reset($criterions);
        /**
         * @var Keyword $keyword
         */
        $keyword = $criterion->getCriterion();
        $oldText = $keyword->getText();
        $newText = 'Updated query ' . uniqid('prefix', true);
        $keyword->setText($newText);
        $criterion->setCriterion($keyword);
        $result = $this->provider->criterions->update([$criterion]);
        $this->assertTrue($result->success);

        $groupCriterions = $this->provider->criterions->getByAdGroupIds([GAUpdateAdGroupId]);
        $updCriterion = null;
        foreach ($groupCriterions as $groupCriterion) {
            $keyword = $groupCriterion->getCriterion();
            if ($keyword->getText() === $newText) {
                $updCriterion = $groupCriterion;
                break;
            }
        }
        $this->assertNotNull($updCriterion);

        $keyword->setText($oldText);
        $updCriterion->setCriterion($keyword);
        $result = $this->provider->criterions->update([$updCriterion]);
        $this->assertTrue($result->success);
    }

    public function testUpdateErrors()
    {
        $adGroup = $this->provider->adGroups->getOne(GAUpdateAdGroupId);
        $this->assertNotNull($adGroup);
        $adGroup->setName('');
        $result = $this->provider->adGroups->update([$adGroup]);
        $this->assertFalse($result->success);
        $this->assertNotEmpty($result->errors);
        $this->assertCount(1, $result->errors[0]);
    }

}