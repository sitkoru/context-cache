<?php

namespace sitkoru\contextcache\adwords;


use Google\AdsApi\AdWords\AdWordsServices;
use Google\AdsApi\AdWords\AdWordsSession;
use Google\AdsApi\AdWords\AdWordsSessionBuilder;
use Google\AdsApi\AdWords\v201702\cm\AdGroupAdService;
use Google\AdsApi\AdWords\v201702\cm\AdGroupCriterionService;
use Google\AdsApi\AdWords\v201702\cm\AdGroupService;
use Google\AdsApi\AdWords\v201702\cm\CampaignService;
use Google\AdsApi\Common\OAuth2TokenBuilder;
use Google\Auth\CredentialsLoader;
use sitkoru\contextcache\common\ICacheProvider;

class AdWordsProvider
{

    /**
     * @var AdWordsAdGroupsProvider
     */
    public $adGroups;

    /**
     * @var AdWordsAdsProvider
     */
    public $ads;

    /**
     * @var AdWordsCampaignsProvider
     */
    public $campaigns;

    /**
     * @var AdWordsAdGroupCriterionsProvider
     */
    public $criterions;
    
    /**
     * @var
     */
    private $oAuthFilePath;

    /**
     * @var
     */
    private $customerId;
    /**
     * @var
     */
    private $refreshToken;

    /**
     * GoogleProvider constructor.
     * @param int            $customerId
     * @param string         $oAuthFilePath
     * @param null|string    $refreshToken
     * @param ICacheProvider $cacheProvider
     */
    public function __construct(
        int $customerId,
        string $oAuthFilePath,
        ?string $refreshToken = null,
        ICacheProvider $cacheProvider
    ) {

        $this->customerId = $customerId;
        $this->refreshToken = $refreshToken;
        $this->oAuthFilePath = $oAuthFilePath;

        $services = new AdWordsServices();
        $session = $this->getSession();

        /**
         * @var CampaignService $campaignService
         */
        $campaignService = $services->get($session, CampaignService::class);
        $this->campaigns = new AdWordsCampaignsProvider($campaignService, $cacheProvider);

        /**
         * @var AdGroupService $adGroupService
         */
        $adGroupService = $services->get($session, AdGroupService::class);
        $this->adGroups = new AdWordsAdGroupsProvider($adGroupService, $cacheProvider);

        /**
         * @var AdGroupAdService $adGroupAdService
         */
        $adGroupAdService = $services->get($session, AdGroupAdService::class);
        $this->ads = new AdWordsAdsProvider($adGroupAdService, $cacheProvider);

        /**
         * @var AdGroupCriterionService $adGroupCriterionService
         */
        $adGroupCriterionService = $services->get($session, AdGroupCriterionService::class);
        $this->criterions = new AdWordsAdGroupCriterionsProvider($adGroupCriterionService, $cacheProvider);


    }

    private function getSession(): AdWordsSession
    {
        $builder = $this->getSessionBuilder();

        return $builder->build();
    }

    private function getSessionBuilder(): AdWordsSessionBuilder
    {

        $oAuth2Credential = $this->getOAuthCredentials();

        $builder = (new AdWordsSessionBuilder())
            ->fromFile($this->oAuthFilePath)
            ->withOAuth2Credential($oAuth2Credential)
            ->withClientCustomerId($this->customerId);

        return $builder;
    }

    public function getOAuthCredentials(): CredentialsLoader
    {
        $authFile = $this->oAuthFilePath;
        $oauthBuilder = (new OAuth2TokenBuilder())
            ->fromFile($authFile);
        if ($this->refreshToken) {
            $oauthBuilder->withRefreshToken($this->refreshToken);
        }

        return $oauthBuilder
            ->build();
    }
}