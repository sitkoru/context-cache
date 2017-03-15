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
        $this->provider->adGroups->update([$adGroup]);

        $updAdGroup = $this->provider->adGroups->getOne(YDUpdateAdGroupId);
        $this->assertEquals('Updated group title', $updAdGroup->Name);

        $adGroup->Name = $oldTitle;
        $this->provider->adGroups->update([$adGroup]);
    }

    public function testUpdateAd()
    {
        $ad = $this->provider->ads->getOne(YDUpdateAdId);
        $this->assertNotNull($ad);
        $oldTitle = $ad->TextAd->Title;
        $newTitle = 'Updated ad title';
        $ad->TextAd->Title = $newTitle;
        $this->provider->ads->update([$ad]);

        $updAd = $this->provider->ads->getOne(YDUpdateAdId);
        $this->assertEquals($newTitle, $updAd->TextAd->Title);

        $updAd->TextAd->Title = $oldTitle;
        $this->provider->ads->update([$updAd]);
    }

    public function testUpdateKeyword()
    {
        $keyword = $this->provider->keywords->getOne(YDUpdateKeywordId);
        $this->assertNotNull($keyword);
        $oldText = $keyword->Keyword;
        $newText = 'Updated query ' . uniqid();
        $keyword->Keyword = $newText;
        $this->provider->keywords->update([$keyword]);

        $groupKeywords = $this->provider->keywords->getByAdGroupIds([$keyword->AdGroupId]);
        $updKeyword = null;
        foreach ($groupKeywords as $groupKeyword) {
            if ($groupKeyword->Keyword === $newText) {
                $updKeyword = $groupKeyword;
            }
        }
        $this->assertNotNull($updKeyword);

        $updKeyword->Keyword = $oldText;
        $this->provider->keywords->update([$updKeyword]);
    }

}