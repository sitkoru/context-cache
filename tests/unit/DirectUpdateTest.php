<?php

namespace sitkoru\contextcache\tests\unit;


use PHPUnit\Framework\TestCase;
use sitkoru\contextcache\common\cache\MongoDbCacheProvider;
use sitkoru\contextcache\ContextEntitiesProvider;

class DirectUpdateTest extends TestCase
{
    private $provider;

    public function __construct($name = null, array $data = [], $dataName = '')
    {
        parent::__construct($name, $data, $dataName);
        $cacheProvider = new MongoDbCacheProvider('mongodb://mongodb');
        $contextEntitiesProvider = new ContextEntitiesProvider($cacheProvider);
        $this->provider = $contextEntitiesProvider->getDirectProvider(DIRECT_UPDATE_ACCESS_TOKEN, DIRECT_UPDATE_LOGIN);
    }

    public function testUpdateGroup()
    {
        $adGroup = $this->provider->adGroups->getOne(YDUpdateAdGroupId);
        $this->assertNotNull($adGroup);
        $oldTitle = $adGroup->Name;
        $adGroup->Name = 'Updated group title';
        $result = $this->provider->adGroups->update([$adGroup]);
        $this->assertTrue($result->success);

        $updAdGroup = $this->provider->adGroups->getOne(YDUpdateAdGroupId);
        $this->assertEquals('Updated group title', $updAdGroup->Name);

        $adGroup->Name = $oldTitle;
        $result = $this->provider->adGroups->update([$adGroup]);
        $this->assertTrue($result->success);
    }

    public function testUpdateAd()
    {
        $ad = $this->provider->ads->getOne(YDUpdateAdId);
        $this->assertNotNull($ad);
        $oldTitle = $ad->TextAd->Title;
        $newTitle = 'Updated ad title';
        $ad->TextAd->Title = $newTitle;
        $result = $this->provider->ads->update([$ad]);
        $this->assertTrue($result->success);

        $updAd = $this->provider->ads->getOne(YDUpdateAdId);
        $this->assertEquals($newTitle, $updAd->TextAd->Title);

        $updAd->TextAd->Title = $oldTitle;
        $result = $this->provider->ads->update([$updAd]);
        $this->assertTrue($result->success);
    }

    public function testUpdateKeyword()
    {
        $keywords = $this->provider->keywords->getByAdGroupIds([YDUpdateAdGroupId]);
        $this->assertNotEmpty($keywords);
        $keyword = reset($keywords);
        $oldText = $keyword->Keyword;
        $newText = 'Updated query ' . uniqid('prefix', true);
        $keyword->Keyword = $newText;
        $result = $this->provider->keywords->update([$keyword]);
        $this->assertTrue($result->success);

        $groupKeywords = $this->provider->keywords->getByAdGroupIds([$keyword->AdGroupId]);
        $updKeyword = null;
        foreach ($groupKeywords as $groupKeyword) {
            if ($groupKeyword->Keyword === $newText) {
                $updKeyword = $groupKeyword;
            }
        }
        $this->assertNotNull($updKeyword);

        $updKeyword->Keyword = $oldText;
        $result = $this->provider->keywords->update([$updKeyword]);
        $this->assertTrue($result->success);
    }

    public function testUpdateErrors()
    {
        $ad = $this->provider->ads->getOne(YDUpdateAdId);
        $this->assertNotNull($ad);
        $badTitle = md5('bad') . md5('ad') . md5('title');
        $ad->TextAd->Title = $badTitle;
        $result = $this->provider->ads->update([$ad]);
        $this->assertFalse($result->success);
        $this->assertNotEmpty($result->errors);
        $this->assertCount(1, $result->errors[$ad->Id]);
    }

}